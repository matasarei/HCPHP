<?php
/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */

namespace core;

use core\Exception;

abstract class Database extends Object {
    
    abstract public function insertRecord($collection, $record);
    
    abstract public function updateRecord($collection, $record, array $primary = ['_id']);
    
    abstract public function replaceRecord($collection, $record, array $primary = ['_id']);

    abstract public function deleteRecords($collection, array $conditions);
    
    /**
     * @var arrya Database instances
     */
    private static $instances = [];
    

    /**
     * Magic
     * @param type $name Database name
     * @param type $arguments Arguments
     * @return Database instance
     */
    static function __callStatic($name, $arguments) {
        if ($arguments) {
            return self::pushInstance($name, $arguments[0]);
        }
        return self::getInstance($name);
    }
    
    /**
     * Push database instance into registry
     * @param string $name
     * @param \core\Database $instance
     */
    static function pushInstance($name, Database $instance, $rewrite = false) {
        if (!$rewrite && isset(self::$instances[(string)$name])) {
            throw new Exception("e_database_instance_exist", 0, [$name]);
        }
        self::$instances[(string)$name] = $instance;
        return $instance;
    }
    
    /**
     * @param string $name name
     * @return Database instance
     */
    static function getInstance($name) {
        if (isset(self::$instances[(string)$name])) {
            return self::$instances[(string)$name];
        }
        trigger_error("Instance '{$name}' does not registered!");
        return null;
    }
} 