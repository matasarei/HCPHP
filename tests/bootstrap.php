<?php

/**
 * Test bootstrap.
 *
 * Registers the framework autoloader the same way application/init.php does, but
 * deliberately does NOT boot the application: no session_start(), no error/exception
 * handlers, no timezone side effects. Tests that need those set them up themselves.
 *
 * @package    hcphp
 * @subpackage tests
 */

use core\Autoloader;
use core\Path;
use Tests\Support\AppConfig;

$root = dirname(__DIR__);
$autoload = $root . '/application/lib/vendor/autoload.php';

if (!file_exists($autoload)) {
    fwrite(STDERR, "Dependencies are not installed. Run: composer install\n");
    exit(1);
}

require_once $autoload;
require_once $root . '/application/core/Autoloader.php';

spl_autoload_register('core\Autoloader::load');

$loader = static function (string $path, string $class): bool {
    $file = "{$path}/{$class}.php";

    if (file_exists($file)) {
        require_once $file;

        return true;
    }

    return false;
};

Autoloader::add($root . '/application', $loader);
Autoloader::add($root . '/application/lib/', $loader);

Path::init($root, 0775, 0664);

// application/config/default.json holds per-deployment values and is not committed, but
// Application::isHttpsEnabled(), getPort() and getHost() all read it -- and Url, Globals and
// anything building a link reach one of those. Without it a fresh checkout fails ten tests
// while a developer's machine, which has the file, passes: exactly the difference CI is for.
//
// Done once here rather than per test class. A class-level teardown would delete a file the
// rest of the run still needs, and a class-level setup leaves whichever class happens to run
// first deciding whether the others work.
AppConfig::ensure();

// Removed only if this run created it; a real config is never touched.
register_shutdown_function([AppConfig::class, 'release']);
