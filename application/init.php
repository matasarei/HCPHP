<?php
/**
 * Init script
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160526
 */

require_once __DIR__ . '/core/Autoloader.php';

use core\Autoloader;

///
// init autoloader.
///
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

use core\Application,
    core\Debug,
    core\Path,
    core\Config,
    core\Globals;

// init cli request.
if (php_sapi_name() === 'cli') {
    Application::initCLI($argv);
}

// init debug.
Debug::init(E_ALL);
// init path (set root dir).
Path::init(dirname(__DIR__));
// init globals interface.
Globals::init();

// init debug.
$default = new Config('default', ['debug' => 0]);
$default->debug = is_numeric($default->debug) ? $default->debug : constant($default->debug);
Debug::mode($default->debug);

///
// global functions.
///
function x($var) {
    Debug::dump($var, true, debug_backtrace());
}