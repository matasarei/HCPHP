<?php

namespace Tests\Unit\Core;

use core\Cache;
use core\Path;
use PHPUnit\Framework\TestCase;

/**
 * Cache could write CACHE_STATIC entries but never clear them in bulk: cache/ grew without
 * bound and the only way to invalidate it was rm on the server.
 *
 * @covers \core\Cache
 */
class CachePurgeTest extends TestCase
{
    private const KEYS = ['alpha', 'beta', 'gamma'];

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->removeAll();
    }

    protected function tearDown(): void
    {
        $this->removeAll();
        $_SESSION = [];

        $templates = new Path('cache/templates');

        if (is_dir((string)$templates)) {
            $templates->rmpath(true);
        }
    }

    private function removeAll(): void
    {
        foreach (self::KEYS as $key) {
            Cache::remove($key, Cache::CACHE_REQUEST);
            Cache::remove($key, Cache::CACHE_SESSION);
            Cache::remove($key, Cache::CACHE_STATIC);
        }
    }

    public function testPurgeClearsTheRequestCache(): void
    {
        Cache::set('alpha', 'value');
        Cache::set('beta', 'value');

        Cache::purge();

        self::assertNull(Cache::get('alpha'));
        self::assertNull(Cache::get('beta'));
    }

    public function testPurgeClearsTheSessionCache(): void
    {
        Cache::set('alpha', 'value', Cache::CACHE_SESSION);
        Cache::set('beta', 'value', Cache::CACHE_SESSION);

        Cache::purge(Cache::CACHE_SESSION);

        self::assertNull(Cache::get('alpha', Cache::CACHE_SESSION));
        self::assertNull(Cache::get('beta', Cache::CACHE_SESSION));
    }

    public function testPurgeClearsTheStaticCache(): void
    {
        Cache::set('alpha', 'value', Cache::CACHE_STATIC);
        Cache::set('beta', 'value', Cache::CACHE_STATIC);

        Cache::purge(Cache::CACHE_STATIC);

        self::assertNull(Cache::get('alpha', Cache::CACHE_STATIC));
        self::assertNull(Cache::get('beta', Cache::CACHE_STATIC));
    }

    public function testPurgeRemovesTheBackingFiles(): void
    {
        Cache::set('alpha', 'value', Cache::CACHE_STATIC);
        self::assertNotSame([], $this->backingFiles());

        Cache::purge(Cache::CACHE_STATIC);

        self::assertSame([], $this->backingFiles());
    }

    /**
     * @return string[] the .tmp files backing CACHE_STATIC, empty when there are none
     */
    private function backingFiles(): array
    {
        return glob((string)new Path(Cache::STATIC_CACHE_PATH) . '/*.tmp') ?: [];
    }

    /**
     * Compiled templates live in cache/templates/. Purging the static cache must not take
     * them with it -- Template owns that directory and clears it separately.
     */
    public function testPurgingTheStaticCacheLeavesSubdirectoriesAlone(): void
    {
        // mkpath() creates the parent of the path it is given, so this is the marker file
        // itself rather than the directory holding it.
        $marker = new Path('cache/templates/marker.tmp');
        $marker->mkpath();
        file_put_contents((string)$marker, 'compiled');

        Cache::set('alpha', 'value', Cache::CACHE_STATIC);
        Cache::purge(Cache::CACHE_STATIC);

        self::assertFileExists((string)$marker);
        self::assertNull(Cache::get('alpha', Cache::CACHE_STATIC));
    }

    public function testPurgingOneTypeLeavesTheOthersIntact(): void
    {
        Cache::set('alpha', 'request value');
        Cache::set('alpha', 'session value', Cache::CACHE_SESSION);
        Cache::set('alpha', 'static value', Cache::CACHE_STATIC);

        Cache::purge(Cache::CACHE_REQUEST);

        self::assertNull(Cache::get('alpha'));
        self::assertSame('session value', Cache::get('alpha', Cache::CACHE_SESSION));
        self::assertSame('static value', Cache::get('alpha', Cache::CACHE_STATIC));
    }

    /**
     * On a fresh checkout nothing has been cached yet, so cache/ may not exist. Purging is
     * a cleanup operation and has nothing to complain about.
     */
    public function testPurgingAnAbsentStaticCacheDirectoryIsNotAnError(): void
    {
        $cache = new Path('cache');

        if (is_dir((string)$cache)) {
            $cache->rmpath(true);
        }

        Cache::purge(Cache::CACHE_STATIC);

        self::assertNull(Cache::get('alpha', Cache::CACHE_STATIC));
    }

    public function testPurgingAnEmptySessionCacheIsNotAnError(): void
    {
        $_SESSION = [];

        Cache::purge(Cache::CACHE_SESSION);

        self::assertSame([], $_SESSION['cache']);
    }
}
