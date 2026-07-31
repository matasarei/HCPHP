<?php

namespace Tests\Unit\Core;

use core\Path;
use core\Template;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Template compiles every template into cache/templates/ and reuses the compiled file while
 * its mtime is newer than the source. There was no way to clear that directory, so a deploy
 * that restores mtimes -- rsync without --times, a checkout onto a warm cache, an archive
 * unpacked with preserved timestamps -- kept serving the previous build's compiled templates
 * with no way to force a recompile short of rm on the server.
 */
class TemplateCacheTest extends TestCase
{
    /**
     * Nested on purpose: real template names carry slashes ('form/default'), so compiled
     * files land in subdirectories and a non-recursive purge would miss most of them.
     */
    private const FIXTURE = 'tests_fixture/nested';

    protected function setUp(): void
    {
        $source = new Path(sprintf('application/templates/%s.php', self::FIXTURE));
        $source->mkpath();
        file_put_contents((string)$source, 'compiled output');

        Template::purgeCaches();
    }

    protected function tearDown(): void
    {
        Template::purgeCaches();

        $dir = new Path('application/templates/tests_fixture');

        if (is_dir((string)$dir)) {
            $dir->rmpath(true);
        }
    }

    public function testMakeCompilesTheTemplateIntoTheCacheDirectory(): void
    {
        (new Template(self::FIXTURE))->make();

        self::assertNotSame([], $this->compiledFiles(), 'make() should have written a compiled template');
    }

    public function testCompiledTemplateLandsInASubdirectory(): void
    {
        (new Template(self::FIXTURE))->make();

        self::assertStringContainsString(
            'tests_fixture' . DIRECTORY_SEPARATOR,
            $this->compiledFiles()[0]
        );
    }

    public function testPurgeCachesRemovesCompiledTemplates(): void
    {
        (new Template(self::FIXTURE))->make();
        self::assertNotSame([], $this->compiledFiles());

        Template::purgeCaches();

        self::assertSame([], $this->compiledFiles());
    }

    public function testPurgeCachesOnAnAlreadyEmptyCacheIsNotAnError(): void
    {
        Template::purgeCaches();
        Template::purgeCaches();

        self::assertSame([], $this->compiledFiles());
    }

    public function testTemplateStillRendersAfterAPurge(): void
    {
        $before = (new Template(self::FIXTURE))->make();

        Template::purgeCaches();

        $after = (new Template(self::FIXTURE))->make();

        self::assertSame('compiled output', $before);
        self::assertSame($before, $after);
    }

    /**
     * Purging the compiled templates must not take the sibling CACHE_STATIC entries with it
     * -- Cache owns those and clears them separately.
     */
    public function testPurgeCachesLeavesTheStaticCacheAlone(): void
    {
        \core\Cache::set('template_cache_probe', 'value', \core\Cache::CACHE_STATIC);

        (new Template(self::FIXTURE))->make();
        Template::purgeCaches();

        self::assertSame('value', \core\Cache::get('template_cache_probe', \core\Cache::CACHE_STATIC));

        \core\Cache::remove('template_cache_probe', \core\Cache::CACHE_STATIC);
    }

    /**
     * @return string[]
     */
    private function compiledFiles(): array
    {
        $dir = (string)new Path(Template::CACHE_PATH);

        if (!is_dir($dir)) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
