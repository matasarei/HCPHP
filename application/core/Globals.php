<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Globals
{
    public static function init()
    {
        session_start();
    }

    public static function reset(array $params = [])
    {
        session_unset();

        foreach ($params as $name) {
            setcookie($name, null, -1, '/');
            unset($_COOKIE[$name]);
        }
    }
    
    /**
     * @param string|null $name
     * @param string $default
     *
     * @param bool $checkEmpty
     *
     * @return bool|float|int|mixed|string
     */
    public static function post(string $name = null, string $default = '', bool $checkEmpty = false)
    {
        if ($name === null) {
            return $_SERVER['REQUEST_METHOD'] === 'POST';
        }

        if (isset($_POST[$name])) {
            return self::filter($_POST[$name], is_null($default) ? '' : $default, $checkEmpty);
        }

        return $default;
    }

    public static function set(string $name, $value, bool $cookies = false, int $time = 3600): bool
    {
        if ($cookies) {
            $_COOKIE[$name] = $value;
            setcookie($name, $value, time() + $time, '/');
        } else {
            $_SESSION[$name] = $value;
        }

        return false;
    }

    /**
     * Get value from $_SESSION (priority) or $_COOKIE
     *
     * @param string $name
     * @param mixed $default Default value (also using to determine required type)
     *
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        if (isset($_SESSION[$name])) {
            return self::filter($_SESSION[$name], $default);
        }

        if (isset($_COOKIE[$name])) {
            return self::filter($_COOKIE[$name], $default);
        }

        return $default;
    }

    /**
     * Get value from $_REQUEST
     *
     * @param string $name
     * @param mixed $default Default value, also using to define required type
     * @param bool $checkEmpty Check if source empty (true) or not null (false)
     *
     * @return mixed
     */
    public static function optional(string $name, $default = '', bool $checkEmpty = false)
    {
        if (isset($_REQUEST[$name])) {
            return self::filter($_REQUEST[$name], $default, $checkEmpty);
        }

        return $default;
    }

    /**
     * Get filtered param
     *
     * @param mixed $source Value, array or object
     * @param mixed $default Default value (also using to cast to required type)
     * @param bool $checkEmpty Check if source empty (true) or not null (false)
     *
     * @return mixed
     */
    public static function filter($source, $default = null, bool $checkEmpty = true)
    {
        $isEmpty = $checkEmpty ? empty($source) : !isset($source);
        $scalar = is_scalar($default) || $default === null;

        if (
            (!is_scalar($source) && $scalar)
            || (is_scalar($source) && !$scalar)
            || $isEmpty
        ) {
            return $default;
        }

        if ($default !== null) {
            settype($source, gettype($default));
        }

        return $source;
    }
    
    public static function file($name)
    {
        if (isset($_FILES[$name])) {
            return $_FILES[$name];
        }

        return [
            'name' => null,
            'type' => null,
            'tmp_name' => null,
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];
    }
}
