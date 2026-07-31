<?php

use core\Cache;
use core\Command;
use core\Template;

class CachePurgeCommand extends Command
{
    public function run(): int
    {
        // Each store is cleared by the class that owns it. This used to rm -r the whole
        // cache/ tree from outside, which happened to work but knew about the layout of two
        // other classes and would have taken anything else stored there with it.
        Cache::purge(Cache::CACHE_STATIC);
        Template::purgeCaches();

        return 0;
    }

    protected function parseArguments(array $args)
    {
    }

    protected function getHelp(): string
    {
        return 'Cache clean-up';
    }
}
