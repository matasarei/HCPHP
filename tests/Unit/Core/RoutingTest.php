<?php

namespace Tests\Unit\Core;

use core\Application;
use PHPUnit\Framework\TestCase;
use Tests\Support\Reflect;
use ReflectionClass;
use RuntimeException;

/**
 * Routing decides which controller runs, so a mistake here is the difference between a page
 * and a 404 -- or between two different pages. The methods are private because nothing but
 * start() should call them, which is exactly why they are reached by reflection here rather
 * than left untested.
 */
class RoutingTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetDispatchState();
    }

    private function resetDispatchState(): void
    {
        $reflection = new ReflectionClass(Application::class);

        foreach (['controllerName', 'actionName', 'requestParameters'] as $name) {
            $property = Reflect::property($reflection->getName(), $name);
            $property->setValue(null, $name === 'requestParameters' ? [] : null);
        }
    }

    private function call(string $method, array $args)
    {
        $reflection = Reflect::method(Application::class, $method);

        return $reflection->invokeArgs(null, $args);
    }

    private function requestParameters(): array
    {
        return Reflect::getStatic(Application::class, 'requestParameters');
    }

    // --- findRoute ---------------------------------------------------------------------------

    public function testAConfiguredRouteIsMatched(): void
    {
        self::assertTrue($this->call('findRoute', ['records/add']));
        self::assertSame('records', Application::getControllerName());
        self::assertSame('edit', Application::getActionName());
    }

    public function testAnUnmatchedRequestFindsNothing(): void
    {
        self::assertFalse($this->call('findRoute', ['nothing/matches/this']));
    }

    /**
     * The captured groups of the pattern become the action's arguments.
     */
    public function testCapturedGroupsBecomeTheParameters(): void
    {
        self::assertTrue($this->call('findRoute', ['records/42/edit']));

        self::assertSame('records', Application::getControllerName());
        self::assertSame('edit', Application::getActionName());
        self::assertSame(['42'], $this->requestParameters());
    }

    public function testSeveralGroupsAreAllCaptured(): void
    {
        self::assertTrue($this->call('findRoute', ['records/7/download/report']));

        self::assertSame(['7', 'report'], $this->requestParameters());
    }

    /**
     * The first matching pattern wins, so a more specific route has to be declared before the
     * general one it would otherwise be swallowed by.
     */
    public function testTheFirstMatchingRouteWins(): void
    {
        $this->call('findRoute', ['records/add']);

        self::assertSame('edit', Application::getActionName(), 'records/add beats records/(\d+)');
    }

    public function testControllerAndActionAreLowercased(): void
    {
        $this->call('findRoute', ['user/5']);

        self::assertSame('user', Application::getControllerName());
        self::assertSame(strtolower(Application::getActionName()), Application::getActionName());
    }

    // --- prepareRequestParameters -------------------------------------------------------------

    public function testAnArrayOfParametersIsReindexed(): void
    {
        self::assertSame(
            ['a', 'b'],
            $this->call('prepareRequestParameters', [[3 => 'a', 7 => 'b'], null])
        );
    }

    public function testAPatternCapturesFromTheRequest(): void
    {
        self::assertSame(
            ['42'],
            $this->call('prepareRequestParameters', ['records\/(\d+)', 'records/42/'])
        );
    }

    /**
     * A pattern that does not already end at a separator gets one, so "records/4" does not
     * match a request for "records/42".
     */
    public function testAPatternIsAnchoredAtASeparator(): void
    {
        self::assertSame([], $this->call('prepareRequestParameters', ['records\/(\d+)', 'other/path']));
    }

    public function testAnEmptyParameterSetIsEmpty(): void
    {
        self::assertSame([], $this->call('prepareRequestParameters', [[], null]));
    }

    // --- autoRoute ----------------------------------------------------------------------------

    public function testAutoRouteReadsControllerAndActionFromThePath(): void
    {
        $this->call('autoRoute', [['user', 'login']]);

        self::assertSame('user', Application::getControllerName());
        self::assertSame('login', Application::getActionName());
    }

    public function testAutoRouteFallsBackToIndexAndDefault(): void
    {
        $this->call('autoRoute', [[]]);

        self::assertSame('index', Application::getControllerName());
        self::assertSame('default', Application::getActionName());
    }

    public function testAutoRouteFallsBackForAnEmptySegment(): void
    {
        $this->call('autoRoute', [['', '']]);

        self::assertSame('index', Application::getControllerName());
        self::assertSame('default', Application::getActionName());
    }

    public function testAutoRouteLowercases(): void
    {
        $this->call('autoRoute', [['User', 'Login']]);

        self::assertSame('user', Application::getControllerName());
        self::assertSame('login', Application::getActionName());
    }

    public function testAutoRouteKeepsTheRemainingSegmentsAsParameters(): void
    {
        $this->call('autoRoute', [['user', 'view', '7', 'extra']]);

        self::assertSame(['7', 'extra'], $this->requestParameters());
    }

    public function testAutoRouteWithNoExtraSegmentsHasNoParameters(): void
    {
        $this->call('autoRoute', [['user', 'view']]);

        self::assertSame([], $this->requestParameters());
    }
}
