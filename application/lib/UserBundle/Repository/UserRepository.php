<?php

namespace UserBundle\Repository;

use core\Repository;
use UserBundle\Entity\User;

class UserRepository extends Repository
{
    /**
     * @param string $authKey
     *
     * @return User|null
     */
    public function getByAuthKey(string $authKey)
    {
        return $this->findOne(['authkey' => $authKey]);
    }

    protected function getCollectionName(): string
    {
        return 'users';
    }
}
