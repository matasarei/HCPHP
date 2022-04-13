<?php

namespace Html\Form;

class Option
{
    protected $value;
    protected $title;
    protected $attributes;

    public function __construct($value, string $title = null, array $attributes = [])
    {
        $this->value = $value;
        $this->title = $title;
        $this->attributes = $attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|int $value
     */
    public function setValue($value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return string|int
     */
    public function getValue()
    {
        return $this->value;
    }
}
