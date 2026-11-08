<?php

namespace Tests\Unit;

use core\Container;
use core\Csrf;
use core\Response;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Table;
use DynamicDB\Factory\DynamicRepositoryFactory;
use DynamicDB\Repository\DynamicRepository;
use DynamicDB\Repository\TableRepository;
use Html\Form\Form;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RecordsController;
use RuntimeException;
use UserBundle\Service\AuthChecker;

/**
 * /records/<id>/delete deleted on a plain GET, with no token: the capability check alone stood
 * between a link and a lost record. Any page, image or redirect the editor's browser followed
 * -- `<img src="/records/12/delete">` on another site -- deleted it with the editor's session.
 *
 * Deleting now takes a POST carrying the form token. A GET or a missing or wrong token deletes
 * nothing.
 */
class RecordsDeleteTest extends TestCase
{
    /**
     * @var DynamicRepository&MockObject
     */
    private $repository;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/application/controllers/records.php';
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    private function controller(): RecordsController
    {
        $authChecker = $this->createMock(AuthChecker::class);
        $authChecker->method('checkCapability')->willReturn(true);

        $this->repository = $this->createMock(DynamicRepository::class);
        $this->repository->method('get')->willReturn(new DynamicEntity());

        $repositoryFactory = $this->createMock(DynamicRepositoryFactory::class);
        $repositoryFactory->method('getRepository')->willReturn($this->repository);

        $tableRepository = $this->createMock(TableRepository::class);
        $tableRepository->method('get')->willReturn(new Table('records', 'Records'));

        $container = new Container();
        $container->set('auth_checker', $authChecker);
        $container->set('view_factory', new \stdClass());
        $container->set('factory_dynamic_repository', $repositoryFactory);
        $container->set('repository_table', $tableRepository);

        return new RecordsController($container);
    }

    public function testGetDeletesNothing(): void
    {
        $controller = $this->controller();
        $this->repository->expects(self::never())->method('remove');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET[Form::KEY_SESSION] = Csrf::getToken();

        $response = $controller->actionDelete('12');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::STATUS_METHOD_NOT_ALLOWED, $response->getResponseCode());
    }

    public function testPostWithoutTokenDeletesNothing(): void
    {
        $controller = $this->controller();
        $this->repository->expects(self::never())->method('remove');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Csrf::getToken();

        $response = $controller->actionDelete('12');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::STATUS_FORBIDDEN, $response->getResponseCode());
    }

    public function testPostWithWrongTokenDeletesNothing(): void
    {
        $controller = $this->controller();
        $this->repository->expects(self::never())->method('remove');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Csrf::getToken();
        $_POST[Form::KEY_SESSION] = str_repeat('0', 64);

        $response = $controller->actionDelete('12');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::STATUS_FORBIDDEN, $response->getResponseCode());
    }

    public function testPostWithTokenDeletes(): void
    {
        $controller = $this->controller();
        // The redirect after a delete exits the process; the sentinel stops the action first.
        $this->repository->expects(self::once())->method('remove')
            ->willThrowException(new RuntimeException('removed'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[Form::KEY_SESSION] = Csrf::getToken();

        $this->expectExceptionMessage('removed');

        $controller->actionDelete('12');
    }
}
