<?php

namespace Tests\Unit\DynamicDB;

use DynamicDB\Entity\File;
use DynamicDB\Validator\Exception\InvalidFileTypeException;
use DynamicDB\Validator\FileUploadValidator;
use PHPUnit\Framework\TestCase;

/**
 * An upload is attacker-controlled in three independent ways: its filename, its declared
 * MIME type, and its contents. The validator must not trust any of them.
 *
 * The case that motivates all of this: a file called "shell.php" used to be stored as
 * shared/dynamicdb/<id>/<field>.php inside the document root, where nginx would execute it.
 *
 * @covers \DynamicDB\Validator\FileUploadValidator
 */
class FileUploadValidatorTest extends TestCase
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @var FileUploadValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hcphp-upload-' . getmypid();

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }

        $this->validator = new FileUploadValidator();
    }

    protected function tearDown(): void
    {
        // scandir rather than glob: one of the fixtures is named ".htaccess" and glob('*')
        // does not match dotfiles.
        foreach (scandir($this->dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($this->dir . '/' . $entry);
            }
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    // --- filenames the web server would execute ------------------------------------------

    /**
     * @dataProvider executableNameProvider
     */
    public function testExecutableExtensionsAreRejected(string $name): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload($name, $this->jpeg()));
    }

    public function executableNameProvider(): array
    {
        return [
            'php'            => ['shell.php'],
            'php5'           => ['shell.php5'],
            'phtml'          => ['shell.phtml'],
            'phar'           => ['shell.phar'],
            'htaccess'       => ['.htaccess'],
            'double suffix'  => ['image.jpg.php'],
            'uppercase'      => ['SHELL.PHP'],
            'shell script'   => ['run.sh'],
        ];
    }

    public function testUnknownExtensionIsRejected(): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload('archive.xyz', $this->jpeg()));
    }

    public function testFileWithNoExtensionIsRejected(): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload('noextension', $this->jpeg()));
    }

    // --- contents that disagree with the name -------------------------------------------

    public function testPhpSourceRenamedToAnImageIsRejected(): void
    {
        // Extension says jpg, contents say PHP. finfo reports text/x-php.
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload('shell.php.jpg', "<?php echo 'pwned';"));
    }

    public function testPolyglotImageContainingPhpIsRejected(): void
    {
        // Real JPEG magic bytes, so finfo reports image/jpeg -- but PHP code rides along.
        // MIME sniffing alone cannot catch this; the content scan must.
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload('holiday.jpg', $this->jpeg("<?php echo 'pwned';")));
    }

    public function testShortEchoTagIsAlsoCaught(): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload('holiday.jpg', $this->jpeg("<?= 'pwned';")));
    }

    /**
     * @dataProvider executableBinaryProvider
     */
    public function testExecutableBinariesAreRejected(string $magic): void
    {
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate($this->upload('payload.zip', $magic . str_repeat("\x00", 128)));
    }

    public function executableBinaryProvider(): array
    {
        return [
            'ELF'    => ["\x7fELF\x02\x01\x01\x00"],
            'PE/EXE' => ['MZ' . "\x90\x00\x03\x00\x00\x00"],
        ];
    }

    public function testClientSuppliedMimeTypeIsNotTrusted(): void
    {
        // $_FILES['type'] comes from the browser. Claiming image/jpeg must not help.
        $this->expectException(InvalidFileTypeException::class);

        $this->validator->validate(
            new File('shell.jpg', 'image/jpeg', $this->write('shell.jpg', "<?php echo 'pwned';"), 22, true)
        );
    }

    // --- size ----------------------------------------------------------------------------

    public function testOversizeFileIsRejected(): void
    {
        $validator = new FileUploadValidator(1024);

        $this->expectException(InvalidFileTypeException::class);

        $validator->validate($this->upload('big.jpg', $this->jpeg(str_repeat('x', 4096))));
    }

    // --- files that must keep working ----------------------------------------------------

    public function testGenuineImageIsAccepted(): void
    {
        $this->validator->validate($this->upload('holiday.jpg', $this->jpeg()));

        $this->addToAssertionCount(1);
    }

    public function testGenuinePdfIsAccepted(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $this->validator->validate($this->upload('report.pdf', $pdf));

        $this->addToAssertionCount(1);
    }

    public function testOfficeDocumentDetectedAsZipIsAccepted(): void
    {
        // .docx/.xlsx are ZIP containers and finfo reports application/zip for them.
        // This is a genuine archive rather than a hand-made stub: a truncated one is
        // sniffed as application/octet-stream by some libmagic builds and not others.
        $this->validator->validate($this->upload('report.docx', $this->docx()));

        $this->addToAssertionCount(1);
    }

    public function testXmlDeclarationIsNotMistakenForPhp(): void
    {
        // "<?xml" opens with the same two characters as a PHP tag. Rejecting it would
        // break every XML upload.
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<root><item>1</item></root>\n";

        $this->validator->validate($this->upload('feed.xml', $xml));

        $this->addToAssertionCount(1);
    }

    public function testPlainTextMentioningScriptKeywordsIsAccepted(): void
    {
        // finfo reports text/html for this, and the words below are harmless in a text file.
        // Rejecting them would make the validator fire on ordinary documents, which is how
        // validators end up switched off in production.
        $text = "notes: we discussed eval( and <script and base64_decode( in the review\n";

        $this->validator->validate($this->upload('notes.txt', $text));

        $this->addToAssertionCount(1);
    }

    public function testNonTemporaryFileSkipsContentInspection(): void
    {
        // Files already on disk (read back from storage) were validated on the way in and
        // may no longer exist at the recorded path.
        $this->validator->validate(new File('holiday.jpg', 'image/jpeg', '/nonexistent/holiday.jpg', 100));

        $this->addToAssertionCount(1);
    }

    // --- helpers -------------------------------------------------------------------------

    private function upload(string $name, string $contents): File
    {
        $path = $this->write($name, $contents);

        return new File($name, 'application/octet-stream', $path, filesize($path), true);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->dir . '/' . str_replace('/', '_', $name);
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * A minimal but genuinely sniffable JPEG, optionally with a payload smuggled inside.
     */
    private function jpeg(string $payload = ''): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . str_repeat("\x00", 32)
            . $payload
            . "\xFF\xD9";
    }

    /**
     * A real deflated ZIP holding one entry, shaped like the inside of a .docx.
     */
    private function docx(): string
    {
        return (string)base64_decode(
            'UEsDBBQAAAAIAK9o/FzMy454JAAAACUAAAARAAAAd29yZC9kb2N1bWVudC54bWyzsa/IzVEoSy0q'
            . 'zszPs1Uy1DNQsrezSclPtstIzcnJt9EHMQFQSwECFAMUAAAACACvaPxczMuOeCQAAAAlAAAAEQAA'
            . 'AAAAAAAAAAAAgAEAAAAAd29yZC9kb2N1bWVudC54bWxQSwUGAAAAAAEAAQA/AAAAUwAAAAAA'
        );
    }
}
