<?php

namespace DynamicDB\Mapper;

use core\MapperInterface;
use DynamicDB\Entity\Table;
use RuntimeException;

class TableMapper implements MapperInterface
{
    private $fieldMapper;

    public function __construct()
    {
        $this->fieldMapper = new FieldMapper();
    }

    public function mapToEntity(array $data): Table
    {
        $table = new Table($data['name'], $data['title']);

        foreach ($data['fields'] as $field) {
            $table->addField($this->fieldMapper->mapToEntity($field));
        }

        return $table;
    }

    public function mapFromEntity($entity): array
    {
        throw new RuntimeException('Not supported');
    }
}
