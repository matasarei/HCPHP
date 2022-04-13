<?php

namespace DynamicDB;

use core\Config;

final class DynamicDbConfigLoader
{
    public static function load(): Config
    {
        static $config;

        if (empty($config)) {
            $config = new Config('dynamicdb', ['tables']);
        }

        return $config;
    }
}
