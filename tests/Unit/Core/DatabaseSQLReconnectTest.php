<?php

namespace Tests\Unit\Core;

use core\Application;
use core\DatabaseSQL;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * A long-running CLI process -- a cron task, an import, a queue worker -- sleeps between
 * units of work, MySQL drops the idle connection, and every query after that dies with
 * "server has gone away". The connection was built once in the constructor and never rebuilt,
 * so the run was over.
 *
 * Retrying is only safe under narrow conditions, and the guards matter more than the retry:
 * replaying a statement on a fresh connection inside a transaction would commit a torn write,
 * and replaying one in a web request would double a user-visible action rather than fail it.
 *
 * @covers \core\DatabaseSQL
 */
class DatabaseSQLReconnectTest extends TestCase
{
    /**
     * @var string
     */
    private $mode;

    /**
     * @var string
     */
    private $errorLog;

    /**
     * @var string|false
     */
    private $previousErrorLog;

    protected function setUp(): void
    {
        $this->mode = Application::getMode();
        Application::setMode(Application::MODE_CLI);

        // A reconnect is worth a line in the error log, and that line would otherwise land in
        // the test output. Capture it instead of silencing the code under test.
        $this->errorLog = tempnam(sys_get_temp_dir(), 'hcphp-reconnect-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        Application::setMode($this->mode);

        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);

        if (is_file($this->errorLog)) {
            unlink($this->errorLog);
        }
    }

    private function readErrorLog(): string
    {
        return is_file($this->errorLog) ? (string)file_get_contents($this->errorLog) : '';
    }

    private function makeDatabase(string $driver = DatabaseSQL::DRIVER_MYSQL): ReconnectingDatabaseDouble
    {
        $database = new ReconnectingDatabaseDouble($driver);
        $database->getDBH()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $database->insertRecord('users', ['email' => 'alice@example.com']);

        // Building the fixture runs statements of its own; the counters describe the query
        // under test, not the setup.
        $database->resetCounters();

        return $database;
    }

