<?php

namespace Tests\Unit\Core;

use core\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Container had no way to ask whether a name was registered and no way to defer
 * construction, so events/Init.php built every service on every request -- a database
 * connection included -- whether or not the route touched them.
 *
 * @covers \core\Container
 */
class ContainerTest extends TestCase
{
    public function testHasIsFalseForAnUnknownName(): void
    {
        self::assertFalse((new Container())->has('nothing'));
    }

    public function testHasIsTrueForAnEagerlySetObject(): void
    {
        $container = new Container();
        $container->set('service', new stdClass());

        self::assertTrue($container->has('service'));
    }

    public function testHasIsTrueForARegisteredFactoryBeforeItIsResolved(): void
    {
        $container = new Container();
        $container->setFactory('service', function () {
            throw new RuntimeException('has() must not resolve the factory');
        });

        self::assertTrue($container->has('service'));
    }

    public function testFactoryIsNotCalledUntilTheServiceIsRequested(): void
    {
        $calls = 0;
        $container = new Container();
        $container->setFactory('service', function () use (&$calls) {
            $calls++;

            return new stdClass();
        });

        self::assertSame(0, $calls, 'registering a factory must not build anything');

        $container->get('service');

        self::assertSame(1, $calls);
    }

    public function testFactoryResultIsSharedAcrossCalls(): void
    {
        $container = new Container();
        $container->setFactory('service', function () {
            return new stdClass();
        });

        self::assertSame($container->get('service'), $container->get('service'));
    }

    public function testFactoryRunsOnlyOnceEvenWhenRequestedRepeatedly(): void
    {
        $calls = 0;
        $container = new Container();
        $container->setFactory('service', function () use (&$calls) {
            $calls++;

            return new stdClass();
        });

        $container->get('service');
        $container->get('service');
        $container->get('service');

        self::assertSame(1, $calls);
    }

    public function testFactoryReceivesTheContainerSoItCanResolveDependencies(): void
    {
        $container = new Container();
        $dependency = new stdClass();
        $container->set('dependency', $dependency);

        $container->setFactory('service', function (Container $c) {
            $service = new stdClass();
            $service->dependency = $c->get('dependency');

            return $service;
        });

        self::assertSame($dependency, $container->get('service')->dependency);
    }

    /**
     * A factory that asks for its own name would otherwise recurse until the process died.
     * The factory is removed before it is invoked, so the second lookup finds nothing
     * registered and reports that instead of hanging.
     */
    public function testSelfReferentialFactoryFailsFastRatherThanLoopingForever(): void
    {
        $container = new Container();
        $container->setFactory('service', function (Container $c) {
            return $c->get('service');
        });

        $this->expectException(InvalidArgumentException::class);

        $container->get('service');
    }

    public function testAnEagerlySetObjectWinsOverAFactoryOfTheSameName(): void
    {
        $object = new stdClass();
        $container = new Container();
        $container->setFactory('service', function () {
            throw new RuntimeException('the eager object should have been used');
        });
        $container->set('service', $object);

        self::assertSame($object, $container->get('service'));
    }

    public function testUnknownNameStillThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Container())->get('nothing');
    }

    /**
     * set() rejects a non-object, so a factory returning one has to be rejected too --
     * otherwise the container's contract holds only for the eager path.
     */
    public function testFactoryReturningANonObjectIsRejected(): void
    {
        $container = new Container();
        $container->setFactory('service', function () {
            return 'not an object';
        });

        $this->expectException(InvalidArgumentException::class);

        $container->get('service');
    }
}
