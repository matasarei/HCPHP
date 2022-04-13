<?php

namespace Html\Form\Exception;

use Exception;
use Html\Form\Field;

class InvalidDataException extends Exception
{
    /**
     * @var Field[]
     */
    protected $fields = [];

    public function addField(Field $field)
    {
        $this->fields[$field->getName()] = $field;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
