<?php

namespace core;

use InvalidArgumentException;
use RuntimeException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Application
{
    const REDIRECT_MOVED = 301;
    const REDIRECT_TEMPORARY = 302;

    const MODE_WEB = 'web';
    const MODE_API = 'api';
    const MODE_CLI = 'cli';

    /**
     * Controller name
     *
     * @var string
     */
    private static $controllerName = null;

    /**
     * Actions name
     *
     * @var string
     */
    private static $actionName = null;

    /**
     * Request params
     *
     * @var array
     */
    private static $requestParameters = [];

    /**
     * @var string
     */
    static $mode = self::MODE_WEB;

    public static function getControllerName(): ?string
    {
        return self::$controllerName;
    }

    public static function getActionName(): ?string
    {
        return self::$actionName;
    }

    public static function getContainer(): Container
    {
        static $container;

        if (!$container instanceof Container) {
            $container = new Container();
        }

        return $container;
    }

    /**
     * Redirect to specific URL
     *
     * @param Url|string $url URL
     * @param int $code Redirect code
     */
    static function redirect($url, int $code = self::REDIRECT_MOVED)
    {
        if (!preg_match("/^\w*\:\/\//", $url)) {
            $url = new Url($url);
        }

        http_response_code($code);
        header(sprintf('Location: %s', $url));

        Application::stop();
    }

    static function setMode(string $mode)
    {
        if (!in_array($mode, [self::MODE_CLI, self::MODE_API, self::MODE_WEB], true)) {
            throw new RuntimeException('Wrong app mode provided');
        }

        self::$mode = $mode;
    }

    static function getMode(): string
    {
        return self::$mode;
    }

    static function getRemoteIp(): string
    {
        $sources = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($sources as $source) {
            if (getenv($source)) {
                return getenv($source);
            }
        }

        return '127.0.0.1';
    }

    static function getServerIp(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $ip = "127.0.0.1";
            $host = gethostname();

            return $host ? gethostbyname($host) : $ip;
        }

        return exec("ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1'");
    }

    /**
     * @param int|null $val
     * @param bool $raw
     *
     * @return bool|float|int|string
     */
    static function maxUploadFilesize(int $val = null, bool $raw = false)
    {
        $getKBytes = function($raw) {
            $values = ['k' => 1, 'm' => 1024, 'g' => pow(1024, 2)];
            $match = [];

            if (preg_match("/(\d+)(\w)/", strtolower($raw), $match, null, 0)) {
                $multiplier = key_exists($match[2], $values) ? $values[$match[2]] : 0;
                return $match[1] * $multiplier;
            }

            return 0;
        };

        if ($val === null) {
            if (!preg_match("/(\d+)(\w)/", strtolower($val))) {
                return false;
            }

            ini_set('post_max_size', $val);
            ini_set('upload_max_filesize', $val);
        }

        $post_filesize = $getKBytes(ini_get('post_max_size'));
        $upload_filesize = $getKBytes(ini_get('upload_max_filesize'));

        if (($post_filesize < 1 && $upload_filesize) || ($upload_filesize < $post_filesize && $upload_filesize > 0)) {
            return $raw ? ini_get('upload_max_filesize') : $upload_filesize;
        }

        return $raw ? ini_get('post_max_size') : $post_filesize;
    }

    static function isRewriteEnabled(): bool
    {
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            return in_array('mod_rewrite', $modules);
        }

        return getenv('HTTP_MOD_REWRITE') == 'On';
    }

    static function isHttpsEnabled(): bool
    {
        $appConfig = new Config('default', ['https' => false]);
        $serverConfig = filter_input(INPUT_SERVER, 'HTTPS');

        return $appConfig->get('https') || 'on' == $serverConfig;
    }

    /**
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    static function getServerParameter(string $name, $default = null)
    {
        $value = filter_input(INPUT_SERVER, $name);

        if (empty(trim($value))) {
            return $default;
        }

        return $value;
    }

    static function getPort(): int
    {
        $config = new Config('default', ['port' => 0]);
        $port = $config->get('port');

        if ($port > 0) {
            return (int)$port;
        }

        if (preg_match("/:(\d+)$/", filter_input(INPUT_SERVER, 'HTTP_HOST'), $matches)) {
            return (int)$matches[1];
        }

        return (int)Application::getServerParameter(
            'SERVER_PORT',
            Application::isHttpsEnabled() ? 443 : 80
        );
    }

    public static function getHost(): string
    {
        $config = new Config('default', ['hostname' => '']);
        $host = $config->get('hostname');

        if (!empty($host)) {
            return $host;
        }

        $host = preg_replace('/:\d+$/', '', filter_input(INPUT_SERVER, 'HTTP_HOST'));

        return $host ?: getenv('SERVER_ADDR');
    }

    public static function backUrl(): ?Url
    {
        $url = new Url(filter_input(INPUT_SERVER, 'HTTP_REFERER'));

        if (self::getHost() === $url->getHost()) {
            return $url;
        }

        return null;
    }

    public static function getCurrentPath(): string
    {
        return Application::getServerParameter('REQUEST_URI', Globals::optional('q'));
    }

    public static function setMemoryLimit(int $megabytes)
    {
        ini_set('memory_limit', sprintf('%dM', $megabytes));
    }

    public static function runCommand(array $argv)
    {
        Application::setMode(self::MODE_CLI);
        Events::triggerEvent('Init');

        unset($argv[0]);

        $name = array_shift($argv);

        if (empty($name)) {
            echo 'No command name provided, example: ./run cache:purge' . PHP_EOL;

            exit(1);
        }

        $path = preg_split('@:@', $name, null, PREG_SPLIT_NO_EMPTY);
        $class = implode('', $path) . 'command';
        $file = new Path('application/commands/' . implode('_', $path)) . '.php';

        if (!file_exists($file)) {
            echo $name . ' not found!' . PHP_EOL;

            exit(1);
        }

        require_once $file;

        try {
            $command = new $class(self::getContainer(), $argv);
        } catch (InvalidArgumentException $exception) {
            echo $exception->getMessage() . PHP_EOL;

            exit(1);
        }

        if (!$command instanceof Command) {
            throw new RuntimeException('Wrong class loaded, expected a "Command" class instead');
        }

        exit($command->run());
    }

    static function start()
    {
        Events::triggerEvent('Init');

        $config = new Config('default', ['lang' => 'en']);
        $query = filter_var(Globals::optional('q'), FILTER_SANITIZE_URL);
        $url = preg_replace('@(^\/+|(\/)\/+)@', "$2", $query, -1);
        $request = $url ? preg_split('@/@', $url, NULL, PREG_SPLIT_NO_EMPTY) : [];

        if (preg_match('@\.\w+$@', $query, $matches)) {
            $file = new Path($query);

            if (!file_exists($file)) {
                http_response_code(404);
                Application::stop();
            }
        }

        Language::setDefaultLanguageCode(rtrim(
            empty($_REQUEST['l']) ? $config->get('lang') : $_REQUEST['l'],
            '/'
        ));

        if (!self::findRoute($url)) {
            self::autoRoute($request);
        }

        Events::triggerEvent('Start', [
            'controller' => self::$controllerName,
            'action'     => self::$actionName,
            'params'     => self::$requestParameters
        ]);

        if (false === ($response = self::processRequest())) {
            $content = null;

            if (Debug::isOn()) {
                $content = sprintf(
                    '404: Controller "%s" or action "%s" does not exist.',
                    self::$controllerName,
                    self::$actionName
                );
            }

            $response = new Response($content, Response::STATUS_NOT_FOUND);
        }

        Events::triggerEvent('ResponseReady', [
            'response' => $response
        ]);

        if (!headers_sent()) {
            if ($response instanceof Response) {
                http_response_code($response->getResponseCode());
            }
        }

        echo $response;

        Application::stop();
    }

    public static function stop(array $context = [])
    {
        Events::triggerEvent('Stop', $context);

        exit();
    }

    /**
     * @param string $name
     *
     * @return Controller
     */
    public static function getController(string $name): ?Controller
    {
        $path = new Path(sprintf('application/controllers/%s.php', $name));

        if (file_exists($path)) {
            require_once $path;
            $className = sprintf('%sController', ucfirst($name));

            if (class_exists($className)) {
                $controller = new $className(self::getContainer());

                if ($controller instanceof Controller) {
                    return $controller;
                }
            }
        }

        return null;
    }

    /**
     * @param string $request full request string
     *
     * @return boolean
     *
     * @throws RuntimeException
     */
    private static function findRoute(string $request): bool
    {
        $config = new Config('routing', ['routes']);
        $matches = [];

        foreach($config->get('routes') as $pattern => $route) {
            if (empty($route->controller)) {
                throw new RuntimeException(
                    'Route must contain controller name',
                    'route_undefined_controller',
                    [
                        'pattern' => $pattern,
                        'route' => $request
                    ]
                );
            }

            if (!preg_match("%^{$pattern}%ui", $request, $matches)) {
                continue;
            }

            if (empty($route->params)) {
                $route->params = [];
            }

            if (empty($route->action)) {
                $route->action = 'default';
            }

            self::$controllerName = strtolower($route->controller);
            self::$actionName = strtolower($route->action);

            unset($matches[0]);
            self::$requestParameters = self::prepareRequestParameters($route->params ?: $matches, $request);

            return true;
        }

        return false;
    }

    /**
     * @param $params string|array
     *
     * @param string|null $request
     *
     * @return array
     */
    private static function prepareRequestParameters($params, string $request = null): array
    {
        if (is_array($params)) {
            return array_values($params);
        }

        if (!preg_match('/\/$/', $params)) {
            $params .= '\/';
        }

        $params = "%{$params}$%ui";
        $matches = [];

        if (preg_match($params, $request, $matches, null)) {
            unset($matches[0]);

            return array_values($matches);
        }

        return [];
    }

    private static function autoRoute(array $request)
    {
        self::$controllerName = !empty($request[0]) ? strtolower($request[0]) : 'index';
        self::$actionName = !empty($request[1]) ? strtolower($request[1]) : 'default';

        if (count($request) > 2) {
            for ($i = 2; $i < count($request); $i++) {
                self::$requestParameters[] = $request[$i];
            }
        }
    }

    /**
     * @return mixed|false
     *
     * @throws RuntimeException
     */
    private static function processRequest()
    {
        $controller = self::getController(self::$controllerName);

        if (null !== $controller) {
            $action = sprintf('action%s', self::$actionName);

            if (method_exists($controller, $action)) {
                return call_user_func_array([$controller, $action], self::$requestParameters);
            }
        }

        return false;
    }
}
