<?php
/**
 * HCPHP
 * 
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */

/**
 * 
 */
class Debug {

    /**
     * Satic only
     */
    private function __construct() { }
    private function __clone() { }
    
    /**
     * 
     */
    static function init() {
        self::show(true);
        set_error_handler(array(__CLASS__, 'errorHandler'));
        set_exception_handler(array(__CLASS__, 'exceptionHandler'));
    }
    
    /**
     * 
     */
    static function show($status) {
        ini_set('display_errors', $status ? 'On' : 'Off');
        error_reporting($status ? E_ALL : 0);
    }
    
    /**
     * 
     */
    static function errorHandler($errno, $errmsg, $errfile, $errline) {
        $errfile = implode('/', array_slice(explode('/', $errfile), -2));
        echo "<p class='error'>ERROR (#{$errno}): {$errmsg} in {$errfile} on line {$errline}</p>";
    }
    /**
     * 
     */
    static function exceptionHandler($exception) {
        $msg = (string)$exception;
        $msg = str_replace('#', "<br />#", $msg);
        echo "<p class='error'>{$msg}</p>\n";
    }
}