<?php

namespace DynamicDB\Field;

use http\Exception\InvalidArgumentException;

/**
 * Text field definition for dymanic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class File extends Text
{
    protected $length = 255;

    public function setLength($value): self
    {
        $this->length = (int)$value;

        if ($this->length > 255) {
            $this->length = 255;

            throw new InvalidArgumentException('Max supported filename length is 255 bytes');
        }

        return $this;
    }
}
