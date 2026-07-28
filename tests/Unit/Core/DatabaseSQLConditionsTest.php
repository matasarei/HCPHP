<?php

namespace Tests\Unit\Core;

use core\DatabaseSQL;
use PHPUnit\Framework\TestCase;

/**
 * A condition value is data, never syntax.
 *
 * DatabaseSQL used to inspect condition *values* and silently promote any value
 * containing "%" from "=" to "LIKE". That turned every lookup whose value comes from
 * user input into a wildcard search -- most damagingly the auth-key lookup, where the
 * cookie "%" matched every row and authenticated the attacker as the first user.
 *
 * These tests pin the operator to equality regardless of the value's contents.
 *
 * @covers \core\DatabaseSQL
 */
class DatabaseSQLConditionsTest extends TestCase
{
    /**
     * @var DatabaseSQL
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);

        $this->database->getDBH()->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                email TEXT NOT NULL,
                authkey TEXT
            )'
        );

        foreach (
            [
                ['alice@example.com', 'key_alice_9f3c2b'],
                ['bob@example.com', 'key_bob_77aa10'],
                ['carol@example.com', 'key_carol_5521de'],
            ] as [$email, $authKey]
        ) {
            $this->database->insertRecord('users', ['email' => $email, 'authkey' => $authKey]);
        }
    }

    public function testGetRecordsMatchesAPercentValueLiterallyAndFindsNothing(): void
    {
        self::assertSame(
            [],
            $this->database->getRecords('users', ['authkey' => '%']),
            'A condition value of "%" must be matched literally, not as a wildcard.'
        );
    }

    public function testGetRecordsDoesNotAllowAPercentValueToActAsAPrefixOracle(): void
    {
        self::assertSame(
            [],
            $this->database->getRecords('users', ['authkey' => 'key_%']),
            'A trailing "%" must not turn an exact lookup into a prefix search.'
        );
    }

    public function testGetRecordReturnsNothingForAPercentValue(): void
    {
        self::assertFalse(
            $this->database->getRecord('users', ['authkey' => '%']),
            'getRecord() must not return a user for the wildcard value "%".'
        );
    }

    public function testGetRecordStillResolvesAGenuineKey(): void
    {
        $record = $this->database->getRecord('users', ['authkey' => 'key_bob_77aa10']);

        self::assertIsArray($record);
        self::assertSame('bob@example.com', $record['email']);
    }

    public function testAValueThatLegitimatelyContainsAPercentIsMatchedExactly(): void
    {
        $this->database->insertRecord('users', ['email' => 'dan@example.com', 'authkey' => '50%_off']);

        $records = $this->database->getRecords('users', ['authkey' => '50%_off']);

        self::assertCount(1, $records, 'A stored value containing "%" must be findable by its exact value.');
        self::assertSame('dan@example.com', $records[0]['email']);
    }

    public function testDeleteRecordsWithAPercentValueDeletesNothing(): void
    {
        $deleted = $this->database->deleteRecords('users', ['authkey' => '%']);

        self::assertSame(0, $deleted, 'A wildcard value must not delete every row in the table.');
        self::assertCount(3, $this->database->getRecords('users'));
    }

    public function testUpdateRecordsWithAPercentValueUpdatesNothing(): void
    {
        $updated = $this->database->updateRecords('users', ['email' => 'owned@example.com'], ['authkey' => '%']);

        self::assertSame(0, $updated, 'A wildcard value must not update every row in the table.');
        self::assertCount(0, $this->database->getRecords('users', ['email' => 'owned@example.com']));
    }

    public function testEqualityAndInConditionsAreUnaffected(): void
    {
        self::assertCount(1, $this->database->getRecords('users', ['email' => 'alice@example.com']));
        self::assertCount(
            2,
            $this->database->getRecords('users', ['email' => ['alice@example.com', 'carol@example.com']]),
            'Array conditions must still compile to IN (...).'
        );
    }
}
