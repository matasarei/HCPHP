<?php

namespace core;

use Throwable;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Debug
{
    private static $mode = 0;
    private static $dump = [];

    /**
     * @param int $mode E_ALL, E_NOTICE...
     */
    static function init(int $mode = 0)
    {
        self::mode($mode);

        set_error_handler(array(__CLASS__, 'errorHandler'));
        set_exception_handler(array(__CLASS__, 'exceptionHandler'));
        register_shutdown_function(array(__CLASS__, 'flush'), true);
    }
    
    /**
     * @param int|null $mode E_ALL, E_NOTICE...
     *
     * @return int current mode
     */
    static function mode(int $mode = null): int
    {
        if ($mode === null) {
            return self::$mode;
        }

        self::$mode = $mode;
        ini_set('display_errors', self::$mode ? 'On' : 'Off');
        error_reporting(self::$mode ? $mode : 0);

        return 1;
    }

    /**
     * Return debug state
     *
     * @return bool Debug state
     */
    static function isOn()
    {
        return (bool)self::$mode;
    }

    static function errorHandler(int $errno, string $errMsg, string $errFile, int $errLine)
    {
        if (self::$mode) {
            $errFile = implode('/', array_slice(explode('/', $errFile), -2));
            $error = sprintf(
                '[E] error %d (%s) in %s on line %d',
                $errno,
                $errMsg,
                $errFile,
                $errLine
            );

            if (Application::getMode() === Application::MODE_CLI) {
                self::_print($error);
            }

            self::dump($error, false);
        }
    }

    static function exceptionHandler(Throwable $exception)
    {
        if (self::$mode) {
            $msg = preg_replace([
                '/\s*(Stack trace)/', '/\s*#/'
            ], ["\n$1", "\n#"], (string)$exception, -1);
            
            self::_print(sprintf("[E] %s\n", $msg) . self::flush());
        }

        if (!headers_sent()) {
            http_response_code(500);
        }
    }

    static function dump($val, bool $export = true, array $backtrace = [])
    {
        if ($export) {
            if (empty($backtrace)) {
                $backtrace = debug_backtrace();
            }

            $current = $backtrace[0];

            if (is_bool($val)) {
                $exported = $val ? 'true' : 'false';
            } else {
                $exported = print_r($val, true);
            }

            $val = sprintf("[D] %s, like %d:\n%s", $current['file'], $current['line'], $exported);
        }

        self::$dump[] = $val . "\n";
    }
    
    /**
     * Flush debug dump or print dump
     *
     * @param bool $echo TRUE to print or FALSE to flush
     *
     * @return string|null
     */
    static function flush(bool $echo = false): ?string
    {
        if (self::$mode) {
            $string = implode(self::$dump);
            self::$dump = [];
            
            if ($echo && $string) {
                self::_print($string);
            }

            return $string;
        }

        return null;
    }

    private static function _print(string $message)
    {
        if (Application::getMode() === Application::MODE_CLI) {
            echo PHP_EOL . $message . PHP_EOL;

            return;
        }

        $message = Xml::tag(
            'span',
            nl2br(str_replace(' ', '&nbsp;', htmlentities($message))),
            [
                'style' => [
                    'background:#fff;',
                    'padding: 2px 0;',
                    'line-height: 18px;'
                ],
                'class' => 'debug-message'
            ]
        );

        echo Xml::tag('div', $message, [
            'style' => [
                'position:absolute;',
                'left:0;',
                'top:0;',
                'min-width:1024px;',
                'z-index:10001;',
                "font: bold 13px Monaco, monospace;",
            ],
            'class' => 'debug-wrapper'
        ]);
    }
}
