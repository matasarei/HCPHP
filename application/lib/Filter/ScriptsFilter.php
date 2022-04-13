<?php

namespace Filter;

class ScriptsFilter implements FilterInterface
{
    public function filter(string $content): ?string
    {
        return preg_replace("/<script.*<(\/script)?/si", "", $content, -1);
    }
}
