<?php

namespace DynamicDB\Builder;

interface QueryBuilderInterface
{
    public function getLike(): string;
    public function getValues(): array;
}
