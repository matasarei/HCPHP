<?php

namespace Tests\Unit\DynamicDB;

use core\Config;
use DynamicDB\Manager\DatabaseManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Tests\Support\RecordingDatabase;

/**
 * DatabaseManager keeps the live schema in step with config/dynamicdb.json. It talks to MySQL
 * through SHOW TABLES and SHOW COLUMNS, which sqlite has no answer for, so those reads are
 * answered from a canned map and what is checked is the DDL it builds and the branch it
 * takes.
 *
 * @covers \DynamicDB\Manager\DatabaseManager
 */
class DatabaseManagerTest extends TestCase
{
    /**
     * @var RecordingDatabase
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new RecordingDatabase();

        // insertRecord() and updateRecords() are real writes -- only executeSQL() and the
        // schema reads are intercepted -- so the registry table has to exist for them.
        $this->database->getDBH()->exec(
            'CREATE TABLE dynamicdb (id INTEGER PRIMARY KEY AUTOINCREMENT, tablename TEXT,
             type TEXT, timecreated INTEGER, timemodified INTEGER)'
        );
    }

    private function manager(array $tables, array $answers = []): DatabaseManager
    {
        $manager = new DatabaseManager($this->database);

        $this->database->answerSchemaQueries($answers);

        // The manager reads config/dynamicdb.json in its constructor; these tests describe
        // their own tables rather than depending on the shipped ones.
        $config = new Config('dynamicdb', ['tables']);
        $config->set('tables', $tables);

        $property = new ReflectionProperty($manager, 'config');
        $property->setAccessible(true);
        $property->setValue($manager, $config);

        return $manager;
    }

    private function table(string $name, array $fields = [], ?string $type = null): stdClass
    {
        $table = new stdClass();
        $table->name = $name;
        $table->fields = $fields;

        if ($type !== null) {
            $table->type = $type;
        }

        return $table;
    }

    private function field(string $name, string $type, array $extra = []): stdClass
    {
        $field = new stdClass();
        $field->name = $name;
        $field->type = $type;

        foreach ($extra as $key => $value) {
            $field->$key = $value;
        }

        return $field;
    }

    private function statements(): string
    {
        return preg_replace('/\s+/', ' ', implode(' || ', $this->database->getStatements()));
    }

    // --- createTable ---------------------------------------------------------------------

    public function testAnAbsentTableIsCreated(): void
    {
        $manager = $this->manager([$this->table('widgets')], ['SHOW TABLES' => [null, 'widgets']]);
        $manager->updateTables();

        self::assertStringContainsString('CREATE TABLE `widgets`', $this->statements());
    }

    public function testANewTableGetsAnAutoIncrementPrimaryKey(): void
    {
        $this->manager([$this->table('widgets')], ['SHOW TABLES' => [null, 'widgets']])->updateTables();

        self::assertStringContainsString('`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT', $this->statements());
        self::assertStringContainsString('PRIMARY KEY (`id`)', $this->statements());
    }

    public function testAnOrdinaryTableGetsTimestampColumns(): void
    {
        $this->manager([$this->table('widgets')], ['SHOW TABLES' => [null, 'widgets']])->updateTables();

        self::assertStringContainsString('`timecreated`', $this->statements());
        self::assertStringContainsString('`timemodified`', $this->statements());
    }

    /**
     * A service table is ordered rather than timestamped.
     */
    public function testAServiceTableGetsAPositionInstead(): void
    {
        $table = $this->table('lookup', [], DatabaseManager::TABLE_SERVICE);
        $this->manager([$table], ['SHOW TABLES' => [null, 'lookup']])->updateTables();

        self::assertStringContainsString('`position`', $this->statements());
        self::assertStringNotContainsString('`timecreated`', $this->statements());
    }

    /**
     * The create is verified rather than assumed: if the table still is not there afterwards,
     * nothing is written to the registry.
     */
    public function testATableThatFailsToAppearIsReported(): void
    {
        $manager = $this->manager([$this->table('widgets')], ['SHOW TABLES' => null]);

        @$manager->updateTables();

        self::assertStringNotContainsString('INSERT INTO', $this->statements());
    }

