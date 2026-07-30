<?php

namespace UserBundle\Service;

use core\Cache;
use core\Csrf;
use core\Events;
use core\Globals;
use Exception;
use UserBundle\Entity\User;
use UserBundle\Exception\InvalidCredentialsException;
use UserBundle\Repository\UserRepository;

class Authenticator
{
    const DEFAULT_LOGIN_TIME = 604800;

    /**
     * Bytes of entropy behind an auth key.
     */
    const AUTH_KEY_BYTES = 32;

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

        // Always a fresh key. Reusing the previous one kept a stolen key working across
        // logins, and the old sha1($id . time()) was derivable: for a known user and a
        // one-day window that is roughly 86,400 candidates to try offline.
        $user->setAuthKey(self::generateAuthKey());
        $user->setAuthTime(time());
        $this->repository->save($user);

        Globals::set(self::KEY_COOKIES_AUTH_KEY, $user->getAuthKey(), $remember, $this->loginTime);
        Cache::set(self::KEY_CACHE_CURRENT_USER, $user);

        Events::triggerEvent(self::LOGIN_EVENT, ['user' => $user]);
    }

    public function logout()
    {
        // Clearing the cookie is not enough: the key stays valid in the database, so anyone
        // holding a copy stays logged in. Retire it server-side first.
        $authKey = Globals::get(self::KEY_COOKIES_AUTH_KEY);

        if (is_string($authKey) && $authKey !== '') {
            $user = $this->repository->getByAuthKey($authKey);

            if ($user instanceof User) {
                $user->setAuthKey(null)->setAuthTime(0);
                $this->repository->save($user);
            }
        }

        Cache::remove(self::KEY_CACHE_CURRENT_USER);
        Csrf::reset();
        Globals::reset([self::KEY_COOKIES_AUTH_KEY]);
    }

    /**
     * @return string
     *
     * @throws Exception When no source of randomness is available
     */
    public static function generateAuthKey(): string
    {
        return bin2hex(random_bytes(self::AUTH_KEY_BYTES));
    }
}
