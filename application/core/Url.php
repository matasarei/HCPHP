<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */
 
class Url extends Object {
    private $_url;
    
    /**
     * @param string Page (controller) name
     * @param string Action name
     * @param array Params
     */
     
    function __construct($url = null) {
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
            $_url = 'https://';
            $port = 443;
        } else {
            $_url = 'http://';
            $port = 80;
        }

        isset($_SERVER['HTTP_HOST']) ? $host = $_SERVER['HTTP_HOST'] : $host = getenv('SERVER_ADDR');

        if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != $port) {
            $_url = "{$_url}{$host}:{$_SERVER["SERVER_PORT"]}";
        } else {
            $_url = "{$_url}{$host}";
        }
                
        if ($url === true) {
            $this->_url = "{$_url}{$_SERVER['REQUEST_URI']}";
        } else {
            if (!file_exists(new Path($url)) && !self::modRewrite()) {
                $this->_url = "{$_url}/index.php?q={$url}";
            } else {
                $this->_url = "{$_url}/{$url}";
            }
        }
    }
    
    function __toString() {
        return $this->_url;
    }
    
    static function modRewrite() {
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            return in_array('mod_rewrite', $modules);
        } else {
            return getenv('HTTP_MOD_REWRITE') == 'On' ? true : false;
        }
    }
}