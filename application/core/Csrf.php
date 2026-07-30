<?php

namespace core;

use Exception;

/**
 * Per-session token proving a form submission came from a page this application served.
 *
 * The token is deliberately read from and written to $_SESSION directly rather than through
 * Globals::get(). Globals::get() falls back to $_COOKIE when the session has no such key, so
 * reading it that way would let anyone able to set a cookie choose the value their submission
 * is compared against -- which is no protection at all.
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Csrf
{
    const SESSION_KEY = '_csrf_token';

    /**
     * Bytes of entropy behind each token.
     */
    const TOKEN_BYTES = 32;

    /**
     * The token for this session, issuing one if there is not already one.
     *
     * @return string
     *
     * @throws Exception When no source of randomness is available
     */
    public static function getToken(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /**
     * @param mixed $candidate Whatever arrived with the request
     *
     * @return bool
     */
    public static function isValid($candidate): bool
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;

        // No token issued, or nothing usable submitted: refuse. Never treat "absent" as "fine".
        if (!is_string($token) || $token === '' || !is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($token, $candidate);
    }

    /**
     * Forget the current token, so the next call to getToken() issues a fresh one. Worth
     * doing whenever the identity behind the session changes.
     */
    public static function reset(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
