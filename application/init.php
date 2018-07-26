<?php
/**
 * Init script
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20180727
 */

require_once __DIR__ . '/core/Autoloader.php';

// Init autoloader.
\core\Autoloader::addPath(__DIR__);
\core\Autoloader::addPath(__DIR__ . '/lib/');

// Init cli request.
if (php_sapi_name() === 'cli') {
    \core\Application::initCLI($argv);
}

// Init debug.
\core\Debug::init(E_ALL);

// Init path builder (set root dir).
\core\Path::init(dirname(__DIR__));

// Init globals interface.
\core\Globals::init();

// Init debug.
$default = new \core\Config('default', ['debug' => 0]);
$default->debug = is_numeric($default->debug) ? $default->debug : constant($default->debug);
\core\Debug::mode($default->debug);

// Composer autoloader.
$path = new \core\Path('application/lib/vendor/autoload.php');

if (file_exists($path)) {
    (include $path);
}

///
// Global functions.
///
function x($var) {
    \core\Debug::dump($var, true, debug_backtrace());
}
