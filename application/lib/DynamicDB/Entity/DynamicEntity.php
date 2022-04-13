<?php

namespace DynamicDB\Entity;

use core\Entity;

class DynamicEntity extends Entity
{
    protected $data = [];

    public function __construct()
    {
        $this
            ->setTimeModified(time())
            ->setTimeCreated(time())
        ;
    }

    public function setTimeCreated(int $time): self
    {
        $this->set('timecreated', $time);

        return $this;
    }

    public function getTimeCreated(): int
    {
        return $this->data['timecreated'];
    }

    public function setTimeModified(int $time): self
    {
        $this->set('timemodified', $time);

        return $this;
    }

    public function get($name)
    {
        return $this->__get($name);
    }

    public function set(string $name, $value): self
    {
        $this->__set($name, $value);

        return $this;
    }

    public function getTimeModified(): int
    {
        return $this->data['timemodified'];
    }

    public function __get(string $name)
    {
        if ($name === 'id') {
            return $this->getId();
        }

        return $this->data[$name] ?? null;
    }

    public function __set(string $name, $value)
    {
        $this->data[$name] = $value;
    }
}
