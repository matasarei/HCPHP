<?php

use core\Globals;
use core\View;
use UserBundle\Repository\UserRepository;
use UserBundle\Service\AuthChecker;

class ViewFactory
{
    private $authChecker;

    public function __construct(UserRepository $userRepository)
    {
        $this->authChecker = new AuthChecker($userRepository);
    }

    public function createView(string $name = null): View
    {
        $view = new View($name);
        $layout = $view->getLayout();

        $layout
            ->set('currentUser', $this->authChecker->getCurrentUser())
            ->set('queryString', Globals::optional('like'))
        ;

        return $view;
    }
}
