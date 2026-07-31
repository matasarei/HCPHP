<?php

namespace Tests\Unit\DynamicDB;

use core\DatabaseSQL;
use core\Path;
use core\Template;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\File as FileEntity;
use DynamicDB\Entity\Table;
use DynamicDB\Field\Real;
use DynamicDB\Field\Text;
use DynamicDB\Manager\FileManager;
use DynamicDB\Mapper\EntityMapper;
use DynamicDB\Mapper\FileMapper;
use Html\Form\Button;
use Html\Form\Form;
use Html\Form\Input;
use Html\Form\Select;
use Html\Form\Textarea;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\AppConfig;
use Tests\Support\RecordingDatabase;
use UnexpectedValueException;

/**
 * The branches the main suites left behind: error paths, template overrides and the placement
 * clause the DDL builders only emit when a column is positioned.
 *
 * @covers \DynamicDB\Mapper\EntityMapper
 * @covers \DynamicDB\Mapper\FileMapper
 * @covers \DynamicDB\Manager\FileManager
 * @covers \DynamicDB\Field\Real
 * @covers \DynamicDB\Field\Text
 * @covers \Html\Form\Button
 * @covers \Html\Form\Form
 * @covers \Html\Form\Select
 * @covers \Html\Form\Textarea
 */
class RemainingBranchesTest extends TestCase
{
    private const DIR = 'application/templates/tests_branch';

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
    }

    protected function tearDown(): void
    {
        Template::purgeCaches();

        foreach ([self::DIR, 'cache/tests_branch_files', 'shared/dynamicdb'] as $dir) {
            $path = new Path($dir);

            if (is_dir((string)$path)) {
                $path->rmpath(true);
            }
        }

        $_SESSION = [];
    }

    private function templateNamed(string $name, string $body): Template
    {
        $path = new Path(sprintf('%s/%s.php', self::DIR, $name));
        $path->mkpath();
        file_put_contents((string)$path, $body);

        return new Template('tests_branch/' . $name);
    }

    // --- a template overrides the built-in rendering -----------------------------------------

    public function testSelectRendersThroughItsTemplate(): void
    {
        $select = new Select('choice');
        $select->setTemplate($this->templateNamed('select', 'SELECT:<?= $field->getName() ?>'));

        self::assertSame('SELECT:choice', $select->getHtml());
    }

    public function testTextareaRendersThroughItsTemplate(): void
    {
        $textarea = new Textarea('body');
        $textarea->setTemplate($this->templateNamed('textarea', 'AREA:<?= $field->getName() ?>'));

        self::assertSame('AREA:body', $textarea->getHtml());
    }

    public function testButtonRendersThroughItsTemplate(): void
    {
        $button = new Button('Send');

        self::assertSame($button, $button->setTemplate($this->templateNamed('button', 'BTN:<?= $button->getName() ?>')));
        self::assertSame('BTN:Send', $button->getHtml());
    }

    public function testFormRendersThroughAnAssignedTemplate(): void
    {
        $form = new Form(Form::METHOD_POST, null, null);

        self::assertSame($form, $form->setTemplate($this->templateNamed('form', 'FORM:<?= $form->getMethod() ?>')));
        self::assertSame('FORM:POST', $form->getHtml());
    }

    // --- Real and Text placement and clamps ----------------------------------------------------

    public function testRealPlacesTheColumnAfterAnother(): void
    {
        $database = new RecordingDatabase();
        $field = new Real($database, 'records', 'price');
        $field->setLength(8.2);
        $field->setAfter('id');

        $field->create();
        self::assertStringContainsString('AFTER `id`', $database->getLastStatementNormalised());

        $field->update();
        self::assertStringContainsString('AFTER `id`', $database->getLastStatementNormalised());
    }

    public function testTextPlacesTheColumnAfterAnother(): void
    {
        $database = new RecordingDatabase();
        $field = new Text($database, 'records', 'title');
        $field->setAfter('id');

        $field->create();
        self::assertStringContainsString('AFTER `id`', $database->getLastStatementNormalised());

        $field->update();
        self::assertStringContainsString('AFTER `id`', $database->getLastStatementNormalised());
    }

    public function testTextLengthIsReadable(): void
    {
        $field = new Text(new RecordingDatabase(), 'records', 'title');
        $field->setLength(64);

        self::assertSame(64, $field->getLength());
    }

    public function testRealLengthAndDefaultAreReadable(): void
    {
        $field = new Real(new RecordingDatabase(), 'records', 'price');
        $field->setLength(8.2);

        // Lossy on purpose-by-accident: the length is stored as the DECIMAL spec "8,2", and
        // floatval() of that keeps only the digits before the comma. Pinned as it is because
        // nothing reads it; a caller wanting the precision back should not use this.
        self::assertSame(8.0, $field->getLength());
        self::assertSame(0, $field->getDefault());
    }

    /**
     * MySQL caps DECIMAL at 65 digits with at most 30 after the point; each part is clamped
     * where it is too long, and the caller is told.
     */
    public function testRealClampsAnOverLongIntegerPart(): void
    {
        $database = new RecordingDatabase();
        $field = new Real($database, 'records', 'price');

        @$field->setLength(300.2);
        $field->create();

        self::assertStringContainsString('DECIMAL(255,', $database->getLastStatementNormalised());
    }

    // --- EntityMapper file resolution ------------------------------------------------------------

    private function fileTable(): Table
    {
        return (new Table('records', 'Records'))->addField(new Field('doc', 'Doc', Field::TYPE_FILE));
    }

    /**
     * An optional file field that was simply not filled in leaves the stored value alone
     * rather than raising.
     */
    public function testAnEmptyUploadResolvesToNull(): void
    {
        $entity = (new EntityMapper($this->fileTable()))->mapToEntity([
            'id' => 1,
            'doc' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
        ]);

        self::assertNull($entity->get('doc'));
    }

    public function testAFailedUploadIsReported(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new EntityMapper($this->fileTable()))->mapToEntity([
            'id' => 1,
            'doc' => ['name' => 'a.txt', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0],
        ]);
    }

    /**
     * A stored filename resolves to a File carrying what is actually on disk.
     */
    public function testAStoredFileResolvesToAFileEntity(): void
    {
        $stored = new Path('shared/dynamicdb/7/doc.txt');
        $stored->mkpath();
        file_put_contents((string)$stored, 'contents');

        $entity = (new EntityMapper($this->fileTable()))->mapToEntity(['id' => 7, 'doc' => 'doc.txt']);
        $file = $entity->get('doc');

        self::assertInstanceOf(FileEntity::class, $file);
        self::assertSame('doc.txt', $file->getName());
        self::assertSame(8, $file->getSize());
        self::assertSame('text/plain', $file->getType());
    }

    public function testFileMapperIsReadOnly(): void
    {
        $this->expectException(RuntimeException::class);

        (new FileMapper())->mapFromEntity(new FileEntity('a', null, '/tmp/a', 1));
    }

    // --- FileManager ------------------------------------------------------------------------------

    public function testDeletingFilesSkipsFieldsThatHoldNoFile(): void
    {
        $table = (new Table('records', 'Records'))
            ->addField(new Field('title', 'Title', Field::TYPE_TEXT))
            ->addField(new Field('doc', 'Doc', Field::TYPE_FILE))
        ;

        $entity = (new DynamicEntity())->set('title', 'not a file')->set('doc', null);
        $entity->setId(1);

        (new FileManager($table))->deleteFiles($entity);

        $this->addToAssertionCount(1);
    }

    public function testDeletingFilesRemovesTheStoredOne(): void
    {
        $stored = new Path('shared/dynamicdb/3/doc.txt');
        $stored->mkpath();
        file_put_contents((string)$stored, 'x');

        $table = $this->fileTable();
        $entity = (new DynamicEntity())->set(
            'doc',
            new FileEntity('doc.txt', 'text/plain', (string)$stored, 1)
        );
        $entity->setId(3);

        (new FileManager($table))->deleteFiles($entity);

        self::assertFileDoesNotExist((string)$stored);
    }

    /**
     * The default mover is move_uploaded_file(), which refuses anything that did not arrive
     * over HTTP POST. That refusal is what makes it safe, and it is why every other test here
     * injects its own mover.
     */
    public function testTheDefaultMoverRefusesAFileThatWasNotUploaded(): void
    {
        $source = new Path('cache/tests_branch_files/source.txt');
        $source->mkpath();
        file_put_contents((string)$source, 'GIF89a');

        $entity = (new DynamicEntity())->set(
            'doc',
            new FileEntity('source.gif', 'image/gif', (string)$source, 6, true)
        );
        $entity->setId(9);

        @(new FileManager($this->fileTable()))->saveFiles($entity);

        self::assertFileDoesNotExist((string)new Path('shared/dynamicdb/9/doc.gif'));
    }
}
