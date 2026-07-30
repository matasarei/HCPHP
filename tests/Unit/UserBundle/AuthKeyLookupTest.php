<?php

namespace Tests\Unit\UserBundle;

use core\Cache;
use core\DatabaseSQL;
use PHPUnit\Framework\TestCase;
use UserBundle\Entity\User;
use UserBundle\Mapper\RoleMapper;
use UserBundle\Mapper\UserMapper;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;
use UserBundle\Service\AuthChecker;
use UserBundle\Service\Authenticator;

/**
 * End-to-end proof of the authentication bypass.
 *
 * The auth cookie is attacker-controlled and lands in a condition array unmodified.
 * While DatabaseSQL promoted "%"-bearing values to LIKE, the cookie "auth_key=%"
 * matched every row and logged the attacker in as the first user in the table.
 *
 * @covers \UserBundle\Repository\UserRepository
 * @covers \UserBundle\Service\AuthChecker
 */
class AuthKeyLookupTest extends TestCase
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var AuthChecker
     */
    private $authChecker;

    protected function setUp(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                firstname TEXT NOT NULL,
                lastname TEXT,
                email TEXT NOT NULL,
                password TEXT,
                role TEXT NOT NULL,
                authkey TEXT,
                authtime INTEGER NOT NULL DEFAULT 0,
                timecreated INTEGER NOT NULL,
                timemodified INTEGER NOT NULL
            )'
        );

        $roleRepository = new RoleRepository(new RoleMapper());
        $this->userRepository = new UserRepository($database, new UserMapper($roleRepository));

        foreach (
            [
                ['Alice', 'alice@example.com', 'key_alice_9f3c2b'],
                ['Bob', 'bob@example.com', 'key_bob_77aa10'],
                ['Carol', 'carol@example.com', 'key_carol_5521de'],
            ] as [$firstName, $email, $authKey]
        ) {
            $role = $roleRepository->get('user');
            // authTime matters now: AuthChecker treats a key older than the login window,
            // or one that was never issued by a login, as expired.
            $user = (new User($email, $firstName, $role))
                ->setAuthKey($authKey)
                ->setAuthTime(time());

            $this->userRepository->save($user);
        }

        $this->authChecker = new AuthChecker($this->userRepository);

        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);
        $_SESSION = [];
        unset($_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY]);
    }

    protected function tearDown(): void
    {
        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);
        $_SESSION = [];
        unset($_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY]);
    }

    public function testWildcardAuthKeyResolvesToNoUser(): void
    {
        self::assertNull(
            $this->userRepository->getByAuthKey('%'),
            'The auth key "%" belongs to nobody and must not resolve to a user.'
        );
    }

    public function testPrefixWildcardAuthKeyResolvesToNoUser(): void
    {
        self::assertNull(
            $this->userRepository->getByAuthKey('key_%'),
            'A partial auth key must not resolve to a user -- that is a prefix oracle.'
        );
    }

    public function testGenuineAuthKeyStillResolvesToItsOwner(): void
    {
        $user = $this->userRepository->getByAuthKey('key_bob_77aa10');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('bob@example.com', $user->getEmail());
    }

    public function testWildcardAuthKeyCookieDoesNotAuthenticate(): void
    {
        $_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY] = '%';

        self::assertNull(
            $this->authChecker->getCurrentUser(),
            'Sending the cookie auth_key=% must not authenticate anyone.'
        );
    }

    public function testGenuineAuthKeyCookieStillAuthenticates(): void
    {
        $_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY] = 'key_carol_5521de';

        $user = $this->authChecker->getCurrentUser();

        self::assertInstanceOf(User::class, $user);
        self::assertSame('carol@example.com', $user->getEmail());
    }

    public function testWildcardAuthKeyGrantsNoCapability(): void
    {
        $_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY] = '%';

        self::assertFalse(
            $this->authChecker->checkCapability('edit_records'),
            'A wildcard auth key must not grant capabilities.'
        );
    }
}
