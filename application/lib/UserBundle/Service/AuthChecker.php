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

    /**
     * @var int How long a key stays usable after the login that issued it
     */
    private $loginTime;

    public function __construct(
        UserRepository $userRepository,
        int $loginTime = Authenticator::DEFAULT_LOGIN_TIME
    ) {
        $this->userRepository = $userRepository;
        $this->loginTime = $loginTime;
        $this->accessConfig = new Config('access', ['capabilities']);
    }

    public function checkCapability(string $name, $context = self::CONTEXT_DEFAULT, ?User $user = null): bool
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
     * The key was written with an expiry on the cookie, but a cookie expiry is only a request
     * to the browser. Without checking it here a captured key worked forever.
     */
    private function hasExpired(User $user): bool
    {
        $authTime = (int)$user->getAuthTime();

        return $authTime <= 0 || $authTime + $this->loginTime < time();
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

                if (null === $user || $user->isSuspended() || $this->hasExpired($user)) {
                    return null;
                }

                Cache::set(Authenticator::KEY_CACHE_CURRENT_USER, $user);

                return $user;
            }
        }

        return $cached;
    }
}
