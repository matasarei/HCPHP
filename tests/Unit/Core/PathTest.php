<?php

namespace Tests\Unit\Core;

use core\Path;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Path stripped leading slashes but did nothing about "..", so a value carrying one resolved
 * outside the project root. Application::start() builds a Path straight from the query
 * string, and several callers build one from stored filenames.
 */
class PathTest extends TestCase
{
    /**
     * @dataProvider traversalProvider
     */
    public function testTraversalCannotEscapeTheRoot(string $candidate): void
    {
        $resolved = (string)new Path($candidate);

        self::assertStringStartsWith(
            Path::getRoot(),
            $resolved,
            sprintf('"%s" resolved outside the project root', $candidate)
        );
        self::assertStringNotContainsString('..', $resolved);
    }

    public function traversalProvider(): array
    {
        return [
            'simple' => ['../../etc/passwd'],
            'leading slash' => ['/../../etc/passwd'],
            'embedded' => ['application/../../etc/passwd'],
            'trailing' => ['application/config/..'],
            'backslashes' => ['..\\..\\etc\\passwd'],
            'mixed separators' => ['application/..\\../etc/passwd'],
            'repeated' => ['a/../../../../../../etc/passwd'],
            'only dots' => ['..'],
        ];
    }

    public function testOrdinaryPathsAreUnaffected(): void
    {
        self::assertSame(
            Path::getRoot() . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config',
            (string)new Path('application/config')
        );
    }

    public function testSingleDotSegmentsAreCollapsed(): void
    {
        self::assertSame(
            (string)new Path('application/config'),
            (string)new Path('application/./config')
        );
    }

    public function testAnExistingFileStillResolves(): void
    {
        $path = new Path('application/config/default.json');

        self::assertFileExists((string)$path);
    }

    public function testValidationStillRejectsAMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Path('application/config/nothing-here.json', true);
    }

    public function testEmptyPathIsTheRoot(): void
    {
        self::assertSame(Path::getRoot(), rtrim((string)new Path(''), DIRECTORY_SEPARATOR));
    }
}
