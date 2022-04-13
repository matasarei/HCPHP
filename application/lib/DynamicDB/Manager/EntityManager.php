<?php

namespace DynamicDB\Manager;

use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use DynamicDB\Repository\DynamicRepository;

final class EntityManager
{
    private $table;
    private $repository;

    public function __construct(Table $table, DynamicRepository $repository)
    {
        $this->table = $table;
        $this->repository = $repository;
    }

    public function save(DynamicEntity $entity, DynamicEntity $oldEntity = null): DynamicEntity
    {
        if ($oldEntity !== null) {
            foreach ($this->table->getFields() as $field) {
                $value = $entity->get($field->getName());

                if ($field->getType() === Field::TYPE_FILE && $value === null) {
                    continue;
                }

                $oldEntity->set($field->getName(), $value);
            }
        }

        $this->repository->save($oldEntity ?? $entity);
        (new FileManager($this->table))->saveFiles($oldEntity ?? $entity);

        return $oldEntity ?? $entity;
    }

    public function delete(DynamicEntity $entity): void
    {
        (new FileManager($this->table))->deleteFiles($entity);

        $this->repository->remove($entity);
    }
}
