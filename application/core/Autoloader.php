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
        // Converted once. Reassigning $class inside the loop meant the second iteration
        // worked on an already-converted name, which happened to be harmless only because
        // the conversion is idempotent.
        $relativePath = str_replace('\\', '/', $class);

        foreach (self::$paths as $index => $path) {
            // addPath() and addLoader() append to the two arrays independently, so an index
            // may have a path and no loader of its own. Fall back rather than fail.
            $callback = self::$loaders[$index] ?? [self::class, 'loadFile'];

            if ($callback($path, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Used for a path registered through addPath(), which carries no loader.
     */
    public static function loadFile(string $path, string $class): bool
    {
        $file = sprintf('%s/%s.php', rtrim($path, '/\\'), $class);

        if (file_exists($file)) {
            require_once $file;

            return true;
        }

        return false;
    }
}
