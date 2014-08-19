<?php
/**
 * HCPHP
 * Confguration
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */
 
class Config extends Object {
    
    private $_vars = array();
    
    function __construct($config, $vars) {
        $path = new Path("application/config/{$config}.xml");
        if (file_exists($path)) {
            $config = simplexml_load_file($path);
            foreach ($vars as $var => $default) {
                if (is_numeric($var)) {
                    $var = $default;
                    $default = null;
                }
                $this->_set($config, $var, $default);
            }
        } else {
            throw new Exception("config/{$config}.xml does not exists!", 1);
            
        }
    }
    
    /**
     * private setter
     */
    private function _set($data, $name, $default = null) 
    {
        if (isset($data->$name) && !empty($data->$name)) {
            $value = (string)$data->$name;
            if (is_numeric($value)) {
                $value = (double)$value;
            }
            $this->_vars[$name] = $value;
        } else if($default !== null) {
            $this->_vars[$name] = $default;
        } else {
            throw new Exception("Can't set '{$name}'. Please check config file!", 1);
        }
    }
    
    /**
     * Getter
     */
    function __get($name) {
        if (isset($this->_vars[$name])) {
            return $this->_vars[$name];
        }
        return null;
    }
}