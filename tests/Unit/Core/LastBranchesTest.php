<?php

namespace Tests\Unit\Core;

use core\Application;
use core\Debug;
use core\Path;
use core\Template;
use core\Url;
use core\View;
use DateTime;
use DynamicDB\Builder\FormBuilder;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\File as FileEntity;
use DynamicDB\Entity\Table;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Field\Integer as IntegerField;
use DynamicDB\Field\Real;
use DynamicDB\Manager\DatabaseManager;
use DynamicDB\Repository\TableRepository;
use DynamicDB\Validator\Exception\InvalidFileTypeException;
use DynamicDB\Validator\FileUploadValidator;
use Filter\TagsFilter;
use PHPUnit\Framework\TestCase;
use Tests\Support\Reflect;
use ReflectionMethod;
use Tests\Support\AppConfig;
use Tests\Support\RecordingDatabase;

/**
 * The last reachable branches.
 */
class LastBranchesTest extends TestCase
{
    protected function tearDown(): void
    {
        Template::purgeCaches();

        foreach (['application/templates/tests_last', 'application/views/tests_last', 'cache/tests_last'] as $dir) {
            $path = new Path($dir);

            if (is_dir((string)$path)) {
                $path->rmpath(true);
            }
        }
    }

    // --- Path -------------------------------------------------------------------------------

    /**
     * When the root is itself relative, a path that already begins with it is returned as it
     * stands rather than having it prepended a second time.
     */
    public function testAPathAlreadyStartingAtTheRootIsNotPrefixedAgain(): void
    {
        $root = Path::getRoot();

        try {
            Path::init('application');

            self::assertSame('application/core', (string)new Path('application/core'));
        } finally {
            Path::init($root, 0775, 0664);
        }
    }

    /**
     * With no root configured the document root is used, which is what happens before
     * init() runs.
     */
    public function testTheDocumentRootIsUsedWhenNoRootWasSet(): void
    {
        $property = Reflect::property(Path::class, 'root');
        $root = $property->getValue();

        $previousDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;

        try {
            $property->setValue(null, null);
            $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';

            self::assertSame('/var/www/html', Path::getRoot());
        } finally {
            $property->setValue(null, $root);

            if ($previousDocumentRoot === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $previousDocumentRoot;
            }
        }
    }

    // --- Template ---------------------------------------------------------------------------

    public function testAnEchoShortcodeWithNothingToPrintIsReported(): void
    {
        $template = new Template('form/default');
        $info = (object)['file' => 'x', 'line' => 1];

        self::assertSame('%shortcode%', @$template->parseEcho([], $info));
    }

    // --- Url --------------------------------------------------------------------------------

    /**
     * With https configured, generated links use it rather than the scheme of the request
     * that happened to build them.
     */
    public function testHttpsIsUsedWhenTheApplicationIsConfiguredForIt(): void
    {
        $path = (string)new Path('application/config/default.json');
        $original = file_get_contents($path);

        try {
            $config = json_decode($original, true);
            $config['https'] = true;
            file_put_contents($path, json_encode($config));

            self::assertSame(Url::SCHEME_HTTPS, (new Url('a/b'))->getScheme());
        } finally {
            file_put_contents($path, $original);
        }
    }

    // --- View -------------------------------------------------------------------------------

    /**
     * A view built with no name takes the one the dispatcher settled on, which is how a
     * controller action renders its own page without naming it.
     */
    public function testAViewWithNoNameFollowsTheCurrentControllerAndAction(): void
    {
        $source = new Path('application/views/tests_last/page.php');
        $source->mkpath();
        file_put_contents((string)$source, 'DISPATCHED');

        $reflection = new \ReflectionClass(Application::class);
        $controller = Reflect::property($reflection->getName(), 'controllerName');
        $action = Reflect::property($reflection->getName(), 'actionName');

        $previous = [$controller->getValue(), $action->getValue()];

        try {
            $controller->setValue(null, 'tests_last');
            $action->setValue(null, 'page');

            self::assertStringContainsString('tests_last', (string)(new View())->getPath());
        } finally {
            $controller->setValue(null, $previous[0]);
            $action->setValue(null, $previous[1]);
        }
    }

    // --- Debug ------------------------------------------------------------------------------

