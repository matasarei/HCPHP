<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2016 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161103
 */

namespace core;

use core\Exception;

/**
 * 
 */
class Path extends Object {
    
    private static $_root;
    private $_path;
    
    /**
     * 
     */
    function __construct($path = '', $check = false) {
        $path = preg_replace('@[/\\\]@ui', DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $this->_path = $path;
        if ($check && !is_readable($this)) {
            throw new Exception("Wrong path or file is not readable ($path)", 1);
        }
    }
    
    /**
     * 
     * @param int $touch
     * @return type
     */
    function mkpath($touch = false) {
        $result = true;
        !is_dir($this) ? $path = dirname((string)$this) : $path = (string)$this;
        if (!file_exists($path)) {
            $result = (bool)mkdir($path, 0777, true); 
        }
        if ($touch && !is_dir($this)) {
            $result = touch($this, $touch ? $touch : time());
        }
        return $result;
    }
    
    /**
     * WARNING!!! USE CAREFULLY!!!
     * Removes object(s) at the current path
     * @param bool $recursive Remove recusive (olny for directories)
     * @param type $path Optional path (for recursive call)
     * @return boolean Result
     */
    function rmpath($recursive = false, $path = null) {
        $result = true;
        !$path && $path = $this;
        if (!file_exists($path)) {
            return false;
        }
        if (is_dir($path)) {
            $objects = scandir($path);
            foreach($objects as $object) {
                if ($object != "." && $object != "..") {
                    $remove = "{$path}/" . basename($object);
                    $result = is_dir($remove) ? ($recursive && $this->rmpath(true, $remove)) : unlink((string)$remove);
                }
            }
            rmdir($path);
        } else {
            return unlink((string)$path);
        }
        return $result;
    }
    
    /**
     * Return string format
     * @return string Path
     */
    function __toString() {
        if (preg_match('#' . addslashes(self::getRoot()) . "#", $this->_path)) {
            return $this->_path;
        }
        return self::getRoot() . DIRECTORY_SEPARATOR . $this->_path;
    }
    
    /**
     * Get file URL
     * @return \core\Url
     */
    function getUrl() {
        return new Url($this->_path);
    }
    
    /**
     * Init static class
     * @param type $value Root path
     * @throws Exception Wrong path exeption
     */
    static function init($value) {
        if (file_exists($value)) {
            self::$_root = $value;
        } else {
            throw new Exception("Path does not exists!", 1);
        }
    }
    
    /**
     * Get root path
     * @return string path
     */
    static function getRoot() {
        if (self::$_root) {
            return self::$_root;    
        } else {
            return $_SERVER['DOCUMENT_ROOT']; 
        }
    }
    
    /**
     * 
     * @return type
     */
    public function getExtension() {
        return pathinfo((string)$this, PATHINFO_EXTENSION);
    }
    
    /**
     * 
     * @param type $strict
     * @return type
     */
    public function isImage($strict = false) {
        if ($strict) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            return preg_match('@image\/@', finfo_file($finfo, (string)$this));
        }
        return preg_match('/.(png|jpeg|jpg|gif|bmp|webp|svg)$/i', basename((string)$this));
    }
    
    /**
     * 
     */
    public function getMimeType() {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, (string)$this);
    }
}

class WrongPathException extends Exception {
    const ERROR_DEFAULT = 'e_path_wrong';
}