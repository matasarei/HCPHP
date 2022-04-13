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
            $path = self::_getFile($name);
            unlink($path);

            return;
        }

        unset(self::$cache[$name]);
    }

    static function getTime(string $name, int $type = self::CACHE_REQUEST): ?int
    {
        $cached = self::_get($name, $type);

        return $cached ? (int)$cached->time : null;
    }

    private static function _getFile(string $name): string
    {
        $path = new Path('cache/%s.tmp', $name);

        if (!file_exists($path)) {
            $path->mkpath(true);
        }

        return (string)$path;
    }

    private static function _get(string $name, string $type = self::CACHE_REQUEST)
    {
        if ($type == self::CACHE_SESSION) {
            if (!empty($_SESSION['cache'][$name])) {
                return unserialize($_SESSION['cache'][$name]);
            }
            
        } elseif ($type == self::CACHE_STATIC) {
            $file = self::_getFile($name);
            $cached = file_get_contents($file);

            if ($cached) {
                return unserialize($cached);
            }
            
        } else {
            if (!empty(self::$cache[$name])) {
                return self::$cache[$name];
            }
        }
        
        return null;
    }
}
