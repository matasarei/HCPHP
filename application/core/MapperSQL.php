<?php
/**
 * SQL Mapper (MVC) absract class
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161018
 */

namespace core;

use core\Exception;

abstract class MapperSQL implements iMapper {
    
    /**
     * Database instance
     * @var DatabaseSQL
     */
    protected $_DB;
    
    /**
     * Default database alias
     * @var string
     */
    protected $_dbalias = 'default';
    
    /**
     * Default table name
     * @var string 
     */
    protected $_table;
    
    /**
     * 
     * @throws Exception
     */
    function __construct() {   
        // check table
        if (empty($this->_table)) {
            throw new Exception('e_undefined_table_name');
        }

        // get instance of database interface.
        $DB = Database::getInstance($this->_dbalias);
        $this->setDatabase($DB);
    }
    
    /**
     * 
     * @param \core\DatabaseSQL $DB
     * @throws Exception
     */
    function setDatabase($DB) {
        if ($DB instanceof DatabaseSQL) {
            $this->_DB = $DB;
        } else {
            throw new Exception('e_uncompatible_database_instance');
        }
    }
    
    /**
     * 
     * @return type
     */
    function getDatabase() {
        return $this->_DB;
    }
    
    /**
     * 
     * @param type $name
     */
    function setTable($name) {
        $this->_table = $name;
    }
    
    /**
     * 
     * @return type
     */
    function getTable() {
        return $this->_table;
    }
    
    /**
     * Prepare instance data to save into database
     * @param \core\Model $instance Model instance
     * @return array Database prepared data
     */
    protected function _prepareData($instance) {
        return [
            'id' => $instance->id
        ];
    }
    
    /**
     * Insert instance data into database
     * @param \core\Model $instance Model instance
     * @return int newid
     */
    public function insert(&$instance) {
        // Prepare dbdata.
        $dbdata = $this->_prepareData($instance);
        unset($dbdata['id']);
        
        // Insert and update instance.
        $id = $this->_DB->insertRecord($this->_table, $dbdata);
        $instance->id = $id;
        return $id;
    }
    
    /**
     * Update instance data
     * @param \core\Model $instance Model instance
     * @return boolean Result flag
     */
    public function update(&$instance) {
        if (!$instance->id) {
            return false;
        }
        
        // Prepare dbdata.
        $dbdata = $this->_prepareData($instance);
        $this->_DB->updateRecord($this->_table, $dbdata);
        return true;
    }
    
    /**
     * 
     * @param mixed $id Instance identifier (int / string)
     * @return boolean Result flag
     */
    public function remove($id) {
        return (bool)$this->_DB->deleteRecords($this->_table, [
            'id' => trim($id)
        ]);
    }
    
    /**
     * Prepare request with conditions
     * @param array $conditions Conditions array
     * @param string $sql SQL query
     * @param array $values Value array
     */
    protected function _prepareConditions(array $conditions, array &$values = []) {
        $clauses = ['1'];
        if (isset($conditions['id'])) {
            $clauses[] = "id = :id";
            $values[] = (int)$conditions['id'];
        }
        return $clauses;
    }
    
    abstract protected function _prepareJoins(array $conditions);
    
    /**
     *
     * @var type 
     */
    protected $_count = 0;

    /**
     * Returns count of found or affected rows
     * @return type
     */
    public function getCount() {
        return $this->_count;
    }
    
    /**
     * Remove records with conditions
     * @param array $conditions Reques conditions
     * @param array $params
     * @return boolean Result flag
     */
    public function removeWith(array $conditions) {
        $values = [];
        $clauses = $this->_prepareConditions($conditions, $values);
        $joins = $this->_prepareJoins($conditions);
        $sql = "DELETE FROM {{$this->_table}} {$joins} "
             . "WHERE " . implode(' AND ', $clauses);
        $count = $this->_DB->executeSQL($sql, $values);
        $this->_count = $count;
        return (bool)$count;
    }
    
    /**
     * Apply data to a model instance
     * @param \core\Model $instance Model instance
     * @param mixed $data
     */
    public static function applyData(&$instance, $data) {
        $instance->id = $data['id'];
    }
    
    /**
     * Prepare order clause
     * @param array $order Order config
     * @return string preated SQL clause
     */
    protected function _prepareOrder($order) {
        if (!is_array($order)) {
            $order = [$order => 'ASC'];
        }
        
        $clause = [];
        $preg = "/\s*(\{[a-z0-9_-]+\}.)?([a-z0-9-_]+)\s+(ASC|DESC)\s*$/i";
        foreach ($order as $name => $keyword) {
            $parts = [];
            if (preg_match($preg, "{$name} {$keyword}", $parts)) {
                $clause[] = "{$parts[1]}`{$parts[2]}` {$parts[3]}";
            }
        }
        return ' ORDER BY ' . implode(', ', $clause);
    }
    
    /**
     * Fetch records with conditions
     * @param array $conditions Conditions
     * @param array $params Optional request params
     */
    public function fetchWith(array $conditions, array $params = []) {
        $values = [];
        $clauses = $this->_prepareConditions($conditions, $values);
        $joins = $this->_prepareJoins($conditions);
        $sql = "SELECT SQL_CALC_FOUND_ROWS {{$this->_table}}.* "
             . "FROM {$this->_table} {$joins} "
             . "WHERE " . implode(' AND ', $clauses);
        
        // Use limit.
        if (isset($params['limit'])) {
            $sql .= ' LIMIT ' . (int)$params['limit'];
        }
        
        // Use offset.
        if (isset($params['offset'])) {
            $sql .= ' OFFSET ' . (int)$params['offset'];
        }
        
        if (isset($params['order'])) {
            $sql .= $this->_prepareOrder($params['order']);
        }
        
        $records = $this->_DB->getRecordsSQL($sql, $values);
        $this->_count = $this->_DB->getResultSQL("SELECT FOUNT_ROWS()");
        return $records;
    }
    
    /**
     * Fetch record with id
     * @param int $id
     * @return array Data
     */
    public function fetchWithId($id) {
        $record = $this->_DB->getRecord($this->_table, ['id' => $id]);
        if ($record) {
            $this->_count = 1;
            return $record;
        }
        return null;
    }
}