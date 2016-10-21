<?php
/**
 * Events
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20151118
 */

namespace core;
 
class Events {
    /**
     * Satic only
     */
    private function __construct() {}
    private function __clone() {}
    
    
    //events container
    static private $_events = [];
    
    /**
     * Add event listener
     * @param type $name event name (on...)
     * @param callable callback function
     */
    static public function addListener($name, callable $callback) {
        !isset(self::$_events[$name]) && self::$_events[$name] = [];
        self::$_events[$name][] = $callback;
    }
    
    /**
     * Remove all listeners
     * @param type $name event name (on...)
     */
    static public function resetEvent($name) {
        self::$_events[$name] = [];
    }
    
    private static $_logpath = '/cache/log.json';
    
    /**
     * Write new record to log file
     * @param array $data
     * @param type $event
     */
    static public function writeToLog(array $data, $event = '') {
        $record = [
            'date'    => date("Y.m.d H:i:s"),
            'event'   => $event,
            'ip'      => Application::getRemoteIP(),
            'ua'      => filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
            'request' => filter_input(INPUT_SERVER, 'REQUEST_URI'),
            'params'  => $data
        ];
        $path = new Path(self::$_logpath);
        if (file_exists($path)) {
            $json = ",\r\n" . json_encode($record);
        } else {
            $path->mkpath(true);
            $json = json_encode($record);
        }

        file_put_contents($path, $json, FILE_APPEND);
    }
    
    /**
     * Get all records from log
     * @return type
     */
    static public function getLog() {
        $path = new Path(self::$_logpath);
        if (file_exists($path)) {
            $log = file_get_contents($path);
            $records = json_decode("[{$log}]");
            return $records ? $records : [];
        }
        return [];
    }
    
    /**
     * Clear / remove log file
     */
    static public function clearLog() {
        $path = new Path(self::$_logpath);
        $path->rmpath();
    }

    /**
     * Trigger event
     * @param type $name event name (on...)
     * @param type $params additional params
     */
    static public function triggerEvent($name, $params = [], $log = false) {
        // write log.
        if ($log) {
            self::writeToLog($params, $name);
        }
        
        // class implemented handlers.
        $filepath = new Path("application\\events\\{$name}.php");
        if (file_exists($filepath)) {
            require_once $filepath;
            new $name((object)$params);
        }
        
        // callback handlers.
        if (isset(self::$_events[$name])) {
            foreach(self::$_events[$name] as $callback) {
                $callback((object)$params);
            }
        }
    }
}