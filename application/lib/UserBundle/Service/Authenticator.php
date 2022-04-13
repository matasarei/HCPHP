<?php

namespace UserBundle\Service;

use core\Cache;
use core\Events;
use core\Globals;
use UserBundle\Entity\User;
use UserBundle\Exception\InvalidCredentialsException;
use UserBundle\Repository\UserRepository;

class Authenticator
{
    const DEFAULT_LOGIN_TIME = 604800;
    const KEY_CACHE_CURRENT_USER = 'current_user';
    const KEY_COOKIES_AUTH_KEY = 'auth_key';
    const LOGIN_EVENT = 'Login';

    private $loginTime;
    private $repository;

    public function __construct(
        UserRepository $repository,
        int $loginTime = self::DEFAULT_LOGIN_TIME
    ) {
        $this->repository = $repository;
        $this->loginTime = $loginTime;
    }

    /**
     * @param string $email
     * @param string $password
     * @param bool $remember
     *
     * @throws InvalidCredentialsException
     */
    public function login(string $email, string $password, bool $remember = false)
    {
        $user = $this->repository->findOne(['email' => $email]);

        if (
            !$user instanceof User
            || !password_verify($password, $user->getPassword())
        ) {
            throw new InvalidCredentialsException('Invalid credentials');
        }

        $this->logout();

        if ($user->getAuthKey() === null) {
            $user->setAuthKey(sha1($user->getId() . time()));
        }

        $user->setAuthTime(time());
        $this->repository->save($user);

        Globals::set(self::KEY_COOKIES_AUTH_KEY, $user->getAuthKey(), $remember, $this->loginTime);
        Cache::set(self::KEY_CACHE_CURRENT_USER, $user);

        Events::triggerEvent(self::LOGIN_EVENT, ['user' => $user]);
    }

    public function logout()
    {
        Globals::reset([self::KEY_COOKIES_AUTH_KEY]);
    }
}
