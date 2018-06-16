<?php

namespace core;

use core\Exception;

/**
 * HCPHP Application instance (static class)
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Application 
{    
    // Modes.
    const MODE_DEFAULT = 'default';
    const MODE_XHR = 'xhr';
    const MODE_CLI = 'cli';
    
    // Responses.
    const STATUS_DEFAULT = 200;
    const STATUS_CREATED = 201;
    const STATUS_ACCEPTED = 202;
    
    // Redirects.
    const REDIRECT_MOVED = 301;
    const REDIRECT_FOUND = 302;
    const REDIRECT_TEMPORARY = 307;
    
    // Errors.
    const ERROR_BAD_REQUEST = 400;
    const ERROR_UNAUTHORIZED = 401;
    const ERROR_FORBIDDEN = 403;
    const ERROR_NOT_FOUND = 404;
    const ERROR_WRONG_METHOD = 405;
    const ERROR_INTERNAL = 500;
    const ERROR_NOT_IMPLEMENTED = 501;
    
    /** 
     * @var array Status message list 
     */
    private static $_messages = [
        200 => "200 OK",
        201 => "201 Created",
        202 => "202 Accepted",
        301 => "301 Moved Permanently",
        302 => "302 Found",
        307 => "307 Temporary Redirect",
        400 => "400 Bad request",
        401 => "401 Unauthorized",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        500 => "500 Internal Server Error",
        501 => "501 Not Implemented"
    ];
    
    /** @var string */
    static $_mode = self::MODE_DEFAULT;
    
    // Static only.
    private function __construct()
    {
    }
    
    private function __clone() 
    {
    }
    
    /**
     * Current controller name
     * @var string 
     */
    private static $_controller = null;
    
    /**
     * Returns controller name
     * 
     * @return string
     */
    public static function getController() 
    {
        return self::$_controller;
    }
    
    /**
     * Current action name
     * @var string
     */
    private static $_action = null;
    
    /**
     * Returns action name
     * 
     * @return string
     */
    public static function getAction() 
    {
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
    static function start() 
    {
        // load config.
        $config = new Config('default', ['lang' => 'en']);
        
        // get request.
        $url = preg_replace("@(^\/+|(\/)\/+)@", "$2", filter_var(Globals::optional('q'), FILTER_SANITIZE_URL), -1);
        $request = empty($url) ? [] : preg_split('@/@', $url, NULL, PREG_SPLIT_NO_EMPTY);

        // trigger init event.
        Events::triggerEvent('Init', [
            'config' => $config,
            'url' => &$url,
            'request' => &$request
        ]);
        
        // init .
        $lang = Globals::optional('l', $config->lang);
        try {
            Language::setDefau-lt($lang);
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
        if (!self::_findRoute($url)) {
            // if not found, setup automatically.
            self::_autoRoute($request);
        }
        
        // application start event.
        Events::triggerEvent('Start', [
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
     * 
     * @param string $request full request string
     * 
     * @return boolean
     */
    private static function _findRoute($request) 
    {
        $config = new Config('routing', ['routes']);
        $matches = [];
        
        // check preconfigured route rules.
        foreach($config->routes as $pattern => $route) {
            
            // check required fields.
            if (empty($route->controller)) {
                throw new Exception('e_undefined_controller', 0, [$pattern]);
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
     * 
     * @param array|string $params Prepared params or match pattern
     * @param string $toExtract String to extract params from
     * 
     * @return array Params
     */
    private static function _makeParams($params, $toExtract = null) 
    {
        // if params is preconfigured. 
        if (is_array($params)) {
            return array_values($params);
        }
        
        // add missing slash.
        if (!preg_match('/\/$/', $params)) {
            $params .= '\/';
        }
        
        // prepare pattern.
        $pattern = "%{$params}$%ui";
        
        // extract params from string.
        $matches = [];
        if (preg_match($pattern, $toExtract, $matches, null, 0)) {
            unset($matches[0]);
            return array_values($matches);
        }
        return [];
    }
    
    /**
     * Define destination automatically
     * 
     * @param array $request Request params
     */
    private static function _autoRoute(array $request) 
    {
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
     * 
     * @return boolean
     */
    private static function _loadController() 
    {
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
    
    /**
     * @param int $code Status code
     * 
     * @param string $message Error message (optional)
     */
    static function sendError($code = self::ERROR_INTERNAL, $message = null) 
    {
        $header = self::getMessage(intval($code));
        
        if (empty($header)) {
            $header = $message;
        }
        
        if (empty($message)) {
            $message = $header;
            self::$_params[0] = null;
        } else {
            self::$_params[0] = $message;
        }
        
        header("HTTP/1.1 {$header}");
        header("Status: {$header}");
        self::$_controller = $code;
        self::$_action = 'default';
        
        if (!self::_loadController()) {
            exit($message);
        }
        
        exit;
    }
    
    /**
     * Send response or request to specified URI
     * 
     * @param array $data Assoc data array
     * @param \core\Url $uri Handler URI
     * 
     * @return Request result
     */
    static function sendData(array $data, $uri = null, $status = self::STATUS_DEFAULT) 
    {
        if ($uri) {
            $url = new Url($uri, $data);
            return file_get_contents($url);
        }
        
        $header = self::getMessage(intval($status));
        
        if (!empty($header)) {
            header("HTTP/1.1 {$header}");
            header("Status: {$header}");
        }
        
        header('Content-type: application/json');
        echo json_encode($data);
        exit();
    }
    
    /**
     * Redirect to specific URL
     * 
     * @param string $url Url
     * 
     * @param int $code Status code (REDIRECT STATUSES ONLY!)
     */
    static function redirect($url, $code = self::REDIRECT_MOVED) 
    {
        if (!preg_match("/^\w*\:\/\//", $url)) {
            $url = new Url($url);
        }

        $header = self::getMessage($code);
        header("HTTP/1.1 {$header}");
        header("Status: {$header}");
        header("Location: {$url}");
        exit();
    }
    
    /**
     * Get status message
     * 
     * @param int $status
     */
    public static function getMessage($status) 
    {
        if (empty(self::$_messages[$status])) {
            return null;
        }
        
        return self::$_messages[$status];
    }
    
    /**
     * Set app mode
     * 
     * @param string $mode
     * 
     * @return boolean
     */
    static function setMode($mode) 
    {
        $reflection = new \ReflectionClass(__CLASS__);
        
        if (!in_array($mode, $reflection->getConstants(), true)) {
            trigger_error("Wrong application mode '{$mode}'");
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current application mode
     * 
     * @return string
     */
    static function getMode() 
    {
        return static::$_mode;
    }
    
    /**
     * Init CLI mode.
     * 
     * @param array $argv Array of arguments passed to script from command line.
     */
    static function initCLI($argv) 
    {
        // The first argument $argv[0] is always current users name.
        !empty($argv[1]) && $_REQUEST['q'] = $argv[1];
        !empty($argv[2]) && $_REQUEST['l'] = $argv[2];
        self::setMode(self::MODE_CLI);
    }
    
    /**
     * Get remote (user) IP
     * 
     * @return string IP address
     */
    static function getRemoteIP() 
    {
        $ip = "127.0.0.1";
        $sources = [
            'HTTP_CLIENT_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach($sources as $source) {    
            if (getenv($source)) {
                return getenv($source);
            }
        }
        
        return $ip;
    }
    
    /**
     * Get application / server IP
     * 
     * @return string IP address
     */
    static function getIP() 
    {
        $ip = "127.0.0.1";
        
	if (preg_match('/^win/i', PHP_OS)) {
            $host = gethostname();
            
            return $host ? gethostbyname($host) : $ip;
        }
        
        return exec(
            sprintf(
                join(' | ', [
                    'ifconfig',
                    'grep -Eo "inet (addr:)?([0-9]*\.){3}[0-9]*"',
                    'grep -Eo "([0-9]*\.){3}[0-9]*"',
                    "grep -v '%s'"
                ]),
                $ip
            )
        );
    }
    
    /**
     * Check mod_rewrite support
     * 
     * @return boolean
     */
    static function modRewrite() 
    {
	if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            
            return in_array('mod_rewrite', $modules);
        } else {
            if (preg_match("/nginx/", filter_input(INPUT_SERVER, 'SERVER_SOFTWARE'))) {
                return true;
            }
            
            return getenv('HTTP_MOD_REWRITE') == 'On';
        }
        
        return false;
    }
        
    /**
     * Get application host address (domain / ip)
     * 
     * @return strting
     */
    static function getHost() 
    {
        $host = filter_input(INPUT_SERVER, 'HTTP_HOST');
        
        return $host ? $host : getenv('SERVER_ADDR');
    }
    
    /**
     * Get previous page / action URL
     * 
     * @return \core\Url
     */
    static function backUrl() {
        $url = new Url(filter_input(INPUT_SERVER, 'HTTP_REFERER'));
        
        if (self::getHost() === $url->getHost()) {
            return $url;
        }
        
        return null;
    }
    
    /**
     * 
     * 
     * @return int
     */
    
    /**
     * Get / set max upload filesize
     * 
     * @param null $val New upload filezise (in bytes)
     * @param bool $raw Get raw value (in bytes), otherwise return size in.
     * 
     * @return int Current max filesize
     */
    static function maxUploadFilesize($val = null, $raw = false) 
    {
        $getKBytes = function($raw) {
            $vals = ['k' => 1, 'm' => 1024, 'g' => pow(1024, 2)];
            $match = [];
            
            if (preg_match("/(\d+)(\w)/", strtolower($raw), $match, null, 0)) {
                $multiplier = key_exists($match[2], $vals) ? $vals[$match[2]] : 0;
                return $match[1] * $multiplier;
            }
            
            return 0;
        };
        
        if ($val) {
            
            if (!preg_match("/(\d+)(\w)/", strtolower($val))) {
                return false;
            }
            ini_set('post_max_size', $val);
            ini_set('upload_max_filesize', $val);
        }

        $post_filesize = $getKBytes(ini_get('post_max_size'));
        $upload_filesize = $getKBytes(ini_get('upload_max_filesize'));
        
        if (
            ($post_filesize < 1 && $upload_filesize) || 
            ($upload_filesize < $post_filesize && $upload_filesize > 0)
        ) {
            return $raw ? ini_get('upload_max_filesize') : $upload_filesize;
        }
        
        return $raw ? ini_get('post_max_size') : $post_filesize;
    }
}
