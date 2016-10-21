<?php
/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */
 
namespace core;

/**
 * Database abstraction layer
 */
class DatabaseSQL extends Database {
    private $_dbh;
    private $_prefix;
    
    /**
     * @var string Current driver name
     */
    private $_driver;
    const DRIVER_MYSQL = 'mysql';
    const DRIVER_PGSQL = 'pgsql';
    const DRIVER_MSSQL = 'mssql';

    /**
     * @var array Default params
     */
    private $_defaults = [
        'port' => [
            'mysql' => '3306',
            'pgsql' => '5432',
            'mssql' => '1433'
        ]
    ];
    
    /**
     * @param string $driver Database type (sqlite, mysql, pgsql, mssql)
     * @param string $uri Resource (host, file or memory(if null))
     * @param string $dbname Database name
     * @param string $user Username
     * @param string $pass Password
     * @param string $prefix Tables prefix
     * @param string $encoding Databse encoding
     * @param string $port Custom port
     */
    function __construct($driver = 'sqlite', $uri = null, $dbname = null, $user = 'root', $pass = '', $prefix = '', $encoding = 'utf8', $port = null) {
        if ($driver === 'sqlite') {
            if (!$uri) { //memory db
                $path = ':memory:';
            } else { //file db
                $path = new Path($uri);
                !file_exists($path) && touch($path); 
            }
            //connect
            $this->_dbh = new \PDO("sqlite:{$path}", null, null);
            
        } else {
            !$port && $port = $this->_defaults['port'][$driver];
            
            //connect
            $this->_dbh = new \PDO("{$driver}:host={$uri};dbname={$dbname}", $user, $pass);
            $this->_dbh->exec("SET NAMES {$encoding}");
        }

        $this->_prefix = $prefix;
        $this->_driver = $driver;
    }
    
    /**
     * 
     * @return type
     */
    function getDBH() {
        return $this->_dbh;
    }
    
    /**
     * 
     * @return type
     */
    function getPrefix() {
        return $this->_prefix;
    }
    
    /**
     * 
     * @param type $table
     * @param type $record
     * @return type
     */
    function insertRecord($table, $record) {
        $this->_checkArg($record, ['object', 'array']);
        is_object($record) && $record = (array)$record;
        
        if ($record) {
            $sql = sprintf("INSERT INTO `%s%s` (`%s`) VALUES (:%s)", $this->_prefix, $table, 
                           implode('`,`', array_keys($record)), implode(", :", array_keys($record)));
        } else {
            $sql = sprintf("INSERT INTO `%s%s` VALUES ()", $this->_prefix, $table);
        }
        
        $this->_execute($sql, $record);
        
        return $this->_dbh->lastInsertId();
    }
    
