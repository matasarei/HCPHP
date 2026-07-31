<?php

namespace Tests\Unit\Core;

use core\Url;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppConfig;

/**
 * Url builds every link the application emits, and its output is fed straight into HTML
 * attributes, so its escaping and its parameter handling both matter.
 *
 * Host and port come from Application, which reads them from the request; in a CLI process
 * there is no request, so the host falls back to 'localhost' and the port to 80. That makes
 * the expectations below deterministic.
 */
class UrlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        AppConfig::ensure();
    }

    public static function tearDownAfterClass(): void
    {
        AppConfig::release();
    }

    public function testRelativePathBecomesAnAbsoluteUrl(): void
    {
        self::assertSame('http://localhost/user/login', (new Url('user/login'))->make());
    }

    public function testLeadingSlashesAreStripped(): void
    {
        self::assertSame('http://localhost/user', (new Url('/user'))->make());
        self::assertSame('http://localhost/user', (new Url('///user'))->make());
    }

    public function testAbsoluteUrlIsParsedIntoItsParts(): void
    {
        $url = new Url('https://example.com:8443/a/b?x=1#frag');

        self::assertSame('https', $url->getScheme());
        self::assertSame('example.com', $url->getHost());
        self::assertSame(8443, $url->getPort());
        self::assertSame('a/b', $url->getPath());
        self::assertSame('frag', $url->getAnchor());
        self::assertSame('1', $url->getParam('x'));
    }

    public function testStandardPortsAreOmittedFromTheOutput(): void
    {
        self::assertSame('http://example.com/a', (new Url('http://example.com:80/a'))->make());
        self::assertSame('https://example.com/a', (new Url('https://example.com:443/a'))->make());
    }

    public function testNonStandardPortIsKept(): void
    {
        self::assertSame('http://example.com:8080/a', (new Url('http://example.com:8080/a'))->make());
    }

    public function testParamsAreAppendedAsAQueryString(): void
    {
        self::assertSame(
            'http://localhost/search?q=php&page=2',
            (new Url('search', ['q' => 'php', 'page' => 2]))->make()
        );
    }

    /**
     * http_build_query() applies RFC1738, so a space becomes '+' rather than '%20'. Both are
     * valid in a query string; this pins which one callers will actually see.
     */
    public function testParamsAreUrlEncodedOnOutput(): void
    {
        self::assertStringContainsString('q=a+b', (new Url('s', ['q' => 'a b']))->make());
        self::assertStringContainsString('q=a%26b', (new Url('s', ['q' => 'a&b']))->make());
    }

    public function testAnchorIsAppendedLast(): void
    {
        self::assertSame(
            'http://localhost/a?x=1#top',
            (new Url('a', ['x' => 1], 'top'))->make()
        );
    }

    public function testParseParamsSplitsAQueryString(): void
    {
        self::assertSame(['a' => '1', 'b' => '2'], Url::parseParams('http://h/p?a=1&b=2'));
    }

    /**
     * Query separators arrive HTML-escaped when a URL is read back out of markup.
     */
    public function testParseParamsAcceptsEscapedSeparators(): void
    {
        self::assertSame(['a' => '1', 'b' => '2'], Url::parseParams('http://h/p?a=1&amp;b=2'));
    }

    public function testParseParamsGivesNullForAValuelessParam(): void
    {
        self::assertSame(['flag' => null], Url::parseParams('http://h/p?flag'));
    }

    public function testParseParamsOnAUrlWithNoQueryIsEmpty(): void
    {
        self::assertSame([], Url::parseParams('http://h/p'));
    }

    public function testParamsCanBeAddedReplacedAndRemoved(): void
    {
        $url = new Url('a', ['x' => 1]);

        // getParam() declares ?string, so a numeric param comes back as a string even though
        // getParams() still holds the int it was given.
        self::assertSame('1', $url->getParam('x'));
        self::assertSame(['x' => 1], $url->getParams());

        $url->addParam('y', 'two');
        self::assertSame('two', $url->getParam('y'));

        $url->addParam('x', 'replaced');
        self::assertSame('replaced', $url->getParam('x'));

        $url->removeParam('x');
        self::assertNull($url->getParam('x'));
        self::assertSame(['y' => 'two'], $url->getParams());
    }

    public function testAddParamDecodesItsInput(): void
    {
        $url = (new Url('a'))->addParam('a%20b', 'c%20d');

        self::assertSame('c d', $url->getParam('a b'));
    }

    public function testSetParamsReplacesTheWholeSet(): void
    {
        $url = new Url('a', ['old' => 1]);
        $url->setParams(['new' => 2]);

        self::assertSame(['new' => 2], $url->getParams());
    }

    public function testSettersAreFluentAndTrim(): void
    {
        $url = new Url('a');

        self::assertSame($url, $url->setScheme(' https '));
        self::assertSame('https', $url->getScheme());

        self::assertSame($url, $url->setHostname(' example.com '));
        self::assertSame('example.com', $url->getHost());

        self::assertSame($url, $url->setAnchor(' top '));
        self::assertSame('top', $url->getAnchor());
    }

    public function testSetPathCollapsesRepeatedAndLeadingSlashes(): void
    {
        $url = new Url('a');
        $url->setPath('//a///b/c');

        self::assertSame('a/b/c', $url->getPath());
    }

    /**
     * An empty port means "the default for this scheme".
     */
    public function testSetPortWithNoValueFollowsTheScheme(): void
    {
        $http = (new Url('a'))->setScheme(Url::SCHEME_HTTP)->setPort(0);
        self::assertSame(80, $http->getPort());

        $https = (new Url('a'))->setScheme(Url::SCHEME_HTTPS)->setPort(0);
        self::assertSame(443, $https->getPort());
    }

    public function testSetPortKeepsAnExplicitValue(): void
    {
        self::assertSame(9000, (new Url('a'))->setPort(9000)->getPort());
    }

    public function testAnchorIsNullWhenNotGiven(): void
    {
        self::assertNull((new Url('a'))->getAnchor());
    }

    /**
     * @dataProvider imagePathProvider
     */
    public function testIsImageRecognisesImageExtensions(string $path, bool $expected): void
    {
        self::assertSame($expected, (bool)(new Url($path))->isImage());
    }

    public function imagePathProvider(): array
    {
        return [
            'png' => ['a/b.png', true],
            'jpeg' => ['a/b.jpeg', true],
            'jpg upper' => ['a/b.JPG', true],
            'gif' => ['a/b.gif', true],
            'bmp' => ['a/b.bmp', true],
            'webp' => ['a/b.webp', true],
            'svg' => ['a/b.svg', true],
            'pdf' => ['a/b.pdf', false],
            'no extension' => ['a/b', false],
            'php' => ['a/b.php', false],
        ];
    }

    public function testGetFileNameAndExtension(): void
    {
        $url = new Url('files/report.pdf');

        self::assertSame('report.pdf', $url->getFileName());
        self::assertSame('.pdf', $url->getExtension());
    }

    public function testGetFileNameAndExtensionAreNullWithoutOne(): void
    {
        $url = new Url('files/report');

        self::assertNull($url->getFileName());
        self::assertNull($url->getExtension());
    }

    public function testCastingToStringBuildsTheUrl(): void
    {
        $url = new Url('a/b', ['x' => 1]);

        self::assertSame($url->make(), (string)$url);
    }

    public function testEmptyPathGivesTheRoot(): void
    {
        self::assertSame('http://localhost/', (new Url())->make());
    }
}
