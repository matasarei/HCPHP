<?php

namespace core;

use RuntimeException;

/**
 * @deprecated Should not be used anymore
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class MagicObject
{
    function __set(string $name, $value)
    {
        $setter = 'set' . ucfirst($name);

        if (method_exists($this, $setter)) {
            $this->$setter($value);

            return;
        }

        throw new RuntimeException('Undefined setter ' . $setter);
    }

    function __get(string $name)
    {
        $getter = 'get' . ucfirst($name);

        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        throw new RuntimeException('Undefined getter ' . $getter);
    }
}
