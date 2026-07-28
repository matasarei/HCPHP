<?php

namespace Tests\Unit\DynamicDB;

use DynamicDB\Entity\File;
use DynamicDB\Mapper\FileMapper;
use DynamicDB\Validator\Exception\InvalidFileTypeException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * FileMapper is the single point where an HTTP upload becomes a File, so it is where an
 * unacceptable upload has to be turned away -- before anything is written to disk or to
 * the database.
 *
 * @covers \DynamicDB\Mapper\FileMapper
 */
class FileMapperTest extends TestCase
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @var FileMapper
     */
    private $mapper;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hcphp-mapper-' . getmypid();

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }

        $this->mapper = new FileMapper();
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
    }

    public function testDangerousUploadIsRejected(): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->mapper->mapToEntity($this->upload('shell.php', "<?php echo 'pwned';"));
    }

    public function testUploadWhoseContentsContradictItsNameIsRejected(): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->mapper->mapToEntity($this->upload('holiday.jpg', "<?php echo 'pwned';"));
    }

    public function testGenuineUploadIsMapped(): void
    {
        $file = $this->mapper->mapToEntity($this->upload('holiday.jpg', $this->jpeg()));

        self::assertInstanceOf(File::class, $file);
        self::assertSame('holiday.jpg', $file->getName());
        self::assertTrue($file->isTemporary());
    }

    /**
     * The "no file was submitted" case must keep its distinct code, because
     * EntityMapper::resolveFile() relies on it to leave an optional field untouched.
     */
    public function testMissingUploadStillReportsUploadErrNoFile(): void
    {
        try {
            $this->mapper->mapToEntity([
                'name' => null,
                'type' => null,
                'tmp_name' => null,
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ]);

            self::fail('Expected an UnexpectedValueException');
        } catch (UnexpectedValueException $exception) {
            self::assertSame(UPLOAD_ERR_NO_FILE, $exception->getCode());
        }
    }

    public function testUploadErrorIsReportedBeforeValidation(): void
    {
        // A failed upload has no readable temp file, so the error must win the race.
        try {
            $this->mapper->mapToEntity([
                'name' => 'holiday.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_INI_SIZE,
                'size' => 0,
            ]);

            self::fail('Expected an UnexpectedValueException');
        } catch (UnexpectedValueException $exception) {
            self::assertSame(UPLOAD_ERR_INI_SIZE, $exception->getCode());
        }
    }

    private function upload(string $name, string $contents): array
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $contents);

        return [
            'name' => $name,
            'type' => 'image/jpeg', // as claimed by the browser; must not be trusted
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ];
    }

    private function jpeg(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . str_repeat("\x00", 32) . "\xFF\xD9";
    }
}
