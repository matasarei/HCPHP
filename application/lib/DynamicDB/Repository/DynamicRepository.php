<?php

namespace DynamicDB\Repository;

use core\Collection;
use core\DatabaseSQL;
use core\Repository;
use DynamicDB\Builder\QueryBuilderInterface;
use DynamicDB\Entity\Table;
use DynamicDB\Mapper\EntityMapper;

class DynamicRepository extends Repository
{
    /**
     * @var DatabaseSQL
     */
    protected $database;

    protected $table;

    public function __construct(DatabaseSQL $database, Table $table)
    {
        parent::__construct($database, new EntityMapper($table));

        $this->table = $table;
    }

    public function findWithQuery(QueryBuilderInterface $queryBuilder): Collection
    {
        $records = $this->database->getRecordsSQL($queryBuilder->getLike(), $queryBuilder->getValues());
        $collection = new Collection();

        foreach ($records as $record) {
            $collection->add($this->mapper->mapToEntity($record));
        }

        return $collection;
    }

    protected function getCollectionName(): string
    {
        return $this->table->getName();
    }

    public function getTable(): Table
    {
        return $this->table;
    }
}
