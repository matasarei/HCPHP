<?php

namespace Tests\Unit\Extracted;

use PHPUnit\Framework\TestCase;

/**
 * <ExtractedClass> on its own, built from plain doubles. (S1)
 *
 * The characterization pin drives the facade, so it covers this class only while the facade
 * still delegates to it. A later slice removes that delegation; this test is what covers the
 * class then. It lands in the same pull request as the extraction.
 *
 * - Build the class with every collaborator passed in — that is its constructor's whole
 *   contract. A collaborator is a small anonymous class or a closure-backed double; a double
 *   records what it was asked and answers what the case needs.
 * - One test per behaviour, the error paths included: the missing record, the empty result,
 *   the failed write.
 * - Mutation-check each one: revert or alter the line it is about and see it fail for the right
 *   reason. Name the mutation in the pull request.
 *
 * Copied from the hcphp plugin's templates/. Replace every <placeholder>.
 */
class ExtractedClassTest extends TestCase
{
    public function testDoesItsOneJob(): void
    {
        // $repository = new class implements <RepositoryInterface> {
        //     public $asked = [];
        //     public function find(array $conditions = [], array $params = []) {
        //         $this->asked[] = $conditions;
        //         return new \core\Collection([...]);
        //     }
        //     ...
        // };
        //
        // $subject = new <ExtractedClass>($repository);
        //
        // self::assertSame(<exact result>, $subject-><method>(<input>));
        // self::assertSame([[<exact conditions>]], $repository->asked);

        self::markTestIncomplete('Fill in from the template: see the class docblock.');
    }

    public function testHandlesTheMissingRecord(): void
    {
        self::markTestIncomplete('Fill in from the template: see the class docblock.');
    }
}
