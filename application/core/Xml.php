<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Xml
{
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

    protected static function prepareAttributes(array $attributes): string
    {
        $string = [];

        foreach ($attributes as $name => $value) {
            if (empty($value) && !is_numeric($value)) {
                $string[] = sprintf(' %s ', $name);

                continue;
            }

            if (is_array($value)) {
                $value = implode(';', $value);
            }

            $value = preg_replace('/\"/', '\"', $value, -1);
            $string[] = sprintf('%s="%s"', $name, $value);
        }

        return implode(' ', $string);
    }
}
