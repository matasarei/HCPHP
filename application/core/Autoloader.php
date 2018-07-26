<?php

namespace core;
 
spl_autoload_register('core\Autoloader::load');

/**
 * @package core
 * @author  Yevhen Matasar <matasar.ei@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Autoloader {
    
    ///
    // Static only.
    ///
    private function __construct() {}
    private function __clone() {}
    
    private static $paths = [];
    private static $loaders = [];

    /**
     * @param string $path
     *
     * @param callable|null $loader
     *
     * @throws \Exception
     */
    public static function addPath($path, callable $loader = null)
    {
        if (!file_exists($path)) {
            throw new \Exception(sprintf('Specified path (`%s`) does not exist!', $path));
        }

        self::$paths[] = $path;
        self::$loaders[] = null === $loader ? null : $loader;
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    public static function load($className)
    {
        foreach (self::$paths as $index => $path) {
            $class = str_replace('\\', '/', $className);
            $callback = self::$loaders[$index];

            if (null === $callback) {
                if (self::defaultLoader($path, $class)) {
                    return true;
                }

                continue;
            }

            if ($callback($path, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @param string $className
     *
     * @return bool
     */
    private static function defaultLoader($path, $className)
    {
        $path = "{$path}/{$className}.php";

        if (file_exists($path)) {
            require_once $path;

            return true;
        }

        return false;
    }
}
