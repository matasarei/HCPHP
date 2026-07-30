<?php

namespace Tests\Unit\Core;

use core\Cache;
use core\Path;
use PHPUnit\Framework\TestCase;

/**
 * Cache::_getFile() built its path as new Path('cache/%s.tmp', $name). Path's second
 * parameter is $validate, not a sprintf argument, so the key was cast to a boolean, the %s
 * was never substituted, and every static cache entry resolved to the same file -- which,
 * because $validate was then truthy, the constructor refused as unreadable. Every single
 * CACHE_STATIC call threw.
 *
 * @covers \core\Cache
 */
class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Cache::remove('alpha');
        Cache::remove('beta');
    }

    protected function tearDown(): void
    {
        foreach (['alpha', 'beta'] as $key) {
            Cache::remove($key, Cache::CACHE_REQUEST);
            Cache::remove($key, Cache::CACHE_SESSION);
            Cache::remove($key, Cache::CACHE_STATIC);
        }

        $_SESSION = [];
    }

    public function testStaticCacheRoundTrips(): void
    {
        Cache::set('alpha', 'first value', Cache::CACHE_STATIC);

        self::assertSame('first value', Cache::get('alpha', Cache::CACHE_STATIC));
    }

    public function testStaticCacheKeepsKeysApart(): void
    {
        Cache::set('alpha', 'value A', Cache::CACHE_STATIC);
        Cache::set('beta', 'value B', Cache::CACHE_STATIC);

        self::assertSame('value A', Cache::get('alpha', Cache::CACHE_STATIC));
        self::assertSame('value B', Cache::get('beta', Cache::CACHE_STATIC));
    }

    public function testStaticCacheWritesOneFilePerKey(): void
    {
        Cache::set('alpha', 'value A', Cache::CACHE_STATIC);
        Cache::set('beta', 'value B', Cache::CACHE_STATIC);

        self::assertFileExists((string)new Path('cache/alpha.tmp'));
        self::assertFileExists((string)new Path('cache/beta.tmp'));
    }

    public function testMissingStaticEntryReturnsNullRatherThanThrowing(): void
    {
        self::assertNull(Cache::get('never-written', Cache::CACHE_STATIC));
    }

    public function testStaticEntryCanBeRemoved(): void
    {
        Cache::set('alpha', 'value', Cache::CACHE_STATIC);
        Cache::remove('alpha', Cache::CACHE_STATIC);

        self::assertNull(Cache::get('alpha', Cache::CACHE_STATIC));
    }

    public function testRemovingAnAbsentStaticEntryIsHarmless(): void
    {
        Cache::remove('never-written', Cache::CACHE_STATIC);

        $this->addToAssertionCount(1);
    }

    public function testStaticEntryExpiresOnceItIsOlderThanTheGivenAge(): void
    {
        Cache::set('alpha', 'value', Cache::CACHE_STATIC);

        self::assertSame('value', Cache::get('alpha', Cache::CACHE_STATIC, 3600));
        self::assertNull(Cache::get('alpha', Cache::CACHE_STATIC, -1), 'Already older than the limit.');
    }

    public function testRequestCacheRoundTrips(): void
    {
        Cache::set('alpha', ['a' => 1]);

        self::assertSame(['a' => 1], Cache::get('alpha'));
    }

    public function testSessionCacheRoundTrips(): void
    {
        Cache::set('alpha', 'session value', Cache::CACHE_SESSION);

        self::assertSame('session value', Cache::get('alpha', Cache::CACHE_SESSION));
    }

    public function testKeysContainingPathSeparatorsCannotEscapeTheCacheDirectory(): void
    {
        Cache::set('../../escaped', 'value', Cache::CACHE_STATIC);

        self::assertFileDoesNotExist(dirname((string)new Path('cache')) . '/escaped.tmp');

        Cache::remove('../../escaped', Cache::CACHE_STATIC);
    }
}
