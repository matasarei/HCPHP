<?php

namespace Tests\Unit\Core;

use core\Application;
use PHPUnit\Framework\TestCase;

/**
 * getRemoteIp() returned the first of Client-IP, X-Forwarded-For, X-Forwarded, Forwarded-For
 * and Forwarded that was set, and REMOTE_ADDR only when none was. Every one of those but the
 * last is a header the client writes, so any client could be any address: an IP allow-list
 * built on it let everyone in, and every logged address was whatever the client said.
 *
 * The old code read getenv(), the new code reads $_SERVER; each request below sets both, so the
 * same test runs against either.
 */
class RemoteIpTest extends TestCase
{
    private const HEADERS = [
        'REMOTE_ADDR',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
    ];

    /**
     * @var array
     */
    private $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;

        foreach (self::HEADERS as $header) {
            putenv($header);
        }
    }

    private function request(array $headers): void
    {
        foreach ($headers as $header => $value) {
            $_SERVER[$header] = $value;
            putenv(sprintf('%s=%s', $header, $value));
        }
    }

    /**
     * @dataProvider spoofingHeaderProvider
     */
    public function testAHeaderTheClientWritesIsIgnored(string $header): void
    {
        $this->request(['REMOTE_ADDR' => '203.0.113.9', $header => '10.0.0.1']);

        self::assertSame('203.0.113.9', Application::getRemoteIp([]));
    }

    public function spoofingHeaderProvider(): array
    {
        return [
            'Client-IP' => ['HTTP_CLIENT_IP'],
            'X-Forwarded-For' => ['HTTP_X_FORWARDED_FOR'],
            'X-Forwarded' => ['HTTP_X_FORWARDED'],
            'Forwarded-For' => ['HTTP_FORWARDED_FOR'],
            'Forwarded' => ['HTTP_FORWARDED'],
        ];
    }

    public function testBehindATrustedProxyTheAddressItAppendedIsUsed(): void
    {
        // The client sent "X-Forwarded-For: 10.0.0.1"; the proxy appended the address it saw.
        $this->request([
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 198.51.100.7',
        ]);

        self::assertSame('198.51.100.7', Application::getRemoteIp(['192.0.2.10']));
    }

    public function testAChainOfTrustedProxiesIsSkippedFromTheRight(): void
    {
        $this->request([
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 198.51.100.7, 192.0.2.11',
        ]);

        self::assertSame('198.51.100.7', Application::getRemoteIp(['192.0.2.10', '192.0.2.11']));
    }

    public function testSomethingThatIsNotAnAddressInTheChainIsSkipped(): void
    {
        $this->request([
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7, <script>',
        ]);

        self::assertSame('198.51.100.7', Application::getRemoteIp(['192.0.2.10']));
    }

    public function testATrustedProxyWithNoForwardedAddressIsTheAnswer(): void
    {
        $this->request(['REMOTE_ADDR' => '192.0.2.10']);

        self::assertSame('192.0.2.10', Application::getRemoteIp(['192.0.2.10']));
    }

    public function testFromAnUntrustedAddressTheChainIsNotRead(): void
    {
        $this->request([
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]);

        self::assertSame('203.0.113.9', Application::getRemoteIp(['192.0.2.10']));
    }

    public function testWithNoConnectionItIsLoopback(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        self::assertSame('127.0.0.1', Application::getRemoteIp([]));
    }
}
