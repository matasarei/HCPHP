<?php

use core\Autoloader;
use core\Config;
use core\Debug;
use core\Globals;
use core\Path;

require_once __DIR__ . '/core/Autoloader.php';
spl_autoload_register('core\Autoloader::load');

$loader = function($path, $class) {
    $path = "{$path}/{$class}.php";

    if (file_exists($path)) {
        require_once $path;
        return true;
    }

    return false;
};
Autoloader::add(__DIR__, $loader);
Autoloader::add(__DIR__ . '/lib/', $loader);

Debug::init(E_ALL);
Path::init(dirname(__DIR__), 0775, 0664);
Globals::init();

$default = new Config('default', ['debug' => 0, 'timezone' => '']);

$debugMode = $default->get('debug');
$default->debug = is_numeric($debugMode) ? $debugMode : constant($debugMode);
Debug::mode($default->debug);

$path = new Path('application/lib/vendor/autoload.php');

if (file_exists($path)) {
    (include $path);
}

if (!$default->isEmpty('timezone')) {
    date_default_timezone_set($default->get('timezone'));
}

// Globals.
function x($var)
{
    Debug::dump($var, true, debug_backtrace());
}
