<?php

namespace core;

use PDO;
use PDOStatement;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DatabaseSQL implements DatabaseInterface
{
    const DRIVER_SQLITE = 'sqlite';
    const DRIVER_MYSQL = 'mysql';
    const DRIVER_PGSQL = 'pgsql';
    const DRIVER_MSSQL = 'mssql';

    const DEFAULT_PORTS = [
        'mysql' => '3306',
        'pgsql' => '5432',
        'mssql' => '1433',
    ];

    /**
     * @var PDO
     */
    private $dbh;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string Current driver name
     */
    private $driver;
    
    /**
     * @param string $driver Database type (sqlite, mysql, pgsql, mssql)
     * @param string|null $uri Resource (host, file or memory(if null))
     * @param string|null $dbname Database name
     * @param string $user Username
     * @param string $pass Password
     * @param string $prefix Tables prefix
     * @param string $encoding Database encoding
     * @param string|null $port Custom port
     */
    public function __construct(
        string $driver = '',
        string $uri = null,
        string $dbname = null,
        string $user = 'root',
        string $pass = '',
        string $prefix = '',
        string $encoding = 'utf8',
        string $port = null
    ) {
        if ($driver === self::DRIVER_SQLITE) {
            if ($uri === null) {
                $path = ':memory:';
            } else {
                $path = new Path($uri);
                $this->checkSqliteFile($path);
            }

            $this->dbh = new PDO(sprintf('sqlite:%s', $path));

        } else {
            if ($port === null) {
                $port = self::DEFAULT_PORTS[$driver];
            }

            $this->dbh = new PDO(
                sprintf('%s:host=%s;port=%d;dbname=%s', $driver, $uri, $port, $dbname),
                $user,
                $pass
            );
        }

        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($driver !== self::DRIVER_SQLITE) {
            $this->dbh->exec(sprintf('SET NAMES %s', $encoding));
        }

        $this->prefix = $prefix;
        $this->driver = $driver;
    }

    public function getDBH(): PDO
    {
        return $this->dbh;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function insertRecord(string $collection, array $record)
    {
        if ($record) {
            $sql = sprintf(
                'INSERT INTO `%s%s` (`%s`) VALUES (:%s)',
                $this->prefix,
                $collection,
                implode('`,`', array_keys($record)), implode(", :", array_keys($record))
            );
        } else {
            $sql = sprintf('INSERT INTO `%s%s` VALUES ()', $this->prefix, $collection);
        }
        
        $this->execute($sql, $record);
        
        return $this->dbh->lastInsertId();
    }

    public function getRecord(string $collection, array $conditions = [])
    {
        $results = $this->getRecords($collection, $conditions, 2);

        if (empty($results)) {
            return false;
        }
        
        if (count($results) > 1) {
            $values = [];
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    $values[] = sprintf('%s => (%s)', $key, implode(', ', $value));
                } else {
                    $values[] = sprintf('%s => %s', $key, $value);
                }
            }

            trigger_error(
                sprintf(
                    'Found more than one record! (%s; %s)',
                    $collection,
                    implode(', ', $values)
                )
            );
        }
        
        return array_shift($results);
    }
    
    /**
     * Return a result (one value)
     *
     * @param string $sql Query
     * @param array $conditions Conditions
     *
     * @return string
     */
    public function getResultSQL(string $sql, array $conditions = [])
    {
        $result = $this->getRecordSQL($sql, $conditions);

        if (!empty($result)) {
            return array_shift($result);
        }

        return null;
    }

    public function getValuesSQL(string $sql, array $conditions = [], string $column = null): array
    {
        $results = $this->getRecordsSQL($sql, $conditions);

        return $this->_getValues($results, $column);
    }

    public function getValues(string $table, array $conditions = [], $column = null): array
    {
        $results = $this->getRecords($table, $conditions);

        return $this->_getValues($results, $column);
    }

    private function _getValues(array $results, $column = null): array
    {
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

    public function getRecordSQL(string $sql, array $conditions = [])
    {
        $results = $this->getRecordsSQL($sql, $conditions);

        if (!$results) {
            return null;
        }
        
        if (count($results) > 1) {
            trigger_error(sprintf('Found more than one record! (%s)', $sql));
        }
        
        return $results[0];
    }

    public function getRecordsSQL(string $sql, array $conditions = []): array
    {
        $sql = preg_replace('/{(\w*)}/U', sprintf('%s$1', $this->prefix), $sql);
        $sth = $this->execute($sql, $conditions);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRecords(string $collection, array $conditions = [], int $limit = null): array
    {
        $sql = sprintf('SELECT * FROM `%s%s`', $this->prefix, $collection);

        if (!empty($conditions)) {
            $sql = sprintf('%s WHERE %s', $sql, implode(' AND ', $this->prepareConditions($conditions)));
        }

        if ($limit > 0) {
            $sql .= sprintf(' LIMIT %d', $limit);
        }

        $sth = $this->execute($sql, $conditions);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function execute(string $sql, $conditions = [])
    {
        $sth = $this->dbh->prepare($sql);

        if (count($conditions) > 0) {
            $this->bindValues($sth, $conditions);
        }

        $sth->execute();
        
        return $sth;
    }

    public function executeSQL(string $sql, array $values = [])
    {
        $sql = preg_replace('/{(\w*)}/U', sprintf('%s$1', $this->prefix), $sql);
        $sth = $this->execute($sql, $values);

        return $sth->rowCount();
    }

    public function replaceRecord(string $collection, array $record, array $keys = ['id']): bool
    {
        $conditions = [];
        foreach ($record as $name => $value) {
            foreach ($keys as $key) {
                if ($name === $key) {
                    $conditions[$name] = $value;
                }
            }
        }

        $result = (bool)$this->deleteRecords($collection, $conditions);
        $result && $this->insertRecord($collection, $record);

        return $result;
    }

    function updateRecord(string $collection, array $record, array $keys = ['id']): int
    {
        $values = [];
        $conditions = [];

        foreach ($record as $name => $value) {
            foreach ($keys as $key) {
                if ($name === $key) {
                    $conditions[$name] = $value;
                }
            }
            $values[$name] = $value;
        }

        return $this->updateRecords($collection, $values, $conditions);
    }

    public function updateRecords(string $table, array $values, array $conditions = []): int
    {
        $keys = [];
        $condKeys = array_keys($conditions);
        
        foreach ($values as $key => $value) {
            if (in_array($key, $condKeys)) {
                $keys[] = sprintf('`%s` = :u_%s', $key, $key);
                $values['u_' . $key] = $value;
            } else {
                $keys[] = sprintf('`%s` = :%s', $key, $key);
            }
        }
        
        $sql = sprintf('UPDATE `%s%s` SET %s', $this->prefix, $table, implode(', ', $keys));

        if ($conditions) {
            $sql = sprintf('%s WHERE %s', $sql, implode(' AND ', $this->prepareConditions($conditions)));
        }
        
        $sth = $this->execute($sql, array_merge($values, $conditions));
        
        return $sth->rowCount();
    }

    public function deleteRecords(string $collection, array $conditions): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s WHERE %s',
            $this->prefix,
            $collection,
            implode(' AND ', $this->prepareConditions($conditions))
        );
        
        $sth = $this->execute($sql, $conditions);
        
        return $sth->rowCount();
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * @param Path|string $path
     */
    private function checkSqliteFile($path)
    {
        if (!file_exists($path)) {
            touch($path);
        }
    }

    private function bindValues(PDOStatement $sth, array $conditions)
    {
        foreach ($conditions as $name => $value) {
            if (is_array($value)) {
                foreach($value as $key => $val) {
                    $sth->bindValue(sprintf(':%s%s', $name, $key), $val);
                }
            } else {
                $sth->bindValue(':' . $name, $value);
            }
        }
    }

    private function prepareConditions(array $conditions): array
    {
        $prepared = [];

        foreach ($conditions as $name => $value) {
            if (is_array($value)) {

                $inConditions = [];

                foreach ($value as $key => $val) {
                    $inConditions[] = sprintf(':%s%s', $name, $key);
                }

                $prepared[] = sprintf('%s IN (%s)', $name, implode(', ', $inConditions));
            } elseif (strpos($value, '%') !== false) {
                $prepared[] = sprintf('`%s` LIKE :%s', $name, $name);
            } else {
                $prepared[] = sprintf('`%s` = :%s', $name, $name);
            }
        }

        return $prepared;
    }
}
