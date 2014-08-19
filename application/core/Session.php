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
class Session {
   
    /**
     * Satic only
     */
    private function __construct() { }
    private function __clone() { }
    
    /**
     * 
     */
    static function __callStatic($var, $value) {
        $var = strtolower($var);
        if (isset($value[0])) {
            return self::set($var, $value[0]);
        } else {
            return self::get($var);
        }
    }
    
    /**
     * 
     */
    static function get($var) {
        $var = strtolower($var);
        if (isset($_SESSION[$var])) {
            return $_SESSION[$var];
        } else {
            return null;
        }
    }
    
    /**
     * 
     */
    static function set($var, $value) {
        $var = strtolower($var);
        $_SESSION[$var] = $value;
    }
    
    /**
     * 
     */
    static function init() {
        session_start();
    }
    
    /**
     * 
     */
    static function remove($var = null) {
        if ($var) {
            unset($_SESSION[$var]);
        } else {
            session_unset();
        }
    }
    
    static function reload() {
        session_destroy();
        session_start();
    }
    
}