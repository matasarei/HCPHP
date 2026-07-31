<?php

namespace Tests\Unit\Core;

use core\Globals;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppConfig;

/**
 * Globals::init() starts the session, and the flags it sets on PHPSESSID are what stop that
 * cookie being readable by a script on the page or sent cross-site. A session cannot be
 * started once output exists, which under a test runner is always, so these run in their own
 * process.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        AppConfig::ensure();
    }

    public static function tearDownAfterClass(): void
    {
        AppConfig::release();
    }

    public function testInitStartsASession(): void
    {
        Globals::init();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * The parameters have to be set before the session starts; afterwards they are ignored.
     */
    public function testTheSessionCookieIsFlaggedBeforeItIsSent(): void
    {
        Globals::init();

        $params = session_get_cookie_params();

        self::assertTrue($params['httponly'], 'no script should be able to read PHPSESSID');
        self::assertSame('Lax', $params['samesite']);
        self::assertSame('/', $params['path']);
    }

    public function testSecureFollowsTheHttpsSetting(): void
    {
        Globals::init();

        // Plain HTTP in this environment, so the flag stays off; forcing it on would break
        // every development stack that does not terminate TLS.
        self::assertFalse(session_get_cookie_params()['secure']);
    }

    /**
     * reset() empties a live session rather than only the array copy, so a logout really does
     * discard what the server holds.
     */
    public function testResetClearsALiveSession(): void
    {
        Globals::init();
        $_SESSION['user'] = 'bob';

        Globals::reset();

        self::assertSame([], $_SESSION);
        self::assertSame(PHP_SESSION_ACTIVE, session_status(), 'the session is emptied, not closed');
    }

    public function testValuesSurviveWithinTheSession(): void
    {
        Globals::init();

        Globals::set('key', 'value');

        self::assertSame('value', Globals::get('key'));
    }
}
