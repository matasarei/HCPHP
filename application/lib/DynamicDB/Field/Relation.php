<?php

namespace DynamicDB\Field;

use DynamicDB\Field\Integer as IntegerField;

/**
 * Text field definition for dymanic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20180513
 */
class Relation extends IntegerField
{   
    /**
     * Field max length (default)
     * @var int
     */
    protected $length = 10;

    public function setLength(int $value): IntegerField
    {
        return parent::setLength($value);
    }
}
