<?php

namespace Tests\Unit\DynamicDB;

use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;
use DynamicDB\Mapper\FieldMapper;
use DynamicDB\Mapper\TableMapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EntityAndMapperTest extends TestCase
{
    // --- Field ---------------------------------------------------------------------------

    public function testFieldCarriesItsDefinition(): void
    {
        $field = new Field('title', 'Title', Field::TYPE_TEXT);

        self::assertSame('title', $field->getName());
        self::assertSame('Title', $field->getDescription());
        self::assertSame(Field::TYPE_TEXT, $field->getType());
    }

    /**
     * The name doubles as the identity, so a field can be looked up by it.
     */
    public function testFieldIdIsItsName(): void
    {
        self::assertSame('title', (new Field('title', 'Title', Field::TYPE_TEXT))->getId());
    }

    public function testFieldOptionalsDefaultToNull(): void
    {
        $field = new Field('title', 'Title', Field::TYPE_TEXT);

        self::assertNull($field->getLength());
        self::assertNull($field->getTable());
        self::assertNull($field->getDefault());
        self::assertNull($field->getFormat());
        self::assertNull($field->getField());
        self::assertSame([], $field->getValues());
    }

    public function testFieldSettersAreFluentAndRoundTrip(): void
    {
        $field = (new Field('rel', 'Relation', Field::TYPE_RELATION))
            ->setLength(64)
            ->setTable('users')
            ->setDefault('none')
            ->setFormat('Y-m-d')
            ->setValues(['a', 'b'])
            ->setField('email')
        ;

        self::assertSame(64, $field->getLength());
        self::assertSame('users', $field->getTable());
        self::assertSame('none', $field->getDefault());
        self::assertSame('Y-m-d', $field->getFormat());
        self::assertSame(['a', 'b'], $field->getValues());
        self::assertSame('email', $field->getField());
    }

    // --- Table ---------------------------------------------------------------------------

    public function testTableCarriesItsNameAndTitle(): void
    {
        $table = new Table('records', 'Records');

        self::assertSame('records', $table->getName());
        self::assertSame('Records', $table->getTitle());
        self::assertCount(0, $table->getFields());
    }

    public function testTableCollectsFieldsInOrder(): void
    {
        $a = new Field('a', 'A', Field::TYPE_TEXT);
        $b = new Field('b', 'B', Field::TYPE_INTEGER);

        $table = (new Table('records', 'Records'))->addField($a)->addField($b);

        self::assertSame([$a, $b], $table->getFields()->getItems());
    }

    // --- File ----------------------------------------------------------------------------

    public function testFileCarriesItsMetadata(): void
    {
        $file = new File('report.pdf', 'application/pdf', '/tmp/x', 1024);

        self::assertSame('report.pdf', $file->getName());
        self::assertSame('application/pdf', $file->getType());
        self::assertSame('/tmp/x', $file->getPath());
        self::assertSame(1024, $file->getSize());
        self::assertFalse($file->isTemporary());
    }

    public function testFileTypeMayBeUnknown(): void
    {
        self::assertNull((new File('a', null, '/tmp/a', 1))->getType());
    }

    public function testTemporaryFileSaysSo(): void
    {
        self::assertTrue((new File('a', null, '/tmp/a', 1, true))->isTemporary());
    }

    /**
     * A file renders as its name, which is what a template shows.
     */
    public function testFileCastsToItsName(): void
    {
        self::assertSame('report.pdf', (string)new File('report.pdf', null, '/tmp/x', 1));
    }

    // --- DynamicEntity -------------------------------------------------------------------

    public function testDynamicEntityStampsItsTimes(): void
    {
        $entity = new DynamicEntity();

        self::assertGreaterThan(0, $entity->getTimeCreated());
        self::assertGreaterThan(0, $entity->getTimeModified());
    }

    public function testDynamicEntityStoresArbitraryFields(): void
    {
        $entity = new DynamicEntity();
        $entity->set('title', 'Hello');

        self::assertSame('Hello', $entity->get('title'));
    }

    public function testUnknownFieldReadsAsNull(): void
    {
        self::assertNull((new DynamicEntity())->get('never_set'));
    }

    public function testDynamicEntityMagicAccessors(): void
    {
        $entity = new DynamicEntity();
        $entity->title = 'Hello';

        self::assertSame('Hello', $entity->title);
    }

    /**
     * id is not a data field; it lives on the Entity base and has to be read from there or an
     * entity loaded from the database reports null for its own id.
     */
    public function testIdIsReadFromTheEntityBase(): void
    {
        $entity = new DynamicEntity();
        $entity->setId(7);

        self::assertSame(7, $entity->get('id'));
        self::assertSame(7, $entity->id);
    }

    public function testSettersAreFluent(): void
    {
        $entity = new DynamicEntity();

        self::assertSame($entity, $entity->set('a', 1));
        self::assertSame($entity, $entity->setTimeCreated(100));
        self::assertSame($entity, $entity->setTimeModified(200));
        self::assertSame(100, $entity->getTimeCreated());
        self::assertSame(200, $entity->getTimeModified());
    }

    // --- FieldMapper ---------------------------------------------------------------------

    public function testFieldMapperReadsTheRequiredKeys(): void
    {
        $field = (new FieldMapper())->mapToEntity([
            'name' => 'title',
            'desc' => 'Title',
            'type' => Field::TYPE_TEXT,
        ]);

        self::assertSame('title', $field->getName());
        self::assertSame('Title', $field->getDescription());
        self::assertSame(Field::TYPE_TEXT, $field->getType());
    }

    public function testFieldMapperReadsEveryOptionalKey(): void
    {
        $field = (new FieldMapper())->mapToEntity([
            'name' => 'rel',
            'desc' => 'Relation',
            'type' => Field::TYPE_RELATION,
            'table' => 'users',
            'length' => 32,
            'default' => 'x',
            'values' => ['a'],
            'format' => 'Y-m-d',
            'field' => 'email',
        ]);

        self::assertSame('users', $field->getTable());
        self::assertSame(32, $field->getLength());
        self::assertSame('x', $field->getDefault());
        self::assertSame(['a'], $field->getValues());
        self::assertSame('Y-m-d', $field->getFormat());
        self::assertSame('email', $field->getField());
    }

    public function testFieldMapperIsReadOnly(): void
    {
        $this->expectException(RuntimeException::class);

        (new FieldMapper())->mapFromEntity(new Field('a', 'A', Field::TYPE_TEXT));
    }

    // --- TableMapper ---------------------------------------------------------------------

    public function testTableMapperBuildsATableWithItsFields(): void
    {
        $table = (new TableMapper())->mapToEntity([
            'name' => 'records',
            'title' => 'Records',
            'fields' => [
                ['name' => 'title', 'desc' => 'Title', 'type' => Field::TYPE_TEXT],
                ['name' => 'count', 'desc' => 'Count', 'type' => Field::TYPE_INTEGER],
            ],
        ]);

        self::assertSame('records', $table->getName());
        self::assertSame('Records', $table->getTitle());
        self::assertCount(2, $table->getFields());
        self::assertSame('title', $table->getFields()->offsetGet(0)->getName());
    }

    public function testTableMapperAcceptsATableWithNoFields(): void
    {
        $table = (new TableMapper())->mapToEntity([
            'name' => 'empty',
            'title' => 'Empty',
            'fields' => [],
        ]);

        self::assertCount(0, $table->getFields());
    }

    public function testTableMapperIsReadOnly(): void
    {
        $this->expectException(RuntimeException::class);

        (new TableMapper())->mapFromEntity(new Table('a', 'A'));
    }
}
