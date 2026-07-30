<?php

namespace core;

use stdClass;

/**
 * Simple cache API
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Cache
{
    /**
     * Request cache
     */
    const CACHE_REQUEST = 0;
    
    /**
     * Session cache, individual for each user
     */
    const CACHE_SESSION = 1;
    
    /**
     * Long term cache, global for all users
     */
    const CACHE_STATIC = 2;
    
    /**
     * Request cache
     *
     * @var array
     */
    static $cache = [];

    static function set(string $name, $val, int $type = self::CACHE_REQUEST)
    {
        $cache = new stdClass();
        $cache->time = time();
        $cache->name = $name;
        $cache->value = $val;
        
        if ($type == self::CACHE_SESSION) {
            $_SESSION['cache'][$name] = serialize($cache);
            
        } elseif ($type == self::CACHE_STATIC) {
            $file = self::_getFile($name);
            file_put_contents($file, serialize($cache));
            
        } else {
            self::$cache[$name] = $cache;
        }
    }

    static function get(string $name, int $type = self::CACHE_REQUEST, int $time = 0)
    {
        $cached = self::_get($name, $type);

        if ($cached) {
            if ($time && (time() - $cached->time) > $time) {
                return null;
            }

            return $cached->value;
        }

        return null;
    }

    public static function remove($name, $type = self::CACHE_REQUEST)
    {
        if ($type == self::CACHE_SESSION) {
            unset($_SESSION['cache'][$name]);

            return;
        }

        if ($type == self::CACHE_STATIC) {
            $path = self::_getFile($name, false);

            if (file_exists($path)) {
                unlink($path);
            }

            return;
        }

        unset(self::$cache[$name]);
    }

    static function getTime(string $name, int $type = self::CACHE_REQUEST): ?int
    {
        $cached = self::_get($name, $type);

        return $cached ? (int)$cached->time : null;
    }

    /**
     * Path of the file backing a static cache entry.
     *
     * This used to read new Path('cache/%s.tmp', $name). Path's second parameter is
     * $validate, not a sprintf argument: the key was cast to a boolean, the %s was never
     * substituted so every key shared one file, and the truthy $validate then made the
     * constructor reject it as unreadable. Every CACHE_STATIC call threw.
     */
    /**
     * Classes that may be reconstructed from a cache entry.
     *
     * The wrapper this class stores is a stdClass, and that is all HCPHP itself puts in the
     * serialized caches. Unrestricted unserialize() on a file or session value is an object
     * injection gadget, so anything else is refused by default.
     *
     * A project that caches its own entities in CACHE_SESSION or CACHE_STATIC has to add
     * them here -- otherwise they come back as __PHP_Incomplete_Class.
     *
     * @var string[]
     */
    const UNSERIALIZE_ALLOWED_CLASSES = [stdClass::class];

    /**
     * @param string $serialized
     *
     * @return mixed
     */
    private static function _unserialize(string $serialized)
    {
        return unserialize($serialized, ['allowed_classes' => self::UNSERIALIZE_ALLOWED_CLASSES]);
    }

    private static function _getFile(string $name, bool $create = true): string
    {
        // A key is a name, not a path. Anything that could climb out of the cache directory
        // is flattened rather than trusted.
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
        $path = new Path(sprintf('cache/%s.tmp', $safeName));

        if ($create && !file_exists($path)) {
            $path->mkpath(true);
        }

        return (string)$path;
    }

    private static function _get(string $name, string $type = self::CACHE_REQUEST)
    {
        if ($type == self::CACHE_SESSION) {
            if (!empty($_SESSION['cache'][$name])) {
                return self::_unserialize($_SESSION['cache'][$name]);
            }

        } elseif ($type == self::CACHE_STATIC) {
            // Reading must not create the file: _getFile() used to, so a miss left an empty
            // file behind and the next read got '' instead of null.
            $file = self::_getFile($name, false);

            if (!is_readable($file)) {
                return null;
            }

            $cached = file_get_contents($file);

            if ($cached) {
                return self::_unserialize($cached);
            }

        } else {
            if (!empty(self::$cache[$name])) {
                return self::$cache[$name];
            }
        }
        
        return null;
    }
}
