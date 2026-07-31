<?php

namespace Tests\Support;

use core\Path;

/**
 * Makes application/config/default.json available to tests that reach code reading it.
 *
 * Application::isHttpsEnabled(), getPort() and getHost() each build a Config('default', ...),
 * which throws when the file is absent -- and it is absent on a fresh checkout, because it
 * holds per-deployment values and is gitignored. Url touches all three in its constructor, so
 * without this the Url tests would pass only on a machine that happens to have been set up.
 *
 * The file is created from the committed sample and removed again, but ONLY when this helper
 * created it. A developer's real config is never written to and never deleted.
 */
final class AppConfig
{
    /**
     * @var bool[] name => whether ensure() created the file
     */
    private static $created = [];

    public static function ensure(string $name = 'default'): void
    {
        $path = (string)new Path(sprintf('application/config/%s.json', $name));

        if (is_readable($path)) {
            return;
        }

        $sample = $path . '.sample';

        if (!is_readable($sample)) {
            return;
        }

        copy($sample, $path);
        self::$created[$name] = true;
    }

    public static function release(string $name = 'default'): void
    {
        if (empty(self::$created[$name])) {
            return;
        }

        $path = (string)new Path(sprintf('application/config/%s.json', $name));

        if (is_file($path)) {
            unlink($path);
        }

        unset(self::$created[$name]);
    }
}
