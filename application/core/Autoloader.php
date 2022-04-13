<?php

namespace core;

use RuntimeException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Autoloader
{
    private static $paths = [];
    private static $loaders = [];

    static function add(string $path, callable $callback)
    {
        if (file_exists($path)) {
            self::$paths[] = $path;
            self::$loaders[] = $callback;
        } else {
            throw new RuntimeException(sprintf('The specified path (%s) does not exist!', $path));
        }
    }

    static function addLoader(callable $function)
    {
        self::$loaders[] = $function;
    }

    static function addPath(string $path)
    {
        if (file_exists($path)) {
            self::$paths[] = $path;
        } else {
            throw new RuntimeException(sprintf('The specified path (%s) does not exist!', $path));
        }
    } 

    public static function load(string $class): bool
    {
        foreach (self::$paths as $index => $path) {
            $class = str_replace('\\', '/', $class);
            $callback = self::$loaders[$index];

            if ($callback($path, $class)) {
                return true;
            }
        }

        return false;
    }
}
