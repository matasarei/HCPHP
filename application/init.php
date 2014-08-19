<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */
if (version_compare(phpversion(), '5.5.0', '<')) {
     die('Requires PHP > 5.5'); 
}

define('HCPHP', true);
require_once __DIR__ . '/core/autoloader.php';

$loader = function($path, $class) {
    $path = "{$path}/{$class}.php";
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    return false;
};

Autoloader::add(__DIR__ . '/core/', $loader);
Autoloader::add(__DIR__ . '/lib/', $loader);

Debug::init();
Path::init(dirname(__DIR__));
Session::init();