    /**
     * init() installs the handlers that turn a PHP error into framework output; without it
     * nothing in this class is ever called.
     */
    public function testInitInstallsTheHandlers(): void
    {
        $mode = Debug::mode();
        $reporting = error_reporting();
        $display = (string)ini_get('display_errors');

        try {
            Debug::init(E_ALL);

            self::assertTrue(Debug::isOn());
            self::assertSame([Debug::class, 'errorHandler'], set_error_handler(null));
        } finally {
            restore_error_handler();
            restore_error_handler();
            restore_exception_handler();
            Debug::mode($mode);
            error_reporting($reporting);
            ini_set('display_errors', $display);
            Debug::flush();
        }
    }

    /**
     * On the command line an error is printed as it happens rather than only collected, so a
     * cron task shows its failure in the log it is already being watched through.
     */
    public function testAnErrorIsPrintedImmediatelyOnTheCommandLine(): void
    {
        $mode = Debug::mode();
        $reporting = error_reporting();
        $display = (string)ini_get('display_errors');
        $applicationMode = Application::getMode();

        try {
            Debug::mode(E_ALL);
            Application::setMode(Application::MODE_CLI);

            ob_start();
            Debug::errorHandler(E_USER_NOTICE, 'printed at once', __FILE__, 1);
            $printed = ob_get_clean();

            self::assertStringContainsString('printed at once', $printed);
        } finally {
            Application::setMode($applicationMode);
            Debug::mode($mode);
            error_reporting($reporting);
            ini_set('display_errors', $display);
            Debug::flush();
        }
    }

    // --- FormBuilder -------------------------------------------------------------------------

    private function builder(Table $table): FormBuilder
    {
        $database = new \core\DatabaseSQL(\core\DatabaseSQL::DRIVER_SQLITE);

        return new FormBuilder($table, new DynamicRepositoryFactory($database, new TableRepository()));
    }

    /**
     * @dataProvider dateTimeDefaultProvider
     */
    public function testADateTimeDefaultIsUnderstoodInEveryForm($default, string $expected): void
    {
        $field = (new Field('created', 'Created', Field::TYPE_DATETIME))
            ->setFormat('Y-m-d H:i:s')
            ->setDefault($default)
        ;
        $table = (new Table('records', 'Records'))->addField($field);

        $html = $this->builder($table)->getEditForm()->getFields()[0]->getHtml();

        self::assertStringContainsString($expected, $html);
    }

    public function dateTimeDefaultProvider(): array
    {
        return [
            'unix timestamp' => [1700000000, (new DateTime())->setTimestamp(1700000000)->format('Y-m-d\TH:i')],
            'formatted string' => ['2026-07-31 10:30:00', '2026-07-31T10:30'],
        ];
    }

    public function testAnEnumValueIsReadFromTheEntityBeingEdited(): void
    {
        $field = (new Field('state', 'State', Field::TYPE_ENUM))->setValues(['draft', 'live']);
        $table = (new Table('records', 'Records'))->addField($field);
        $entity = (new DynamicEntity())->set('state', 'live');

        $html = $this->builder($table)->getEditForm($entity)->getFields()[0]->getHtml();

        self::assertStringContainsString('<option value="live" selected="selected">', $html);
    }

    /**
     * getRelationDefault() is listed in the class docblock but nothing calls it; the relation
     * widget marks its selection through Select instead. Covered so the dead branch is at
     * least known to work if something starts using it.
     */
    public function testTheUnusedRelationDefaultHelperStillResolvesAKey(): void
    {
        $builder = $this->builder(new Table('records', 'Records'));
        $method = Reflect::method($builder, 'getRelationDefault');

        self::assertSame(7, $method->invoke($builder, [7 => 'match', 8 => 'other'], 'match'));
        self::assertSame(0, $method->invoke($builder, [7 => 'a'], 'nothing matches'));
    }

    // --- Field defaults -----------------------------------------------------------------------

    public function testIntegerAndRealReportTheirDefaults(): void
    {
        $database = new RecordingDatabase();

        self::assertSame(0, (new IntegerField($database, 'records', 'count'))->getDefault());
        self::assertSame(0, (new Real($database, 'records', 'price'))->getDefault());
    }

    /**
     * Integer and Real override prepareDefault() to skip the quoting a text default needs,
     * but neither calls it: their create() and update() interpolate the value straight into
     * the statement. Covered so the override is known to still return what it claims, since
     * nothing else would notice if it stopped.
     *
     * @dataProvider numericFieldProvider
     */
    public function testTheNumericDefaultIsPreparedUnquoted(string $class): void
    {
        $field = new $class(new RecordingDatabase(), 'records', 'value');
        $field->setDefault(5);

        $method = Reflect::method($field, 'prepareDefault');

        self::assertSame('5', (string)$method->invoke($field));
    }

