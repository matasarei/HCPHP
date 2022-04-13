<?php

namespace DynamicDB\Entity;

use core\Entity;

class File extends Entity
{
    private $name;
    private $type;
    private $path;
    private $size;
    private $temporary;

    public function __construct(
        string $name,
        ?string $type,
        string $path,
        int $size,
        bool $temporary = false
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->path = $path;
        $this->size = $size;
        $this->temporary = $temporary;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }

    public function __toString()
    {
        return $this->getName();
    }
}
