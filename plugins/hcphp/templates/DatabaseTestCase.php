<?php

namespace Tests\Support;

use core\DatabaseSQL;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Base for tests whose code reads or writes the database: a real in-memory sqlite connection per
 * test, registered where the code looks the database up, with tables built from the code itself.
 *
 * A copy built on HCPHP rarely keeps a schema file, and a dump of a production database is never
 * the way to get one. The tables a test needs are the ones its mappers write: each mapper names
 * its table and turns an entity into a row, and the row's keys are the columns.
 * createTableFromRow() builds the table from that row, so a column the mapper starts writing
 * reaches the test without anyone editing a fixture.
 *
 * Each test creates only the tables it touches, in setUp(). Nothing is shared between tests: the
 * connection is new for every test, and tearDown() unregisters it.
 *
 * What sqlite cannot run stays on a recording double (Tests\Support\RecordingDatabase in HCPHP):
 * MySQL-only SQL such as SQL_CALC_FOUND_ROWS / FOUND_ROWS(), ON DUPLICATE KEY UPDATE,
 * MATCH ... AGAINST, ENUM or UNSIGNED DDL, CONCAT() on sqlite older than 3.44, and anything that
 * asks MySQL about its own schema (SHOW TABLES, information_schema).
 *
 * Copied from the hcphp plugin's templates/. Adapt the database class name (SqlDatabase in some
 * copies) and the two registration hooks, then delete this paragraph.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * @var DatabaseSQL|null
     */
    protected $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $this->registerDatabase($this->database);
    }

    protected function tearDown(): void
    {
        $this->unregisterDatabase();
        $this->database = null;

        parent::tearDown();
    }

    /**
     * Puts the connection everywhere this copy's code finds the database: a static registry
     * (Database::getInstance('default')), a container entry ('database'), or nowhere when every
     * repository takes it as a constructor argument. Register at every point the code reads, or
     * part of it still tries to reach a real server. Clear the copy's entity cache here too, if
     * it keeps one, so an entity loaded by an earlier test is not returned for the same id.
     */
    protected function registerDatabase(DatabaseSQL $database): void
    {
    }

    /**
     * Undoes registerDatabase(), unconditionally, so a later test cannot inherit this connection.
     */
    protected function unregisterDatabase(): void
    {
    }

    /**
     * Creates $table with a column for every key of $row, a row as the mapper writes it, and an
     * integer primary key `id` that sqlite fills on insert.
     *
     * Column types come from the sample values: an int or a bool is INTEGER, a float is REAL,
     * anything else is TEXT. The type matters because the database layer binds every value as a
     * string, and a TEXT column keeps it as text and compares it as text: '9' > '50', so a time
     * window over timestamps stored that way matches the wrong rows. Pass $types for a column
     * whose sample is null, or is a numeric string that must compare as a number. A numeric
     * string left as TEXT keeps its leading zeros, which a phone number needs.
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $types column => sqlite type, overriding the inferred one
     */
    protected function createTableFromRow(string $table, array $row, array $types = []): void
    {
        $columns = ['"id" INTEGER PRIMARY KEY'];

        foreach ($row as $column => $value) {
            if ($column === 'id') {
                continue;
            }

            $columns[] = sprintf('"%s" %s', $column, $types[$column] ?? self::typeOf($value));
        }

        $this->database->getDBH()->exec(
            sprintf('CREATE TABLE "%s%s" (%s)', $this->database->getPrefix(), $table, implode(', ', $columns))
        );
    }

    /**
     * The row a mapper writes for an entity, through whichever method this copy's mappers use:
     * the public mapFromEntity() in HCPHP, a protected _prepareData() or prepareData() in older
     * copies. Build the entity with every field set, so each column's sample value has its type.
     *
     * @param object $mapper
     * @param object $entity
     *
     * @return array<string, mixed>
     */
    protected function rowFromMapper($mapper, string $method, $entity): array
    {
        $reflection = new ReflectionMethod($mapper, $method);

        // Required on 7.4, a no-op from 8.1, deprecated from 8.5.
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invoke($mapper, $entity);
    }

    /**
     * @param mixed $value
     */
    private static function typeOf($value): string
    {
        if (is_int($value) || is_bool($value)) {
            return 'INTEGER';
        }

        if (is_float($value)) {
            return 'REAL';
        }

        return 'TEXT';
    }
}
