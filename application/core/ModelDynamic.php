<?php
/**
 * Dynamic model abstract class
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */

namespace core;

abstract class ModelDynamic extends Model {
    
    /**
     * Dynamic properties container
     * @var array
     */
    protected $_data = [];
    
    /**
     * Magic getter
     * @param type $name
     * @return type
     */
    public function __get($name) {
        try {
            $value = parent::__get($name);
        } catch (\Exception $ex) {
            $value = isset($this->_data[$name]) ? $this->_data[$name] : null;
        }
        return $value;
    }
    
    /**
     * Magic setter
     * @param type $name
     * @param type $value
     */
    public function __set($name, $value) {
        try {
            parent::__set($name, $value);
        } catch (\Exception $ex) {
            $this->_data[$name] = $value;
        }
    }
    
    public function getProperty($name) {
        return $this->__get($name);
    }
    
    public function setProperty($name, $value) {
        $this->__set($name, $value);
    }
    
}