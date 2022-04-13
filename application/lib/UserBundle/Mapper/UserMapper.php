<?php

namespace UserBundle\Mapper;

use core\MapperInterface;
use UnexpectedValueException;
use UserBundle\Entity\User;
use UserBundle\Repository\RoleRepository;

class UserMapper implements MapperInterface
{
    /**
     * @var RoleRepository
     */
    private $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * @param User $entity
     * @return array
     */
    public function mapFromEntity($entity): array
    {
        return [
            'id' => $entity->getId(),
            'firstname' => $entity->getFirstName(),
            'lastname' => $entity->getLastName(),
            'email' => $entity->getEmail(),
            'password' => $entity->getPassword(),
            'role' => $entity->getRole()->getId(),
            'authkey' => $entity->getAuthKey(),
            'authtime' => $entity->getAuthTime(),
            'timecreated' => $entity->getTimeCreated(),
            'timemodified' => $entity->getTimeModified(),
        ];
    }

    public function mapToEntity(array $data)
    {
        $role = $this->roleRepository->get($data['role']);

        if (null === $role) {
            throw new UnexpectedValueException(sprintf('No role found for "%s"', $data['role']));
        }

        return (new User($data['email'], $data['firstname'], $role))
            ->setId($data['id'])
            ->setLastName($data['lastname'])
            ->setPassword($data['password'])
            ->setAuthKey($data['authkey'])
            ->setAuthTime($data['authtime'])
            ->setTimeCreated($data['timecreated'])
            ->setTimeModified($data['timemodified'])
        ;
    }
}
