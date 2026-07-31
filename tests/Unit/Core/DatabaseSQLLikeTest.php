<?php

namespace Tests\Unit\Core;

use core\DatabaseSQL;
use core\Like;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * LIKE is still available -- it just has to be asked for explicitly.
 */
class DatabaseSQLLikeTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);

        $this->database->getDBH()->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)'
        );

        foreach (
            [
                'alice@example.com',
                'bob@example.com',
                'carol@other.org',
                '100%_discount@example.com',
            ] as $email
        ) {
            $this->database->insertRecord('users', ['email' => $email]);
        }
    }

    public function testExplicitLikeMatchesAWildcardPattern(): void
    {
        $records = $this->database->getRecords('users', ['email' => new Like('%@example.com')]);

        self::assertCount(3, $records);
    }

    public function testExplicitLikeMatchesEveryRowWhenAskedTo(): void
    {
        self::assertCount(4, $this->database->getRecords('users', ['email' => new Like('%')]));
    }

    public function testContainsEscapesWildcardsInUntrustedInput(): void
    {
        $records = $this->database->getRecords('users', ['email' => Like::contains('100%_discount')]);

        self::assertCount(1, $records, 'Wildcards in the search term must be matched literally.');
        self::assertSame('100%_discount@example.com', $records[0]['email']);
    }

    public function testContainsWithABareWildcardMatchesOnlyRowsHoldingThatCharacter(): void
    {
        $records = $this->database->getRecords('users', ['email' => Like::contains('%')]);

        self::assertCount(
            1,
            $records,
            'A user typing "%" into a search box must match only rows literally containing "%".'
        );
        self::assertSame('100%_discount@example.com', $records[0]['email']);
    }

    public function testStartsWithAndEndsWith(): void
    {
        self::assertCount(1, $this->database->getRecords('users', ['email' => Like::startsWith('alice')]));
        self::assertCount(1, $this->database->getRecords('users', ['email' => Like::endsWith('other.org')]));
    }

    public function testUnderscoreIsEscapedToo(): void
    {
        $this->database->insertRecord('users', ['email' => 'aXc@example.com']);
        $this->database->insertRecord('users', ['email' => 'a_c@example.com']);

        $records = $this->database->getRecords('users', ['email' => Like::startsWith('a_c')]);

        self::assertCount(1, $records, '"_" is a single-character wildcard and must be escaped.');
        self::assertSame('a_c@example.com', $records[0]['email']);
    }

    public function testEscapeCharacterItselfIsEscaped(): void
    {
        $this->database->insertRecord('users', ['email' => 'we!rd@example.com']);

        $records = $this->database->getRecords('users', ['email' => Like::contains('we!rd')]);

        self::assertCount(1, $records);
        self::assertSame('we!rd@example.com', $records[0]['email']);
    }

    public function testLikeWorksInDeleteConditions(): void
    {
        $deleted = $this->database->deleteRecords('users', ['email' => new Like('%@other.org')]);

        self::assertSame(1, $deleted);
        self::assertCount(3, $this->database->getRecords('users'));
    }

    public function testLikeInsideAnInConditionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // SQL has no "IN (LIKE ...)". Binding it silently would match the pattern
        // literally and return quietly wrong rows, so refuse it instead.
        $this->database->getRecords('users', ['email' => [new Like('%@example.com'), 'bob@example.com']]);
    }

    public function testPatternIsReadableAsAString(): void
    {
        $like = new Like('%foo%');

        self::assertSame('%foo%', $like->getPattern());
        self::assertSame('%foo%', (string)$like);
    }
}
