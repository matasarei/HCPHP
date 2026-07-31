<?php

namespace Tests\Unit\Core;

use core\Application;
use core\Debug;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Debug decides what a failure looks like to whoever is watching. With debug off it must say
 * nothing at all -- an error page that leaks a stack trace to the public is the failure mode
 * this class exists to avoid.
 */
class DebugTest extends TestCase
{
    /**
     * @var int
     */
    private $mode;

    /**
     * @var int
     */
    private $reporting;

    /**
     * @var string
     */
    private $display;

    /**
     * @var string
     */
    private $applicationMode;

    protected function setUp(): void
    {
        $this->mode = Debug::mode();
        $this->reporting = error_reporting();
        $this->display = (string)ini_get('display_errors');
        $this->applicationMode = Application::getMode();
    }

    protected function tearDown(): void
    {
        Debug::mode($this->mode);
        error_reporting($this->reporting);
        ini_set('display_errors', $this->display);
        Application::setMode($this->applicationMode);
        Debug::flush();
    }

    private function withDebugOn(callable $body): void
    {
        Debug::mode(E_ALL);
        Debug::flush();
        Application::setMode(Application::MODE_API);

        $body();
    }

    // --- mode ------------------------------------------------------------------------------

    public function testDebugIsOffByDefault(): void
    {
        self::assertFalse(Debug::isOn());
        self::assertSame(0, Debug::mode());
    }

    public function testModeReadsBackWhatWasSet(): void
    {
        Debug::mode(E_ALL);

        self::assertSame(E_ALL, Debug::mode());
        self::assertTrue(Debug::isOn());
    }

    public function testTurningDebugOnEnablesErrorDisplay(): void
    {
        Debug::mode(E_ALL);

        self::assertSame('On', (string)ini_get('display_errors'));
    }

    public function testTurningDebugOffSilencesErrorDisplay(): void
    {
        Debug::mode(E_ALL);
        Debug::mode(0);

        self::assertSame('Off', (string)ini_get('display_errors'));
        self::assertFalse(Debug::isOn());
    }

    // --- with debug off ------------------------------------------------------------------------

    public function testFlushReturnsNothingWhileDebugIsOff(): void
    {
        Debug::mode(0);

        self::assertNull(Debug::flush());
    }

    /**
     * The handler is registered process-wide, so it runs for every notice whether or not
     * anyone is watching. With debug off it must produce nothing.
     */
    public function testTheErrorHandlerIsSilentWhileDebugIsOff(): void
    {
        Debug::mode(0);

        ob_start();
        Debug::errorHandler(E_USER_NOTICE, 'a notice', __FILE__, __LINE__);
        $printed = ob_get_clean();

        self::assertSame('', $printed);
        self::assertNull(Debug::flush());
    }

    public function testTheExceptionHandlerIsSilentWhileDebugIsOff(): void
    {
        Debug::mode(0);

        ob_start();
        Debug::exceptionHandler(new RuntimeException('boom'));
        $printed = ob_get_clean();

        self::assertSame('', $printed);
    }

    // --- with debug on ---------------------------------------------------------------------------

    public function testTheErrorHandlerRecordsTheMessage(): void
    {
        $this->withDebugOn(function () {
            Debug::errorHandler(E_USER_NOTICE, 'something happened', '/a/b/File.php', 42);

            $dump = (string)Debug::flush();

            self::assertStringContainsString('something happened', $dump);
            self::assertStringContainsString('42', $dump);
        });
    }

    /**
     * Only the last two path segments are kept, so a dump does not print the whole layout of
     * the server it came from.
     */
    public function testTheErrorHandlerShortensTheFilePath(): void
    {
        $this->withDebugOn(function () {
            Debug::errorHandler(E_USER_NOTICE, 'x', '/very/long/path/to/core/File.php', 1);

            $dump = (string)Debug::flush();

            self::assertStringContainsString('core/File.php', $dump);
            self::assertStringNotContainsString('/very/long/path', $dump);
        });
    }

    public function testDumpRecordsAValueWithItsOrigin(): void
    {
        $this->withDebugOn(function () {
            Debug::dump(['a' => 1]);

            $dump = (string)Debug::flush();

            self::assertStringContainsString('[D]', $dump);
            self::assertStringContainsString('DebugTest.php', $dump);
            self::assertStringContainsString('a', $dump);
        });
    }

    public function testDumpRendersBooleansReadably(): void
    {
        $this->withDebugOn(function () {
            Debug::dump(true);
            Debug::dump(false);

            $dump = (string)Debug::flush();

            self::assertStringContainsString('true', $dump);
            self::assertStringContainsString('false', $dump);
        });
    }

    public function testDumpCanRecordAValueVerbatim(): void
    {
        $this->withDebugOn(function () {
            Debug::dump('raw line', false);

            self::assertStringContainsString('raw line', (string)Debug::flush());
        });
    }

    public function testFlushEmptiesTheBuffer(): void
    {
        $this->withDebugOn(function () {
            Debug::dump('once', false);

            self::assertStringContainsString('once', (string)Debug::flush());
            self::assertSame('', (string)Debug::flush());
        });
    }

    public function testFlushCanPrintWhatItDrained(): void
    {
        $this->withDebugOn(function () {
            Debug::dump('printed', false);

            ob_start();
            Debug::flush(true);
            $printed = ob_get_clean();

            self::assertStringContainsString('printed', $printed);
        });
    }

    public function testTheExceptionHandlerPrintsTheFailure(): void
    {
        $this->withDebugOn(function () {
            ob_start();
            Debug::exceptionHandler(new RuntimeException('boom'));
            $printed = ob_get_clean();

            self::assertStringContainsString('boom', $printed);
            self::assertStringContainsString('RuntimeException', $printed);
        });
    }

    /**
     * In a web request the dump is wrapped in markup so it lands somewhere visible; on the
     * command line it is printed plainly.
     */
    public function testWebOutputIsWrappedInMarkup(): void
    {
        Debug::mode(E_ALL);
        Application::setMode(Application::MODE_WEB);
        Debug::flush();
        Debug::dump('web output', false);

        ob_start();
        Debug::flush(true);
        $printed = ob_get_clean();

        self::assertStringContainsString('debug-wrapper', $printed);
    }

    public function testCliOutputIsPlain(): void
    {
        Debug::mode(E_ALL);
        Application::setMode(Application::MODE_CLI);
        Debug::flush();
        Debug::dump('cli output', false);

        ob_start();
        Debug::flush(true);
        $printed = ob_get_clean();

        self::assertStringNotContainsString('debug-wrapper', $printed);
        self::assertStringContainsString('cli output', $printed);
    }
}
