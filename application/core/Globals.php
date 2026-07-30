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
    /**
     * SameSite policy applied to every cookie this class writes, including the session
     * cookie. Lax keeps the cookie off cross-site POSTs and subresource loads while still
     * letting someone who follows a link from elsewhere arrive logged in.
     */
    const COOKIE_SAMESITE = 'Lax';

    /**
     * @var callable|null Overridden in tests; see setCookieWriter()
     */
    private static $cookieWriter;

    public static function init()
    {
        // On an ordinary login the auth key lives in the session, so PHPSESSID is the cookie
        // that actually carries the user's identity. It must be flagged before the session
        // starts -- afterwards is too late.
        session_set_cookie_params(self::getSessionCookieOptions(Application::isHttpsEnabled()));

        session_start();
    }

    public static function reset(array $params = [])
    {
        // Calling session_unset() without a session raises a warning and does nothing;
        // CLI commands and tests run without one.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
        }

        $_SESSION = [];

        foreach ($params as $name) {
            // Expiring a cookie only works when the flags and path match the ones it was
            // written with; otherwise the browser treats it as a different cookie.
            self::writeCookie($name, '', self::getCookieOptions(1, Application::isHttpsEnabled()));
            unset($_COOKIE[$name]);
        }
    }

    /**
     * Replace how cookies are written. Only for tests: setcookie() cannot run once any
     * output exists, which under a test runner is always.
     *
     * @param callable|null $writer function(string $name, string $value, array $options): bool
     */
    public static function setCookieWriter(?callable $writer = null)
    {
        self::$cookieWriter = $writer;
    }

    private static function writeCookie(string $name, string $value, array $options): bool
    {
        $writer = self::$cookieWriter ?? 'setcookie';

        return (bool)$writer($name, $value, $options);
    }

    /**
     * Attributes applied to every cookie written here.
     *
     * Split out from the setcookie() calls because setcookie() cannot be observed from a
     * test; this part is pure and pinned by unit tests, and the header itself is checked
     * over HTTP.
     *
     * @param int $expires Unix timestamp, or 0 for a session cookie
     * @param bool $secure Whether the site is served over HTTPS
     *
     * @return array
     */
    public static function getCookieOptions(int $expires, bool $secure): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => self::COOKIE_SAMESITE,
        ];
    }

    /**
     * The same flags, in the shape session_set_cookie_params() expects.
     *
     * It does not take the same array as setcookie(): the expiry key is named "lifetime" and
     * is a duration rather than a timestamp, and passing "expires" raises a warning and is
     * ignored, leaving the session cookie unflagged.
     *
     * @param bool $secure Whether the site is served over HTTPS
     *
     * @return array
     */
    public static function getSessionCookieOptions(bool $secure): array
    {
        $options = self::getCookieOptions(0, $secure);
        unset($options['expires']);

        // 0 = until the browser closes.
        return ['lifetime' => 0] + $options;
    }
    
    /**
     * @param string|null $name
     * @param string $default
     *
     * @param bool $checkEmpty
     *
     * @return bool|float|int|mixed|string
     */
    public static function post(?string $name = null, string $default = '', bool $checkEmpty = false)
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
            self::writeCookie(
                $name,
                (string)$value,
                self::getCookieOptions(time() + $time, Application::isHttpsEnabled())
            );
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
