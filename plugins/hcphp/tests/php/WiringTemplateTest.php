<?php

namespace Tests\Plugin;

use core\Container;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Architecture\WiringTest;

require_once dirname(__DIR__, 2) . '/templates/WiringTest.php';

/**
 * Runs the wiring template against containers whose answers are known: one wired right, one
 * that builds the wrong class, one missing a registration.
 */
class WiringTemplateTest extends TestCase
{
    public function testPassesForAWiringThatMatches(): void
    {
        $wiring = $this->wiring(['a' => stdClass::class], function (Container $container) {
            $container->setFactory('a', function () {
                return new stdClass();
            });
        });

        $wiring->testEveryServiceResolvesToItsClass();
        $wiring->testEveryServiceIsSharedNotRebuilt();
        $wiring->testEveryExpectedServiceIsRegistered();

        $this->addToAssertionCount(1);
    }

    public function testNamesAServiceThatResolvesToTheWrongClass(): void
    {
        $wiring = $this->wiring(['a' => TestCase::class], function (Container $container) {
            $container->setFactory('a', function () {
                return new stdClass();
            });
        });

        $message = $this->failureOf(function () use ($wiring) {
            $wiring->testEveryServiceResolvesToItsClass();
        });

        self::assertStringContainsString('a resolved to stdClass, expected ' . TestCase::class, $message);
    }

    public function testNamesAMissingRegistration(): void
    {
        $wiring = $this->wiring(['missing' => stdClass::class], function (Container $container) {
        });

        $message = $this->failureOf(function () use ($wiring) {
            $wiring->testEveryExpectedServiceIsRegistered();
        });

        self::assertStringContainsString('missing is not registered', $message);
    }

    private function failureOf(callable $test): string
    {
        try {
            $test();
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        self::fail('The wiring test passed where it should have failed');
    }

    private function wiring(array $expected, callable $register): WiringTest
    {
        return new class ($expected, $register) extends WiringTest {
            private $fixtureExpected;
            private $register;

            public function __construct(array $expected, callable $register)
            {
                parent::__construct('testEveryServiceResolvesToItsClass');
                $this->fixtureExpected = $expected;
                $this->register = $register;
            }

            protected function container(): Container
            {
                $container = new Container();
                ($this->register)($container);

                return $container;
            }

            protected function expected(): array
            {
                return $this->fixtureExpected;
            }
        };
    }
}
