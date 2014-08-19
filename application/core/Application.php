<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license
 */
 
/**
 * Registry
 */
class Application {
    
    /**
     * Registry entries
     */
    private static $_registry = array();

    /**
     * Satic only
     */
    private function __construct() { }
    private function __clone() { }

    /**
     * set entry
     */
    static function set($name, $value) {
        $name = strtolower($name);
        if (isset(self::$_registry[$name])) {
            throw new Exception("Registry: the {$name} is already set!", 1);
        }
        self::$_registry[$name] = $value;
    }

    /**
     * get entry
     */
    static function get($name) {
        $name = strtolower($name);
        if (isset(self::$_registry[$name])) {
            return self::$_registry[$name];
        }
        $self = "_{$name}";
        if (isset(self::$$self)) {
            return self::$$self;
        }
        throw new Exception("Registry: {$name} is not set!", 1);
    }

    /**
     * remove entry
     */
    static function remove($name) {
        $name = strtolower($name);
        if (isset(self::$_registry[$name])) {
            unset(self::$_registry[$name]);
            return true;
        }
        return false;
    }

    /**
     * Get / set entry automatic
     * With params: set entry
     * Without params: get entry
     */
    static function __callStatic($name, $params) {
        if ($params) {
            self::set($name, $params[0]);
        } else {
            return self::get($name);
        }
        return true;
    }
 
    //////////////////////////////////////
    
    private static $_controller = null;
    private static $_action = null;
    private static $_params = array();
    
    /**
     *
     */
    static function start() {
        //get request
        isset($_REQUEST['q']) ? $request = preg_split('@/@', $_REQUEST['q'], NULL, PREG_SPLIT_NO_EMPTY) : $request = array();
        
        //get controller, action, params
        !empty($request[0]) ? self::$_controller = strtolower($request[0]) : self::$_controller = 'index';
        !empty($request[1]) ? self::$_action = strtolower($request[1]) : self::$_action = 'default';
        if (count($request) > 2) {
            for ($i=2; $i < count($request); $i++) { 
                self::$_params[] = $request[$i];
            }
        }
        
        if (!self::_loadController()) {
            self::$_controller = '404';
            self::$_action = 'default';
            if (!self::_loadController()) {
                header('HTTP/1.1 404 Not Found');
                header("Status: 404 Not Found");
                die("404: Not found!");
            }
        }
    }
    
    /**
     * 
     */
    private static function _loadController() {
        $path = new Path(sprintf('application/controllers/%s.php', self::$_controller));
        if (file_exists($path)) {
            require_once $path;
            $controller = sprintf('Controller%s', self::$_controller);
            $controller = new $controller(self::$_controller, self::$_action);
            $action = sprintf('action%s', self::$_action);
            if (method_exists($controller, $action)) {
                call_user_func_array(array($controller, $action), self::$_params);
                return true;
            } 
        }
        return false;
    }
    
    /**
     * 
     */
    static function redirect($url) {
        if (!preg_match("/^\w*\:\/\//", $url)) {
            $url = new Url($url);   
        }
        header("Location: {$url}");
        exit();
    }
        
}