    /**
     * 
     * @param type $table
     * @param array $records
     * @return type
     */
    function insertRecords($table, array $records) {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = $this->insertRecord($table, $record);
        }
        return $ids;
    }
    
    /**
     * 
     * @param type $table
     * @param array $conditions
     * @param type $boxind
     * @return boolean
     */
    function getRecord($table, array $conditions = [], $boxind = false) {
        $results = $this->getRecords($table, $conditions, $boxind);
        if (!$results) return false;
        
        if (count($results) > 1) {
            $values = [];
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    $values[] = sprintf("%s => (%s)", $key, implode(', ', $value));
                } else {
                    $values[] = "{$key} => {$value}";
                }
            }
            trigger_error(sprintf("Founded more than one record! (%s; %s)", $table, implode(', ', $values)));
        }
        
        return $results[0];
    }
    
    /**
     * Return a result (one value) from query
     * @param string $sql Query
     * @param array $conditions Conditions
     * @return string Value
     */
    function getResultSQL($sql, array $conditions = []) {
        $result = $this->getRecordSQL($sql, $conditions);
        if ($result) {
            return array_shift($result);
        }
        return null;
    }
    
    /**
     * Return values (one column) from query
     * @param type $sql
     * @param array $conditions
     * @return array Values
     */
    function getValuesSQL($sql, array $conditions = [], $column = null) {
        $results = $this->getRecordsSQL($sql, $conditions);
        return $this->_getValues($results, $column);
    }
    
    /**
     * Return values (one column) from table
     * @param type $sql
     * @param array $conditions
     * @return array Values
     */
    function getValues($table, array $conditions = [], $column = null) {
        $results = $this->getRecords($table, $conditions);
        return $this->_getValues($results, $column);
    }
    
    /**
     * Get values from results
     * @param type $results
     * @return type
     */
    function _getValues($results, $column = null) {
        $values = [];
        foreach($results as $result) {
            if ($column) {
                $values[] = $result[$column];
            } else {
                $values[] = array_shift($result);
            }
        }
        return $values;
    }
    
    /**
     * 
     * @param type $sql
     * @param array $conditions
     * @param type $boxing
     * @return boolean
     */
    function getRecordSQL($sql, array $conditions = [], $boxing = false) {
        $results = $this->getRecordsSQL($sql, $conditions, $boxing);
        if (!$results) {
            return null;
        }
        
        if (count($results) > 1) {
            trigger_error("Founded more than one record! ({$sql})");
        }
        
        return $results[0];
    }
    
    /**
     * 
     * @param type $sql
     * @param array $conditions
     * @param type $boxing
     * @return type
     */
    function getRecordsSQL($sql, array $conditions = [], $boxing = false) {
        $sql = preg_replace('/{(\w*)}/U', "{$this->prefix}$1", $sql);
        
        $sth = $this->_execute($sql, $conditions);
        
        if ($boxing) {
            return $this->_resultBoxing($sth->fetchAll(\PDO::FETCH_ASSOC), true);
        } else {
            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        } 
    }
    
    /**
     * 
     * @param type $table
     * @param array $conditions
     * @param type $boxing
     * @return type
     */
    function getRecords($table, array $conditions = [], $boxing = false) {
        $sql = "SELECT * FROM `{$this->_prefix}{$table}`";
        if ($conditions) {
            $sql = sprintf("%s WHERE %s", $sql, implode(' AND ', $this->_prepareConditions($conditions)));
        }
        
        $sth = $this->_execute($sql, $conditions);
        
        if ($boxing) {
            return $this->_resultBoxing($sth->fetchAll(\PDO::FETCH_ASSOC), true);
        } else {
            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        }                   
    }

    /**
     * 
     * @param type $sql
     * @param type $conditions
     * @return type
     * @throws SQLQueryException
     */
    private function _execute($sql, $conditions = []) {
        $sth = $this->_dbh->prepare($sql);
        if (!$sth) {
            throw new SQLQueryException(implode(';', $this->_dbh->errorInfo()), $sql);
        }
        
        $conditions && $this->_bindValues($sth, $conditions);
        
        if (!$sth->execute()) {
            throw new SQLQueryException(implode(';', $sth->errorInfo()), $sql);
        }
        
        return $sth;
    }
    
    /**
     * Execute SQL query
     * @param type $sql SQL query
     * @param array $values Bind values
     * @return int Rows count
     */
    function executeSQL($sql, array $values = []) {
        $sql = preg_replace('/{(\w*)}/U', "{$this->prefix}$1", $sql);
        $sth = $this->_execute($sql, $values);
        return $sth->rowCount();
    }
    
    /**
     * Replace record in database, !only if record exist!
     * @param string $table Table name
     * @param mixed $record Record in array or std format
     * @param array $primary Primary keys (id by default)
     * @return bool Returns false if not replaced
     */
    function replaceRecord($table, $record, array $primary = ['id']) {
        $this->_checkArg($record, ['object', 'array']);
        $conditions = [];
        foreach ($record as $key => $value) {
            foreach ($primary as $p) {
                if ($key === $p) {
                    $conditions["{$key}"] = $value;
                }
            }
        }
        $result = (bool)$this->deleteRecords($table, $conditions);
        $result && $this->insertRecord($table, $record);
        return $result;
    }
    
    /**
     * Update record
     * @param type $table Table name (without prefix)
     * @param mixed $record Data (object / array)
     * @param array $primary Primary keys
     * @return type Result
     */
    function updateRecord($table, $record, array $primary = ['id']) {
        $this->_checkArg($record, ['object', 'array']);
        $values = [];
        $conditions = [];
        foreach ($record as $key => $value) {
            foreach ($primary as $p) {
                if ($key === $p) {
                    $conditions["{$key}"] = $value;
                }
            }
            $values["{$key}"] = $value;
        }
        return $this->updateRecords($table, $values, $conditions);
    }
    
    /**
     * 
     * @param type $table
     * @param array $values
     * @param array $conditions
     * @return type
     */
    function updateRecords($table, array $values, array $conditions = []) {
        $keys = [];
        $condKeys = array_keys($conditions);
        
        foreach ($values as $key => $value) {
            if (in_array($key, $condKeys)) {
                $keys[] = "`{$key}` = :u_{$key}";
                $values["u_{$key}"] = $value;
            } else {
                $keys[] = "`{$key}` = :{$key}";
            }
        }
        
        $sql = sprintf("UPDATE `%s%s` SET %s", $this->_prefix, $table, implode(', ', $keys));
        if ($conditions) {
            $sql = sprintf("%s WHERE %s", $sql, implode(' AND ', $this->_prepareConditions($conditions)));
        }
        
        $sth = $this->_execute($sql, array_merge($values, $conditions));
        
        return $sth->rowCount();
    }
    
    /**
     * 
     * @param type $table
     * @param array $conditions
     * @return type
     */
    function deleteRecords($table, array $conditions) {
        $sql = sprintf("DELETE FROM %s%s WHERE %s", $this->_prefix, 
                       $table, implode(' AND ', $this->_prepareConditions($conditions)));
        
        $sth = $this->_execute($sql, $conditions);
        
        return $sth->rowCount();
    }
    
    /**
     * Boxing query results
     * @param array $results Query results ()
     * @param bool $multiple More than one row
     * @return \stdClass Boxed values
     */
    private function _resultBoxing(array $results, $multiple) {
        if ($multiple) {
            $boxed = [];
            foreach ($results as $result) {
                $boxed[] = $this->_resultBoxing($result, false);
            }
            return $boxed;
        } else {
            $boxed = new \stdClass;
            foreach ($results as $name => $value) {
                $boxed->$name = $value;
            }
            return $boxed;
        }
    }

    /**
     * 
     * @param type $conditions
     * @return type
     */
    private function _prepareConditions($conditions) {
        $prepared = [];
        foreach ($conditions as $cname => $cvalue) {
            if (is_array($cvalue)) {
                $inconditions = [];
                foreach ($cvalue as $key => $value) {
                    $inconditions[] = ":{$cname}{$key}";
                }
                $prepared[] = sprintf('%s IN (%s)', $cname, implode(', ', $inconditions));
            } elseif (strpos($cvalue, '%') !== false) {
                $prepared[] = "`{$cname}` LIKE :{$cname}";
            } else {
                $prepared[] = "`{$cname}` = :{$cname}";
            }
        }
        return $prepared;
    }
    
    /**
     * Bind values
     * @param type $sth Statement handle
     * @param type $conditions Conditions
     */
    private function _bindValues($sth, $conditions) {
        foreach ($conditions as $cname => $cvalue) {
            if (is_array($cvalue)) {
                foreach($cvalue as $key => $value) {
                    $sth->bindValue(":{$cname}{$key}", $value);
                }
            } else {
                $sth->bindValue(":{$cname}", $cvalue);
            }
        }
    }
}

class SQLQueryException extends \Exception {
    
    private $_sql;
    
    public function __construct($message = "", $sql = "") {
        parent::__construct($message);
        $this->_sql = $sql;
    }
    
    public function __toString() {
        $msg = parent::__toString();
        return "{$msg}\nQuery:\n{$this->_sql}";
    }
    
}