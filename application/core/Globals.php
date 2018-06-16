<?php

namespace core;
 
/**
 * Global data
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Globals 
{
    // Satic only.
    private function __construct() 
    {
    }
    
    private function __clone() 
    {
    }
    
    /**
     * init function
     * @param array $params
     */
    public static function init() 
    {
        session_start();
    }
    
    /**
     * Reset data
     * 
     * @param array $params
     */
    public static function reset(array $params = []) 
    {
        session_unset();
        foreach ($params as $name) {
            unset($_COOKIE[$name]);
            setcookie($name, null, -1, '/');
        }
    }
    
    /**
     * Check if POST request or get POST value
     * 
     * @param string $name Var name (leave empty to check if POST request)
     * @param mixed $default Default value (using to determ required type)
     * @param bool $checkEmpty Check if source empty (true) or not null (false)
     * 
     * @return mixed Result
     */
    public static function post($name = null, $default = '', $checkEmpty = false) 
    {
        if (!$name) {
            return filter_input(INPUT_SERVER, REQUEST_METHOD) === 'POST';
        }
        
        if (filter_input(INPUT_POST, $name)) {
            return self::filter(
                filter_input(INPUT_POST, $name), 
                is_null($default) ? '' : $default, 
                $checkEmpty
            );
        }
        
        return $default;
    }
    
    /**
     * Set global value (session as default)
     * 
     * @param string $name Var name
     * @param mixed $value Value
     * @param bool $rewrite Can rewrite data
     * @param int $time Save for some 
     */
    public static function set($name, $value, $rewrite = false, $time = 0) 
    {
        if ($time > 0) {
            if (null === filter_input(INPUT_COOKIE, $name) || $rewrite) {
                $_COOKIE[$name] = $value;
                setcookie($name, $value, time() + $time, '/');
            } else {
                trigger_error("Cookies: {$name} is already set!");
            }
        } else {
            null === filter_input(INPUT_SESSION, $name) || $rewrite ?
                $_SESSION[$name] = $value :
                trigger_error("Session: {$name} is already set!");
        }
        
        return false;
    }
    
    /**
     * Get value from $_SESSION (priority) or $_COOKIE
     * 
     * @param string $name
     * @param mixed $default Default value (also using to determ required type)
     * 
     * @return mixed
     */
    public static function get($name, $default = '') 
    {
        $sval = filter_input(INPUT_SESSION, $name);
        
        if (null !== $sval) {
            return self::filter($sval, $default);
        }
        
        $cval = filter_input(INPUT_COOKIE, $name);
        
        if (null !== $cval) {
            return self::filter($cval, $default);
        }
        
        return $default;
    }
    
    /**
     * Get value from $_REQUEST
     * 
     * @param string $name
     * @param mixed $default Default value (using for determ required type)
     * @param bool $checkEmpty Check if source empty (true) or not null (false)
     * 
     * @return mixed
     */
    public static function optional($name, $default = '', $checkEmpty = false) 
    {
        $val = filter_input(INPUT_REQUEST, $name);
        
        if (null !== $val) {
            return self::filter($val, $default, $checkEmpty);
        }
        
        return $default;
    }
    
    /**
     * Get filtered param
     * 
     * @param mixed $source Value, array or object
     * @param mixed $default Default value (using for determ required type)
     * @param bool $checkEmpty Check if source empty (true) or not null (false)
     * 
     * @return mixed return mixed Prepared value
     */
    public static function filter($source, $default = '', $checkEmpty = true) 
    {
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
     * 
     * @param array $vars
     */
    public static function getFew(array $vars) 
    {
        $return = [];
        foreach ($vars as $name => $default) {
            $return[$name] = self::optional($name, $default);
        }
        
        return $return;
    }
    
    /**
     * @param string $name
     * 
     * @return string
     */
    public static function file($name) 
    {
        if (isset($_FILES[$name])) {
            return $_FILES[$name];
        }
        
        return [
            "name" => null,
            "type" => null,
            "tmp_name" => null,
            "error" => UPLOAD_ERR_NO_FILE,
            "size" => 0
        ];
    }
}
