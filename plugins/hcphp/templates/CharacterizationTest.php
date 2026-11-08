<?php

namespace Tests\Unit\Characterization;

use PHPUnit\Framework\TestCase;

/**
 * Pins what <Class>::<method>() does today, before slice <id> moves it. (S0)
 *
 * A characterization test asserts current behaviour — including behaviour that looks wrong. It
 * is green before the move and must stay green after it, unchanged; that is the proof the move
 * changed nothing. A behaviour that looks like a bug is pinned as it is and named in the pull
 * request as "candidate for a separate fix"; it is never fixed here.
 *
 * Rules for the assertions (trap-catalog.md §6):
 * - exact values with assertSame, never "contains" or "not empty";
 * - order asserted where the caller depends on it (ids in order, not a count);
 * - the SQL itself where the method builds SQL, not a substring of it;
 * - real entities as inputs, not anonymous stubs, once entity shape matters;
 * - every test seen red under a mutation of the code it pins, with the mutation named in the PR.
 *
 * Copied from the hcphp plugin's templates/. Replace every <placeholder>.
 */
class CharacterizationTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore any global state the test touched — $_SESSION, $_POST, container entries,
        // core\Path roots — unconditionally, so moving test files cannot change results.
        parent::tearDown();
    }

    public function testPinsTheMainPathExactly(): void
    {
        // $database = new \Tests\Support\RecordingDatabase(...);   // records the SQL it is handed
        // $subject = new <Class>(<collaborators>);
        //
        // $result = $subject-><method>(<input that exercises the main path>);
        //
        // self::assertSame(<exact value today>, $result);
        // self::assertSame(['<exact SQL today>'], $database->getStatements());

        self::markTestIncomplete('Fill in from the template: see the class docblock.');
    }

    public function testPinsTheQuirkAsItIsToday(): void
    {
        // The behaviour that looks wrong: an empty(0) limit that becomes 100, a scalar condition
        // that matches nothing, an order that is id ASC where time DESC was meant. Pinned, named,
        // left for its own pull request.

        self::markTestIncomplete('Fill in from the template: see the class docblock.');
    }
}
