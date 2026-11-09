<?php

namespace core;

use UnexpectedValueException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Language
{
    /**
     * @var self[]
     */
    private static $instances = [];

    /**
     * Default language code
     *
     * @var string
     */
    protected static $defaultLanguageCode = 'en';

    /**
     * @var string
     */
    private $languageCode;

    /**
     * @var array
     */
    private $strings = [];

    /**
     * A language code names a file under application/lang, so it is a code and nothing else:
     * "en", "uk", "pt-BR", "zh_Hant". No dot, no slash, no backslash -- Path turns "\" into "/".
     */
    public static function isValidCode(string $languageCode): bool
    {
        return preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $languageCode) === 1;
    }

    public static function exists(string $languageCode): bool
    {
        return self::isValidCode($languageCode) && is_readable(self::path($languageCode));
    }

    /**
     * The language a request asked for (?l=), when there is such a language; otherwise the
     * fallback. The request must not be able to name a file, nor to make every page throw by
     * asking for a language that is not installed.
     *
     * @param mixed $requested
     */
    public static function chooseCode($requested, string $fallback): string
    {
        if (!is_string($requested)) {
            return $fallback;
        }

        // A link may carry a trailing slash (?l=en/); start() has always accepted it.
        $requested = rtrim($requested, '/');

        return self::exists($requested) ? $requested : $fallback;
    }

    public static function setDefaultLanguageCode(string $languageCode)
    {
        self::$defaultLanguageCode = $languageCode;
    }

    public static function getDefaultLanguageCode(): string
    {
        return self::$defaultLanguageCode;
    }

    public static function getInstance(?string $languageCode = null): self
    {
        if (empty($languageCode)) {
            $languageCode = self::$defaultLanguageCode;
        }

        return self::$instances[$languageCode] ?? self::createInstance($languageCode);
    }

    /**
     * @return string
     */
    public function getCurrentLanguageCode(): string
    {
        return $this->languageCode;
    }

    /**
     * @param string $name
     * @param array $args
     * @param bool $encodeSpecialChars
     *
     * @return mixed|string
     */
    public function getString(string $name, array $args = [], bool $encodeSpecialChars = true)
    {
        if (!isset($this->strings[$name])) {
            trigger_error(sprintf('String "%s" in "%s" does not exist', $name, $this->languageCode));

            return '%' . $name . '%';
        }

        $string = $this->strings[$name];

        if ($args) {
            $string = vsprintf($string, $args);
        }

        if ($encodeSpecialChars) {
            $string = htmlspecialchars($string);
        }

        return $string;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function __get(string $name)
    {
        return $this->getString($name);
    }

    private static function createInstance(string $languageCode): self
    {
        self::$instances[$languageCode] = new self($languageCode);

        return self::$instances[$languageCode];
    }

    private static function path(string $languageCode): Path
    {
        return new Path(sprintf('application/lang/%s.json', $languageCode));
    }

    private function __construct(string $languageCode)
    {
        // The same exception as a missing language, which callers already handle.
        if (!self::isValidCode($languageCode)) {
            throw new UnexpectedValueException(sprintf('Not a language code: "%s"', $languageCode));
        }

        $this->languageCode = $languageCode;
        $path = self::path($languageCode);

        if (is_readable($path)) {
            $strings = json_decode(file_get_contents($path), true);
        } else {
            $strings = null;
        }

        if (empty($strings)) {
            throw new UnexpectedValueException(
                sprintf('Wrong file format or language config (%s) does not exist', $this->languageCode)
            );
        }

        $this->strings = $strings;
    }
}
