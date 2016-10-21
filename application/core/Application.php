<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161020
 */

namespace core;

use core\Exception,
    core\Globals;

class Application {
    
    /**
     * Satic only
     */
    private function __construct() {}
    private function __clone() {}
    
    /**
     * Controller name
     * @var string 
     */
    private static $_controller = null;
    
    /**
     * Returns controller name
     * @return type
     */
    public static function getController() {
        return self::$_controller;
    }
    
    /**
     * Actions name
     * @var string
     */
    private static $_action = null;
    
    /**
     * Returns action name
     * @return type
     */
    public static function getAction() {
        return self::$_action;
    }
    
    /**
     * Request params
     * @var array
     */
    private static $_params = [];
    
    /**
     * Start application
     */
    static function start() {
        Events::triggerEvent('onInit');
        
        // load config.
        $config = new Config('default', ['lang' => 'en']);
        
        // get request.
        $url = filter_var(Globals::optional('q'), FILTER_SANITIZE_URL);
        $request = $url ? preg_split('@/@', $url, NULL, PREG_SPLIT_NO_EMPTY) : [];
        
        // init language.
        $lang = empty($_REQUEST['l']) ? $config->lang : $_REQUEST['l'];
        try {
            Language::setDefault($lang);
        } catch (Exception $e) {
            if (Debug::isOn()) {
                trigger_error("Wrong language code! Can't load default language config ({$lang})");
                trigger_error($e->getMessage());
                exit();
            } else {
                self::redirect(new Url());
            }
        }
        
        // search in manual configurated routes.
        if (!self::_findRoute(Globals::optional('q'))) {
            // if not found, setup automatically.
            self::_autoRoute($request);
        }
        
        Events::triggerEvent('onStart', [
            'controller' => self::$_controller,
            'action'     => self::$_action,
            'params'     => self::$_params
        ], true);
        
        // try to load controller.
        if(!self::_loadController()) {
            if (Debug::isOn()) {
                $message = "404: Controller '%s' or action '%s' does not exists.";
                self::sendError(self::ERROR_NOT_FOUND, sprintf($message, self::$_controller, self::$_action));
            } else {
                self::sendError(self::ERROR_NOT_FOUND);
            }
        }
    }
    
