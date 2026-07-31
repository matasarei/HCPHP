<?php

namespace Tests\Unit\Core;

use core\Application;
use core\Container;
use core\Path;
use core\Template;
use core\Url;
use core\View;
use DynamicDB\DynamicDbConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppConfig;
use UserBundle\Service\AuthChecker;
use UserBundle\Repository\UserRepository;
use ViewFactory;

/**
 * Application is mostly static request plumbing. What can be checked without a live request
 * is checked here; start(), stop() and processRequest() drive the whole dispatch and belong
 * to an HTTP-level test rather than this one.
 */
class ApplicationTest extends TestCase
{
    /**
     * @var string
     */
    private $mode;

    protected function setUp(): void
    {
        $this->mode = Application::getMode();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Application::setMode($this->mode);
        $_SESSION = [];
        Template::purgeCaches();
    }

    // --- mode ------------------------------------------------------------------------------

    public function testModeDefaultsToWeb(): void
    {
        self::assertSame(Application::MODE_WEB, Application::getMode());
    }

    /**
     * @dataProvider modeProvider
     */
    public function testModeCanBeSetToEachKnownValue(string $mode): void
    {
        Application::setMode($mode);

        self::assertSame($mode, Application::getMode());
    }

    public function modeProvider(): array
    {
        return [
            'web' => [Application::MODE_WEB],
            'api' => [Application::MODE_API],
            'cli' => [Application::MODE_CLI],
        ];
    }

    public function testAnUnknownModeIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Wrong app mode provided');

