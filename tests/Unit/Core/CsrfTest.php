<?php

namespace Tests\Unit\Core;

use core\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * The form token used to be the session id itself, read through Globals::get(). That put the
 * session id into the page HTML, and Globals::get() falls back to $_COOKIE, so the value the
 * check compared against was partly under the client's control.
 *
 * @covers \core\Csrf
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testTokenIsStableWithinASession(): void
    {
        self::assertSame(Csrf::getToken(), Csrf::getToken());
    }

    public function testTokenIsLongAndUnpredictable(): void
    {
        $first = Csrf::getToken();
        $_SESSION = [];
        $second = Csrf::getToken();

        self::assertNotSame($first, $second);
        self::assertSame(64, strlen($first), '32 random bytes, hex encoded');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    public function testTokenIsNotTheSessionId(): void
    {
        $_COOKIE['PHPSESSID'] = 'the-actual-session-id';

        self::assertNotSame('the-actual-session-id', Csrf::getToken());
    }

    public function testCorrectTokenValidates(): void
    {
        self::assertTrue(Csrf::isValid(Csrf::getToken()));
    }

    /**
     * @dataProvider rejectedValueProvider
     *
     * @param mixed $candidate
     */
    public function testWrongOrMissingTokenIsRejected($candidate): void
    {
        Csrf::getToken();

        self::assertFalse(Csrf::isValid($candidate));
    }

    public function rejectedValueProvider(): array
    {
        return [
            'empty string' => [''],
            'null' => [null],
            'wrong token' => [str_repeat('a', 64)],
            'array' => [[]],
            'truncated prefix' => [substr(str_repeat('a', 64), 0, 32)],
        ];
    }

    public function testValidationFailsClosedWhenNoTokenWasIssued(): void
    {
        // Nothing in the session: every candidate must be refused, including an empty one.
        self::assertFalse(Csrf::isValid('anything'));
        self::assertFalse(Csrf::isValid(''));
    }

    public function testCookieCannotSupplyTheExpectedToken(): void
    {
        // Globals::get() falls back to $_COOKIE. If the token were read that way, anyone who
        // could set a cookie could also choose the value it is compared against.
        $_COOKIE[Csrf::SESSION_KEY] = 'attacker-chosen';

        self::assertFalse(Csrf::isValid('attacker-chosen'));
    }

    public function testResetIssuesANewToken(): void
    {
        $first = Csrf::getToken();
        Csrf::reset();

        self::assertNotSame($first, Csrf::getToken());
        self::assertFalse(Csrf::isValid($first));
    }
}
