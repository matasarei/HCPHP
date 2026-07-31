<?php

namespace Tests\Unit\Core;

use core\Globals;
use PHPUnit\Framework\TestCase;

/**
 * Globals::filter() is the type contract every controller relies on: the default decides what
 * type comes back, so a scalar default never yields an array and vice versa.
 *
 * @covers \core\Globals
 */
class GlobalsTest extends TestCase
{
    protected function setUp(): void
    {
        $_REQUEST = [];
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
        $_FILES = [];
        unset($_SERVER['REQUEST_METHOD']);
        Globals::setCookieWriter(function () {
            return true;
        });
    }

    protected function tearDown(): void
    {
        $_REQUEST = [];
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
        $_FILES = [];
        unset($_SERVER['REQUEST_METHOD']);
        Globals::setCookieWriter(null);
    }

    // --- filter -----------------------------------------------------------------------------

    public function testFilterReturnsAMatchingScalar(): void
    {
        self::assertSame('value', Globals::filter('value', 'default'));
    }

    public function testFilterFallsBackWhenTheSourceIsEmpty(): void
    {
        self::assertSame('default', Globals::filter('', 'default'));
        self::assertSame('default', Globals::filter(null, 'default'));
    }

    /**
     * A scalar default asks for a scalar; an array cannot satisfy it.
     */
    public function testFilterRefusesAnArrayForAScalarDefault(): void
    {
        self::assertSame('default', Globals::filter(['a'], 'default'));
    }

    public function testFilterRefusesAScalarForAnArrayDefault(): void
    {
        self::assertSame([], Globals::filter('a', []));
    }

    public function testFilterAcceptsAnArrayForAnArrayDefault(): void
    {
        self::assertSame(['a'], Globals::filter(['a'], []));
    }

    /**
     * checkEmpty false asks "is it set", which keeps a legitimate empty value.
     */
    public function testFilterCanTreatEmptyAsPresent(): void
    {
        self::assertSame('', Globals::filter('', 'default', false));
        self::assertSame('default', Globals::filter(null, 'default', false));
    }

    public function testFilterCoercesToTheDefaultsType(): void
    {
        self::assertSame(5, Globals::filter('5', 0));
        self::assertSame(1.5, Globals::filter('1.5', 0.0));
    }

    // --- optional and get --------------------------------------------------------------------

    public function testOptionalReadsFromTheRequest(): void
    {
        $_REQUEST['q'] = 'search';

        self::assertSame('search', Globals::optional('q'));
    }

    public function testOptionalFallsBackWhenAbsent(): void
    {
        self::assertSame('', Globals::optional('absent'));
        self::assertSame('fallback', Globals::optional('absent', 'fallback'));
    }

    public function testGetPrefersTheSession(): void
    {
        $_SESSION['key'] = 'from session';
        $_COOKIE['key'] = 'from cookie';

        self::assertSame('from session', Globals::get('key'));
    }

    public function testGetFallsBackToTheCookie(): void
    {
        $_COOKIE['key'] = 'from cookie';

        self::assertSame('from cookie', Globals::get('key'));
    }

    public function testGetReturnsTheDefaultWhenNeitherIsSet(): void
    {
        self::assertNull(Globals::get('absent'));
        self::assertSame('fallback', Globals::get('absent', 'fallback'));
    }

    // --- post ---------------------------------------------------------------------------------

    public function testPostWithNoNameReportsWhetherThisIsAPost(): void
    {
        self::assertFalse(Globals::post());

        $_SERVER['REQUEST_METHOD'] = 'POST';

        self::assertTrue(Globals::post());
    }

    public function testPostReadsAField(): void
    {
        $_POST['email'] = 'bob@example.com';

        self::assertSame('bob@example.com', Globals::post('email'));
    }

    public function testPostFallsBackForAnAbsentField(): void
    {
        self::assertSame('', Globals::post('absent'));
        self::assertSame('fallback', Globals::post('absent', 'fallback'));
    }

    // --- set and reset --------------------------------------------------------------------------

    public function testSetStoresInTheSession(): void
    {
        Globals::set('key', 'value');

        self::assertSame('value', $_SESSION['key']);
        self::assertSame('value', Globals::get('key'));
    }

    public function testSetCanStoreACookieInstead(): void
    {
        Globals::set('key', 'value', true);

        self::assertSame('value', $_COOKIE['key']);
        self::assertArrayNotHasKey('key', $_SESSION);
    }

    public function testResetClearsNamedValues(): void
    {
        Globals::set('a', 1);
        Globals::set('b', 2, true);

        Globals::reset(['a', 'b']);

        self::assertArrayNotHasKey('a', $_SESSION);
        self::assertArrayNotHasKey('b', $_COOKIE);
    }

    /**
     * The two halves are not symmetrical: the session is emptied whatever is passed, but only
     * the cookies named are expired -- there is no list of the others to work from, and
     * clearing every cookie on the domain would take ones this application never set.
     */
    public function testResetAlwaysEmptiesTheSessionButOnlyNamedCookies(): void
    {
        Globals::set('a', 1);
        Globals::set('b', 2, true);

        Globals::reset();

        self::assertSame([], $_SESSION);
        self::assertSame(['b' => 2], $_COOKIE, 'an unnamed cookie is left alone');
    }

    // --- file ------------------------------------------------------------------------------------

    public function testFileReadsAnUpload(): void
    {
        $upload = ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 1];
        $_FILES['doc'] = $upload;

        self::assertSame($upload, Globals::file('doc'));
    }

    /**
     * Always upload-shaped, so a caller can read ['error'] without checking for null first.
     * UPLOAD_ERR_NO_FILE is what "nothing was sent" looks like to PHP itself, which is the
     * code FileMapper keys off to leave an optional file field alone.
     */
    public function testFileReportsNoFileRatherThanNull(): void
    {
        $stub = Globals::file('doc');

        self::assertSame(UPLOAD_ERR_NO_FILE, $stub['error']);
        self::assertNull($stub['name']);
        self::assertNull($stub['tmp_name']);
        self::assertSame(0, $stub['size']);
    }

    /**
     * The downstream forks grew a "docs[1]" syntax for picking one entry out of a multi-file
     * input. HCPHP has no such thing: the name is a plain key, so that string simply misses.
     */
    public function testFileTakesTheNameAsAPlainKey(): void
    {
        $_FILES['docs'] = ['name' => ['a.txt'], 'type' => ['text/plain'],
            'tmp_name' => ['/tmp/a'], 'error' => [0], 'size' => [1]];

        self::assertSame($_FILES['docs'], Globals::file('docs'));
        self::assertSame(UPLOAD_ERR_NO_FILE, Globals::file('docs[0]')['error']);
    }
}
