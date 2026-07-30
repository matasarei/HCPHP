<?php

use core\Globals;
use core\View;
use UserBundle\Service\AuthChecker;

class ViewFactory
{
    private $authChecker;

    /**
     * Takes the shared AuthChecker rather than building its own, so every caller agrees on
     * how long a login lasts.
     */
    public function __construct(AuthChecker $authChecker)
    {
        $this->authChecker = $authChecker;
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
