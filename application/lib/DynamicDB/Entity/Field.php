<?php

namespace DynamicDB\Entity;

use core\Entity;

class Field extends Entity
{
    const TYPE_INTEGER = 'Integer';
    const TYPE_REAL = 'Real';
    const TYPE_TEXT = 'Text';
    const TYPE_ENUM = 'Enum';
    const TYPE_FILE = 'File';
    const TYPE_RELATION = 'Relation';
    const TYPE_DATETIME = 'DateTime';
    const TYPE_JSON = "JSON";
    const TYPE_BOOLEAN = "Boolean";

    private $name;
    private $description;
    private $type;
    private $length = null;
    private $table = null;
    private $default = null;
    private $values = [];
    private $format = null;
    private $field = null;

    public function __construct(string $name, string $description, string $type)
    {
        $this->id = $this->name = $name;
        $this->description = $description;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * A Real field's length is a decimal: 10.5 means DECIMAL(10,5), ten digits before the
     * point and five after. Typing this int truncated that to 10 -- silently on PHP 7, with
     * a deprecation on 8.1+ -- so the shipped config warned on every load and the form built
     * from it used the wrong bound.
     *
     * @param int|float $length
     *
     * @return self
     */
    public function setLength($length): self
    {
        $this->length = $length;

        return $this;
    }

    /**
     * @return int|float|null
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    public function setFormat($format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function setValues(array $values): self
    {
        $this->values = $values;

        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }
}
