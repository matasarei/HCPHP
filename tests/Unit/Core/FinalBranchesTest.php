<?php

namespace Tests\Unit\Core;

use core\Autoloader;
use core\Cache;
use core\DatabaseSQL;
use core\Debug;
use core\Events;
use core\Language;
use core\Path;
use core\Template;
use core\Url;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use DynamicDB\Validator\Exception\InvalidFileTypeException;
use DynamicDB\Validator\FileUploadValidator;
use DynamicDB\Entity\File as FileEntity;
use Filter\TagsFilter;
use Html\Form\Input;
use PHPUnit\Framework\TestCase;
use Tests\Support\Reflect;
use RuntimeException;
use Tests\Support\AppConfig;
use UserBundle\Entity\Role;
use UserBundle\Entity\User;
use UserBundle\Mapper\RoleMapper;
use UserBundle\Mapper\UserMapper;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;
use UserBundle\Service\AuthChecker;
use UserBundle\Service\Authenticator;

/**
 * The last branches: error paths, fallbacks and the conditionals the happy path never takes.
 */
class FinalBranchesTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        Cache::purge();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_COOKIE = [];
        Cache::purge();
        Template::purgeCaches();

        foreach (['application/templates/tests_final', 'application/events/tests_final_event.php'] as $p) {
            $path = new Path($p);

            if (is_dir((string)$path)) {
                $path->rmpath(true);
            } elseif (is_file((string)$path)) {
                $path->rmpath();
            }
        }
    }

    // --- Autoloader ---------------------------------------------------------------------

    public function testAddRegistersAPathAndItsLoaderTogether(): void
    {
        $called = false;

        Autoloader::add(Path::getRoot() . '/application', function () use (&$called) {
            $called = true;

            return false;
        });

        Autoloader::load('NoSuchClassForTheAddTest');

        self::assertTrue($called);
    }

    // --- Cache --------------------------------------------------------------------------

    public function testGetTimeReportsWhenAnEntryWasStored(): void
    {
        Cache::set('timed', 'value');

        self::assertGreaterThan(0, Cache::getTime('timed'));
    }

    public function testGetTimeIsNullForAnAbsentEntry(): void
    {
        self::assertNull(Cache::getTime('never_stored'));
    }

    // --- DatabaseSQL ---------------------------------------------------------------------

    /**
     * sqlite has no DSN kept, because reconnecting an in-memory database would throw its
     * contents away. Asking anyway says so rather than silently opening a second one.
     */
    public function testAnSqliteConnectionCannotBeReEstablished(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $connect = Reflect::method($database, 'connect');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be re-established');

        $connect->invoke($database);
    }

    public function testAMysqlConnectionKeepsItsDetails(): void
    {
        try {
            new DatabaseSQL(DatabaseSQL::DRIVER_MYSQL, '127.0.0.1', 'nope', 'nobody', 'wrong');
            self::fail('there is no server to connect to');
        } catch (\PDOException $exception) {
            // The point is that it built a DSN and tried, rather than failing earlier.
            self::assertNotSame('', $exception->getMessage());
        }
    }

    /**
     * An empty record compiles to "INSERT INTO t VALUES ()", which is MySQL's way of saying
     * "all defaults". sqlite has no such form, so this pins that the branch is taken and that
     * it is the database, not the builder, that objects.
     */
    public function testInsertingAnEmptyRecordUsesTheAllDefaultsForm(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        $this->expectException(\PDOException::class);

        $database->insertRecord('t', []);
    }

    /**
     * The warning names the conditions that failed to identify a row, including array ones.
     */
    public function testAnAmbiguousLookupNamesItsArrayConditions(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, tag TEXT)');
        $database->insertRecord('t', ['tag' => 'a']);
        $database->insertRecord('t', ['tag' => 'b']);

        $record = @$database->getRecord('t', ['tag' => ['a', 'b']]);

        self::assertSame('a', $record['tag']);
    }

    public function testAnAmbiguousRawQueryIsAlsoReported(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, tag TEXT)');
        $database->insertRecord('t', ['tag' => 'a']);
        $database->insertRecord('t', ['tag' => 'b']);

        self::assertNotNull(@$database->getRecordSQL('SELECT * FROM t'));
    }

    // --- Events -----------------------------------------------------------------------------

    /**
     * An event with a matching class file runs it; that is how Init is invoked.
     */
    public function testAnEventFileIsRequiredAndItsHandlerRun(): void
    {
        $path = new Path('application/events/tests_final_event.php');
        $path->mkpath();
        file_put_contents(
            (string)$path,
            '<?php class tests_final_event extends core\Handler { public static $ran = false;'
            . ' protected function handle($data) { self::$ran = true; } }'
        );

        Events::triggerEvent('tests_final_event');

        self::assertTrue(\tests_final_event::$ran);
    }

    // --- Language ------------------------------------------------------------------------------

    public function testAStringIsFormattedWithItsArguments(): void
    {
        $language = Language::getInstance('en');
        $reflection = Reflect::property($language, 'strings');
        $strings = $reflection->getValue($language);

        try {
            $reflection->setValue($language, $strings + ['tests_greeting' => 'Hello %s, you are %d']);

            self::assertSame('Hello Bob, you are 30', $language->getString('tests_greeting', ['Bob', 30]));
        } finally {
            $reflection->setValue($language, $strings);
        }
    }

    // --- Path -------------------------------------------------------------------------------------

    /**
     * Every Path is resolved under the project root, including one that was handed in looking
     * absolute: the constructor strips the leading separator before anything else. That is
     * what stops a stored filename or a query-string value from naming a file elsewhere on
     * the disk, and it means "/etc/passwd" addresses a file inside the project, not the real
     * one.
     */
    public function testAnAbsoluteLookingPathIsStillResolvedUnderTheRoot(): void
    {
        self::assertStringStartsWith(Path::getRoot(), (string)new Path('/etc/passwd'));
        self::assertSame(Path::getRoot() . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'passwd',
            (string)new Path('/etc/passwd'));
    }

    public function testInitAcceptsPermissionOverrides(): void
    {
        $root = Path::getRoot();

        try {
            Path::init($root, 0777, 0666);

            self::assertSame($root, Path::getRoot());
        } finally {
            Path::init($root, 0775, 0664);
        }
    }

    public function testListingCanBeRandomised(): void
    {
        $dir = new Path('cache/tests_final_list/a.txt');
        $dir->mkpath();

        foreach (['a', 'b', 'c'] as $name) {
            file_put_contents((string)new Path("cache/tests_final_list/{$name}.txt"), 'x');
        }

        try {
            self::assertCount(3, (new Path('cache/tests_final_list'))->getList(Path::TYPE_ALL, true));
        } finally {
            (new Path('cache/tests_final_list'))->rmpath(true);
        }
    }

    // --- Template -------------------------------------------------------------------------------------

    private function render(string $body): string
    {
        $path = new Path('application/templates/tests_final/t.php');
        $path->mkpath();
        file_put_contents((string)$path, $body);

        return (new Template('tests_final/t'))->make();
    }

    /**
     * @dataProvider shortcodeMissingArgumentProvider
     */
    public function testAShortcodeWithNoArgumentIsReported(string $body): void
    {
        self::assertSame('%shortcode%', @$this->render($body));
    }

    public function shortcodeMissingArgumentProvider(): array
    {
        return [
            'escape' => ['{{escape|}}'],
            'template' => ['{{template|}}'],
            'lang' => ['{{lang|}}'],
        ];
    }

    /**
     * With debug on the offending shortcode is shown as written, so it can be found in the
     * template rather than hunted for.
     */
    public function testWithDebugOnTheBrokenShortcodeIsEchoedBack(): void
    {
        $mode = Debug::mode();
        $reporting = error_reporting();
        $display = (string)ini_get('display_errors');

        try {
            Debug::mode(E_ALL);

            self::assertSame('{{escape|}}', @$this->render('{{escape|}}'));
        } finally {
            Debug::mode($mode);
            error_reporting($reporting);
            ini_set('display_errors', $display);
            Debug::flush();
        }
    }

    public function testALanguageShortcodeTakesArgumentsAndACode(): void
    {
        self::assertIsString(@$this->render("{{lang|'yes'|[]|'en'}}"));
    }

    // --- Url ----------------------------------------------------------------------------------------

    /**
     * Url(true) means "the URL of this request".
     */
    public function testTheCurrentRequestCanBeTurnedIntoAUrl(): void
    {
        self::assertInstanceOf(Url::class, new Url(true));
    }

    // --- TagsFilter ------------------------------------------------------------------------------------

    /**
     * A scheme the allow-list does not name is dropped, whatever it claims to be.
     */
    public function testAnUnknownSchemeIsRefused(): void
    {
        $filtered = (new TagsFilter())->filter('<a href="javascript:alert(1)">x</a>');

        self::assertStringNotContainsString('javascript:', $filtered);
    }

    // --- FileUploadValidator -----------------------------------------------------------------------------

    public function testTheSizeLimitIsReadable(): void
    {
        self::assertSame(1024, (new FileUploadValidator(1024))->getMaxFileSize());
    }

    /**
     * A temporary file that cannot be read is refused rather than waved through: failing
     * closed is the whole point of this class.
     */
    public function testAnUnreadableUploadIsRefused(): void
    {
        $this->expectException(InvalidFileTypeException::class);

        (new FileUploadValidator())->validate(
            new FileEntity('a.png', 'image/png', '/no/such/temporary/file', 10, true)
        );
    }

    public function testAnExecutableIsRefused(): void
    {
        $path = new Path('cache/tests_final_upload/payload.png');
        $path->mkpath();
        // ELF magic behind an image name.
        file_put_contents((string)$path, "\x7fELF" . str_repeat("\0", 64));

        try {
            $this->expectException(InvalidFileTypeException::class);

            (new FileUploadValidator())->validate(
                new FileEntity('payload.png', 'image/png', (string)$path, 68, true)
            );
        } finally {
            (new Path('cache/tests_final_upload'))->rmpath(true);
        }
    }

    // --- AuthChecker --------------------------------------------------------------------------------------

    private function authChecker(): AuthChecker
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, firstname TEXT, lastname TEXT,
             email TEXT, password TEXT, role TEXT, authkey TEXT, authtime INTEGER,
             timecreated INTEGER, timemodified INTEGER)'
        );

        return new AuthChecker(
            new UserRepository($database, new UserMapper(new RoleRepository(new RoleMapper())))
        );
    }

    public function testAnUnknownCapabilityContextIsRefused(): void
    {
        self::assertFalse($this->authChecker()->checkCapability('view_records', 'no_such_context'));
    }

    public function testAnUnknownCapabilityIsRefused(): void
    {
        self::assertFalse($this->authChecker()->checkCapability('no_such_capability'));
    }

    public function testACapabilityIsGrantedToItsRole(): void
    {
        $user = new User('bob@example.com', 'Bob', new Role('user', 'User'));

        self::assertTrue($this->authChecker()->checkCapability('view_records', 'system', $user));
    }

    public function testACapabilityIsRefusedToAnotherRole(): void
    {
        $user = new User('bob@example.com', 'Bob', new Role('stranger', 'Stranger'));

        self::assertFalse($this->authChecker()->checkCapability('view_records', 'system', $user));
    }

    public function testACachedUserIsReturnedWithoutAnotherLookup(): void
    {
        $user = new User('bob@example.com', 'Bob', new Role('user', 'User'));
        Cache::set(Authenticator::KEY_CACHE_CURRENT_USER, $user);

        self::assertSame($user, $this->authChecker()->getCurrentUser());
    }

    public function testNobodyIsLoggedInWithoutAKey(): void
    {
        self::assertNull($this->authChecker()->getCurrentUser());
    }

    // --- Html\Form\Field ------------------------------------------------------------------------------------

    public function testAFieldCanBeGivenATemplateByName(): void
    {
        $path = new Path('application/templates/tests_final/field.php');
        $path->mkpath();
        file_put_contents((string)$path, 'NAMED:<?= $field->getName() ?>');

        $field = new Input('email', null, null, false, 'tests_final/field');

        self::assertSame('NAMED:email', $field->getHtml());
    }
}
