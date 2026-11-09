<?php

namespace Tests\Unit\Core;

use core\Url;
use PHPUnit\Framework\TestCase;

/**
 * Url(true) means "this request, on this site". It took the request path and then, like any
 * other string, parsed it as an absolute URL when it looked like one. So a request for
 * /http://evil.example:80/ produced a Url on evil.example, and anything that sends the user
 * back to "where they were" -- a login's back URL -- sent them off-site.
 *
 * There is no request in a CLI process, so Application::getCurrentPath() falls back to the "q"
 * parameter, the same one the web server's rewrite fills in.
 */
class UrlCurrentRequestTest extends TestCase
{
    /**
     * @var array
     */
    private $request;

    protected function setUp(): void
    {
        $this->request = [$_GET, $_REQUEST];
    }

    protected function tearDown(): void
    {
        [$_GET, $_REQUEST] = $this->request;
    }

    private function currentUrl(string $requestPath): Url
    {
        $_GET['q'] = $_REQUEST['q'] = $requestPath;

        return new Url(true);
    }

    /**
     * @dataProvider offSitePathProvider
     */
    public function testTheRequestPathNeverNamesAnotherHost(string $requestPath): void
    {
        self::assertSame('localhost', $this->currentUrl($requestPath)->getHost());
    }

    public function offSitePathProvider(): array
    {
        return [
            'http with a port' => ['/http://evil.example:80/?q=1'],
            'https with a port' => ['/https://evil.example:443/x'],
            'without a port' => ['/http://evil.example/'],
            'with credentials' => ['/http://user@evil.example:80/'],
        ];
    }

    public function testAnOrdinaryPathIsKept(): void
    {
        $url = $this->currentUrl('/user/login');

        self::assertSame('localhost', $url->getHost());
        self::assertSame('http://localhost/user/login', $url->make());
    }

    public function testAPathThatMerelyContainsAUrlStaysAPathOnThisSite(): void
    {
        $url = $this->currentUrl('/http://evil.example:80/');

        self::assertStringStartsWith('http://localhost/', $url->make());
    }
}
