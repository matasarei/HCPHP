<?php

namespace UserBundle\Service;

use core\Cache;
use core\Config;
use core\Globals;
use UserBundle\Entity\User;
use UserBundle\Repository\UserRepository;

class AuthChecker
{
    const CONTEXT_DEFAULT = 'system';

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var Config
     */
    private $accessConfig;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->accessConfig = new Config('access', ['capabilities']);
    }

    public function checkCapability(string $name, $context = self::CONTEXT_DEFAULT, User $user = null): bool
    {
        $capabilities = $this->accessConfig->get('capabilities')->$context ?? null;

        if (null === $capabilities) {
            return false;
        }

        $capability = $capabilities->$name ?? null;

        if (null === $capability) {
            return false;
        }

        if (null === $user) {
            $user = $this->getCurrentUser();
        }

        if (
            null !== $user
            && in_array($user->getRole()->getName(), $capability->roles, true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return User|null
     */
    public function getCurrentUser()
    {
        /** @var User $cached */
        $cached = Cache::get(Authenticator::KEY_CACHE_CURRENT_USER);

        if (!$cached instanceof User) {
            $authKey = Globals::get(Authenticator::KEY_COOKIES_AUTH_KEY);

            if (null !== $authKey) {
                $user = $this->userRepository->getByAuthKey($authKey);

                if (null === $user || $user->isSuspended()) {
                    return null;
                }

                Cache::set(Authenticator::KEY_CACHE_CURRENT_USER, $user);

                return $user;
            }
        }

        return $cached;
    }
}
