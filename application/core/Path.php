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
class Path extends Object {
	
    private static $_root;
    private $_path;
    
	function __construct($path = '') {
        $path = preg_replace('@[/\\\]@ui', DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $this->_path = $path;
	}
    
    
    function __toString() {
        return self::getRoot() . DIRECTORY_SEPARATOR . $this->_path;
    }
    
    /**
     * 
     */
    static function init($value) {
        if (file_exists($value)) {
            self::$_root = $value;
        } else {
            throw new Exception("Path does not exists!", 1);
        }
    }
    
    /**
     * 
     */
    static function getRoot() {
        if (self::$_root) {
            return self::$_root;    
        } else {
            return $_SERVER['DOCUMENT_ROOT']; 
        }
    }
    
}

