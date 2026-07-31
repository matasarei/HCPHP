<?php

namespace Tests\Unit\DynamicDB;

use core\Path;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;
use DynamicDB\Manager\FileManager;
use DynamicDB\Validator\Exception\InvalidFileTypeException;
use PHPUnit\Framework\TestCase;

/**
 * FileManager is the last thing standing between an upload and the filesystem. It re-checks
 * the file even though FileMapper already did, so a caller that builds a File by hand cannot
 * write something executable into the storage directory.
 */
class FileManagerTest extends TestCase
{
    private const ENTITY_ID = 987654;

    /**
     * @var string
     */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hcphp-manager-' . getmypid();

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($this->dir . '/' . $entry);
            }
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }

        $storage = new Path(sprintf('shared/dynamicdb/%d', self::ENTITY_ID));
        $storage->rmpath(true);
    }

    /**
     * @var array[] [$from, $to] recorded instead of actually moving
     */
    private $moved = [];

    public function testExecutableUploadIsNeverWrittenToStorage(): void
    {
        $entity = $this->entity($this->file('shell.php', "<?php echo 'pwned';"));

        try {
            $this->manager()->saveFiles($entity);
            self::fail('Expected the upload to be refused');
        } catch (InvalidFileTypeException $exception) {
            // expected
        }

        self::assertSame([], $this->moved, 'Nothing may be written for a refused upload.');
        self::assertFileDoesNotExist(
            (string)new Path(sprintf('shared/dynamicdb/%d/file_field.php', self::ENTITY_ID))
        );
    }

    public function testPolyglotImageIsNeverWrittenToStorage(): void
    {
        $entity = $this->entity($this->file('holiday.jpg', $this->jpeg("<?php echo 'pwned';")));

        $this->expectException(InvalidFileTypeException::class);

        try {
            $this->manager()->saveFiles($entity);
        } finally {
            self::assertSame([], $this->moved);
        }
    }

    public function testGenuineUploadIsStoredUnderTheValidatedExtension(): void
    {
        $entity = $this->entity($this->file('holiday.JPG', $this->jpeg()));

        $this->manager()->saveFiles($entity);

        self::assertCount(1, $this->moved);
        self::assertStringEndsWith(
            sprintf('shared/dynamicdb/%d/file_field.jpg', self::ENTITY_ID),
            $this->moved[0][1],
            'The stored extension comes from the validated file, lowercased.'
        );
    }

    public function testNothingIsStoredWhenALaterFieldIsRefused(): void
    {
        // Two file fields, the second unacceptable. The first must not already be on disk
        // by the time the second is refused.
        $table = (new Table('records', 'Records'))
            ->addField(new Field('first_file', 'First', Field::TYPE_FILE))
            ->addField(new Field('second_file', 'Second', Field::TYPE_FILE));

        $entity = (new DynamicEntity())
            ->set('first_file', $this->file('holiday.jpg', $this->jpeg()))
            ->set('second_file', $this->file('shell.php', "<?php echo 'pwned';"))
            ->setId(self::ENTITY_ID);

        $manager = new FileManager($table, null, function (string $from, string $to): bool {
            $this->moved[] = [$from, $to];

            return true;
        });

        $this->expectException(InvalidFileTypeException::class);

        try {
            $manager->saveFiles($entity);
        } finally {
            self::assertSame([], $this->moved, 'A refused field must roll the whole save back.');
        }
    }

    public function testAlreadyStoredFileIsNotMovedAgain(): void
    {
        // Not temporary: this is a file read back from storage, not a fresh upload.
        $entity = $this->entity(new File('holiday.jpg', 'image/jpeg', '/nonexistent.jpg', 10));

        $this->manager()->saveFiles($entity);

        self::assertSame([], $this->moved);
    }

    // --- helpers -------------------------------------------------------------------------

    /**
     * move_uploaded_file() only succeeds for files that genuinely arrived via HTTP POST, so
     * the move is replaced here with a recorder.
     */
    private function manager(): FileManager
    {
        return new FileManager(
            $this->table(),
            null,
            function (string $from, string $to): bool {
                $this->moved[] = [$from, $to];

                return true;
            }
        );
    }

    private function table(): Table
    {
        return (new Table('records', 'Records'))
            ->addField(new Field('file_field', 'File', Field::TYPE_FILE));
    }

    private function entity(File $file): DynamicEntity
    {
        return (new DynamicEntity())
            ->set('file_field', $file)
            ->setId(self::ENTITY_ID);
    }

    private function file(string $name, string $contents): File
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $contents);

        return new File($name, 'image/jpeg', $path, filesize($path), true);
    }

    private function jpeg(string $payload = ''): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . str_repeat("\x00", 32) . $payload . "\xFF\xD9";
    }
}
