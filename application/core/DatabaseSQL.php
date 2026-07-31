<?php

namespace core;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

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
     * "MySQL server has gone away". Reported both for a connection the server closed after
     * wait_timeout and for one killed while a statement was running -- it says the connection
     * is gone, not whether the statement ran.
     */
    const ERROR_GONE_AWAY = 2006;

    /**
     * "Lost connection to MySQL server during query".
     */
    const ERROR_LOST_DURING_QUERY = 2013;

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
     * Connection details, kept so a dropped connection can be rebuilt.
     *
     * Null for sqlite: an in-memory database cannot be reconnected without discarding it, and
     * a file one never drops. connect() refuses to run without a DSN.
     *
     * @var string|null
     */
    private $dsn;

    /**
     * @var string
     */
    private $user = '';

    /**
     * @var string
     */
    private $pass = '';

    /**
     * @var string
     */
    private $encoding = 'utf8';

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
        ?string $uri = null,
        ?string $dbname = null,
        string $user = 'root',
        string $pass = '',
        string $prefix = '',
        string $encoding = 'utf8',
        ?string $port = null
    ) {
        $this->prefix = $prefix;
        $this->driver = $driver;
        $this->encoding = $encoding;

        if ($driver === self::DRIVER_SQLITE) {
            if ($uri === null) {
                $path = ':memory:';
            } else {
                $path = new Path($uri);
                $this->checkSqliteFile($path);
            }

            $this->dbh = new PDO(sprintf('sqlite:%s', $path));
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return;
        }

        if ($port === null) {
            $port = self::DEFAULT_PORTS[$driver];
        }

        $this->dsn = sprintf('%s:host=%s;port=%d;dbname=%s', $driver, $uri, $port, $dbname);
        $this->user = $user;
        $this->pass = $pass;

        $this->connect();
    }

    /**
     * Open the connection described by the constructor arguments.
     *
     * Called once from the constructor and again by execute() when a server-side connection
     * has gone away. Everything that configures a handle belongs here, or the rebuilt one
     * would come back with different settings from the original.
     */
    protected function connect(): void
    {
        if ($this->dsn === null) {
            throw new RuntimeException('This connection cannot be re-established.');
        }

        $this->dbh = new PDO($this->dsn, $this->user, $this->pass);
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->dbh->exec(sprintf('SET NAMES %s', $this->encoding));
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

    public function getValuesSQL(string $sql, array $conditions = [], ?string $column = null): array
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

    public function getRecords(string $collection, array $conditions = [], ?int $limit = null): array
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

    private function execute(string $sql, array $conditions = [])
    {
        try {
            return $this->executeStatement($sql, $conditions);
        } catch (PDOException $exception) {
            if (!$this->shouldReconnect($exception, $sql)) {
                throw $exception;
            }

            $this->logReconnect($exception);
            $this->connect();

            // Once only. If the second attempt fails too the connection is not coming back
            // and the caller has to hear about it.
            return $this->executeStatement($sql, $conditions);
        }
    }

    /**
     * Record that the connection was rebuilt.
     *
     * A CLI process that sleeps between units of work -- a cron task, an import, a queue
     * worker -- outlives the server's idle timeout, and every query after that fails until
     * the connection is rebuilt. A reconnect that leaves no trace turns a server dropping
     * connections into an unexplained slowdown, so it goes to two places:
     *
     * - the server error log, because this is an operational event rather than a programming
     *   mistake, and because it is the only record that survives with debug off -- which is
     *   how production runs;
     * - Debug, where every other framework message surfaces, so it is not the one event
     *   missing from the output a developer is actually reading.
     *
     * Guarded by isOn(): Debug::dump() appends unconditionally while flush() only drains the
     * buffer when debug is on, so dumping with it off would grow that buffer for the life of
     * a long-running process -- exactly the process this reconnect exists for.
     */
    private function logReconnect(PDOException $exception): void
    {
        $message = sprintf('[DatabaseSQL] connection lost, reconnecting: %s', $exception->getMessage());

        error_log($message);

        if (Debug::isOn()) {
            Debug::dump($message, false);
        }
    }

    /**
     * Prepare, bind and run one statement.
     *
     * @throws PDOException
     */
    protected function executeStatement(string $sql, array $conditions = []): PDOStatement
    {
        $sth = $this->dbh->prepare($sql);

        if (count($conditions) > 0) {
            $this->bindValues($sth, $conditions);
        }

        $sth->execute();

        return $sth;
    }

    /**
     * Whether $exception is worth retrying on a rebuilt connection.
     *
     * Every clause is a safety guard, not an optimisation:
     *
     * - MySQL only. sqlite has no server to lose and reconnecting an in-memory database would
     *   silently discard it. The other drivers report connection loss differently and are not
     *   verified here, so they are left alone rather than guessed at.
     * - CLI only. In a web request a failed statement should surface; replaying it invisibly
     *   risks repeating a user-visible action.
     * - Not inside a transaction. The transaction died with the connection, so replaying just
     *   this statement would commit a fragment of it.
     * - A read. See isReadOnly(): the error does not say whether the server ran the statement,
     *   so a write is never replayed.
     */
    protected function shouldReconnect(PDOException $exception, string $sql): bool
    {
        if ($this->getDriver() !== self::DRIVER_MYSQL) {
            return false;
        }

        if (Application::getMode() !== Application::MODE_CLI) {
            return false;
        }

        if ($this->dbh->inTransaction()) {
            return false;
        }

        if (!self::isLostConnection($exception)) {
            return false;
        }

        // Only a read. A write is never replayed, whatever the error says -- see isReadOnly().
        return self::isReadOnly($sql);
    }

    /**
     * Whether $sql only reads, and so may be run a second time with no visible effect.
     *
     * This is the whole of the replay rule, because the error carries no usable information
     * about whether the server ran the statement. Measured against MariaDB 11, not assumed:
     *
     * - A connection closed by the server after wait_timeout reports 2006, "server has gone
     *   away". So does a connection killed while a statement had been executing for two
     *   seconds -- same code, same message, for both reads and writes.
     * - PDO's MySQL driver emulates prepares by default (ATTR_EMULATE_PREPARES = 1) and this
     *   class never turns that off, so prepare() makes no server round-trip and the failure
     *   always surfaces at execute() -- exactly where a statement that did reach the server
     *   fails too.
     *
     * There is therefore no way to tell "never sent" from "sent, ran, and the answer was lost
     * on the way back". Replaying an INSERT in the second case writes the row twice, and
     * "SET n = n + 1" counts twice. A read replayed in either case is harmless.
     *
     * The allow-list is deliberately short: anything unrecognised counts as a write. A missed
     * retry costs a failed run, which the caller sees; a wrong one costs duplicated data,
     * which nobody sees.
     */
    public static function isReadOnly(string $sql): bool
    {
        return (bool)preg_match('/^\s*\(?\s*(SELECT|SHOW|DESC(RIBE)?|EXPLAIN)\b/i', $sql);
    }

    /**
     * Whether a PDOException means the connection went away, as opposed to the statement
     * being wrong.
     *
     * This answers only "is the connection gone", not "may the statement be run again" --
     * see isReadOnly() for that. Neither code distinguishes a statement that never reached
     * the server from one that ran.
     *
     * The message is checked as well because the driver code is not always populated -- PDO
     * leaves errorInfo[1] at 0 for some connection-level failures.
     *
     * Deliberately not included: 1213 (deadlock) and 1205 (lock wait timeout). Both are
     * retryable in principle, but not by reconnecting -- the transaction they belonged to is
     * gone, and replaying one statement of it would be wrong.
     */
    public static function isLostConnection(PDOException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? 0;

        if (in_array($driverCode, [self::ERROR_GONE_AWAY, self::ERROR_LOST_DURING_QUERY], true)) {
            return true;
        }

        $message = $exception->getMessage();

        return strpos($message, 'server has gone away') !== false
            || strpos($message, 'Lost connection') !== false;
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
            } elseif ($value instanceof Like) {
                $sth->bindValue(':' . $name, $value->getPattern());
            } else {
                $sth->bindValue(':' . $name, $value);
            }
        }
    }

    /**
     * Compile a condition array into SQL predicates.
     *
     * The operator is chosen by the value's *type*, never by its contents: an array
     * means IN, a Like means LIKE, anything else means `=`. A plain string is matched
     * literally even when it contains `%` or `_`.
     *
     * @param array $conditions
     *
     * @return array
     */
    private function prepareConditions(array $conditions): array
    {
        $prepared = [];

        foreach ($conditions as $name => $value) {
            if (is_array($value)) {

                $inConditions = [];

                foreach ($value as $key => $val) {
                    if ($val instanceof Like) {
                        throw new InvalidArgumentException(
                            sprintf('LIKE is not supported inside an IN condition ("%s")', $name)
                        );
                    }

                    $inConditions[] = sprintf(':%s%s', $name, $key);
                }

                $prepared[] = sprintf('%s IN (%s)', $name, implode(', ', $inConditions));
            } elseif ($value instanceof Like) {
                $prepared[] = sprintf(
                    "`%s` LIKE :%s ESCAPE '%s'",
                    $name,
                    $name,
                    Like::ESCAPE_CHARACTER
                );
            } else {
                $prepared[] = sprintf('`%s` = :%s', $name, $name);
            }
        }

        return $prepared;
    }
}
