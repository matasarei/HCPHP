<?php

namespace Tests\Unit\DynamicDB;

use core\DatabaseSQL;
use core\Url;
use DynamicDB\Builder\FormBuilder;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Repository\TableRepository;
use Html\Form\Button;
use Html\Form\Input;
use Html\Form\Select;
use Html\Form\Textarea;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppConfig;

/**
 * FormBuilder turns a table definition into the form a user edits records with, choosing a
 * widget per field type.
 */
class FormBuilderTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    public static function setUpBeforeClass(): void
    {
        AppConfig::ensure();
    }

    public static function tearDownAfterClass(): void
    {
        AppConfig::release();
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $this->database->getDBH()->exec(
            'CREATE TABLE records (id INTEGER PRIMARY KEY AUTOINCREMENT, text_field TEXT,
             timecreated INTEGER, timemodified INTEGER)'
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function builder(Table $table): FormBuilder
    {
        return new FormBuilder(
            $table,
            new DynamicRepositoryFactory($this->database, new TableRepository())
        );
    }

    private function tableWith(Field ...$fields): Table
    {
        $table = new Table('records', 'Records');

        foreach ($fields as $field) {
            $table->addField($field);
        }

        return $table;
    }

    /**
     * @return \Html\Form\Field
     */
    private function firstField(Table $table)
    {
        return $this->builder($table)->getEditForm()->getFields()[0];
    }

    // --- widget selection ------------------------------------------------------------------

    public function testTextFieldBecomesAnInput(): void
    {
        $field = $this->firstField($this->tableWith(new Field('title', 'Title', Field::TYPE_TEXT)));

        self::assertInstanceOf(Input::class, $field);
        self::assertSame('title', $field->getName());
    }

    /**
     * A long text column gets room to type in.
     */
    public function testLongTextFieldBecomesATextarea(): void
    {
        $definition = (new Field('body', 'Body', Field::TYPE_TEXT))->setLength(1024);

        self::assertInstanceOf(Textarea::class, $this->firstField($this->tableWith($definition)));
    }

    public function testMediumAndLongTextBecomeWysiwygTextareas(): void
    {
        foreach ([Field::TYPE_TEXT => null, 'MediumText' => null, 'LongText' => null] as $type => $ignored) {
            if ($type === Field::TYPE_TEXT) {
                continue;
            }

            $field = $this->firstField($this->tableWith(new Field('body', 'Body', $type)));

            self::assertInstanceOf(Textarea::class, $field, $type);
            self::assertSame('wysiwyg-field', $field->getAttribute('class'), $type);
        }
    }

    public function testIntegerFieldBecomesANumberInput(): void
    {
        $definition = (new Field('count', 'Count', Field::TYPE_INTEGER))->setLength(4);
        $field = $this->firstField($this->tableWith($definition));

        self::assertInstanceOf(Input::class, $field);
        self::assertSame('number', $field->getType());
        self::assertSame('0', $field->getAttribute('min'));
    }

    public function testRealFieldBecomesAFractionalNumberInput(): void
    {
        $definition = (new Field('price', 'Price', Field::TYPE_REAL))->setLength(10.5);
        $field = $this->firstField($this->tableWith($definition));

        self::assertSame('number', $field->getType());
        self::assertSame('0.1', $field->getAttribute('step'));
    }

    public function testBooleanFieldBecomesAYesNoSelect(): void
    {
        $field = $this->firstField($this->tableWith(new Field('active', 'Active', Field::TYPE_BOOLEAN)));

        self::assertInstanceOf(Select::class, $field);
        self::assertStringContainsString('<option', $field->getHtml());
    }

    public function testEnumFieldBecomesASelectOfItsValues(): void
    {
        $definition = (new Field('state', 'State', Field::TYPE_ENUM))->setValues(['draft', 'live']);
        $field = $this->firstField($this->tableWith($definition));

        self::assertInstanceOf(Select::class, $field);
        self::assertStringContainsString('draft', $field->getHtml());
        self::assertStringContainsString('live', $field->getHtml());
    }

    /**
     * An enum value written as %key% is a translation lookup rather than a literal.
     */
    public function testEnumPlaceholderValuesBecomeNamedOptions(): void
    {
        $definition = (new Field('state', 'State', Field::TYPE_ENUM))->setValues(['%draft%']);
        $html = $this->firstField($this->tableWith($definition))->getHtml();

        self::assertStringContainsString('state_draft', $html);
    }

    public function testFileFieldBecomesAFileInput(): void
    {
        $field = $this->firstField($this->tableWith(new Field('doc', 'Document', Field::TYPE_FILE)));

        self::assertInstanceOf(Input::class, $field);
        self::assertSame('file', $field->getType());
    }

    public function testDateTimeFieldBecomesALocalDateTimeInput(): void
    {
        $definition = (new Field('created', 'Created', Field::TYPE_DATETIME))->setFormat('Y-m-d H:i:s');
        $field = $this->firstField($this->tableWith($definition));

        self::assertSame('datetime-local', $field->getType());
    }

    public function testJsonFieldBecomesATaggedInput(): void
    {
        $field = $this->firstField($this->tableWith(new Field('meta', 'Meta', Field::TYPE_JSON)));

        self::assertSame('json-field', $field->getAttribute('class'));
    }

    public function testRelationFieldBecomesASelectOfTheRelatedRows(): void
    {
        $this->database->insertRecord('records', ['text_field' => 'Target', 'timecreated' => 1, 'timemodified' => 1]);

        $definition = (new Field('owner', 'Owner', Field::TYPE_RELATION))
            ->setTable('records')
            ->setField('text_field')
        ;

        $field = $this->firstField($this->tableWith($definition));

        self::assertInstanceOf(Select::class, $field);
        self::assertStringContainsString('Target', $field->getHtml());
    }

    public function testUnknownTypeFallsBackToAPlainInput(): void
    {
        $field = $this->firstField($this->tableWith(new Field('odd', 'Odd', 'NoSuchType')));

        self::assertInstanceOf(Input::class, $field);
        self::assertSame('text', $field->getType());
    }

    // --- the form as a whole -------------------------------------------------------------------

    public function testFormCarriesEveryFieldAndBothButtons(): void
    {
        $form = $this->builder($this->tableWith(
            new Field('title', 'Title', Field::TYPE_TEXT),
            new Field('count', 'Count', Field::TYPE_INTEGER)
        ))->getEditForm();

        self::assertCount(2, $form->getFields());
        self::assertCount(2, $form->getButtons());
    }

    public function testTheFirstButtonSubmits(): void
    {
        $form = $this->builder($this->tableWith(new Field('a', 'A', Field::TYPE_TEXT)))->getEditForm();

        self::assertSame(Button::TYPE_SUBMIT, $form->getButtons()[0]->getType());
    }

    public function testTheCancelButtonPointsAtTheGivenUrl(): void
    {
        $form = $this->builder($this->tableWith(new Field('a', 'A', Field::TYPE_TEXT)))
            ->getEditForm(null, new Url('records'))
        ;

        self::assertStringContainsString('records', $form->getButtons()[1]->getUrl());
    }

    /**
     * A field with no configured default has to be filled in.
     */
    public function testFieldsWithoutADefaultAreRequired(): void
    {
        $optional = (new Field('opt', 'Optional', Field::TYPE_TEXT))->setDefault('x');
        $form = $this->builder($this->tableWith(
            new Field('req', 'Required', Field::TYPE_TEXT),
            $optional
        ))->getEditForm();

        self::assertTrue($form->getFields()[0]->isRequired());
        self::assertFalse($form->getFields()[1]->isRequired());
    }

    public function testEditingAnEntityPreFillsTheFields(): void
    {
        $entity = (new DynamicEntity())->set('title', 'Existing');
        $table = $this->tableWith(new Field('title', 'Title', Field::TYPE_TEXT));

        $form = $this->builder($table)->getEditForm($entity);

        self::assertStringContainsString('Existing', $form->getFields()[0]->getHtml());
    }

    public function testWithoutAnEntityTheConfiguredDefaultIsUsed(): void
    {
        $definition = (new Field('title', 'Title', Field::TYPE_TEXT))->setDefault('Preset');

        self::assertStringContainsString('Preset', $this->firstField($this->tableWith($definition))->getHtml());
    }

    public function testAnEmptyTableStillProducesAUsableForm(): void
    {
        $form = $this->builder(new Table('records', 'Records'))->getEditForm();

        self::assertCount(0, $form->getFields());
        self::assertCount(2, $form->getButtons());
    }
}
