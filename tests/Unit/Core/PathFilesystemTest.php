<?php

namespace Tests\Unit\Core;

use core\Collection;
use core\Path;
use core\Url;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The traversal behaviour is pinned in PathTest; this covers the filesystem side -- creating,
 * listing, removing and describing paths.
 *
 * @covers \core\Path
 */
class PathFilesystemTest extends TestCase
{
    private const DIR = 'cache/tests_fixture_path';

    protected function setUp(): void
    {
        $this->removeFixture();
    }

    protected function tearDown(): void
    {
        $this->removeFixture();
    }

    private function removeFixture(): void
    {
        $path = new Path(self::DIR);

        if (is_dir((string)$path)) {
            $path->rmpath(true);
        }
    }

    private function fixtureFile(string $name, string $contents = 'x'): Path
    {
        $path = new Path(self::DIR . '/' . $name);
        $path->mkpath();
        file_put_contents((string)$path, $contents);

        return $path;
    }

    // --- construction -------------------------------------------------------------------

    public function testPathIsResolvedAgainstTheProjectRoot(): void
    {
        self::assertStringStartsWith(Path::getRoot(), (string)new Path('a/b'));
    }

    public function testSeparatorsAreNormalised(): void
    {
        self::assertSame((string)new Path('a/b/c'), (string)new Path('a\\b\\c'));
    }

    public function testValidationRejectsAnUnreadablePath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Path('no/such/file.txt', true);
    }

    public function testValidationAcceptsAReadablePath(): void
    {
        $file = $this->fixtureFile('readable.txt');

        self::assertInstanceOf(Path::class, new Path(self::DIR . '/readable.txt', true));
        self::assertFileExists((string)$file);
    }

    // --- creating and removing ------------------------------------------------------------

    public function testMkpathCreatesTheParentDirectory(): void
    {
        $path = new Path(self::DIR . '/nested/deeper/file.txt');
        $path->mkpath();

        self::assertDirectoryExists(dirname((string)$path));
        self::assertFileDoesNotExist((string)$path);
    }

    public function testMkpathWithTouchCreatesTheFileToo(): void
    {
        $path = new Path(self::DIR . '/file.txt');
        $path->mkpath(true);

        self::assertFileExists((string)$path);
    }

    public function testTouchCreatesTheFile(): void
    {
        $path = new Path(self::DIR . '/touched.txt');
        $path->touch();

        self::assertFileExists((string)$path);
    }

    public function testRmpathRemovesAFile(): void
    {
        $file = $this->fixtureFile('doomed.txt');

        self::assertTrue($file->rmpath());
        self::assertFileDoesNotExist((string)$file);
    }

    public function testRmpathOnSomethingAbsentIsFalse(): void
    {
        self::assertFalse((new Path(self::DIR . '/never.txt'))->rmpath());
    }

    public function testRmpathRemovesADirectoryTreeWhenRecursive(): void
    {
        $this->fixtureFile('nested/deep/file.txt');
        $directory = new Path(self::DIR);

        self::assertTrue($directory->rmpath(true));
        self::assertDirectoryDoesNotExist((string)$directory);
    }

    // --- describing --------------------------------------------------------------------------

    public function testGetExtension(): void
    {
        self::assertSame('pdf', (new Path('a/report.pdf'))->getExtension());
        self::assertSame('gz', (new Path('a/archive.tar.gz'))->getExtension());
        self::assertNull((new Path('a/noextension'))->getExtension());
    }

    public function testGetFileName(): void
    {
        self::assertSame('report.pdf', (new Path('a/b/report.pdf'))->getFileName());
        self::assertNull((new Path('a/b/plain'))->getFileName());
    }

    public function testGetFileNameDecodesAnEscapedName(): void
    {
        self::assertSame('my report.pdf', (new Path('a/my%20report.pdf'))->getFileName());
    }

    /**
     * @dataProvider imageNameProvider
     */
    public function testIsImageByExtension(string $name, bool $expected): void
    {
        self::assertSame($expected, (new Path('a/' . $name))->isImage());
    }

    public function imageNameProvider(): array
    {
        return [
            'png' => ['a.png', true],
            'jpg' => ['a.jpg', true],
            'JPEG upper' => ['a.JPEG', true],
            'svg' => ['a.svg', true],
            'pdf' => ['a.pdf', false],
            'no extension' => ['a', false],
        ];
    }

    /**
     * The strict check reads the file rather than trusting its name, which is what stops a
     * .png that is really a script.
     */
    public function testStrictIsImageReadsTheContent(): void
    {
        $liar = $this->fixtureFile('liar.png', '<?php echo 1;');

        self::assertTrue($liar->isImage(), 'the name says image');
        self::assertFalse($liar->isImage(true), 'the content says otherwise');
    }

    public function testGetMimeTypeReadsTheFile(): void
    {
        $file = $this->fixtureFile('plain.txt', "hello\n");

        self::assertSame('text/plain', $file->getMimeType());
    }

    public function testGetUrlBuildsAUrlForThePath(): void
    {
        self::assertInstanceOf(Url::class, (new Path('a/b.png'))->getUrl());
    }

    // --- listing ------------------------------------------------------------------------------

    public function testGetListReturnsNullForSomethingThatIsNotADirectory(): void
    {
        $file = $this->fixtureFile('a.txt');

        self::assertNull($file->getList());
        self::assertNull((new Path(self::DIR . '/absent'))->getList());
    }

    public function testGetListReturnsTheChildren(): void
    {
        $this->fixtureFile('a.txt');
        $this->fixtureFile('b.txt');
        $this->fixtureFile('sub/c.txt');

        $list = (new Path(self::DIR))->getList();

        self::assertInstanceOf(Collection::class, $list);
        self::assertCount(3, $list, 'two files and a directory');
    }

    public function testGetListCanBeFilteredToFiles(): void
    {
        $this->fixtureFile('a.txt');
        $this->fixtureFile('sub/c.txt');

        self::assertCount(1, (new Path(self::DIR))->getList(Path::TYPE_FILE));
    }

    public function testGetListCanBeFilteredToDirectories(): void
    {
        $this->fixtureFile('a.txt');
        $this->fixtureFile('sub/c.txt');

        self::assertCount(1, (new Path(self::DIR))->getList(Path::TYPE_DIRECTORY));
    }

    /**
     * The dot entries scandir() returns are not children.
     */
    public function testGetListSkipsDotEntries(): void
    {
        $this->fixtureFile('a.txt');

        foreach ((new Path(self::DIR))->getList() as $child) {
            self::assertStringNotContainsString(DIRECTORY_SEPARATOR . '.', (string)$child);
        }
    }

    public function testGetListOnAnEmptyDirectoryIsEmpty(): void
    {
        (new Path(self::DIR . '/placeholder'))->mkpath();

        self::assertCount(0, (new Path(self::DIR))->getList());
    }

    // --- root ---------------------------------------------------------------------------------

    public function testInitRejectsAPathThatDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Path::init('/no/such/directory/anywhere');
    }

    public function testGetRootIsTheConfiguredRoot(): void
    {
        self::assertDirectoryExists(Path::getRoot());
    }
}
