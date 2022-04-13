<?php

namespace DynamicDB\Mapper;

use core\MapperInterface;
use core\Path;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;
use UnexpectedValueException;

class EntityMapper implements MapperInterface
{
    private $table;
    private $fileMapper;

    public function __construct(Table $table)
    {
        $this->table = $table;
        $this->fileMapper = new FileMapper();
    }

    /**
     * @param array $data
     *
     * @return DynamicEntity
     */
    public function mapToEntity(array $data)
    {
        $entity = new DynamicEntity();

        foreach ($this->table->getFields() as $field) {
            $name = $field->getName();
            $value = $data[$name] ?? $field->getDefault();

            if ($field->getType() === Field::TYPE_FILE) {
                $entity->set($name, $this->resolveFile($name, $value, $data['id'] ?? null));

                continue;
            }

            if ($field->getType() === Field::TYPE_DATETIME && !is_numeric($value)) {
                $entity->set($name, strtotime($value));

                continue;
            }

            if ($field->getType() === Field::TYPE_BOOLEAN) {
                $entity->set($name, $value ? 1 : 0);

                continue;
            }

            $entity->set($name, $value);
        }

        $entity->setId($data['id'] ?? null);

        if (isset($data['timecreated'])) {
            $entity->setTimeCreated($data['timecreated']);
        }

        if (isset($data['timemodified'])) {
            $entity->setTimeModified($data['timemodified']);
        }

        return $entity;
    }

    /**
     * @param DynamicEntity $entity
     *
     * @return array
     */
    public function mapFromEntity($entity): array
    {
        $data = [];

        foreach ($this->table->getFields() as $field) {
            $name = $field->getName();
            $data[$name] = $entity->get($name) ?? $field->getDefault();
        }

        $data['timecreated'] = $entity->getTimeCreated();
        $data['timemodified'] = $entity->getTimeModified();

        if ($entity->getId() !== null) {
            $data['id'] = $entity->getId();
        }

        return $data;
    }

    private function resolveFile(string $name, $value, int $id = null): ?File
    {
        if (is_array($value)) {
            try {
                return $this->fileMapper->mapToEntity($value);
            } catch (UnexpectedValueException $exception) {
                if ($exception->getCode() === UPLOAD_ERR_NO_FILE) {
                    return null;
                }

                throw $exception;
            }
        }

        if ($id === null) {
            return null;
        }

        $path = new Path(
            sprintf(
                'shared/dymanicdb/%d/%s.%s',
                $id,
                $name,
                pathinfo($value, PATHINFO_EXTENSION)
            )
        );
        $realPath = (string)$path;

        if (!file_exists($realPath)) {
            return null;
        }

        return new File(
            $value,
            mime_content_type($realPath) ?: null,
            $realPath,
            filesize($realPath)
        );
    }
}
