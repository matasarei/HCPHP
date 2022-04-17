<?php

namespace core;

use InvalidArgumentException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Path extends MagicObject
{
    const TYPE_ALL = "a";
    const TYPE_DIRECTORY = 'd';
    const TYPE_FILE = 'f';

    /**
     * @var string
     */
    private static $root;

    /**
     * @var string
     */
    private $path;

    private static $dirMod = 0755;
    private static $fileMod = 0644;

    function __construct(string $path = '', bool $validate = false)
    {
        $path = preg_replace('@[/\\\]@ui', DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $this->path = $path;

        if ($validate && !is_readable($this)) {
            throw new InvalidArgumentException(sprintf('Wrong path or file is not readable (%s)', $path), 1);
        }
    }

    /**
     * @param bool $touch
     * @param int|null $chmod
     *
     * @return bool
     */
    function mkpath(bool $touch = false, int $chmod = null): bool
    {
        $result = true;
        $path = is_dir($this) ? (string)$this : dirname((string)$this);

        if (!file_exists($path)) {
            $chmod = $chmod ?: self::$dirMod;
            $umask = umask(0);
            $result = mkdir($path, $chmod, true);
            umask($umask); 
        }

        if ($touch && !is_dir($this)) {
            $chmod = $chmod ?: self::$fileMod;
            $result = touch($this) && chmod($this, $chmod);
        }

        return $result;
    }

    function touch(): bool
    {
        return $this->mkpath(true);
    }

    /**
     * WARNING!!! USE CAREFULLY!!!
     * Removes object(s) at the current path
     *
     * @param bool $recursive Remove recursive (only for directories)
     * @param string|null $path Optional path (for recursive call)
     *
     * @return bool Result
     */
    function rmpath(bool $recursive = false, string $path = null): bool
    {
        $result = true;

        if ($path === null) {
            $path = $this;
        }

        if (!file_exists($path)) {
            return false;
        }

        if (is_dir($path)) {
            $objects = scandir($path);
            foreach($objects as $object) {
                if (!preg_match("/^\.+$/", $object)) {
                    $remove = $path . DIRECTORY_SEPARATOR . basename($object);
                    $result = is_dir($remove) ? ($recursive && $this->rmpath(true, $remove)) : unlink($remove);
                }
            }
            rmdir($path);
        } else {
            return unlink((string)$path);
        }

        return $result;
    }
    
    /**
     * Return string format
     *
     * @return string Path
     */
    function __toString()
    {
        if (preg_match('#' . addslashes(self::getRoot()) . "#", $this->path)) {
            return $this->path;
        }

        return self::getRoot() . DIRECTORY_SEPARATOR . $this->path;
    }
    
    /**
     * Get file URL
     *
     * @return Url
     */
    function getUrl(): Url
    {
        return new Url($this->path);
    }

    static function init(string $value, int $dirMod = null, int $fileMod = null)
    {
        if (file_exists($value)) {
            self::$root = $value;
        } else {
            throw new InvalidArgumentException('Path does not exist!', 1);
        }

        if ($dirMod) {
            self::$dirMod = $dirMod;
        }

        if ($fileMod) {
            self::$fileMod = $fileMod;
        }
    }

    static function getRoot()
    {
        if (self::$root) {
            return self::$root;
        }

        return $_SERVER['DOCUMENT_ROOT'];
    }

    public function getExtension(): ?string
    {
        $ext = pathinfo((string)$this, PATHINFO_EXTENSION);

        if (empty($ext)) {
            return null;
        }

        return $ext;
    }
    
    /**
     * @return string|null
     */
    public function getFileName(): ?string
    {
        if (preg_match("/([\w-?&;\.#~=\@\%\s]+\.[a-z]+)$/Uui", $this->path, $matches, null)) {
            return urldecode(array_shift($matches));
        }

        return null;
    }

    public function isImage(bool $strict = false): bool
    {
        if ($strict) {
            $info = finfo_open(FILEINFO_MIME_TYPE);

            return preg_match('@image\/@', finfo_file($info, (string)$this));
        }

        return preg_match('/.(png|jpeg|jpg|gif|bmp|webp|svg)$/i', basename((string)$this));
    }

    /**
     * @return false|string
     */
    public function getMimeType()
    {
        $info = finfo_open(FILEINFO_MIME_TYPE);

        return finfo_file($info, (string)$this);
    }

    public function getList(string $type = self::TYPE_ALL, bool $randomize = false): ?Collection
    {
        $path = (string)$this;

        if (!is_dir($path) || !is_readable($path)) {
            return null;
        }

        $result = scandir($path);
        $items = [];

        foreach ($result as $name) {
            if (preg_match("/^\.+$/", $name)) {
                continue;
            }

            $path = new Path("{$this->path}/{$name}");
            $is_dir = is_dir($path);
            if (($type === self::TYPE_DIRECTORY && !$is_dir) || ($type === self::TYPE_FILE && $is_dir)) {
                continue;
            }

            $items[] = $path;
        }

        if ($randomize) {
            shuffle($items);
        }

        return new Collection($items);
    }
}
