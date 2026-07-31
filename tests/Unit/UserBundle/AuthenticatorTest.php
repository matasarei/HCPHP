<?php

namespace Tests\Unit\UserBundle;

use core\Cache;
use core\DatabaseSQL;
use core\Globals;
use PHPUnit\Framework\TestCase;
use UserBundle\Entity\User;
use UserBundle\Exception\InvalidCredentialsException;
use UserBundle\Mapper\RoleMapper;
use UserBundle\Mapper\UserMapper;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;
use UserBundle\Service\AuthChecker;
use UserBundle\Service\Authenticator;

/**
 * Auth keys were sha1($id . time()): for a known user and a one-day window that is about
 * 86,400 candidates, so the key could be derived rather than stolen. They were also never
 * given an expiry and were left valid in the database after logout, so one leak lasted
 * forever.
 */
class AuthenticatorTest extends TestCase
{
    private const LOGIN_TIME = 3600;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * @var AuthChecker
     */
    private $authChecker;

    protected function setUp(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY, firstname TEXT NOT NULL, lastname TEXT,
                email TEXT NOT NULL, password TEXT, role TEXT NOT NULL, authkey TEXT,
                authtime INTEGER NOT NULL DEFAULT 0,
                timecreated INTEGER NOT NULL, timemodified INTEGER NOT NULL
            )'
        );

        $roleRepository = new RoleRepository(new RoleMapper());
        $this->userRepository = new UserRepository($database, new UserMapper($roleRepository));

        $user = (new User('alice@example.com', 'Alice', $roleRepository->get('user')))
            ->setPassword(password_hash('correct horse', PASSWORD_DEFAULT));
        $this->userRepository->save($user);

        $this->authenticator = new Authenticator($this->userRepository, self::LOGIN_TIME);
        $this->authChecker = new AuthChecker($this->userRepository, self::LOGIN_TIME);

        $_SESSION = [];
        $_COOKIE = [];
        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);

        // setcookie() cannot run once output exists, and under a test runner it always does.
        Globals::setCookieWriter(static function (): bool {
            return true;
        });
    }

    protected function tearDown(): void
    {
        Globals::setCookieWriter(null);
        $_SESSION = [];
        $_COOKIE = [];
        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);
    }

    public function testAuthKeyIsNotDerivableFromTheUserAndTheClock(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');
        $key = $this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey();

        self::assertNotSame(sha1('1' . time()), $key);

        // A whole minute of candidates, in case the clock moved during the test.
        for ($offset = -60; $offset <= 60; $offset++) {
            self::assertNotSame(sha1('1' . (time() + $offset)), $key);
        }
    }

    public function testAuthKeyHasRealEntropy(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');
        $key = $this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key, '32 random bytes, hex encoded');
    }

    public function testEachLoginIssuesAFreshKey(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');
        $first = $this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey();

        $this->authenticator->login('alice@example.com', 'correct horse');
        $second = $this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey();

        self::assertNotSame($first, $second, 'Reusing the previous key keeps a stolen one alive.');
    }

    public function testLogoutInvalidatesTheKeyInTheDatabase(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');
        $key = $this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey();

        $this->authenticator->logout();
        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);

        self::assertNull(
            $this->userRepository->getByAuthKey($key),
            'A key that still works after logout means logout did nothing that mattered.'
        );
    }

    public function testExpiredKeyNoLongerAuthenticates(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');

        $user = $this->userRepository->findOne(['email' => 'alice@example.com']);
        $key = $user->getAuthKey();

        // Backdate the login past the window.
        $user->setAuthTime(time() - self::LOGIN_TIME - 1);
        $this->userRepository->save($user);
        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);

        $_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY] = $key;

        self::assertNull($this->authChecker->getCurrentUser());
    }

    public function testKeyWithinTheWindowStillAuthenticates(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');
        $key = $this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey();

        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);
        $_SESSION = [];
        $_COOKIE[Authenticator::KEY_COOKIES_AUTH_KEY] = $key;

        $user = $this->authChecker->getCurrentUser();

        self::assertInstanceOf(User::class, $user);
        self::assertSame('alice@example.com', $user->getEmail());
    }

    public function testWrongPasswordIsRefused(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->authenticator->login('alice@example.com', 'wrong');
    }

    public function testUnknownEmailIsRefused(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->authenticator->login('nobody@example.com', 'correct horse');
    }

    public function testFailedLoginDoesNotIssueAKey(): void
    {
        try {
            $this->authenticator->login('alice@example.com', 'wrong');
        } catch (InvalidCredentialsException $exception) {
            // expected
        }

        self::assertNull($this->userRepository->findOne(['email' => 'alice@example.com'])->getAuthKey());
    }
}
