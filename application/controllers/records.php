<?php

use core\Application;
use core\Container;
use core\Controller;
use core\Response;
use core\Url;
use DynamicDB\Builder\FormBuilder;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Manager\EntityManager;
use DynamicDB\Mapper\EntityMapper;
use DynamicDB\Repository\DynamicRepository;
use DynamicDB\Repository\TableRepository;
use UserBundle\Service\AuthChecker;

class RecordsController extends Controller
{
    /**
     * @var AuthChecker
     */
    private $authChecker;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var Table
     */
    private $table;

    /**
     * @var DynamicRepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var DynamicRepository
     */
    private $repository;

    /**
     * @var ViewFactory
     */
    private $viewFactory;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->authChecker = new AuthChecker($container->get('repository_user'));

        $this->viewFactory = $container->get('view_factory');
        $this->repositoryFactory = $container->get('factory_dynamic_repository');
        $this->repository = $this->repositoryFactory->getRepository('records');

        /** @var TableRepository $tableRepository */
        $tableRepository = $container->get('repository_table');
        $this->table = $tableRepository->get('records');
        $this->entityManager = new EntityManager($this->table, $this->repository);
    }

    public function actionView(string $id)
    {
        if (!$this->authChecker->checkCapability('view_records')) {
            return new Response('Access denied', Response::STATUS_FORBIDDEN);
        }

        $entity = $this->repositoryFactory->getRepository('records')->get($id);

        if ($entity === null) {
            return new Response('Record not found', Response::STATUS_NOT_FOUND);
        }

        return $this->viewFactory
            ->createView()
            ->set('entity', $entity)
            ->set('table', $this->table)
        ;
    }

    public function actionEdit(string $id = null)
    {
        if (!$this->authChecker->checkCapability('edit_records')) {
            return new Response('Access denied', Response::STATUS_FORBIDDEN);
        }

        $entity = null;

        if ($id !== null) {
            $entity = $this->repository->get($id);

            if ($entity === null) {
                return new Response('Record not found', Response::STATUS_NOT_FOUND);
            }
        }

        $builder = new FormBuilder($this->table, $this->repositoryFactory);
        $form = $builder->getEditForm($entity, new Url('user'));
        $view = $this->viewFactory->createView()->set('form', $form);

        try {
            $data = $form->getData();

            if ($data !== null) {
                $dataObject = (new EntityMapper($this->table))->mapToEntity($data);
                $entity = $this->entityManager->save($dataObject, $entity);

                Application::redirect(
                    new Url('/records/' . $entity->getId()),
                    Application::REDIRECT_TEMPORARY
                );
            }
        } catch (Exception $exception) {
            $view->set('exception', $exception);
        }

        return $view;
    }

    public function actionDelete(string $id): ?Response
    {
        if (!$this->authChecker->checkCapability('edit_records')) {
            return new Response('Access denied', Response::STATUS_FORBIDDEN);
        }

        $entity = $this->repository->get($id);
        $this->entityManager->delete($entity);

        Application::redirect(new Url('/user/'));

        return null;
    }

    public function actionDownload(string $id, string $field)
    {
        if (!$this->authChecker->checkCapability('get_files')) {
            return new Response('Access denied', Response::STATUS_FORBIDDEN);
        }

        /** @var DynamicEntity $entity */
        $entity = $this->repository->get($id);

        $file = $entity->get($field);

        if (!($file instanceof File)) {
            return new Response('File not found', Response::STATUS_NOT_FOUND);
        }

        return $file;
    }
}
