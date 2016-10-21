<?php
/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */

namespace core;

class DateTime extends \DateTime {
    
    private $_format = 'd.m.Y H:i';
    
    public function setFormat($val) {
        $this->_format = $val;
    }
    
    public function getFormat() {
        return $this->_format;
    }
    
    public function format($format = null) {
        !$format && $format = $this->_format;
        return parent::format($format);
    }
    
    public function __toString() {
        return $this->format();
    }
    
}