<?php
/**
 * Simple caching API
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141109
 */

namespace core;

class Cache {
    
    /**
     * Satic only
     */
    private function __construct() {}
    private function __clone() {}
    
    /**
     * Requst cache
     */
    const CACHE_REQUEST = 0;
    
    /**
     * Session cache, individual for each user
     */
    const CACHE_SESSION = 1;
    
    /**
     * Long term cache, global for all users
     */
    const CACHE_STATIC    = 2;
    
    /**
     * Reqauest cache
     * @var array
     */
    static $_cache = [];
    
    static function set($name, $val, $type = self::CACHE_REQUEST) {
        $cache = new \stdClass();
        $cache->time = time();
        $cache->name = (string)$name;
        $cache->value = $val;
        
        if ($type == self::CACHE_SESSION) {
            $_SESSION['cache'][$cache->name] = serialize($cache);
            
        } elseif ($type == self::CACHE_STATIC) {
            $file = self::_getFile($name);
            file_put_contents($file, serialize($cache));
            
        } else {
            self::$_cache[$cache->name] = $cache;
        }
    }
    
    /**
     * 
     * @param string $name Record name
     * @param string $type Cache type
     * @return type
     */
    static function get($name, $type = self::CACHE_REQUEST) {
        $cached = self::_get((string)$name, $type);
        return $cached ? $cached->value : null;
    }
    
    /**
     * Get cache creation time
     * @param string $name Record name
     * @param string $type Cache type
     * @return type
     */
    static function getTime($name, $type = self::CACHE_REQUEST) {
        $cached = self::_get((string)$name, $type);
        return $cached ? (int)$cached->time : null;
    }
    
    /**
     * Get path to the cache file
     * @param string $name
     * @return string Path
     */
    private static function _getFile($name) {
        $path = new Path("cache/{$name}.tmp");
        if (!file_exists($path)) {
            $path->mkpath(true);
        }
        return (string)$path;
    }
    
    /**
     * Get cache record
     * @param string $name
     * @param string $type
     * @return string 
     */
    private static function _get($name, $type = self::CACHE_REQUEST) {
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
            if (!empty(self::$_cache[$name])) {
                return self::$_cache[$name];
            }
        }
        
        return null;
    }
    
    /**
     * Remove record from cache
     * @param string $name Name
     * @param string $type Cache type
     */
    public static function remove($name, $type = self::CACHE_REQUEST) {
        if ($type == self::CACHE_SESSION) {
            unset($_SESSION['temp'][(string)$name]);
        } elseif ($type == self::CACHE_STATIC) {
            $path = self::_getFile($name);
            unlink($path);
        } else {
            unset(self::$_cache[(string)$name]);
        }
    }
    
    /**
     * Purge cache (remove all records)
     * @param string $type Cache type
     */
    public static function purge($type = self::CACHE_REQUEST) {
        if ($type == self::CACHE_SESSION) {
            $_SESSION['cache'] = [];
            
        } elseif ($type == self::CACHE_STATIC) {
            
            // removes all cache files.
            $path = new Path('cache');
            foreach(scandir($path) as $object) {
                $temp = new Path("cache/{$object}");
                // keeps all directories like templates cache etc.
                !is_dir($temp) && $temp->rmpath();
            }
            
        } else {
            self::$_cache = [];
        }
    }
    
}