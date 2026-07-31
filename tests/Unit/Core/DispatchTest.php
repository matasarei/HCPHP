<?php

namespace Tests\Unit\Core;

use core\Application;
use core\Controller;
use core\Path;
use core\Response;
use PHPUnit\Framework\TestCase;
use Tests\Support\Reflect;
use ReflectionClass;

/**
 * Dispatch turns a resolved controller and action into a response. start(), stop() and
 * redirect() end the process, so they belong to an HTTP-level test; processRequest() and
 * getController() do not, and they are where the decisions live.
 */
class DispatchTest extends TestCase
{
    private const CONTROLLER = 'application/controllers/tests_probe.php';

    protected function setUp(): void
    {
        $this->writeController(
            '<?php class Tests_probeController extends core\Controller {'
            . ' public function actionDefault() { return new core\Response("dispatched"); }'
            . ' public function actionWithArgs($a, $b) { return new core\Response($a . "-" . $b); } }'
        );
    }

    protected function tearDown(): void
    {
        $path = new Path(self::CONTROLLER);

        if (is_file((string)$path)) {
            $path->rmpath();
        }

        $this->setDispatch(null, null, []);
    }

    private function writeController(string $body): void
    {
        $path = new Path(self::CONTROLLER);
        $path->mkpath();
        file_put_contents((string)$path, $body);
    }

    private function setDispatch(?string $controller, ?string $action, array $params): void
    {
        $reflection = new ReflectionClass(Application::class);

        foreach (['controllerName' => $controller, 'actionName' => $action, 'requestParameters' => $params] as $name => $value) {
            $property = Reflect::property($reflection->getName(), $name);
            $property->setValue(null, $value);
        }
    }

    private function processRequest()
    {
        $method = Reflect::method(Application::class, 'processRequest');

        return $method->invoke(null);
    }

    // --- getController ---------------------------------------------------------------------

    public function testAControllerIsLoadedFromItsFile(): void
    {
        self::assertInstanceOf(Controller::class, Application::getController('tests_probe'));
    }

    public function testAMissingControllerFileGivesNull(): void
    {
        self::assertNull(Application::getController('tests_no_such_controller'));
    }

    /**
     * The file is named after the controller but the class inside it is not, so nothing can
     * be instantiated. Reported as "no such controller" rather than as a fatal error.
     */
    public function testAFileWithoutTheExpectedClassGivesNull(): void
    {
        // A name of its own: require_once has already run for tests_probe and PHP cannot
        // unload a class, so reusing that name would find the one still in memory.
        $path = new Path('application/controllers/tests_empty.php');
        $path->mkpath();
        file_put_contents((string)$path, '<?php // deliberately defines nothing');

        try {
            self::assertNull(Application::getController('tests_empty'));
        } finally {
            $path->rmpath();
        }
    }

    /**
     * A class of the right name that is not a Controller is refused, so a stray file cannot
     * be reached over HTTP just by being in the directory.
     */
    public function testAClassThatIsNotAControllerIsRefused(): void
    {
        $path = new Path('application/controllers/tests_impostor.php');
        $path->mkpath();
        file_put_contents((string)$path, '<?php class Tests_impostorController { public function actionDefault() {} }');

        try {
            self::assertNull(Application::getController('tests_impostor'));
        } finally {
            (new Path('application/controllers/tests_impostor.php'))->rmpath();
        }
    }

    // --- processRequest ---------------------------------------------------------------------

    public function testTheResolvedActionIsCalled(): void
    {
        $this->setDispatch('tests_probe', 'default', []);

        $response = $this->processRequest();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('dispatched', $response->getContent());
    }

    /**
     * Route parameters become the action's arguments, in order.
     */
    public function testRouteParametersArePassedToTheAction(): void
    {
        $this->setDispatch('tests_probe', 'withargs', ['one', 'two']);

        // Action names are lowercased during routing, and method names are case-insensitive
        // in PHP, so "withargs" reaches actionWithArgs().
        self::assertSame('one-two', $this->processRequest()->getContent());
    }

    public function testAnUnknownActionIsNotDispatched(): void
    {
        $this->setDispatch('tests_probe', 'no_such_action', []);

        self::assertFalse($this->processRequest());
    }

    public function testAnUnknownControllerIsNotDispatched(): void
    {
        $this->setDispatch('tests_no_such_controller', 'default', []);

        self::assertFalse($this->processRequest());
    }
}
