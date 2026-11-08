<?php

namespace Tests\Unit\Architecture;

use core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Every service the wiring registers resolves, to the class callers expect, once.
 *
 * A constructor-injection slice adds edges to the service graph. A missing registration, a
 * factory that builds the wrong class or a cycle between two factories shows up only on the
 * page that first asks for the service — this finds it in the suite instead. Resolving the
 * whole graph here is the "boot test" the S5 recipe requires.
 *
 * Copied from the hcphp plugin's templates/. Configure container() and expected(); add a row to
 * expected() in the pull request that registers a service.
 */
class WiringTest extends TestCase
{
    /**
     * Configure: a container with the application's wiring registered, and the infrastructure it
     * would reach — the database, the mailer — replaced by test doubles registered under the
     * same ids. No real connection is opened by resolving a service.
     */
    protected function container(): Container
    {
        $container = new Container();

        // $container->set('database', new \Tests\Support\RecordingDatabase(...));
        // (new \Wiring())->register($container);   // however this application registers

        return $container;
    }

    /**
     * Configure: service id => the class it must resolve to.
     *
     * @return array<string, string>
     */
    protected function expected(): array
    {
        return [
            // 'repository_user' => \UserBundle\Repository\UserRepository::class,
        ];
    }

    public function testEveryServiceResolvesToItsClass(): void
    {
        $container = $this->container();
        $wrong = [];

        foreach ($this->expected() as $id => $class) {
            $service = $container->get($id);

            if (!$service instanceof $class) {
                $wrong[] = sprintf('%s resolved to %s, expected %s', $id, get_class($service), $class);
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }

    public function testEveryServiceIsSharedNotRebuilt(): void
    {
        $container = $this->container();
        $rebuilt = [];

        foreach (array_keys($this->expected()) as $id) {
            if ($container->get($id) !== $container->get($id)) {
                $rebuilt[] = $id;
            }
        }

        self::assertSame([], $rebuilt, 'Built anew on every get(): ' . implode(', ', $rebuilt));
    }

    public function testEveryExpectedServiceIsRegistered(): void
    {
        // has() answers without building, so a missing registration is named here on its own,
        // not as the "not registered" error of whichever factory happened to ask for it first.
        $container = $this->container();

        foreach (array_keys($this->expected()) as $id) {
            self::assertTrue($container->has($id), "$id is not registered");
        }
    }
}
