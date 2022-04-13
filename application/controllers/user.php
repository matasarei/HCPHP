<?php

use core\Application;
use core\Container;
use core\Controller;
use core\Globals;
use core\Url;
use core\View;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Repository\TableRepository;
use Html\Form\Exception\InvalidDataException;
use UserBundle\Exception\InvalidCredentialsException;
use UserBundle\Form\LoginFormFactory;
use UserBundle\Repository\UserRepository;
use UserBundle\Service\AuthChecker;
use UserBundle\Service\Authenticator;

class UserController extends Controller
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * @var AuthChecker
     */
    private $authChecker;

    /**
     * @var TableRepository
     */
    private $tableRepository;

    /**
     * @var ViewFactory
     */
    private $viewFactory;

    /**
     * @var LoginFormFactory
     */
    private $loginFormFactory;

    /**
     * @var DynamicRepositoryFactory
     */
    private $repositoryFactory;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->viewFactory = $container->get('view_factory');
        $this->loginFormFactory = new LoginFormFactory();

        $this->userRepository = $container->get('repository_user');
        $this->authChecker = new AuthChecker($this->userRepository);
        $this->authenticator = $container->get('authenticator');

        $this->tableRepository = $container->get('repository_table');
        $this->repositoryFactory = $container->get('factory_dynamic_repository');
    }

    public function actionDefault(): View
    {
        $user = $this->authChecker->getCurrentUser();

        if ($user === null) {
            Application::redirect(new Url('user/login'), Application::REDIRECT_TEMPORARY);
        }

        $table = $this->tableRepository->get('records');
        $repository = $this->repositoryFactory->getRepository('records');
        $queryBuilder = new RecordsQueryBuilder($table, Globals::optional('like'));

        return $this->viewFactory
            ->createView()
            ->set('user', $user)
            ->set('table', $table)
            ->set('records', $repository->findWithQuery($queryBuilder))
        ;
    }

    public function actionLogin(): View
    {
        if ($this->authChecker->getCurrentUser() !== null) {
            Application::redirect(new Url('user'), Application::REDIRECT_TEMPORARY);
        }

        $view = $this->viewFactory->createView();
        $form = $this->loginFormFactory->createForm();

        try {
            $data = $form->getData();

            if ($data !== null) {
                $this->authenticator->login($data['email'], $data['password'], !empty($data['remember_me']));

                Application::redirect(new Url('user'), Application::REDIRECT_TEMPORARY);
            }
        } catch (InvalidCredentialsException | InvalidDataException $exception) {
            $view->set('error', $exception->getMessage());
        }

        $view->set('form', $form);

        return $view;
    }

    public function actionLogout(): void
    {
        $this->authenticator->logout();

        Application::redirect(new Url('/'));
    }
}
