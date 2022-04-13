<?php

namespace UserBundle\Manager;

use InvalidArgumentException;
use UserBundle\Entity\User;
use UserBundle\Repository\RoleRepository;
use UserBundle\Repository\UserRepository;

class UserManager
{
    private $userRepository;
    private $roleRepository;

    public function __construct(UserRepository $userRepository, RoleRepository $roleRepository)
    {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
    }

    public function createUser(
        string $email,
        string $firstname,
        string $password,
        string $roleName = 'user'
    ): User {
        $userRole = $this->roleRepository->get($roleName);

        if ($userRole === null) {
            throw new InvalidArgumentException('Wrong role name');
        }

        $user = (new User($email, $firstname, $userRole))
            ->setPassword(password_hash($password, PASSWORD_DEFAULT))
        ;

        $this->userRepository->save($user);

        return $user;
    }
}
