<?php

namespace Filter;

interface FilterInterface
{
    public function filter(string $content): ?string;
}