    /**
     * Find preconfigured route
     * @param string $request full request string
     * @return boolean
     */
    private static function _findRoute($request) {
        $config = new Config('routing', ['routes']);
        $matches = [];
        
        // check preconfigured route rules.
        foreach($config->routes as $pattern => $route) {
            
            // check required fields.
            if (empty($route->controller)) {
                throw new Exception('e_route_controller_undefined', 0, [$pattern]);
            }

            // if request match route rule.
            if (preg_match("%^{$pattern}%ui", $request, $matches)) {
                
                // set default values.
                empty($route->params) && $route->params = [];
                empty($route->action) && $route->action = 'default';
                
                // setup route.
                self::$_controller = strtolower($route->controller);
                self::$_action = strtolower($route->action);
                
                // process params.
                unset($matches[0]);
                self::$_params = self::_makeParams($route->params ? $route->params : $matches, $request);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Make params from request or route configuration
     * @param type $params
     * @param type $toExtract
     * @return array Params
     */
    private static function _makeParams($params, $toExtract = null) {
        // if params is preconfigured. 
        if (is_array($params)) {
            return array_values($params);
        }
        
        // add missing slash.
        if (!preg_match('/\/$/', $params)) {
            $params .= '\/';
        }
        
        // prepare pattern.
        $params = "%{$params}$%ui";
        
        // extract params from string.
        $matches = [];
        if (preg_match($params, $toExtract, $matches, null, 0)) {
            unset($matches[0]);
            return array_values($matches);
        }
        return [];
    }
    
    /**
     * Define destination automatically
     * @param array $request Request params
     */
    private static function _autoRoute(array $request) {
        // get controller, action, params.
        self::$_controller = !empty($request[0]) ? strtolower($request[0]) : 'index';
        self::$_action = !empty($request[1]) ? strtolower($request[1]) : 'default';
        if (count($request) > 2) {
            for ($i = 2; $i < count($request); $i++) { 
                self::$_params[] = $request[$i];
            }
        }
    }
    
    /**
     * Load current controller
     * @return boolean
     */
    private static function _loadController() {
        $path = new Path(sprintf('application/controllers/%s.php', self::$_controller));
        if (file_exists($path)) {
            require_once $path;
            $controller = sprintf('Controller%s', self::$_controller);
            $action = sprintf('action%s', self::$_action);
            if (class_exists($controller) && method_exists($controller, $action)) {
                $controller = new $controller(self::$_controller, self::$_action);
                call_user_func_array([$controller, $action], self::$_params);
                return true;
            }
        }
        return false;
    }
    
    const ERROR_FORBIDDEN = 403;
    const ERROR_NOT_FOUND = 404;
    const ERROR_INTERNAL = 500;
    
    /**
     * 
     * @param type $code
     * @param type $message
     */
    static function sendError($code = self::ERROR_INTERNAL, $message = null) {
        if ($code == 403) {
            $header = "403 Forbidden";
        } elseif ($code == 404) {
            $header = "404 Not Found";
        } else {
            $header = "500 Internal Server Error";
        }
        
        if ($message) {
            self::$_params[0] = $message;
        } else {
            self::$_params[0] = null;
            $message = $header;
        }
        
        
        header("HTTP/1.0 {$header}");
        header("Status: {$header}");
        self::$_controller = $code;
        self::$_action = 'default';
        if (!self::_loadController()) {
            die($message);
        }
        exit;
    }
    
    const REDIRECT_MOVED = 301;
    const REDIRECT_TEMPORARY = 302;
    
    /**
     * Redirect to specific URL
     * @param \core\Url $url URL
     */
    static function redirect($url, $code = self::REDIRECT_MOVED) {
        if (!preg_match("/^\w*\:\/\//", $url)) {
            $url = new Url($url);
        }
        
        if ($code == self::REDIRECT_TEMPORARY) {
            header('HTTP/1.1 302 Moved Temporarily');
        } else {
            header('HTTP/1.1 301 Moved Permanently');
        }        
        header("Location: {$url}");
        exit();
    }
    
    const MODE_DEFAULT = 'default';
    const MODE_AJAX = 'ajax';
    const MODE_CLI = 'cli';
    
    /**
     *
     * @var type 
     */
    static $_mode = self::MODE_DEFAULT;
    
    /**
     * 
     * @param type $mode
     * @return boolean
     */
    static function setMode($mode) {
        $reflection = new \ReflectionClass(__CLASS__);
        if (!in_array($mode, $reflection->getConstants(), true)) {
            trigger_error("Wrong application mode '{$mode}'");
            return false;
        }
        return true;
    }
    
    /**
     * Get current application mode
     * @return type
     */
    static function getMode() {
        return static::$_mode;
    }
    
    /**
     * Initialize CLI modes
     * @param type $argv Array of arguments passed to script from the command line.
     */
    static function initCLI($argv) {
        // The first argument $argv[0] is always the name that was used to run the script.
        !empty($argv[1]) && $_REQUEST['q'] = $argv[1];
        !empty($argv[2]) && $_REQUEST['l'] = $argv[2];
        self::setMode(self::MODE_CLI);
    }
    
    /**
     * Get remote (user) IP
     * @return string IP address
     */
    static function getRemoteIP() {
        $ip = "127.0.0.1";
        $sources = ['HTTP_CLIENT_IP', 
                    'HTTP_X_FORWARDED_FOR', 
                    'HTTP_X_FORWARDED',
                    'HTTP_FORWARDED_FOR',
                    'HTTP_FORWARDED',
                    'REMOTE_ADDR'];
        
        foreach($sources as $source) {
            if (getenv($source)) {
                return getenv($source);
            }
        }
        return $ip;
    }
    
    /**
     * Get application / server IP
     * @return string IP address
     */
    static function getIP() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $ip = "127.0.0.1";
            $host = gethostname();
            return $host ? gethostbyname($host) : $ip;
        }
        return exec("ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1'");
    }
    
    /**
     * Check mod_rewrite support
     * @return type
     */
    static function modRewrite() {
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            return in_array('mod_rewrite', $modules);
        } else {
            return getenv('HTTP_MOD_REWRITE') == 'On' ? true : false;
        }
    }
}