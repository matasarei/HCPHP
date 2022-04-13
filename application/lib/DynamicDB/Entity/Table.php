<?php

namespace DynamicDB\Entity;

use core\Collection;
use core\Entity;

class Table extends Entity
{
    private $name;
    private $title;
    private $fields;

    public function __construct(string $name, string $title)
    {
        $this->name = $name;
        $this->title = $title;
        $this->fields = new Collection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function addField(Field $field): self
    {
        $this->fields->add($field);

        return $this;
    }

    /**
     * @return Collection|Field[]
     */
    public function getFields(): Collection
    {
        return $this->fields;
    }
}
