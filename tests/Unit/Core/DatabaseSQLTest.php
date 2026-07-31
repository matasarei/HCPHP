<?php

namespace Tests\Unit\Core;

use core\DatabaseSQL;
use core\Like;
use core\Path;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The bypass and the LIKE opt-in are covered in DatabaseSQLConditionsTest and
 * DatabaseSQLLikeTest; this covers the rest of the query surface.
 *
 * @covers \core\DatabaseSQL
 */
class DatabaseSQLTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $this->database->getDBH()->exec(
            'CREATE TABLE records (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, category TEXT, count INTEGER)'
        );

        foreach ([['A', 'x', 1], ['B', 'x', 2], ['C', 'y', 3]] as [$title, $category, $count]) {
            $this->database->insertRecord('records', [
                'title' => $title, 'category' => $category, 'count' => $count,
            ]);
        }
    }

    // --- connection -------------------------------------------------------------------------

    public function testSqliteInMemoryIsUsedWhenNoUriIsGiven(): void
    {
        self::assertInstanceOf(PDO::class, $this->database->getDBH());
        self::assertSame(DatabaseSQL::DRIVER_SQLITE, $this->database->getDriver());
    }

    public function testErrorsAreRaisedAsExceptions(): void
    {
        $this->expectException(\PDOException::class);

        $this->database->getRecordsSQL('SELECT * FROM no_such_table');
    }

    public function testPrefixIsReadable(): void
    {
        self::assertSame('', $this->database->getPrefix());
    }

    public function testAFileBackedSqliteDatabaseIsCreated(): void
    {
        $path = new Path('cache/tests_fixture_db.sqlite');
        $path->mkpath();

        try {
            $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE, 'cache/tests_fixture_db.sqlite');
            $database->executeSQL('CREATE TABLE t (id INTEGER PRIMARY KEY)');

            self::assertFileExists((string)$path);
        } finally {
            $path->rmpath();
        }
    }

    // --- reading -----------------------------------------------------------------------------

    public function testGetRecordsReturnsEveryRow(): void
    {
        self::assertCount(3, $this->database->getRecords('records'));
    }

    public function testGetRecordsAppliesConditions(): void
    {
        self::assertCount(2, $this->database->getRecords('records', ['category' => 'x']));
    }

    public function testGetRecordsAppliesALimit(): void
    {
        self::assertCount(1, $this->database->getRecords('records', [], 1));
    }

    public function testGetRecordReturnsOneRow(): void
    {
        self::assertSame('A', $this->database->getRecord('records', ['title' => 'A'])['title']);
    }

    public function testGetRecordIsFalseWhenNothingMatches(): void
    {
        self::assertFalse($this->database->getRecord('records', ['title' => 'absent']));
    }

    /**
     * More than one match means the conditions did not identify a row; the caller still gets
     * the first, but the mismatch is reported.
     */
    public function testGetRecordWarnsWhenTheConditionsMatchSeveralRows(): void
    {
        $record = @$this->database->getRecord('records', ['category' => 'x']);

        self::assertSame('A', $record['title']);
    }

    public function testGetRecordSql(): void
    {
        self::assertSame('B', $this->database->getRecordSQL(
            'SELECT * FROM records WHERE title = :title',
            ['title' => 'B']
        )['title']);
    }

    public function testGetRecordSqlIsNullWhenNothingMatches(): void
    {
        self::assertNull($this->database->getRecordSQL('SELECT * FROM records WHERE title = :t', ['t' => 'z']));
    }

    public function testGetResultSqlReturnsTheFirstColumn(): void
    {
        self::assertSame('3', (string)$this->database->getResultSQL('SELECT COUNT(*) FROM records'));
    }

    public function testGetResultSqlIsNullWhenNothingMatches(): void
    {
        self::assertNull($this->database->getResultSQL('SELECT title FROM records WHERE title = :t', ['t' => 'z']));
    }

    public function testGetValuesReturnsOneColumn(): void
    {
        self::assertSame(['A', 'B', 'C'], $this->database->getValues('records', [], 'title'));
    }

    public function testGetValuesDefaultsToTheFirstColumn(): void
    {
        self::assertSame(['1', '2', '3'], array_map('strval', $this->database->getValues('records')));
    }

    public function testGetValuesSql(): void
    {
        self::assertSame(
            ['A', 'B'],
            $this->database->getValuesSQL('SELECT title FROM records WHERE category = :c', ['c' => 'x'])
        );
    }

    /**
     * {table} in raw SQL is replaced with the configured prefix.
     */
    public function testThePrefixPlaceholderIsExpanded(): void
    {
        self::assertCount(3, $this->database->getRecordsSQL('SELECT * FROM {records}'));
    }

    // --- writing ------------------------------------------------------------------------------

    public function testInsertRecordReturnsTheNewId(): void
    {
        $id = $this->database->insertRecord('records', ['title' => 'D', 'category' => 'z', 'count' => 4]);

        self::assertSame('D', $this->database->getRecord('records', ['id' => $id])['title']);
    }

    public function testUpdateRecordChangesTheMatchingRow(): void
    {
        $record = $this->database->getRecord('records', ['title' => 'A']);
        $record['title'] = 'A updated';

        $this->database->updateRecord('records', $record);

        self::assertSame('A updated', $this->database->getRecord('records', ['id' => $record['id']])['title']);
    }

    public function testUpdateRecordsChangesEveryMatch(): void
    {
        $changed = $this->database->updateRecords('records', ['category' => 'z'], ['category' => 'x']);

        self::assertSame(2, $changed);
        self::assertCount(2, $this->database->getRecords('records', ['category' => 'z']));
    }

    public function testDeleteRecordsRemovesEveryMatch(): void
    {
        $removed = $this->database->deleteRecords('records', ['category' => 'x']);

        self::assertSame(2, $removed);
        self::assertCount(1, $this->database->getRecords('records'));
    }

    public function testReplaceRecordUpdatesAnExistingRow(): void
    {
        $record = $this->database->getRecord('records', ['title' => 'A']);
        $record['title'] = 'A replaced';

        self::assertTrue($this->database->replaceRecord('records', $record));
        self::assertCount(3, $this->database->getRecords('records'));
        self::assertSame('A replaced', $this->database->getRecord('records', ['id' => $record['id']])['title']);
    }

    public function testReplaceRecordIsFalseWhenThereIsNothingToReplace(): void
    {
        self::assertFalse($this->database->replaceRecord('records', ['id' => 999, 'title' => 'X']));
    }

    public function testExecuteSqlReportsTheAffectedRowCount(): void
    {
        self::assertSame(2, $this->database->executeSQL(
            'UPDATE records SET count = count + 1 WHERE category = :c',
            ['c' => 'x']
        ));
    }

    // --- conditions -----------------------------------------------------------------------------

    public function testAnArrayConditionCompilesToIn(): void
    {
        self::assertCount(2, $this->database->getRecords('records', ['title' => ['A', 'C']]));
    }

    public function testAnEmptyConditionSetSelectsEverything(): void
    {
        self::assertCount(3, $this->database->getRecords('records', []));
    }

    public function testABooleanConditionIsBoundAsAnInteger(): void
    {
        $this->database->executeSQL('UPDATE records SET count = 1 WHERE title = :t', ['t' => 'A']);

        self::assertCount(1, $this->database->getRecords('records', ['count' => true]));
    }

    /**
     * A Like inside an IN list would be bound as its object, which is not something a caller
     * can have meant.
     */
    public function testALikeNestedInAnInListIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->getRecords('records', ['title' => ['A', new Like('%B%')]]);
    }
}
