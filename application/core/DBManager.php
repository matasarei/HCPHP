<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */

class DBManager extends Object {
    private $_dbh;
    private $_prefix;
    
    function __construct($driver, $host, $dbname, $user, $pass, $prefix, $encoding) {
        $this->_dbh = new PDO("{$driver}:host={$host};dbname={$dbname}", $user, $pass);
        $this->_dbh->exec("set names {$encoding}");
        $this->_prefix = $prefix;
    }
    
    function getDBH() {
        return $this->_dbh;
    }
    
    function getPrefix() {
        return $this->_prefix;
    }
    
    function insertRecord($table, $record) {
        $this->_checkArg($record, array('object', 'array'));
        is_object($record) && $record = (array)$record;
        
        $sql = sprintf("INSERT INTO `%s%s` (`%s`) VALUES (:%s)", $this->_prefix, $table, 
                       implode('`,`', array_keys($record)), implode(", :", array_keys($record)));

        $sth = $this->_dbh->prepare($sql);
        
        if (!$sth->execute($record)) {
            throw new Exception(implode(';', $sth->errorInfo()), 1);
        }
        
        return $this->_dbh->lastInsertId();
    }
    
    function insertRecords($table, array $records) {
        $ids = array();
        foreach ($records as $record) {
            $ids[] = $this->insertRecord($table, $record);
        }
        return $ids;
    }
    
    function getRecord($table, array $conditions = array()) {
        $results = $this->getRecords($table, $conditions);
        if (!$results) return false;
        
        if (count($results) > 1) {
            $values = array();
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
    
    function getRecordSQL($sql, array $conditions = array(), $boxing = true) {
        $results = $this->getRecordsSQL($sql, $conditions, $boxing);
        if (!$results) return false;
        
        if (count($results) > 1) {
            trigger_error("Founded more than one record! ({$sql})");
        }
        
        return $results[0];
    }
        
    function getRecordsSQL($sql, array $conditions = array(), $boxing = true) {
        $sql = str_replace('{', $this->prefix, str_replace('}', null, $sql));

        $sth = $this->_dbh->prepare($sql);
        
        if (!$sth->execute($conditions)) {
            throw new Exception(implode(';', $sth->errorInfo()), 1);
        }
        
        if ($boxing) {
            return $this->_resultBoxing($sth->fetchAll(PDO::FETCH_ASSOC), true);
        } else {
            return $sth->fetchAll(PDO::FETCH_ASSOC);
        } 
    }
        
    function getRecords($table, array $conditions = array(), $boxing = true) {
        $sql = "SELECT * FROM `{$this->_prefix}{$table}`";
        if ($conditions) {
            $sql = sprintf("%s WHERE %s", $sql, implode(' AND ', $this->_prepareConditions($conditions)));
        }
        
        $sth = $this->_dbh->prepare($sql);
        $this->_bindValues($sth, $conditions);
        
        if (!$sth->execute()) {
            throw new Exception(implode(';', $sth->errorInfo()), 1);
        }
        if ($boxing) {
            return $this->_resultBoxing($sth->fetchAll(PDO::FETCH_ASSOC), true);
        } else {
            return $sth->fetchAll(PDO::FETCH_ASSOC);
        }   
    }
    
    function execute($sql, array $values = array()) {
        $sql = str_replace('{', $this->prefix, str_replace('}', null, $sql));
        $sth = $this->_dbh->prepare($sql);
        $sth->execute($values);
        return $sth->rowCount();
    }
    
    function updateRecord($table, $record, $primary = array('id')) {
        $this->_checkArg($record, array('object', 'array'));
        $values = array();
        $conditions = array();
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
    
    function updateRecords($table, array $values, array $conditions = array()) {
        $keys = array();
        foreach (array_keys($values) as $key) {
            $keys[] = "`{$key}` = :{$key}";
        }
        $sql = sprintf("UPDATE `%s%s` SET %s", $this->_prefix, $table, implode(', ', $keys));
        if ($conditions) {
            $sql = sprintf("%s WHERE %s", $sql, implode(' AND ', $this->_prepareConditions($conditions)));
        }
        
        $sth = $this->_dbh->prepare($sql);
        $this->_bindValues($sth, array_merge($values, $conditions));
        
        if (!$sth->execute()) {
            throw new Exception(implode(';', $sth->errorInfo()), 1);
        }
        
        return $sth->rowCount();
    }
    
    function deleteRecords($table, array $conditions) {
        $sql = sprintf("DELETE FROM %s%s WHERE %s", $this->_prefix, 
                       $table, implode(' AND ', $this->_prepareConditions($conditions)));
                       
        var_dump($sql);
                       
        $sth = $this->_dbh->prepare($sql);
        $this->_bindValues($sth, $conditions);
        
        if (!$sth->execute()) {
            throw new Exception(implode(';', $sth->errorInfo()), 1);
        }
        
        return $sth->rowCount();
    }
    
    private function _resultBoxing($results, $multiple) {
        if ($multiple) {
            $boxed = array();
            foreach ($results as $result) {
                $boxed[] = $this->_resultBoxing($result, false);
            }
            return $boxed;
        } else {
            $boxed = new stdClass;
            foreach ($results as $name => $value) {
                $boxed->$name = $value;
            }
            return $boxed;
        }
    }

    private function _prepareConditions($conditions) {
        $prepared = array();
        foreach ($conditions as $cname => $cvalue) {
            if (is_array($cvalue)) {
                $inconditions = array();
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