    // --- updateTables ----------------------------------------------------------------------

    public function testAnExistingTableIsTouchedRatherThanRecreated(): void
    {
        $this->database->insertRecord('dynamicdb', [
            'tablename' => 'widgets', 'type' => 'default', 'timecreated' => 1, 'timemodified' => 1,
        ]);

        $manager = $this->manager([$this->table('widgets')], ['SHOW TABLES' => 'widgets']);
        $manager->updateTables();

        self::assertStringNotContainsString('CREATE TABLE', $this->statements());

        // The registry write is a real one rather than raw SQL, so it is checked on the row.
        $row = $this->database->getRecord('dynamicdb', ['tablename' => 'widgets']);

        self::assertGreaterThan(1, (int)$row['timemodified'], 'the registry entry was refreshed');
    }

    public function testEveryFieldOfATableIsApplied(): void
    {
        $table = $this->table('widgets', [
            $this->field('title', 'Text', ['length' => 64]),
            $this->field('count', 'Integer', ['length' => 4]),
        ]);

        $this->manager([$table], ['SHOW TABLES' => 'widgets', 'SHOW COLUMNS' => null])->updateTables();

        self::assertStringContainsString('ADD COLUMN `title`', $this->statements());
        self::assertStringContainsString('ADD COLUMN `count`', $this->statements());
    }

    /**
     * A table with no name cannot be created or found, so it is skipped with a complaint
     * rather than producing "CREATE TABLE ``".
     */
    public function testATableWithNoNameIsSkipped(): void
    {
        $manager = $this->manager([$this->table('')], ['SHOW TABLES' => null]);

        @$manager->updateTables();

        self::assertStringNotContainsString('CREATE TABLE ``', $this->statements());
    }

    public function testSeveralTablesAreAllProcessed(): void
    {
        $manager = $this->manager(
            [$this->table('widgets'), $this->table('gadgets')],
            ['SHOW TABLES' => [null, 'widgets', null, 'gadgets']]
        );

        @$manager->updateTables();

        self::assertStringContainsString('CREATE TABLE `widgets`', $this->statements());
        self::assertStringContainsString('CREATE TABLE `gadgets`', $this->statements());
    }

    // --- initialize -----------------------------------------------------------------------------

    /**
     * The registry table is created on a database that has never been initialised.
     */
    public function testTheRegistryTableIsCreatedWhenAbsent(): void
    {
        $manager = $this->manager([], ['LIKE "{dynamicdb}"' => null, 'MAX(timemodified)' => 0]);

        $manager->initialize();

        self::assertStringContainsString('create table dynamicdb', $this->statements());
    }

    public function testTheRegistryIsLeftAloneWhenItAlreadyExists(): void
    {
        $manager = $this->manager([], [
            'LIKE "{dynamicdb}"' => 'dynamicdb',
            'MAX(timemodified)' => time() + 3600,
        ]);

        $manager->initialize();

        self::assertStringNotContainsString('create table dynamicdb', $this->statements());
    }

    /**
     * The schema is rebuilt only when the configuration is newer than the last run, so an
     * unchanged config costs two reads rather than a pass over every table.
     */
    public function testTablesAreOnlyUpdatedWhenTheConfigurationIsNewer(): void
    {
        $manager = $this->manager(
            [$this->table('widgets')],
            ['LIKE "{dynamicdb}"' => 'dynamicdb', 'MAX(timemodified)' => time() + 3600]
        );

        $manager->initialize();

        self::assertStringNotContainsString('CREATE TABLE `widgets`', $this->statements());
    }

    public function testAnOutdatedRegistryTriggersAnUpdate(): void
    {
        $manager = $this->manager(
            [$this->table('widgets')],
            ['LIKE "{dynamicdb}"' => 'dynamicdb', 'MAX(timemodified)' => 0, 'SHOW TABLES' => [null, 'widgets']]
        );

        @$manager->initialize();

        self::assertStringContainsString('CREATE TABLE `widgets`', $this->statements());
    }
}
