<?php

namespace DynamicDB\Field;

/**
 * Date and time field definition for dymanic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DateTime extends Integer
{   
    /**
     * Default unix timestamp length is 10 (until 11/20/2286)
     * @var int 
     */
    protected $length = 10;
    
    /**
     * Does not support negative values
     * @var bool
     */
    protected $unsigned = true;
}
