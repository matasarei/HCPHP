<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */ 

spl_autoload_register('Autoloader::load');
 
class Autoloader {
    
    /**
     * Satic only
     */
    private function __construct() {}
    private function __clone() {}
    
    private static $_paths = [];
    private static $_loaders = [];

    public static function load($class) {
        /*
        foreach (self::$_paths as $path) {
            $path = sprintf('%s/%s.php', $path, strtolower($class));
            if (file_exists($path)) {
                require_once($path);
                return true;
            }
        }
        return false;
         * 
         */
        foreach (self::$_paths as $index => $path) {
            $callback = self::$_loaders[$index];
            if ($callback($path, $class)) {
                return true;
            }
        }
        return false;
    }
    
    static function add($path, callable $callback) {
        if (file_exists($path)) {
            self::$_paths[] = $path;
            self::$_loaders[] = $callback;
        } else {
            throw new Exception("The specified path ({$path}) does not exists!", 1);
        }
    }
    
    static function addLoader($function) {
        if (is_callable($function)) {
            self::$_loaders[] = $function;
        } else {
            throw new Exception("The value is not a function!", 1);       
        }
    }
    
    static function addPath($path) {
        if (file_exists($path)) {
            self::$_paths[] = $path;
        } else {
            throw new Exception("The specified path ({$path}) does not exists!", 1);
        }
    }    
}