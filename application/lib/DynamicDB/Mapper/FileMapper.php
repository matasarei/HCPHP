<?php

namespace DynamicDB\Mapper;

use core\MapperInterface;
use DynamicDB\Entity\File;
use RuntimeException;
use UnexpectedValueException;

class FileMapper implements MapperInterface
{
    /**
     * @param array $data
     *
     * @return File
     */
    public function mapToEntity(array $data)
    {
        if (!empty($data['error'])) {
            throw new UnexpectedValueException('File cannot be uploaded', (int)$data['error']);
        }

        return new File(
            $data['name'],
            $data['type'],
            $data['tmp_name'],
            $data['size'],
            true
        );
    }

    public function mapFromEntity($entity): array
    {
        throw new RuntimeException('Not supported');
    }
}
