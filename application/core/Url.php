<?php
/**
 * HCPHP
 * URL paser / generator
 *
 * @package    hcphp
 * @subpackage core
 * @author     Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161020
 */

namespace core;

/**
 * URL
 */
class Url extends Object {

    protected $_params = [];
    protected $_host;
    protected $_scheme;
    protected $_port;
    protected $_path;
    protected $_anchor;

    /**
     * @param string $path Path
     * @param array $params Url params
     * @param string $anchor Anchor
     */
    public function __construct($path = '', array $params = [], $anchor = null) {
        
        // Get current path.
        if ($path === true) {
            // Current anchor is not avaliable at server side.
            $request = filter_input(INPUT_SERVER, 'REQUEST_URI'); // no anchor here.
            $path = ltrim(parse_url($request, PHP_URL_PATH), '/\\');
            $this->_params = $this->parseParams($request);
        } else {
            $path = ltrim($path, '/\\');
        }

        $url = Filters::filter($path, Filters::URL);
        if ($url) {
            $this->_scheme = parse_url($url, PHP_URL_SCHEME);
            $this->_host = parse_url($url, PHP_URL_HOST);
            $this->_port = (int)parse_url($url, PHP_URL_PORT);
            $this->_path = parse_url($url, PHP_URL_PATH);
            $this->_anchor = parse_url($url, PHP_URL_FRAGMENT);
            
        } else {
            $this->_path = (string)$path;
            
            $secured = filter_input(INPUT_SERVER, 'HTTPS');
                    
            if ($secured === 'on') {
                $this->_scheme = 'https';
                $this->_port = 443;
            } else {
                $this->_scheme = 'http';
                $this->_port = 80;
            }
            
            $host = filter_input(INPUT_SERVER, 'HTTP_HOST');
            $this->_host = $host ? $host : getenv('SERVER_ADDR');
        }
        
        $anchor && $this->_anchor = $anchor;

        // Prepare URL params.
        $this->_params = $this->parseParams($url);
        foreach ($params as $name => $val) {
            $this->addParam($name, $val);
        }
    }
    
    public static function parseParams($url) {
        $params_str = parse_url($url, PHP_URL_QUERY);
        $params = [];
        if ($params_str) {
            foreach (preg_split("@&@", $params_str, -1, null) AS $p_fragment) {
                $var = preg_split("@=@", $p_fragment, -1, null);
                $params[$var[0]] = empty($var[1]) ? null : $var[1];
            }
        }
        return $params;
    }
    
    /**
     * To string magic convert
     */
    public function __toString() {
        try {
            return $this->make();
        } catch (\Exception $ex) {
            trigger_error($ex->getMessage());
            return '';
        }
    }
    
    /**
     * Make URL
     * @return string URL
     */
    public function make() {
        $url = "{$this->_scheme}://{$this->_host}";
        if ($this->_port && !in_array($this->_port, [80, 443], true)) {
            $url .= ":{$this->_port}";
        }
        
        if (!file_exists(new Path($this->_path)) && !Application::modRewrite()) {
            $url .= "/index.php?q={$this->_path}";
        } else {
            $url .= "/{$this->_path}";
        }
        
        if ($this->_params) {
            $url = "{$url}?" . http_build_query($this->_params);
        }
        
        return $this->_anchor ? "{$url}#{$this->_anchor}" : $url;
    }
    
    /**
     * 
     * @param type $val
     */
    public function setAnchor($val) {
        $this->_anchor = trim($val);
    } 
    
    /**
     * 
     * @return type
     */
    public function getAnchor() {
        return $this->_anchor;
    }
    
    /**
     * 
     * @param type $path
     */
    public function setPath($path) {
        $this->_path = trim($path);
    }
    
    /**
     * 
     * @return type
     */
    public function getPath() {
        return $this->_path;
    }

    /**
     * 
     * @param type $val
     */
    public function setPort($val) {
        $this->_port = (int)$val;
    }
    
    /**
     * 
     * @return type
     */
    public function getPort() {
        return $this->_port;
    }
    
    /**
     * 
     * @param type $val
     */
    public function setScheme($val) {
        $this->_scheme = trim($val);
    }
    
    /**
     * 
     * @return type
     */
    public function getScheme() {
        return $this->_scheme;
    }
    
    /**
     * 
     * @param type $val
     */
    public function setHost($val) {
        $this->_host = trim($val);
    }
    
    /**
     * 
     * @return type
     */
    public function getHost() {
        return $this->_host;
    }
    
    /**
     * 
     * @param array $params
     */
    public function setParams(array $params) {
        $this->_params = $params;
    } 
    
    /**
     * 
     * @param type $name
     * @param type $val
     */
    public function addParam($name, $val) {
        $this->_params[$name] = $val;
    }
    
    /**
     * 
     * @return type
     */
    public function getParams() {
        return $this->_params;
    }
    
    /**
     * 
     * @param type $name
     */
    public function removeParam($name) {
        unset($this->_params[$name]);
    }
}