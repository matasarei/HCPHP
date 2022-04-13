<?php

namespace DynamicDB\Mapper;

use core\MapperInterface;
use DynamicDB\Entity\Field;
use RuntimeException;

class FieldMapper implements MapperInterface
{
    /**
     * @param array $data
     *
     * @return Field
     */
    public function mapToEntity(array $data)
    {
        $field = new Field($data['name'], $data['desc'], $data['type']);

        if (isset($data['table'])) {
            $field->setTable($data['table']);
        }

        if (isset($data['length'])) {
            $field->setLength($data['length']);
        }

        if (isset($data['default'])) {
            $field->setDefault($data['default']);
        }

        if (isset($data['default'])) {
            $field->setDefault($data['default']);
        }

        if (isset($data['values'])) {
            $field->setValues($data['values']);
        }

        if (isset($data['format'])) {
            $field->setFormat($data['format']);
        }

        if (isset($data['field'])) {
            $field->setField($data['field']);
        }

        return $field;
    }

    public function mapFromEntity($entity): array
    {
        throw new RuntimeException('Not supported');
    }
}
