<?php

namespace Tests\Unit\Core;

use core\Config;
use core\Path;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigTest extends TestCase
{
    private const NAME = 'tests_fixture_config';

    /**
     * @var string
     */
    private $path;

    protected function setUp(): void
    {
        $this->path = (string)new Path(sprintf('application/config/%s.json', self::NAME));

        file_put_contents($this->path, json_encode([
            'host' => 'localhost',
            'port' => 3306,
            'debug' => 0,
            'empty' => '',
            'list' => ['a', 'b'],
            'map' => ['k' => 'v'],
        ]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    private function config(array $vars): Config
    {
        return new Config(self::NAME, $vars);
    }

    public function testReadsRequiredKeys(): void
    {
        $config = $this->config(['host', 'port']);

        self::assertSame('localhost', $config->get('host'));
        self::assertSame(3306, $config->get('port'));
    }

    /**
     * A list entry means required, a map entry means optional with that default. The two are
     * told apart by whether the array key is numeric.
     */
    public function testMissingRequiredKeyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Can't set 'absent'");

        $this->config(['absent']);
    }

    public function testMissingOptionalKeyFallsBackToItsDefault(): void
    {
        self::assertSame('fallback', $this->config(['absent' => 'fallback'])->get('absent'));
    }

    /**
     * A null default used to be indistinguishable from "no default at all".
     */
    public function testNullIsAUsableDefault(): void
    {
        self::assertNull($this->config(['absent' => null])->get('absent'));
    }

    public function testPresentKeyWinsOverItsDefault(): void
    {
        self::assertSame('localhost', $this->config(['host' => 'other'])->get('host'));
    }

    /**
     * A present-but-falsy value must not be replaced by the default.
     */
    public function testZeroIsKeptRatherThanTreatedAsAbsent(): void
    {
        self::assertSame(0, $this->config(['debug' => 99])->get('debug'));
        self::assertSame('', $this->config(['empty' => 'nope'])->get('empty'));
    }

    public function testUnknownKeyReadsAsNull(): void
    {
        self::assertNull($this->config(['host'])->get('never_declared'));
    }

    public function testValuesCanBeOverriddenAtRuntime(): void
    {
        $config = $this->config(['host']);
        $config->set('host', 'replaced');

        self::assertSame('replaced', $config->get('host'));
    }

    public function testMagicAccessors(): void
    {
        $config = $this->config(['host']);

        self::assertSame('localhost', $config->host);

        $config->host = 'magic';

        self::assertSame('magic', $config->host);
    }

    public function testIsEmpty(): void
    {
        $config = $this->config(['host', 'empty', 'debug']);

        self::assertFalse($config->isEmpty('host'));
        self::assertTrue($config->isEmpty('empty'));
        self::assertTrue($config->isEmpty('debug'));
        self::assertTrue($config->isEmpty('never_declared'));
    }

    public function testGetArrayConvertsNestedObjectsToArrays(): void
    {
        $config = $this->config(['list', 'map']);

        self::assertSame(['a', 'b'], $config->getArray('list'));
        self::assertSame(['k' => 'v'], $config->getArray('map'));
    }

    public function testTimeModifiedComesFromTheFile(): void
    {
        self::assertSame(filemtime($this->path), $this->config(['host'])->getTimeModified());
    }

    /**
     * database.json and default.json are no longer committed, so a fresh checkout has only
     * the samples. The message has to say what to copy.
     */
    public function testMissingFileExplainsHowToCreateIt(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('.json.sample');

        new Config('tests_fixture_absent_config', []);
    }
}