    private static function lostConnection(int $code = 2006, string $message = 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'): PDOException
    {
        $exception = new PDOException($message);
        $exception->errorInfo = ['HY000', $code, $message];

        return $exception;
    }

    // --- isLostConnection(): the pure part, and the part most likely to be wrong ---------

    /**
     * @dataProvider lostConnectionProvider
     */
    public function testIsLostConnectionRecognisesADroppedConnection(PDOException $exception): void
    {
        self::assertTrue(DatabaseSQL::isLostConnection($exception));
    }

    public function lostConnectionProvider(): array
    {
        return [
            'driver code 2006' => [self::lostConnection(2006)],
            'driver code 2013' => [self::lostConnection(2013, 'SQLSTATE[HY000]: Lost connection to MySQL server during query')],
            'message only, no driver code' => [self::lostConnection(0, 'SQLSTATE[HY000]: MySQL server has gone away')],
            'lost connection message' => [self::lostConnection(0, 'Lost connection to MySQL server at reading initial communication packet')],
        ];
    }

    /**
     * @dataProvider survivingErrorProvider
     */
    public function testIsLostConnectionIgnoresErrorsThatAreNotConnectionLoss(PDOException $exception): void
    {
        self::assertFalse(DatabaseSQL::isLostConnection($exception));
    }

    public function survivingErrorProvider(): array
    {
        return [
            'duplicate key' => [self::lostConnection(1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'PRIMARY'")],
            'unknown column' => [self::lostConnection(1054, "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'nope' in 'field list'")],
            'deadlock' => [self::lostConnection(1213, 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock')],
            'access denied' => [self::lostConnection(1045, "SQLSTATE[28000]: Access denied for user 'root'@'localhost'")],
            'no error info at all' => [new PDOException('something went wrong')],
        ];
    }

    /**
     * A deadlock is also a "retry me" error, but it is not this method's job and retrying it
     * on a fresh connection would be wrong -- the transaction it belonged to is gone.
     */
    public function testDeadlockIsNotTreatedAsConnectionLoss(): void
    {
        self::assertFalse(DatabaseSQL::isLostConnection(self::lostConnection(1213, 'Deadlock found')));
    }

    // --- shouldReconnect(): the guards ---------------------------------------------------

    public function testReconnectsOnMysqlInCliOutsideATransaction(): void
    {
        self::assertTrue($this->makeDatabase()->shouldReconnectFor(self::lostConnection()));
    }

    public function testDoesNotReconnectForANonMysqlDriver(): void
    {
        $database = $this->makeDatabase(DatabaseSQL::DRIVER_SQLITE);

        self::assertFalse(
            $database->shouldReconnectFor(self::lostConnection()),
            'sqlite has no server to lose, and reconnecting an in-memory database would discard it'
        );
    }

    public function testDoesNotReconnectInAWebRequest(): void
    {
        Application::setMode(Application::MODE_WEB);

        self::assertFalse(
            $this->makeDatabase()->shouldReconnectFor(self::lostConnection()),
            'a web request should fail visibly rather than silently replay a statement'
        );
    }

    public function testDoesNotReconnectInsideATransaction(): void
    {
        $database = $this->makeDatabase();
        $database->getDBH()->beginTransaction();

        self::assertFalse(
            $database->shouldReconnectFor(self::lostConnection()),
            'the transaction is gone with the connection; replaying one statement would tear the write'
        );

        $database->getDBH()->rollBack();
    }

    public function testDoesNotReconnectForAnOrdinarySqlError(): void
    {
        self::assertFalse($this->makeDatabase()->shouldReconnectFor(self::lostConnection(1062, 'Duplicate entry')));
    }

    // --- the retry itself ----------------------------------------------------------------

    public function testStatementIsRetriedOnceOnAFreshConnection(): void
    {
        $database = $this->makeDatabase();
        $database->failNextStatement();

        $records = $database->getRecords('users', ['email' => 'alice@example.com']);

        self::assertCount(1, $records, 'the retried statement should return the real result');
        self::assertSame(1, $database->connectCalls, 'exactly one reconnect');
        self::assertSame(2, $database->statementAttempts, 'one failure, one retry');
    }

    /**
     * A reconnect that leaves no trace turns a failing server into a mysterious slowdown.
     */
    public function testReconnectIsRecordedInTheErrorLog(): void
    {
        $database = $this->makeDatabase();
        $database->failNextStatement();

        $database->getRecords('users', ['email' => 'alice@example.com']);

        self::assertStringContainsString('connection lost, reconnecting', $this->readErrorLog());
    }

    public function testHealthyQueriesLogNothing(): void
    {
        $this->makeDatabase()->getRecords('users', ['email' => 'alice@example.com']);

        self::assertSame('', $this->readErrorLog());
    }

    public function testStatementIsNotRetriedMoreThanOnce(): void
    {
        $database = $this->makeDatabase();
        $database->failEveryStatement();

        try {
            $database->getRecords('users', ['email' => 'alice@example.com']);
            self::fail('a connection that stays down must surface the failure');
        } catch (PDOException $exception) {
            self::assertStringContainsString('gone away', $exception->getMessage());
        }

        self::assertSame(1, $database->connectCalls, 'one reconnect attempt, not a loop');
        self::assertSame(2, $database->statementAttempts);
    }

    public function testAnOrdinaryErrorIsNotRetriedAndIsRethrownUnchanged(): void
    {
        $database = $this->makeDatabase();
        $database->failEveryStatement(self::lostConnection(1062, 'Duplicate entry'));

        try {
            $database->getRecords('users', ['email' => 'alice@example.com']);
            self::fail('the error should have propagated');
        } catch (PDOException $exception) {
            self::assertStringContainsString('Duplicate entry', $exception->getMessage());
        }

        self::assertSame(0, $database->connectCalls);
        self::assertSame(1, $database->statementAttempts, 'no retry for an error the query itself caused');
    }

    public function testHealthyQueriesNeverReconnect(): void
    {
        $database = $this->makeDatabase();

        $database->getRecords('users', ['email' => 'alice@example.com']);

        self::assertSame(0, $database->connectCalls);
        self::assertSame(1, $database->statementAttempts);
    }
}

/**
 * Drives the retry path over a real sqlite connection.
 *
 * getDriver() is overridden rather than reflected onto so the MySQL guards can be exercised
 * without a MySQL server; connect() is stubbed because reconnecting a ':memory:' database
 * would throw its contents away, which is exactly why the driver guard exists.
 */
class ReconnectingDatabaseDouble extends DatabaseSQL
{
    public $connectCalls = 0;
    public $statementAttempts = 0;

    /**
     * @var string
     */
    private $driverName;

    /**
     * @var PDOException|null
     */
    private $failure;

    /**
     * @var bool
     */
    private $failOnce = false;

    public function __construct(string $driver = DatabaseSQL::DRIVER_MYSQL)
    {
        parent::__construct(DatabaseSQL::DRIVER_SQLITE);

        $this->driverName = $driver;
    }

    public function getDriver(): string
    {
        return $this->driverName;
    }

    public function shouldReconnectFor(PDOException $exception): bool
    {
        return $this->shouldReconnect($exception);
    }

    public function resetCounters(): void
    {
        $this->connectCalls = 0;
        $this->statementAttempts = 0;
    }

    public function failNextStatement(?PDOException $exception = null): void
    {
        $this->failure = $exception ?? self::goneAway();
        $this->failOnce = true;
    }

    public function failEveryStatement(?PDOException $exception = null): void
    {
        $this->failure = $exception ?? self::goneAway();
        $this->failOnce = false;
    }

    private static function goneAway(): PDOException
    {
        $exception = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $exception->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];

        return $exception;
    }

    protected function connect(): void
    {
        $this->connectCalls++;
    }

    protected function executeStatement(string $sql, array $conditions = []): \PDOStatement
    {
        $this->statementAttempts++;

        if ($this->failure !== null) {
            $failure = $this->failure;

            if ($this->failOnce) {
                $this->failure = null;
            }

            throw $failure;
        }

        return parent::executeStatement($sql, $conditions);
    }
}
