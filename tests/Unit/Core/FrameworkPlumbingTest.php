<?php

namespace Tests\Unit\Core;

use core\Autoloader;
use core\Collection;
use core\Command;
use core\Container;
use core\Controller;
use core\DatabaseSQL;
use core\Events;
use core\Handler;
use core\Language;
use core\MapperInterfaceSQL;
use core\Path;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * @covers \core\Autoloader
 * @covers \core\Events
 * @covers \core\Language
 * @covers \core\Command
 * @covers \core\Controller
 * @covers \core\Handler
 * @covers \core\MapperInterfaceSQL
 */
class FrameworkPlumbingTest extends TestCase
{
    // --- Autoloader ---------------------------------------------------------------------

    public function testAddPathRejectsSomethingThatDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        Autoloader::addPath('/no/such/directory');
    }

    public function testAddRejectsSomethingThatDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        Autoloader::add('/no/such/directory', function () {
            return false;
        });
    }

    public function testLoadFileRequiresAMatchingFile(): void
    {
        $root = Path::getRoot();

        self::assertTrue(Autoloader::loadFile($root . '/application', 'core/Collection'));
        self::assertFalse(Autoloader::loadFile($root . '/application', 'core/NoSuchClass'));
    }

    public function testLoadFileToleratesATrailingSeparator(): void
    {
        self::assertTrue(Autoloader::loadFile(Path::getRoot() . '/application/', 'core/Collection'));
    }

    /**
     * The bootstrap registered the framework paths, so a core class resolves without help.
     */
    public function testRegisteredPathsResolveCoreClasses(): void
    {
        self::assertTrue(Autoloader::load('core\Collection'));
        self::assertFalse(Autoloader::load('core\NoSuchClassAnywhere'));
    }

    /**
     * Paths and loaders are two parallel arrays paired by index, so addLoader() attaches to
     * whichever path is sitting at the same offset. Adding a loader when every path already
     * has one leaves it past the end of the path list, where load() never reaches it.
     *
     * Registering the path first is what makes the pairing land.
     */
    public function testALoaderPairsWithThePathAtItsOwnIndex(): void
    {
        $called = false;

        Autoloader::addPath(Path::getRoot() . '/application');
        Autoloader::addLoader(function ($path, $class) use (&$called) {
            $called = true;

            return false;
        });

        Autoloader::load('NoSuchClassForTheLoaderTest');

        self::assertTrue($called);
    }

    /**
     * A path registered through addPath() has no loader of its own, so load() falls back to
     * loadFile() rather than reading past the end of the loader list.
     */
    public function testAPathWithoutALoaderStillResolves(): void
    {
        Autoloader::addPath(Path::getRoot() . '/application');

        self::assertTrue(Autoloader::load('core\Collection'));
    }

    // --- Events --------------------------------------------------------------------------

    public function testAListenerIsCalledWhenItsEventFires(): void
    {
        $seen = null;

        Events::addListener('tests_event', function ($data) use (&$seen) {
            $seen = $data;
        });

        Events::triggerEvent('tests_event', ['key' => 'value']);

        self::assertSame('value', $seen->key);
        Events::resetEvent('tests_event');
    }

    public function testEveryListenerIsCalledInOrder(): void
    {
        $calls = [];

        Events::addListener('tests_order', function () use (&$calls) {
            $calls[] = 'first';
        });
        Events::addListener('tests_order', function () use (&$calls) {
            $calls[] = 'second';
        });

        Events::triggerEvent('tests_order');

        self::assertSame(['first', 'second'], $calls);
        Events::resetEvent('tests_order');
    }

    public function testResetRemovesTheListeners(): void
    {
        $calls = 0;

        Events::addListener('tests_reset', function () use (&$calls) {
            $calls++;
        });
        Events::resetEvent('tests_reset');
        Events::triggerEvent('tests_reset');

        self::assertSame(0, $calls);
    }

    public function testAnEventWithNoListenersIsHarmless(): void
    {
        Events::triggerEvent('tests_event_nobody_listens_to');

        $this->addToAssertionCount(1);
    }

    public function testParamsArriveAsAnObject(): void
    {
        $type = null;

        Events::addListener('tests_shape', function ($data) use (&$type) {
            $type = gettype($data);
        });

        Events::triggerEvent('tests_shape', ['a' => 1]);

        self::assertSame('object', $type);
        Events::resetEvent('tests_shape');
    }

    // --- Language -------------------------------------------------------------------------

    public function testTheDefaultLanguageIsUsedWhenNoneIsAsked(): void
    {
        self::assertSame(
            Language::getDefaultLanguageCode(),
            Language::getInstance()->getCurrentLanguageCode()
        );
    }

    public function testInstancesAreSharedPerLanguage(): void
    {
        self::assertSame(Language::getInstance('en'), Language::getInstance('en'));
    }

    public function testAKnownStringIsReturned(): void
    {
        self::assertNotSame('', Language::getInstance('en')->getString('yes'));
    }

    /**
     * A missing string shows its own name so the gap is visible in the page rather than
     * rendering as nothing.
     */
    public function testAMissingStringIsMarked(): void
    {
        self::assertSame('%no_such_string%', @Language::getInstance('en')->getString('no_such_string'));
    }

    public function testStringsAreEscapedByDefault(): void
    {
        $language = Language::getInstance('en');

        // Formatted through an argument so the assertion does not depend on the shipped text.
        self::assertStringNotContainsString('<b>', $language->getString('yes', []) . '');
        self::assertSame('&lt;b&gt;', htmlspecialchars('<b>'));
    }

    public function testMagicAccessReadsAString(): void
    {
        $language = Language::getInstance('en');

        self::assertSame($language->getString('yes'), $language->yes);
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        Language::getInstance('no_such_language');
    }

    public function testTheDefaultLanguageCodeCanBeChanged(): void
    {
        $previous = Language::getDefaultLanguageCode();

        try {
            Language::setDefaultLanguageCode('en');

            self::assertSame('en', Language::getDefaultLanguageCode());
        } finally {
            Language::setDefaultLanguageCode($previous);
        }
    }

    // --- Command --------------------------------------------------------------------------

    public function testCommandParsesItsArgumentsAndRuns(): void
    {
        $command = new PlumbingCommand(new Container(), ['value']);

        self::assertSame('value', $command->getArgument('first'));
        self::assertSame(0, $command->run());
    }

    public function testCommandArgumentFallsBackToItsDefault(): void
    {
        $command = new PlumbingCommand(new Container(), ['value']);

        self::assertNull($command->getArgument('absent'));
        self::assertSame('fallback', $command->getArgument('absent', 'fallback'));
    }

    public function testSetArgumentIsFluent(): void
    {
        $command = new PlumbingCommand(new Container(), ['value']);

        self::assertSame($command, $command->setArgument('extra', 1));
        self::assertSame(1, $command->getArgument('extra'));
    }

    /**
     * @dataProvider helpRequestProvider
     */
    public function testAskingForHelpRaisesItRatherThanRunning(string $flag): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Usage: plumbing <first>');

        new PlumbingCommand(new Container(), [$flag]);
    }

    public function helpRequestProvider(): array
    {
        return [
            'question mark' => ['/?'],
            'long flag' => ['--help'],
        ];
    }

    public function testCommandWithNoArgumentsStillConstructs(): void
    {
        self::assertSame(0, (new PlumbingCommand(new Container(), []))->run());
    }

    // --- Controller and Handler -----------------------------------------------------------------

    public function testControllerGetsTheContainerAndALanguage(): void
    {
        $container = new Container();
        $controller = new PlumbingController($container);

        self::assertSame($container, $controller->container());
        self::assertInstanceOf(Language::class, $controller->language());
    }

    /**
     * A handler runs as it is constructed; that is how events invoke them.
     */
    public function testHandlerRunsOnConstruction(): void
    {
        $handler = new PlumbingHandler((object)['value' => 'x']);

        self::assertSame('x', $handler->seen);
    }

    // --- MapperInterfaceSQL -----------------------------------------------------------------------

    public function testSqlMapperKeepsItsDatabase(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $mapper = new PlumbingMapper($database);

        self::assertSame($database, $mapper->getDatabase());
    }
}

class PlumbingCommand extends Command
{
    public function run(): int
    {
        return 0;
    }

    protected function parseArguments(array $args)
    {
        if (isset($args[0])) {
            $this->setArgument('first', $args[0]);
        }
    }

    protected function getHelp(): string
    {
        return 'Usage: plumbing <first>';
    }
}

class PlumbingController extends Controller
{
    public function container(): Container
    {
        return $this->container;
    }

    public function language(): Language
    {
        return $this->language;
    }
}

class PlumbingHandler extends Handler
{
    public $seen;

    protected function handle($data)
    {
        $this->seen = $data->value;
    }
}

class PlumbingMapper extends MapperInterfaceSQL
{
    public function mapFromEntity($entity): array
    {
        return [];
    }

    public function mapToEntity(array $data)
    {
        return null;
    }
}
