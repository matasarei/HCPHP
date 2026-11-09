<?php

namespace Tests\Unit\Core;

use core\DatabaseSQL;
use InvalidArgumentException;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * An IN condition's placeholders were named after the array's keys, and the keys were written
 * into the SQL text. A list of ids decoded from a JSON object -- {"0": 2, "0) OR (1=1": 2} --
 * therefore became `id IN (:id0, :id0) OR (1=1)`. PDO's MySQL driver emulates prepares by
 * default, so the injected text ran: every row matched, every row was updated.
 *
 * SQLite refuses that statement at execute() (it binds by native placeholders), which is why
 * the SQL text itself is asserted here, as well as what each call returns.
 */
class DatabaseSQLInListTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    /**
     * @var string[]
     */
    private $statements = [];

    protected function setUp(): void
    {
        $statements = &$this->statements;

        $this->database = new class (DatabaseSQL::DRIVER_SQLITE) extends DatabaseSQL {
            /**
             * @var string[]
             */
            public $statements;

            protected function executeStatement(string $sql, array $conditions = []): PDOStatement
            {
                $this->statements[] = $sql;

                return parent::executeStatement($sql, $conditions);
            }
        };
        $this->database->statements = &$statements;

        $this->database->getDBH()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');

        foreach (['alice@example.com', 'bob@example.com', 'carol@example.com'] as $email) {
            $this->database->insertRecord('users', ['email' => $email]);
        }
    }

    public function testArrayKeysNeverReachTheSqlText(): void
    {
        try {
            $this->database->getRecords('users', ['id' => ['0' => 2, '0) OR (1=1' => 2]]);
        } catch (PDOException $exception) {
            // Only SQLite stops here. The text below is what MySQL's emulated prepares would run.
        }

        $sql = (string)end($this->statements);

        self::assertStringNotContainsString('OR (1=1', $sql);
        self::assertStringContainsString('IN (:id0, :id1)', $sql);
    }

    public function testAListWithStringKeysMatchesByItsValues(): void
    {
        $records = $this->database->getRecords('users', ['id' => ['first' => 1, '0) OR (1=1' => 3]]);

        self::assertSame(['alice@example.com', 'carol@example.com'], array_column($records, 'email'));
    }

    public function testAListWithGapsInItsKeysMatchesByItsValues(): void
    {
        // What array_filter() leaves behind.
        $records = $this->database->getRecords('users', ['id' => [3 => 2, 7 => 3]]);

        self::assertSame(['bob@example.com', 'carol@example.com'], array_column($records, 'email'));
    }

    public function testUpdateRecordsWithInjectedKeysUpdatesOnlyTheListedIds(): void
    {
        $updated = $this->database->updateRecords(
            'users',
            ['email' => 'owned@example.com'],
            ['id' => ['0' => 999, '0) OR (1=1' => 999]]
        );

        self::assertSame(0, $updated);
        self::assertCount(0, $this->database->getRecords('users', ['email' => 'owned@example.com']));
    }

    public function testDeleteRecordsWithInjectedKeysDeletesOnlyTheListedIds(): void
    {
        $deleted = $this->database->deleteRecords('users', ['id' => ['0' => 2, '0) OR (1=1' => 2]]);

        self::assertSame(1, $deleted);
        self::assertCount(2, $this->database->getRecords('users'));
    }

    /**
     * @dataProvider unsafeColumnNameProvider
     */
    public function testAConditionColumnMustBeAPlainIdentifier($column): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->getRecords('users', [$column => 1]);
    }

    public function unsafeColumnNameProvider(): array
    {
        return [
            'backtick' => ['id` = 1 OR `id'],
            'parenthesis' => ['id) OR (1=1'],
            'space' => ['id OR 1'],
            'dot' => ['users.id'],
            'integer key' => [0],
        ];
    }

    public function testAPlainListStillCompilesToTheSamePlaceholders(): void
    {
        $records = $this->database->getRecords('users', ['id' => [1, 2]]);

        self::assertCount(2, $records);
        self::assertStringContainsString('id IN (:id0, :id1)', (string)end($this->statements));
    }
}
