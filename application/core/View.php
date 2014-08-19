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
class View extends Object {
	
    private $_data;
    private $_template;
    private $_content;
    
	function __construct($view) {
	    $config = new Config('template', array('default'));
        
        $this->template = $config->default;
        $this->_content = new Path("application/views/{$view}.php");
        $this->_data = array();
	}
    
    function setTemplate($name) {
        $path = new Path("application/templates/{$name}/");
        if (!$path) {
            throw new Exception("Template '{$name}' does not exist!", 1);
        }
        $this->_template = $path;
    }
    
    public function getData() {
        return $this->_data;
    }
    
    public function setData($data) {
        $this->_checkArg($data, array('array', 'object'));
        if (is_object($data)) {
            $this->_data = (array)$data;
        } else {
            $this->_data = $data;
        }
    }
    
    public function render($data = array()) {
        !$data && $data = $this->_data;
        is_object($data) && $data = (array)$data;
        extract($data, EXTR_OVERWRITE);
        
        include "{$this->_template}/header.php";
        include $this->_content;
        include "{$this->_template}/footer.php";
    }
}