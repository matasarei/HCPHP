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
use UserBundle\Service\Authenticator;

/**
 * On an ordinary login the auth key is stored in the session, so the session id is the
 * identity. login() kept whatever id the browser arrived with: someone who got a victim to use
 * an id they knew -- a cookie set from a sibling subdomain, a shared machine -- was logged in
 * as the victim the moment the victim logged in.
 *
 * A session cannot be started once output exists, which under a test runner is always, so
 * these run in their own process.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionFixationTest extends TestCase
{
    private const PLANTED_ID = 'plantedbyanattacker0123456789ab';

    /**
     * @var Authenticator
     */
    private $authenticator;

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
        $userRepository = new UserRepository($database, new UserMapper($roleRepository));
        $userRepository->save(
            (new User('alice@example.com', 'Alice', $roleRepository->get('user')))
                ->setPassword(password_hash('correct horse', PASSWORD_DEFAULT))
        );

        $this->authenticator = new Authenticator($userRepository);
        Cache::remove(Authenticator::KEY_CACHE_CURRENT_USER);

        Globals::setCookieWriter(static function (): bool {
            return true;
        });

        session_id(self::PLANTED_ID);
        Globals::init();
    }

    public function testLoginIssuesANewSessionId(): void
    {
        self::assertSame(self::PLANTED_ID, session_id(), 'precondition: the planted id is in use');

        $this->authenticator->login('alice@example.com', 'correct horse');

        self::assertNotSame(self::PLANTED_ID, session_id());
        self::assertNotSame('', session_id());
    }

    public function testTheIdentityMovesToTheNewSession(): void
    {
        $this->authenticator->login('alice@example.com', 'correct horse');

        $authKey = Globals::get(Authenticator::KEY_COOKIES_AUTH_KEY);
        self::assertIsString($authKey);
        self::assertSame(64, strlen($authKey));
    }

    public function testAFailedLoginLeavesTheSessionAlone(): void
    {
        try {
            $this->authenticator->login('alice@example.com', 'wrong');
            self::fail('expected InvalidCredentialsException');
        } catch (InvalidCredentialsException $exception) {
            self::assertSame(self::PLANTED_ID, session_id());
        }
    }
}
