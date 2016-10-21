<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @subpackagу core
 * @author     Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161020
 */
 
namespace core;

use core\Exception;

/**
 * Object
 */
abstract class Object {
    
    function __set($name, $value)
    {
        $setter = "set{$name}";
        if (method_exists($this, $setter)) {
            $this->$setter($value);
            return true;
        }
        throw new Exception("Undefined setter {$setter}", 1);
    }
    
    function __get($name)
    {
        $getter = "get{$name}";
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }
        throw new Exception("Undefined getter {$getter}", 1);
    }
    
    protected function _checkArg($arg, array $types) {
        if (!in_array(gettype($arg), $types, true)) {
            $msg = sprintf('Expects %s; %s given!', implode(', ' , $types), gettype($arg));
            throw new Exception($msg, 1);
        }
    }
}