<?php
/**
 * HCPHP
 * 
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */
 
/**
 * 
 */
class Controller extends Object {
    protected $_name;
    protected $_action;
    
	function __construct($name, $action) {
        $this->_action = $action;
        
        $loader = function($path, $class) {
            $class = preg_replace("/^model/ui", "", $class);
            $path = sprintf('%s/%s.php', $path, strtolower($class));
            if (file_exists($path)) {
                require_once $path;
                return true;
            }
            return false;
        };
        Autoloader::add(new Path("application/models"), $loader); 
	}
    
    function getAction() {
        return $this->_action;
    }
    
    function getName() {
        return $this->_name;
    }
}