        Application::setMode('nonsense');
    }

    // --- container -------------------------------------------------------------------------

    public function testTheContainerIsShared(): void
    {
        self::assertInstanceOf(Container::class, Application::getContainer());
        self::assertSame(Application::getContainer(), Application::getContainer());
    }

    // --- request parameters -----------------------------------------------------------------

    /**
     * There is no request in a CLI process, so every server parameter falls back.
     */
    public function testServerParameterFallsBackWhenTheHeaderIsAbsent(): void
    {
        self::assertNull(Application::getServerParameter('NO_SUCH_HEADER'));
        self::assertSame('fallback', Application::getServerParameter('NO_SUCH_HEADER', 'fallback'));
    }

    public function testHostFallsBackToAnEmptyStringWithoutARequest(): void
    {
        self::assertSame('', Application::getHost());
    }

    public function testPortFallsBackToTheHttpDefault(): void
    {
        self::assertSame(80, Application::getPort());
    }

    public function testHttpsIsOffByDefault(): void
    {
        self::assertFalse(Application::isHttpsEnabled());
    }

    public function testRewriteDetectionReturnsABoolean(): void
    {
        self::assertIsBool(Application::isRewriteEnabled());
    }

    public function testCurrentPathIsEmptyWithoutARequest(): void
    {
        self::assertSame('', Application::getCurrentPath());
    }

    public function testControllerAndActionAreUnsetBeforeDispatch(): void
    {
        self::assertNull(Application::getControllerName());
        self::assertNull(Application::getActionName());
    }

    /**
     * Falls back to loopback when no forwarding header names a client.
     */
    public function testRemoteIpFallsBackToLoopback(): void
    {
        self::assertSame('127.0.0.1', Application::getRemoteIp());
    }

    public function testBackUrlIsNullWithoutAReferrer(): void
    {
        self::assertNull(Application::backUrl());
    }

    // --- upload limits ------------------------------------------------------------------------

    public function testMaxUploadFilesizeReportsTheSmallerLimit(): void
    {
        self::assertGreaterThan(0, Application::maxUploadFilesize());
    }

    public function testMaxUploadFilesizeCanReportTheRawIniValue(): void
    {
        self::assertIsString(Application::maxUploadFilesize(null, true));
    }

    /**
     * The parameter is a size string like "8M", and anything that is not one is refused
     * rather than written into the ini.
     */
    public function testMaxUploadFilesizeRefusesSomethingThatIsNotASize(): void
    {
        self::assertFalse(Application::maxUploadFilesize('not a size'));
    }

    /**
     * A well-formed size is accepted and a limit comes back. Whether it takes effect is
     * another matter: post_max_size and upload_max_filesize are PHP_INI_PERDIR, so the
     * ini_set() calls are silently ignored outside a php.ini or vhost. Worth knowing before
     * trusting this to raise a limit at runtime.
     */
    public function testMaxUploadFilesizeAcceptsAWellFormedSize(): void
    {
        self::assertIsNumeric(Application::maxUploadFilesize('8M'));
    }

    public function testMemoryLimitIsApplied(): void
    {
        $previous = ini_get('memory_limit');

        try {
            Application::setMemoryLimit(256);

            self::assertSame('256M', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    // --- controllers ---------------------------------------------------------------------------

    public function testAnUnknownControllerIsNull(): void
    {
        self::assertNull(Application::getController('no_such_controller'));
    }

    // --- View and ViewFactory ---------------------------------------------------------------------

    private function view(string $body = 'BODY'): string
    {
        $path = new Path('application/views/tests_view/page.php');
        $path->mkpath();
        file_put_contents((string)$path, $body);

        return 'tests_view/page';
    }

    private function removeViewFixture(): void
    {
        $dir = new Path('application/views/tests_view');

        if (is_dir((string)$dir)) {
            $dir->rmpath(true);
        }
    }

    public function testViewRendersInsideItsLayout(): void
    {
        $name = $this->view('BODY');

        try {
            $layoutPath = new Path('application/templates/tests_layout.php');
            $layoutPath->mkpath();
            file_put_contents((string)$layoutPath, '[<?= $content ?>]');

            $view = new View($name, 'tests_layout');

            self::assertSame('[BODY]', $view->make());
        } finally {
            $this->removeViewFixture();
            (new Path('application/templates/tests_layout.php'))->rmpath();
        }
    }

    public function testMissingViewIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        new View('tests_view/no_such_view');
    }

    public function testLayoutIsReadable(): void
    {
        $name = $this->view();

        try {
            self::assertInstanceOf(Template::class, (new View($name))->getLayout());
        } finally {
            $this->removeViewFixture();
        }
    }

    /**
     * setLayout() built a new Template from the one it was handed, coercing the object to a
     * string -- which renders it -- and using that markup as a filename.
     */
    public function testSetLayoutKeepsTheTemplateItIsGiven(): void
    {
        $name = $this->view();

        try {
            $layoutPath = new Path('application/templates/tests_layout.php');
            $layoutPath->mkpath();
            file_put_contents((string)$layoutPath, 'LAYOUT:<?= $content ?>');

            $layout = new Template('tests_layout');
            $view = new View($name);
            $view->setLayout($layout);

            self::assertSame($layout, $view->getLayout());
            self::assertSame('LAYOUT:BODY', $view->make());
        } finally {
            $this->removeViewFixture();
            (new Path('application/templates/tests_layout.php'))->rmpath();
        }
    }

    public function testViewFactoryPutsTheCurrentUserAndSearchTermInTheLayout(): void
    {
        $name = $this->view();
        $_REQUEST['like'] = 'search term';

        try {
            $database = new \core\DatabaseSQL(\core\DatabaseSQL::DRIVER_SQLITE);
            $database->getDBH()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, authkey TEXT, authtime INTEGER)');

            $checker = new AuthChecker(
                new UserRepository($database, new \UserBundle\Mapper\UserMapper(
                    new \UserBundle\Repository\RoleRepository(new \UserBundle\Mapper\RoleMapper())
                ))
            );

            $view = (new ViewFactory($checker))->createView($name);

            self::assertInstanceOf(View::class, $view);
            self::assertNull($view->getLayout()->get('currentUser'), 'nobody is logged in');
            self::assertSame('search term', $view->getLayout()->get('queryString'));
        } finally {
            unset($_REQUEST['like']);
            $this->removeViewFixture();
        }
    }

    // --- DynamicDbConfigLoader ----------------------------------------------------------------------

    public function testTheDynamicDbConfigIsLoadedOnceAndShared(): void
    {
        $first = DynamicDbConfigLoader::load();

        self::assertSame($first, DynamicDbConfigLoader::load());
        self::assertIsArray($first->getArray('tables'));
    }
}
