<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141109
 */

namespace core;

use core\Exception;

/**
 * 
 */
class Config {
    
    private $_vars = [];
    private $_timemodified = 0;
    private $_path;
    
    function __construct($config, array $vars) {
        $path = new Path("application/config/{$config}.json", true);
        $this->_path = $path;
        
        $config = json_decode(file_get_contents($path));
        
        $this->_timemodified = filemtime($path);
        
        foreach ($vars as $var => $default) {
            if (is_numeric($var)) {
                $var = $default;
                $default = null;
            }
            $this->_set($config, $var, $default);
        } 
    }
    
    /**
     * private setter
     */
    private function _set($data, $name, $default = null) 
    {
        if (isset($data->$name)) {
            $this->_vars[$name] = $data->$name;
        } else if($default !== null) {
            $this->_vars[$name] = $default;
        } else {
            throw new Exception('e_config_val_undefined', 0, [$name, (string)$path]);
        }
    }
    
    function getTimeModified() {
        return $this->_timemodified;
    }
    
    /**
     * 
     * @param type $name
     * @param type $value
     */
    public function __set($name, $value) {
        $this->_vars[$name] = $value;
    }

    /**
     * Getter
     */
    function __get($name) {
        if (isset($this->_vars[$name])) {
            return $this->_vars[$name];
        }
    }
}