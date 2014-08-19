<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */

/**
 * Object
 */
class Object {
    
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
            throw new InvalidArgumentException($msg, 1);
        }
    }
}