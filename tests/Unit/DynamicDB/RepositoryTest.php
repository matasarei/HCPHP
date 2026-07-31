<?php

namespace Tests\Unit\DynamicDB;

use core\Cache;
use core\Collection;
use core\DatabaseSQL;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Mapper\EntityMapper;
use DynamicDB\Repository\DynamicRepository;
use DynamicDB\Repository\TableRepository;
use PHPUnit\Framework\TestCase;
use RecordsQueryBuilder;
use RuntimeException;

/**
 * @covers \DynamicDB\Repository\DynamicRepository
 * @covers \DynamicDB\Repository\TableRepository
 * @covers \DynamicDB\Factory\DynamicRepositoryFactory
 * @covers \DynamicDB\Mapper\EntityMapper
 * @covers \core\Repository
 */
class RepositoryTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $this->database->getDBH()->exec(
            'CREATE TABLE records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                count INTEGER,
                active INTEGER,
                timecreated INTEGER,
                timemodified INTEGER
            )'
        );

        Cache::purge();
    }

    protected function tearDown(): void
    {
        Cache::purge();
    }

    private function table(): Table
    {
        return (new Table('records', 'Records'))
            ->addField(new Field('title', 'Title', Field::TYPE_TEXT))
            ->addField(new Field('count', 'Count', Field::TYPE_INTEGER))
            ->addField(new Field('active', 'Active', Field::TYPE_BOOLEAN))
        ;
    }

    private function repository(): DynamicRepository
    {
        return new DynamicRepository($this->database, $this->table());
    }

    private function entity(string $title, int $count = 1): DynamicEntity
    {
        return (new DynamicEntity())->set('title', $title)->set('count', $count)->set('active', 1);
    }

    // --- save / get / remove ----------------------------------------------------------------

    public function testSavingANewEntityAssignsAnId(): void
    {
        $entity = $this->entity('First');

        self::assertNull($entity->getId());

        $this->repository()->save($entity);

        self::assertNotNull($entity->getId());
    }

    public function testSavedEntityCanBeReadBack(): void
    {
        $repository = $this->repository();
        $entity = $this->entity('First', 7);
        $repository->save($entity);

        $loaded = $repository->get($entity->getId());

        self::assertInstanceOf(DynamicEntity::class, $loaded);
        self::assertSame('First', $loaded->get('title'));
        self::assertSame(7, (int)$loaded->get('count'));
    }

    public function testGetReturnsNullForAnUnknownId(): void
    {
        self::assertNull($this->repository()->get(999));
    }

    public function testSavingAnEntityThatHasAnIdUpdatesIt(): void
    {
        $repository = $this->repository();
        $entity = $this->entity('Before');
        $repository->save($entity);
        $id = $entity->getId();

        $entity->set('title', 'After');
        $repository->save($entity);

        self::assertSame($id, $entity->getId(), 'no second row');
        self::assertSame('After', $repository->get($id)->get('title'));
        self::assertCount(1, $repository->find());
    }

    public function testRemoveDeletesTheRow(): void
    {
        $repository = $this->repository();
        $entity = $this->entity('Doomed');
        $repository->save($entity);

        $repository->remove($entity);

        self::assertNull($repository->get($entity->getId()));
    }

    // --- find -------------------------------------------------------------------------------

    public function testFindReturnsEveryRow(): void
    {
        $repository = $this->repository();
        $repository->save($this->entity('A'));
        $repository->save($this->entity('B'));

        $found = $repository->find();

        self::assertInstanceOf(Collection::class, $found);
        self::assertCount(2, $found);
    }

    public function testFindAppliesConditions(): void
    {
        $repository = $this->repository();
        $repository->save($this->entity('Match'));
        $repository->save($this->entity('Other'));

        self::assertCount(1, $repository->find(['title' => 'Match']));
    }

    public function testFindAppliesALimit(): void
    {
        $repository = $this->repository();
        $repository->save($this->entity('A'));
        $repository->save($this->entity('B'));
        $repository->save($this->entity('C'));

        self::assertCount(2, $repository->find([], ['limit' => 2]));
    }

    public function testFindOneReturnsTheFirstMatch(): void
    {
        $repository = $this->repository();
        $repository->save($this->entity('Only'));

        self::assertSame('Only', $repository->findOne(['title' => 'Only'])->get('title'));
    }

    public function testFindOneReturnsNullWhenNothingMatches(): void
    {
        self::assertNull($this->repository()->findOne(['title' => 'absent']));
    }

    public function testFindOnAnEmptyTableIsAnEmptyCollection(): void
    {
        self::assertCount(0, $this->repository()->find());
    }

    // --- findWithQuery -----------------------------------------------------------------------

    public function testFindWithQueryRunsTheBuildersStatement(): void
    {
        $repository = $this->repository();
        $repository->save($this->entity('Findable'));
        $repository->save($this->entity('Other'));

        $found = $repository->findWithQuery(new RecordsQueryBuilder($this->table(), 'Find'));

        self::assertCount(1, $found);
        self::assertSame('Findable', $found->offsetGet(0)->get('title'));
    }

    public function testFindWithQueryWithNoSearchTermReturnsEverything(): void
    {
        $repository = $this->repository();
        $repository->save($this->entity('A'));
        $repository->save($this->entity('B'));

        self::assertCount(2, $repository->findWithQuery(new RecordsQueryBuilder($this->table(), '')));
    }

    public function testRepositoryExposesItsTable(): void
    {
        self::assertSame('records', $this->repository()->getTable()->getName());
    }

    // --- EntityMapper ------------------------------------------------------------------------

    public function testMapperFillsMissingColumnsFromTheFieldDefaults(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField((new Field('title', 'Title', Field::TYPE_TEXT))->setDefault('untitled'))
        ;

        $entity = (new EntityMapper($table))->mapToEntity(['id' => 1]);

        self::assertSame('untitled', $entity->get('title'));
    }

    public function testMapperCoercesBooleansToOneOrZero(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('active', 'Active', Field::TYPE_BOOLEAN))
        ;
        $mapper = new EntityMapper($table);

        self::assertSame(1, $mapper->mapToEntity(['active' => 'yes'])->get('active'));
        self::assertSame(0, $mapper->mapToEntity(['active' => ''])->get('active'));
    }

    public function testMapperTurnsANonNumericDateIntoATimestamp(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('created', 'Created', Field::TYPE_DATETIME))
        ;

        $entity = (new EntityMapper($table))->mapToEntity(['created' => '2026-07-31 10:00:00']);

        self::assertSame(strtotime('2026-07-31 10:00:00'), $entity->get('created'));
    }

    public function testMapperLeavesANumericDateAlone(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('created', 'Created', Field::TYPE_DATETIME))
        ;

        $entity = (new EntityMapper($table))->mapToEntity(['created' => 1700000000]);

        self::assertSame(1700000000, $entity->get('created'));
    }

    public function testMapperCarriesIdAndTimestamps(): void
    {
        $entity = (new EntityMapper($this->table()))->mapToEntity([
            'id' => 5,
            'timecreated' => 100,
            'timemodified' => 200,
        ]);

        self::assertSame(5, $entity->getId());
        self::assertSame(100, $entity->getTimeCreated());
        self::assertSame(200, $entity->getTimeModified());
    }

    public function testMapFromEntityProducesEveryColumn(): void
    {
        $mapper = new EntityMapper($this->table());
        $entity = $this->entity('Row');
        $entity->setId(3);

        $data = $mapper->mapFromEntity($entity);

        self::assertSame('Row', $data['title']);
        self::assertSame(3, $data['id']);
        self::assertArrayHasKey('timecreated', $data);
        self::assertArrayHasKey('timemodified', $data);
    }

    public function testMapFromEntityOmitsTheIdWhenThereIsNone(): void
    {
        $data = (new EntityMapper($this->table()))->mapFromEntity($this->entity('Row'));

        self::assertArrayNotHasKey('id', $data);
    }

    // --- TableRepository and the factory ---------------------------------------------------------

    public function testTableRepositoryReadsTheShippedConfiguration(): void
    {
        $repository = new TableRepository();

        self::assertNotNull($repository->get('records'));
        self::assertSame('records', $repository->get('records')->getName());
    }

    public function testTableRepositoryReturnsNullForAnUnknownTable(): void
    {
        self::assertNull((new TableRepository())->get('no_such_table'));
    }

    public function testTableRepositoryListsEveryTable(): void
    {
        self::assertGreaterThan(0, count((new TableRepository())->find()));
    }

    public function testTableRepositoryIsReadOnly(): void
    {
        $repository = new TableRepository();

        $this->expectException(RuntimeException::class);

        $repository->save(new Table('a', 'A'));
    }

    public function testTableRepositoryRemoveIsAlsoRefused(): void
    {
        $repository = new TableRepository();

        $this->expectException(RuntimeException::class);

        $repository->remove(new Table('a', 'A'));
    }

    public function testFactoryBuildsARepositoryForAConfiguredTable(): void
    {
        $factory = new DynamicRepositoryFactory($this->database, new TableRepository());

        self::assertInstanceOf(DynamicRepository::class, $factory->getRepository('records'));
    }

    public function testFactoryReusesTheSameRepository(): void
    {
        $factory = new DynamicRepositoryFactory($this->database, new TableRepository());

        self::assertSame($factory->getRepository('records'), $factory->getRepository('records'));
    }

    /**
     * The message used to interpolate the null table it had just failed to find, so it named
     * nothing and warned about the null on top.
     */
    public function testFactoryNamesTheTableItCouldNotFind(): void
    {
        $factory = new DynamicRepositoryFactory($this->database, new TableRepository());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing configuration for table no_such_table');

        $factory->getRepository('no_such_table');
    }
}
