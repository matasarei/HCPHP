<?php

namespace DynamicDB\Validator;

use DynamicDB\Entity\File;
use DynamicDB\Validator\Exception\InvalidFileTypeException;

/**
 * Decides whether an upload may be stored.
 *
 * Three things about an upload are attacker-controlled and none of them are trusted here:
 *
 *  - the filename, so the extension is checked against a deny-list and then an allow-list;
 *  - the declared MIME type ($_FILES['type'] is sent by the browser), so the type is
 *    re-detected from the file's own bytes with finfo and that is what gets checked;
 *  - the contents, so the bytes are scanned for a PHP open tag and for executable headers.
 *
 * The content scan is not redundant with the MIME check. A file can carry valid JPEG magic
 * bytes -- and so be reported as image/jpeg -- while still containing `<?php` further in.
 * Only the scan catches that.
 *
 * Deliberately *not* done: rejecting files because they contain strings like "<script",
 * "eval(" or "base64_decode(". Ordinary PDFs, ZIP-based Office documents and plain text hit
 * those constantly, and a validator that rejects real documents is one that gets switched
 * off. The checks kept here are the ones that mean something.
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class FileUploadValidator
{
    const DEFAULT_MAX_SIZE = 52428800; // 50 MB

    /**
     * Extensions that must never be stored, whatever else the file looks like.
     * Checked before the allow-list so a name like "image.jpg.php" cannot slip through.
     */
    const FORBIDDEN_EXTENSIONS = [
        // Anything the web server might execute.
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar', 'inc',
        'asp', 'aspx', 'jsp', 'jspx', 'cgi', 'pl', 'py', 'rb',
        // Server configuration.
        'htaccess', 'htpasswd', 'ini', 'conf',
        // Executables and libraries.
        'exe', 'com', 'bat', 'cmd', 'msi', 'sh', 'bash', 'zsh', 'ps1',
        'dll', 'so', 'dylib', 'app', 'deb', 'rpm', 'jar',
        // Client-side script.
        'js', 'mjs', 'vbs', 'wsf', 'hta',
        // SVG is an image that can execute script when rendered inline.
        'svg', 'svgz',
    ];

    /**
     * Everything the application is willing to store. Anything absent is rejected.
     */
    const ALLOWED_EXTENSIONS = [
        // Images.
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff',
        // Documents.
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'rtf',
        // Text and data.
        'txt', 'csv', 'json', 'xml',
        // Archives.
        'zip', '7z', 'rar', 'gz', 'tar',
    ];

    /**
     * MIME types acceptable when detected from the file's own bytes.
     *
     * Note text/html: finfo reports it for plain text files that merely contain angle
     * brackets. The extension allow-list is what keeps real .html out, so accepting the
     * detected type here does not open a hole.
     */
    const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'image/x-ms-bmp', 'image/tiff',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'text/plain', 'text/html', 'text/csv', 'text/rtf', 'application/rtf',
        'text/xml', 'application/xml', 'application/json',
        // ZIP covers .docx/.xlsx/.pptx/.odt and friends, which are ZIP containers.
        'application/zip', 'application/x-zip-compressed',
        'application/x-7z-compressed', 'application/x-rar', 'application/x-rar-compressed',
        'application/gzip', 'application/x-gzip', 'application/x-tar',
    ];

    /**
     * Leading bytes of things the operating system would run.
     */
    const EXECUTABLE_SIGNATURES = [
        "\x7fELF",         // ELF (Linux)
        'MZ',              // PE (Windows)
        "\xfe\xed\xfa\xce", // Mach-O 32-bit
        "\xfe\xed\xfa\xcf", // Mach-O 64-bit
        "\xce\xfa\xed\xfe", // Mach-O 32-bit, reversed
        "\xcf\xfa\xed\xfe", // Mach-O 64-bit, reversed
        "\xca\xfe\xba\xbe", // Mach-O universal binary
    ];

    /**
     * Markers whose presence anywhere in the file makes it unsafe to store.
     */
    const SCRIPT_MARKERS = ['<?php', '<?='];

    /**
     * Bytes read per pass when scanning for the markers above.
     */
    const SCAN_CHUNK_SIZE = 65536;

    /**
     * @var int
     */
    private $maxFileSize;

    public function __construct(int $maxFileSize = self::DEFAULT_MAX_SIZE)
    {
        $this->maxFileSize = $maxFileSize;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * @param File $file
     *
     * @throws InvalidFileTypeException
     */
    public function validate(File $file): void
    {
        $extension = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));

        if ($extension === '') {
            throw new InvalidFileTypeException('Files must have an extension');
        }

        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw new InvalidFileTypeException(sprintf('Forbidden file type ".%s"', $extension));
        }

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidFileTypeException(sprintf('Unsupported file type ".%s"', $extension));
        }

        // Anything already in storage was checked on the way in, and its recorded path may
        // no longer resolve. Only freshly uploaded files get their bytes inspected.
        if (!$file->isTemporary()) {
            $this->assertWithinSizeLimit($file->getSize());

            return;
        }

        // Refuse rather than wave through: a fresh upload we cannot read is one we cannot
        // check, and the safe direction for a security control is closed.
        if (!is_readable($file->getPath())) {
            throw new InvalidFileTypeException('Uploaded file could not be read');
        }

        // Measure the file instead of believing the File object. This method also runs as
        // the last check before storage, where the object may have been assembled by a
        // caller rather than by FileMapper.
        $this->assertWithinSizeLimit((int)filesize($file->getPath()));
        $this->assertDetectedTypeIsAllowed($file);
        $this->assertNotExecutable($file);
        $this->assertContainsNoScript($file);
    }

    /**
     * @throws InvalidFileTypeException
     */
    private function assertWithinSizeLimit(int $size): void
    {
        if ($size > $this->maxFileSize) {
            throw new InvalidFileTypeException(
                sprintf('File is larger than the %d byte limit', $this->maxFileSize)
            );
        }
    }

    /**
     * @throws InvalidFileTypeException
     */
    private function assertDetectedTypeIsAllowed(File $file): void
    {
        $info = finfo_open(FILEINFO_MIME_TYPE);

        if ($info === false) {
            throw new InvalidFileTypeException('File type could not be determined');
        }

        $detected = finfo_file($info, $file->getPath());
        finfo_close($info);

        if (!is_string($detected) || !in_array($detected, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidFileTypeException(
                sprintf('Unsupported file contents (%s)', is_string($detected) ? $detected : 'unknown')
            );
        }
    }

    /**
     * @throws InvalidFileTypeException
     */
    private function assertNotExecutable(File $file): void
    {
        // Silenced deliberately: the false is handled on the next line, and the warning would
        // otherwise be the only thing a caller sees before the exception it actually wants.
        $handle = @fopen($file->getPath(), 'rb');

        if ($handle === false) {
            throw new InvalidFileTypeException('File could not be read');
        }

        $header = (string)fread($handle, 8);
        fclose($handle);

        foreach (self::EXECUTABLE_SIGNATURES as $signature) {
            if (strpos($header, $signature) === 0) {
                throw new InvalidFileTypeException('Executable files cannot be uploaded');
            }
        }
    }

    /**
     * Scan the whole file, not just its head: a PHP payload is usually appended after
     * otherwise valid image data, where a header-only check would never see it.
     *
     * @throws InvalidFileTypeException
     */
    private function assertContainsNoScript(File $file): void
    {
        $handle = @fopen($file->getPath(), 'rb');

        if ($handle === false) {
            throw new InvalidFileTypeException('File could not be read');
        }

        // Carry the tail of each chunk over so a marker split across a boundary is still seen.
        $overlap = max(array_map('strlen', self::SCRIPT_MARKERS)) - 1;
        $carry = '';

        while (!feof($handle)) {
            $chunk = fread($handle, self::SCAN_CHUNK_SIZE);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $window = $carry . $chunk;

            foreach (self::SCRIPT_MARKERS as $marker) {
                if (strpos($window, $marker) !== false) {
                    fclose($handle);

                    throw new InvalidFileTypeException('File contains executable code');
                }
            }

            $carry = substr($window, -$overlap);
        }

        fclose($handle);
    }
}