    public function numericFieldProvider(): array
    {
        return [
            'integer' => [IntegerField::class],
            'real' => [Real::class],
        ];
    }

    /**
     * MySQL allows at most 30 digits after the decimal point, and the part that is too long
     * is the part that gets clamped.
     */
    public function testRealClampsAnOverLongFractionalPart(): void
    {
        $database = new RecordingDatabase();
        $field = new Real($database, 'records', 'price');

        @$field->setLength(12.4);
        $field->create();

        self::assertStringContainsString('DECIMAL(12,', $database->getLastStatementNormalised());
    }

    // --- DatabaseManager ------------------------------------------------------------------------

    /**
     * A table recreated after its row was already registered updates that row rather than
     * adding a second one for the same name.
     */
    public function testRecreatingATableUpdatesItsExistingRegistryRow(): void
    {
        $database = new RecordingDatabase();
        $database->getDBH()->exec(
            'CREATE TABLE dynamicdb (id INTEGER PRIMARY KEY AUTOINCREMENT, tablename TEXT,
             type TEXT, timecreated INTEGER, timemodified INTEGER)'
        );
        $database->insertRecord('dynamicdb', [
            'tablename' => 'widgets', 'type' => 'default', 'timecreated' => 1, 'timemodified' => 1,
        ]);

        $manager = new DatabaseManager($database);
        // createTable() makes a single confirming read: the table is there afterwards.
        $database->answerSchemaQueries(['SHOW TABLES' => 'widgets']);

        $create = Reflect::method($manager, 'createTable');
        $create->invoke($manager, 'widgets');

        self::assertCount(1, $database->getRecords('dynamicdb', ['tablename' => 'widgets']));
        self::assertGreaterThan(
            1,
            (int)$database->getRecord('dynamicdb', ['tablename' => 'widgets'])['timemodified']
        );
    }

    // --- FileUploadValidator ---------------------------------------------------------------------

    private function validatorMethod(string $name): ReflectionMethod
    {
        $method = Reflect::method(FileUploadValidator::class, $name);

        return $method;
    }

    /**
     * validate() checks readability before opening, so these guards only fire if the file
     * disappears between the two -- which is exactly the race they are there for.
     *
     * @dataProvider readingGuardProvider
     */
    public function testAFileThatCannotBeOpenedIsRefused(string $method): void
    {
        $this->expectException(InvalidFileTypeException::class);
        $this->expectExceptionMessage('File could not be read');

        $this->validatorMethod($method)->invoke(
            new FileUploadValidator(),
            new FileEntity('a.png', 'image/png', '/no/such/file/at/all', 1, true)
        );
    }

    public function readingGuardProvider(): array
    {
        return [
            'executable check' => ['assertNotExecutable'],
            'script scan' => ['assertContainsNoScript'],
        ];
    }

    /**
     * The scan reads in chunks and carries the tail of each over. A file whose length is an
     * exact multiple of the chunk size leaves the handle at the end without EOF being set, so
     * the next read comes back empty and the loop has to stop rather than spin.
     */
    public function testAFileEndingExactlyOnAChunkBoundaryIsScannedWithoutSpinning(): void
    {
        $path = new Path('cache/tests_last/aligned.gif');
        $path->mkpath();

        $gif = "GIF89a\x01\x00\x01\x00\x00\x00\x00";
        file_put_contents((string)$path, $gif . str_repeat("\0", FileUploadValidator::SCAN_CHUNK_SIZE - strlen($gif)));

        self::assertSame(FileUploadValidator::SCAN_CHUNK_SIZE, filesize((string)$path));

        $this->validatorMethod('assertContainsNoScript')->invoke(
            new FileUploadValidator(),
            new FileEntity('aligned.gif', 'image/gif', (string)$path, FileUploadValidator::SCAN_CHUNK_SIZE, true)
        );

        $this->addToAssertionCount(1);
    }

    // --- TagsFilter --------------------------------------------------------------------------------

    /**
     * A URL that is nothing but whitespace and control characters normalises to the empty
     * string, which names no destination, so the attribute goes.
     */
    public function testAnEmptyUrlAttributeIsDropped(): void
    {
        $filtered = (new TagsFilter())->filter("<a href=\"&#9;&#10; \">x</a>");

        self::assertStringNotContainsString('href=', $filtered);
        self::assertStringContainsString('x</a>', $filtered);
    }
}
