<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Repository implements RepositoryInterface
{
    /**
     * @var DatabaseInterface
     */
    protected $database;

    /**
     * @var MapperInterface
     */
    protected $mapper;

    public function __construct(
        DatabaseInterface $database,
        MapperInterface $mapper
    ) {
        $this->database = $database;
        $this->mapper = $mapper;
    }

    /**
     * @param string|int $id Object ID
     *
     * @return Entity|null
     */
    public function get($id)
    {
        $data = $this->database->getRecord(
            $this->getCollectionName(),
            [
                'id' => $id
            ]
        );

        if (empty($data)) {
            return null;
        }

        return $this->mapper->mapToEntity($data);
    }

    /**
     * @param Entity $entity
     */
    public function save($entity)
    {
        $data = $this->mapper->mapFromEntity($entity);

        if ($entity->getId() !== null) {
            $this->database->updateRecord($this->getCollectionName(), $data);

            return;
        }

        $id = $this->database->insertRecord($this->getCollectionName(), $data);

        $entity->setId($id);
    }

    /**
     * @param Entity $entity
     */
    public function remove($entity)
    {
        $this->database->deleteRecords(
            $this->getCollectionName(),
            [
                'id' => $entity->getId()
            ]
        );
    }

    /**
     * @param array $conditions Search conditions
     * @param array $params Search parameters (like limits etc.)
     *
     * @return Collection|Entity[]
     */
    public function find(array $conditions = [], array $params = []): Collection
    {
        $records = $this->database->getRecords(
            $this->getCollectionName(),
            $conditions,
            $params['limit'] ?? null
        );

        $collection = new Collection();

        foreach ($records as $record) {
            $collection->add($this->mapper->mapToEntity($record));
        }

        return $collection;
    }

    /**
     * @param array $conditions
     *
     * @return Entity|null
     */
    public function findOne(array $conditions = [])
    {
        $entity = $this->find($conditions, ['limit' => 1])->current();

        if (empty($entity)) {
            return null;
        }

        return $entity;
    }

    /**
     * Get collection / database name
     *
     * @return string
     */
    abstract protected function getCollectionName(): string;
}
