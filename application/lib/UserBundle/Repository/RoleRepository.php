<?php

namespace UserBundle\Repository;

use core\Collection;
use core\Config;
use core\MapperInterface;
use core\RepositoryInterface;
use RuntimeException;
use UserBundle\Entity\Role;
use UserBundle\Mapper\RoleMapper;

class RoleRepository implements RepositoryInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var RoleMapper
     */
    private $mapper;

    public function __construct(MapperInterface $roleMapper)
    {
        $this->mapper = $roleMapper;
        $this->config = new Config('access', ['roles']);
    }

    /**
     * @param string $id
     *
     * @return Role|null
     */
    public function get($id)
    {
        $item = $this->find(['name' => $id])->current();

        if ($item instanceof Role) {
            return $item;
        }

        return null;
    }

    public function find(array $conditions = [], array $params = []): Collection
    {
        $collection = new Collection();

        foreach ($this->config->get('roles') as $role) {
            $entity = $this->mapper->mapToEntity((array)$role);

            if (count($conditions) === 0) {
                $collection->add($entity);

                continue;
            }

            if ($entity->getName() === ($conditions['name'] ?? null)) {
                $collection->add($entity);
            }
        }

        return $collection;
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
