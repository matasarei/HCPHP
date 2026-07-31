<?php

namespace Tests\Unit\DynamicDB;

use core\DatabaseSQL;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use DynamicDB\Manager\DatabaseManager;
use DynamicDB\Manager\EntityManager;
use DynamicDB\Mapper\EntityMapper;
use DynamicDB\Repository\DynamicRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Tests\Support\RecordingDatabase;

/**
 * @covers \DynamicDB\Manager\EntityManager
 * @covers \DynamicDB\Manager\DatabaseManager
 * @covers \DynamicDB\Mapper\EntityMapper
 */
class ManagerTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $this->database->getDBH()->exec(
            'CREATE TABLE records (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, count INTEGER,
             timecreated INTEGER, timemodified INTEGER)'
        );
    }

    private function table(): Table
    {
        return (new Table('records', 'Records'))
            ->addField(new Field('title', 'Title', Field::TYPE_TEXT))
            ->addField(new Field('count', 'Count', Field::TYPE_INTEGER))
        ;
    }

    private function manager(): EntityManager
    {
        $table = $this->table();

        return new EntityManager($table, new DynamicRepository($this->database, $table));
    }

    private function entity(string $title, int $count = 1): DynamicEntity
    {
        return (new DynamicEntity())->set('title', $title)->set('count', $count);
    }

    // --- EntityManager -----------------------------------------------------------------------

    public function testSavingANewEntityStoresIt(): void
    {
        $entity = $this->manager()->save($this->entity('First'));

        self::assertNotNull($entity->getId());
        self::assertCount(1, $this->database->getRecords('records'));
    }

    public function testSaveReturnsTheEntityItStored(): void
    {
        $entity = $this->entity('First');

        self::assertSame($entity, $this->manager()->save($entity));
    }

    /**
     * Editing copies the submitted values onto the row that was loaded, so the update keeps
     * the existing id instead of inserting a second row.
     */
    public function testSavingOverAnExistingEntityUpdatesIt(): void
    {
        $manager = $this->manager();
        $existing = $manager->save($this->entity('Before'));
        $id = $existing->getId();

        $result = $manager->save($this->entity('After'), $existing);

        self::assertSame($id, $result->getId());
        self::assertCount(1, $this->database->getRecords('records'));
        self::assertSame('After', $this->database->getRecord('records', ['id' => $id])['title']);
    }

    public function testSavingOverAnExistingEntityReturnsTheOldOne(): void
    {
        $manager = $this->manager();
        $existing = $manager->save($this->entity('Before'));

        self::assertSame($existing, $manager->save($this->entity('After'), $existing));
    }

    /**
     * An edit form that was not asked for a new upload sends nothing for the file field, and
     * "nothing" must not wipe the file already stored.
     */
    public function testAnAbsentFileValueDoesNotClearTheStoredOne(): void
    {
        $this->database->getDBH()->exec('ALTER TABLE records ADD COLUMN doc TEXT');

        $table = (new Table('records', 'Records'))
            ->addField(new Field('title', 'Title', Field::TYPE_TEXT))
            ->addField(new Field('doc', 'Doc', Field::TYPE_FILE))
        ;
        $manager = new EntityManager($table, new DynamicRepository($this->database, $table));

        $existing = (new DynamicEntity())->set('title', 'Row')->set('doc', 'keep-me.pdf');
        $this->database->insertRecord('records', [
            'title' => 'Row', 'doc' => 'keep-me.pdf', 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $existing->setId((int)$this->database->getDBH()->lastInsertId());

        $submitted = (new DynamicEntity())->set('title', 'Renamed')->set('doc', null);
        $manager->save($submitted, $existing);

        self::assertSame('keep-me.pdf', $existing->get('doc'));
        self::assertSame('Renamed', $existing->get('title'));
    }

    public function testDeleteRemovesTheRow(): void
    {
        $manager = $this->manager();
        $entity = $manager->save($this->entity('Doomed'));

        $manager->delete($entity);

        self::assertCount(0, $this->database->getRecords('records'));
    }

    // --- EntityMapper edge cases ----------------------------------------------------------------

    public function testAnEmptyDateTimeStaysEmptyRatherThanBecomingFalse(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('created', 'Created', Field::TYPE_DATETIME))
        ;

        $entity = (new EntityMapper($table))->mapToEntity(['created' => null]);

        self::assertNull($entity->get('created'));
    }

    public function testAFileColumnWithNoStoredFileMapsToNull(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('doc', 'Doc', Field::TYPE_FILE))
        ;

        $entity = (new EntityMapper($table))->mapToEntity(['id' => 1, 'doc' => 'missing.pdf']);

        self::assertNull($entity->get('doc'), 'nothing on disk means nothing to hand back');
    }

    public function testAFileColumnOnAnUnsavedRowMapsToNull(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('doc', 'Doc', Field::TYPE_FILE))
        ;

        self::assertNull((new EntityMapper($table))->mapToEntity(['doc' => 'x.pdf'])->get('doc'));
    }

    // --- DatabaseManager --------------------------------------------------------------------------

    private function alterTable(DatabaseManager $manager, string $table, stdClass $field)
    {
        $method = new ReflectionMethod($manager, 'alterTable');
        $method->setAccessible(true);

        return $method->invoke($manager, $table, $field);
    }

    public function testAnUnsupportedFieldTypeIsReported(): void
    {
        $manager = new DatabaseManager(new RecordingDatabase());

        $field = new stdClass();
        $field->type = 'NoSuchType';
        $field->name = 'x';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Field type "NoSuchType" is not supported');

        $this->alterTable($manager, 'records', $field);
    }

    public function testAnAbsentColumnIsCreated(): void
    {
        $database = new RecordingDatabase();
        $database->answerSchemaQueries(['SHOW COLUMNS' => null]);
        $manager = new DatabaseManager($database);

        $field = new stdClass();
        $field->type = 'Text';
        $field->name = 'title';
        $field->length = 64;

        $this->alterTable($manager, 'records', $field);

        self::assertStringContainsString('ADD COLUMN `title` VARCHAR(64)', $database->getLastStatementNormalised());
    }

    public function testAnExistingColumnIsAltered(): void
    {
        $database = new RecordingDatabase();
        $database->answerSchemaQueries(['SHOW COLUMNS' => 'title']);
        $manager = new DatabaseManager($database);

        $field = new stdClass();
        $field->type = 'Text';
        $field->name = 'title';
        $field->length = 128;

        $this->alterTable($manager, 'records', $field);

        self::assertStringContainsString('CHANGE COLUMN `title` `title`', $database->getLastStatementNormalised());
    }

    public function testEnumValuesAreAppliedToTheColumn(): void
    {
        $database = new RecordingDatabase();
        $database->answerSchemaQueries(['SHOW COLUMNS' => null]);
        $manager = new DatabaseManager($database);

        $field = new stdClass();
        $field->type = 'Enum';
        $field->name = 'state';
        $field->values = ['draft', 'live'];

        $this->alterTable($manager, 'records', $field);

        self::assertStringContainsString("ENUM('draft','live')", $database->getLastStatementNormalised());
    }

    public function testADefaultIsAppliedToTheColumn(): void
    {
        $database = new RecordingDatabase();
        $database->answerSchemaQueries(['SHOW COLUMNS' => null]);
        $manager = new DatabaseManager($database);

        $field = new stdClass();
        $field->type = 'Text';
        $field->name = 'title';
        $field->default = 'untitled';

        $this->alterTable($manager, 'records', $field);

        self::assertStringContainsString("DEFAULT 'untitled'", $database->getLastStatementNormalised());
    }

    /**
     * A renamed column is found under its old name and altered rather than added beside it,
     * which would leave the data behind in the original.
     */
    public function testARenamedColumnIsMovedRatherThanRecreated(): void
    {
        $database = new RecordingDatabase();
        $database->answerSchemaQueries(['LIKE \'old_name\'' => 'old_name', 'SHOW COLUMNS' => null]);
        $manager = new DatabaseManager($database);

        $field = new stdClass();
        $field->type = 'Text';
        $field->name = 'new_name';
        $field->oldname = 'old_name';

        $this->alterTable($manager, 'records', $field);

        self::assertStringContainsString('CHANGE COLUMN `old_name` `new_name`', $database->getLastStatementNormalised());
    }
}
