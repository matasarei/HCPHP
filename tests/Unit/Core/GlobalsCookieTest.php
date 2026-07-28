<?php

namespace Tests\Unit\Core;

use core\Globals;
use PHPUnit\Framework\TestCase;

/**
 * Cookies used to be written with no flags at all, which meant the session cookie and the
 * "remember me" auth key were readable by any script on the page, sent over plain HTTP, and
 * attached to cross-site requests.
 *
 * setcookie() itself cannot be observed from a unit test, so the decision lives in a pure
 * function and that is what is pinned here. The header itself is checked over HTTP.
 *
 * @covers \core\Globals
 */
class GlobalsCookieTest extends TestCase
{
    public function testCookiesAreHttpOnly(): void
    {
        // The auth key has no business being reachable from JavaScript; without this an XSS
        // anywhere on the site becomes account takeover.
        self::assertTrue(Globals::getCookieOptions(3600, false)['httponly']);
    }

    public function testCookiesAreSameSiteLax(): void
    {
        // Lax withholds the cookie on cross-site POST and subresource loads, while still
        // letting someone following a link from elsewhere arrive logged in.
        self::assertSame('Lax', Globals::getCookieOptions(3600, false)['samesite']);
    }

    public function testCookiesAreScopedToTheWholeSite(): void
    {
        self::assertSame('/', Globals::getCookieOptions(3600, false)['path']);
    }

    public function testSecureFollowsWhetherTheSiteIsOnHttps(): void
    {
        // Forcing it on would silently break every plain-HTTP development stack, which is
        // how the flag ends up being removed altogether.
        self::assertTrue(Globals::getCookieOptions(3600, true)['secure']);
        self::assertFalse(Globals::getCookieOptions(3600, false)['secure']);
    }

    public function testExpiryIsPassedThroughUnchanged(): void
    {
        self::assertSame(1785240000, Globals::getCookieOptions(1785240000, false)['expires']);
    }

    public function testSessionCookieCarriesTheSameFlags(): void
    {
        $options = Globals::getSessionCookieOptions(true);

        self::assertTrue($options['httponly']);
        self::assertTrue($options['secure']);
        self::assertSame('Lax', $options['samesite']);
        self::assertSame('/', $options['path']);
    }

    /**
     * session_set_cookie_params() does not accept the same array as setcookie(): the expiry
     * key is "lifetime", a duration, and an "expires" key is rejected with a warning and
     * then ignored -- which leaves PHPSESSID unflagged while everything still looks fine.
     *
     * Calling the function for real is no good here: it refuses once any output has been
     * produced, and PHPUnit has produced some by now, so it would fail for the wrong reason.
     * The accepted key set is pinned instead.
     */
    public function testSessionOptionsUseOnlyKeysThatFunctionAccepts(): void
    {
        $accepted = ['lifetime', 'path', 'domain', 'secure', 'httponly', 'samesite'];
        $options = Globals::getSessionCookieOptions(false);

        self::assertSame([], array_diff(array_keys($options), $accepted));
        self::assertArrayHasKey('lifetime', $options);
        self::assertArrayNotHasKey('expires', $options);
    }

    public function testAnExpiredOptionSetIsStillFullyFlagged(): void
    {
        // Deleting a cookie has to repeat the flags and path it was set with, or the browser
        // treats it as a different cookie and keeps the original.
        $options = Globals::getCookieOptions(0, false);

        self::assertSame(0, $options['expires']);
        self::assertTrue($options['httponly']);
        self::assertSame('Lax', $options['samesite']);
        self::assertSame('/', $options['path']);
    }
}
