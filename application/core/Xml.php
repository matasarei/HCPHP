<?php

namespace core;

use InvalidArgumentException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Xml
{
    /**
     * What an attribute name is allowed to look like.
     */
    const ATTRIBUTE_NAME_PATTERN = '/^[A-Za-z_:][A-Za-z0-9_.:-]*$/';

    static function tag(string $name, string $content = null, array $attributes = []): string
    {
        $string = [sprintf('<%s', $name)];

        if ($attributes) {
            array_push($string, ' ', self::prepareAttributes($attributes));
        }

        if ($content !== null) {
            $string[] = sprintf('>%s</%s>', $content, $name);
        } else {
            $string[] = ' />';
        }

        return implode('', $string);
    }

    /**
     * @param array $attributes
     *
     * @return string
     *
     * @throws InvalidArgumentException When an attribute name is not a valid name
     */
    protected static function prepareAttributes(array $attributes): string
    {
        $string = [];

        foreach ($attributes as $name => $value) {
            // Escaping the value cannot rescue a hostile name -- it would be written outside
            // the quotes. Names are literals everywhere in this project, so refusing anything
            // else costs nothing and closes the hole.
            if (!preg_match(self::ATTRIBUTE_NAME_PATTERN, (string)$name)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid attribute name "%s"', $name)
                );
            }

            if (empty($value) && !is_numeric($value)) {
                $string[] = sprintf(' %s ', $name);

                continue;
            }

            if (is_array($value)) {
                $value = implode(';', $value);
            }

            // The value sits between quotes, so quotes inside it have to stop being quotes.
            // ENT_SUBSTITUTE matters: without it, a value carrying invalid UTF-8 comes back
            // as an empty string on PHP 7, silently dropping the attribute.
            $string[] = sprintf(
                '%s="%s"',
                $name,
                htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return implode(' ', $string);
    }
}
