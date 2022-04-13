<?php

use core\Command;
use core\Path;

class CachePurgeCommand extends Command
{
    public function run(): int
    {
        $cache = new Path('cache');
        $cache->rmpath(true);
        $cache->mkpath();

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
