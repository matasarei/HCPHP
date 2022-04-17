<?php

namespace DynamicDB\Manager;

use core\Path;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;

final class FileManager
{
    private $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function saveFiles(DynamicEntity $dynamicEntity): void
    {
        foreach ($this->table->getFields() as $field) {
            $file = $dynamicEntity->get($field->getName());

            if (!($file instanceof File) || !$file->isTemporary()) {
                continue;
            }

            $path = new Path(
                sprintf(
                    'shared/dynamicdb/%d/%s.%s',
                    $dynamicEntity->getId(),
                    $field->getName(),
                    pathinfo($file->getName(), PATHINFO_EXTENSION)
                )
            );
            $path->mkpath();

            move_uploaded_file($file->getPath(), (string)$path);
        }
    }

    public function deleteFiles(DynamicEntity $dynamicEntity): void
    {
        foreach ($this->table->getFields() as $field) {
            $file = $dynamicEntity->get($field->getName());

            if (!($file instanceof File)) {
                continue;
            }

            //unlink($file->getPath());
        }
    }
}
