<?php
/**
 * Global data
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141109
 */

namespace core;
 
class Globals {
    
    /**
     * Satic only
     */
    private function __construct() {}
    private function __clone() {}
    
    /**
     * init function
     * @param array $params
     */
    public static function init() {
        session_start();
    }
    
    /**
     * Reset data
     * @param type $params
     */
    public static function reset(array $params = []) {
        session_unset();
        foreach ($params as $name) {
            unset($_COOKIE[$name]);
            setcookie($name, null, -1, '/');
        }
    }
    
    /**
     * Check if POST request or get POST value
     * @param type $name Var name (leave empty to check if POST request)
     * @param type $default Default value (using to determ required type)
     * @param type $checkEmpty Check if source empty (true) or not null (false)
     * @return mixed Result
     */
    public static function post($name = null, $default = '', $checkEmpty = false) {
        if (!$name) {
            return $_SERVER['REQUEST_METHOD'] === 'POST';
        } elseif (isset($_POST[$name])) {
            return self::filter($_POST[$name], is_null($default) ? '' : $default, $checkEmpty);
        }
        return $default;
    }
    
    /**
     * Set global value (session as default)
     * @param string $name Var name
     * @param mixed $value Value
     * @param bool $rewrite Can rewrite data
     * @param bool $cookies Use cookies for save data
     * @param int $time An hour by default
     */
    public static function set($name, $value, $rewrite = false, $cookies = false, $time = 3600) {
        if ($cookies) {
            if (isset($_COOKIE[$name]) || $rewrite) {
                $_COOKIE[$name] = $value;
                setcookie($name, $value, time() + $time, '/');
            } else {
                trigger_error("Cookies: {$name} is already set!");
            }
        } else {
            !isset($_SESSION[$name]) || $rewrite ?
                $_SESSION[$name] = $value :
                trigger_error("Session: {$name} is already set!");
        }
        return false;
    }
    
    /**
     * Get value from $_SESSION (priority) or $_COOKIE
     * @param type $name
     * @param type $default Default value (using for determ required type)
     * @return type
     */
    public static function get($name, $default = '') {
        if (isset($_SESSION[$name])) {
            return self::filter($_SESSION[$name], $default);
        } elseif (isset($_COOKIE[$name])) {
            return self::filter($_COOKIE[$name], $default);
        }
        return $default;
    }
    
    /**
     * Get value from $_REQUEST
     * @param type $name
     * @param type $default Default value (using for determ required type)
     * @param type $checkEmpty Check if source empty (true) or not null (false)
     * @return type
     */
    public static function optional($name, $default = '', $checkEmpty = false) {
        if (isset($_REQUEST[$name])) {
            return self::filter($_REQUEST[$name], $default, $checkEmpty);
        }
        return $default;
    }
    
    /**
     * Get filtered param
     * @param mixed $source Value, array or object
     * @param mixed $default Default value (using for determ required type)
     * @param type $checkEmpty Check if source empty (true) or not null (false)
     * @return type return mixed Prepared value
     */
    public static function filter($source, $default = '', $checkEmpty = true) {
        $empty = $checkEmpty ? empty($source) : !isset($source);
        $scalar = is_scalar($default);
        if ((!is_scalar($source) && $scalar) ||
            (is_scalar($source) && !$scalar) || $empty) {
            return $default;
        }
        settype($source, gettype($default));
        return $source;
    }
    
    /**
     * Get few values from $_REQUEST
     * @param array $vars
     */
    public static function getFew(array $vars) {
        $return = array();
        foreach ($vars as $name => $default) {
            $return[$name] = self::optional($name, $default);
        }
        return $return;
    }
    
    public static function file($name) {
        if (isset($_FILES[$name])) {
            return $_FILES[$name];
        }
        return array(
            "name" => null,
            "type" => null,
            "tmp_name" => null,
            "error" => UPLOAD_ERR_NO_FILE,
            "size" => 0
        );
    }
}