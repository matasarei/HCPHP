<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141109
 */

namespace core;
  
/**
 * 
 */
class Debug {
    
    /**
     * Satic only
     */
    private function __construct() { }
    private function __clone() { }
    
    private static $_mode = 0;
    
    private static $_dump = array();
    
    /**
     * Please use php constants (E_ALL, E_NOTICE...)
     */
    static function init($mode = 0) {
        self::mode($mode);
        set_error_handler(array(__CLASS__, 'errorHandler'));
        set_exception_handler(array(__CLASS__, 'exceptionHandler'));
        register_shutdown_function(array(__CLASS__, 'flush'), true);
    }
    
    /**
     * Please use php constants (E_ALL, E_NOTICE...)
     * @param int $mode Debug mode (leave empty to return current)
     * @return int current mode
     */
    static function mode($mode = null) {
        if (!is_null($mode)) {
            self::$_mode = (int)$mode;
            ini_set('display_errors', self::$_mode ? 'On' : 'Off');
            error_reporting(self::$_mode ? $mode : 0);
        } else {
            return self::$_mode;
        }
        return 1;
    }
    
    /**
     * Return debug state
     * @return bool Debug state
     */
    static function isOn() {
        return (bool)self::$_mode;
    }
    
    /**
     * Notice / Error handler
     * @param type $errno
     * @param type $errmsg
     * @param type $errfile
     * @param type $errline
     */
    static function errorHandler($errno, $errmsg, $errfile, $errline) {
        if (self::$_mode) {
            $errfile = implode('/', array_slice(explode('/', $errfile), -2));
            self::dump("ERROR {$errno} ({$errmsg}) in {$errfile} on line {$errline}", false);
        }
    }
    
    /**
     * Exceptions handler
     * @param type $exception
     */
    static function exceptionHandler($exception) {
        if (self::$_mode) {
            $msg = preg_replace([
                "/\s*(Stack trace)/", "/\s*#/"
            ], ["\n$1", "\n#"], (string)$exception, -1);
            
            self::_print("{$msg}\n" . self::flush());
        }
    }
    
    /**
     * Value dump
     * @param type $val
     * @param type $export
     */
    static function dump($val, $export = true, array $backtrace = []) {
        if ($export) {
            !$backtrace && $backtrace = debug_backtrace();
            $current = $backtrace[0];
            
            $val = "{$current['file']}, line {$current['line']}:\n" . print_r($val, true);
        }
        
        self::$_dump[] = "{$val}\n";
    }
    
    /**
     * Flush debug bump or print dump
     * @param bool $echo TRUE to print or FALSE to flush
     * @return string Printed debug output or NULL
     */
    static function flush($echo = false) {
        if (self::$_mode) {
            $string = implode(self::$_dump);
            self::$_dump = [];
            
            if ($echo && $string) {
                echo self::_print($string);
            }
            return $string;
        }
    }
    
    /**
     * 
     * @param type $message
     */
    private static function _print($message) {
        //prepare for browser
        $message = nl2br(str_replace(" ", "&nbsp;", $message));
        
        //prepare list
        $message = Html::tag('span', $message, [
            'style' => [
                'background:#fff',
                'padding: 2px 0',
                'line-height: 18px'
            ],
            'class' => 'debug-message'
        ]);
        
        //insert in wrapper and display
        echo Html::tag('div', $message, [
            'style' => [
                'position:absolute',
                'left:0',
                'top:0',
                'min-width:1024px',
                'z-index:10001',
                "font: bold 13px Monaco, monospace",
            ],
            'class' => 'debug-wrapper'
        ]);
    }
}