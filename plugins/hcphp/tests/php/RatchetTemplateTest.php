<?php

namespace Tests\Plugin;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\ConstructionRatchetTest;

require_once dirname(__DIR__, 2) . '/templates/ConstructionRatchetTest.php';

/**
 * Runs the ratchet template against a fixture whose constructions are known, including the
 * cases name resolution gets wrong: group imports and aliases, a trait `use` inside a class,
 * a closure `use`, a braced namespace, `new X` without parentheses, a comment between `new`
 * and the name, constructions inside comments and strings,
 * dynamic and anonymous classes, and the excluded wiring file.
 */
class RatchetTemplateTest extends TestCase
{
    private const EXPECTED = [
        'App\Controller\LocalService' => ['application/controllers/rating.php' => 1],
        'App\Controller\LoggerManager' => ['application/controllers/rating.php' => 1],
        'App\Controller\Settings::getInstance' => ['application/controllers/rating.php' => 1],
        'Foo\Repo\BazRepository' => ['application/controllers/rating.php' => 2],
        'Foo\Repo\QuxRepository' => ['application/controllers/rating.php' => 1],
        'Foo\Service\BarService' => [
            'application/controllers/rating.php' => 1,
            'application/lib/Lib/Braced.php' => 1,
        ],
        'Other\ThingService' => ['application/controllers/rating.php' => 1],
    ];

    public function testCountsEveryConstructionByItsResolvedName(): void
    {
        self::assertSame(self::EXPECTED, $this->ratchet(null)->found());
    }

    public function testPassesAtExactCeilings(): void
    {
        $ratchet = $this->ratchet(array_map('array_sum', self::EXPECTED));

        // Throws AssertionFailedError, failing this test, when any count is off its ceiling.
        $ratchet->testInlineConstructionsStayAtTheirCeilings();

        $this->addToAssertionCount(1);
    }

    public function testFailsAboveACeilingAndNamesTheFix(): void
    {
        $ceilings = array_map('array_sum', self::EXPECTED);
        $ceilings['Foo\Service\BarService'] = 1;

        $message = $this->failureOf($this->ratchet($ceilings));

        self::assertStringContainsString('new Foo\Service\BarService( is built inline 2 times, ceiling 1', $message);
        self::assertStringContainsString('application/lib/Lib/Braced.php (1)', $message);
        self::assertStringContainsString('Take it from the container instead.', $message);
    }

    public function testFailsBelowACeilingAndAsksForItToBeLowered(): void
    {
        $ceilings = array_map('array_sum', self::EXPECTED);
        $ceilings['Other\ThingService'] = 3;

        $message = $this->failureOf($this->ratchet($ceilings));

        self::assertStringContainsString('lower its ceiling to 1', $message);
    }

    public function testFirstRunListsTheBaselineToPaste(): void
    {
        $message = $this->failureOf($this->ratchet([]));

        self::assertStringContainsString("'Foo\\\\Service\\\\BarService' => 2,", $message);
        self::assertStringContainsString("'App\\\\Controller\\\\Settings::getInstance' => 1,", $message);
    }

    private function failureOf(ConstructionRatchetTest $ratchet): string
    {
        try {
            $ratchet->testInlineConstructionsStayAtTheirCeilings();
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        self::fail('The ratchet passed where it should have failed');
    }

    /**
     * @param array<string, int>|null $ceilings
     */
    private function ratchet(?array $ceilings): ConstructionRatchetTest
    {
        return new class ($ceilings) extends ConstructionRatchetTest {
            private $fixtureCeilings;

            public function __construct(?array $ceilings)
            {
                parent::__construct('testInlineConstructionsStayAtTheirCeilings');
                $this->fixtureCeilings = $ceilings ?? [];
            }

            protected function root(): string
            {
                return dirname(__DIR__) . '/fixtures/ratchet';
            }

            protected function ceilings(): array
            {
                return $this->fixtureCeilings;
            }

            public function found(): array
            {
                return $this->countConstructions();
            }
        };
    }
}
