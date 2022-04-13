<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Events
{
    private static $events = [];

    static public function addListener(string $name, callable $callback)
    {
        if (!isset(self::$events[$name])) {
            self::$events[$name] = [];
        }

        self::$events[$name][] = $callback;
    }

    static public function resetEvent(string $name)
    {
        self::$events[$name] = [];
    }

    /**
     * @param string $name
     * @param array $params
     */
    static public function triggerEvent(string $name, array $params = [])
    {
        $filepath = new Path(sprintf('application\events\%s.php', $name));

        if (file_exists($filepath)) {
            require_once $filepath;
            new $name((object)$params);
        }

        if (isset(self::$events[$name])) {
            foreach(self::$events[$name] as $callback) {
                $callback((object)$params);
            }
        }
    }
}
