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

    public static function setDefaultLanguageCode(string $languageCode)
    {
        self::$defaultLanguageCode = $languageCode;
    }

    public static function getDefaultLanguageCode(): string
    {
        return self::$defaultLanguageCode;
    }

    public static function getInstance(string $languageCode = null): self
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

    private function __construct(string $languageCode)
    {
        $this->languageCode = $languageCode;
        $path = new Path(sprintf('application/lang/%s.json', $this->languageCode));

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
