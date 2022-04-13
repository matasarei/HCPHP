<?php

namespace DynamicDB\Field;

use LogicException;

/**
 * @package    dyamicdb
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MediumText extends Text
{
    const TEXT_FIELD_TYPE = 'MEDIUMTEXT';

    protected $length = self::LENGTH_MAX + 1;

    public function setLength(int $value)
    {
        throw new LogicException(
            sprintf(
            'Field length cannot be changed for %s',
            static::TEXT_FIELD_TYPE
            )
        );
    }
}
