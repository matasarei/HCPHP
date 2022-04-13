<?php

namespace UserBundle\Mapper;

use core\MapperInterface;
use RuntimeException;
use UserBundle\Entity\Role;

class RoleMapper implements MapperInterface
{
    public function mapToEntity(array $data): Role
    {
        return new Role($data['name'], $data['desc']);
    }

    public function mapFromEntity($entity): array
    {
        throw new RuntimeException('Not supported');
    }
}
