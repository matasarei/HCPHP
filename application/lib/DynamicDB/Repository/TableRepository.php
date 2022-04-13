<?php

namespace DynamicDB\Repository;

use core\Collection;
use core\RepositoryInterface;
use DynamicDB\DynamicDbConfigLoader;
use DynamicDB\Mapper\TableMapper;
use RuntimeException;

class TableRepository implements RepositoryInterface
{
    private $tables;
    private $mapper;

    public function __construct()
    {
        $this->mapper = new TableMapper();
        $this->tables = DynamicDbConfigLoader::load()->getArray('tables');
    }

    public function get($id)
    {
        $key = array_search($id, array_column($this->tables, 'name'));

        if ($key === false) {
            return null;
        }

        return $this->mapper->mapToEntity($this->tables[$key]);
    }

    public function find(array $conditions = [], array $params = []): Collection
    {
        return new Collection(
            array_map(
                function($data) {
                    return $this->mapper->mapToEntity($data);
                },
                $this->tables
            )
        );
    }

    public function save($entity)
    {
        throw new RuntimeException('Not supported');
    }

    public function remove($entity)
    {
        throw new RuntimeException('Not supported');
    }
}
