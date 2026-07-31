<?php

namespace Tests\Unit;

use core\DatabaseSQL;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use PHPUnit\Framework\TestCase;
use RecordsQueryBuilder;

/**
 * getLike() drops the WHERE clause when there is no search term, but getValues() went on
 * returning a 'like' value regardless. Binding a parameter a statement does not contain is an
 * error rather than a no-op, so the records list -- the page every user lands on after
 * logging in -- died with
 *
 *     SQLSTATE[HY093]: Invalid parameter number: number of bound variables does not match
 *     number of tokens
 *
 * It went unnoticed because logging in was broken for an unrelated reason, so nothing reached
 * the page.
 */
class RecordsQueryBuilderTest extends TestCase
{
    private function table(): Table
    {
        return (new Table('records', 'Records'))
            ->addField(new Field('text_field', 'Text', Field::TYPE_TEXT))
            ->addField(new Field('int_field', 'Int', Field::TYPE_INTEGER))
        ;
    }

    public function testNoSearchTermProducesNoWhereClause(): void
    {
        self::assertStringNotContainsString(
            ':like',
            (new RecordsQueryBuilder($this->table(), ''))->getLike()
        );
    }

    public function testNoSearchTermBindsNothing(): void
    {
        self::assertSame(
            [],
            (new RecordsQueryBuilder($this->table(), ''))->getValues(),
            'a value bound to a token the statement does not have is an error'
        );
    }

    public function testSearchTermProducesAWhereClauseOverEveryField(): void
    {
        $sql = (new RecordsQueryBuilder($this->table(), 'abc'))->getLike();

        self::assertStringContainsString('text_field LIKE :like', $sql);
        self::assertStringContainsString('int_field LIKE :like', $sql);
        self::assertStringContainsString(' OR ', $sql);
    }

    public function testSearchTermIsBoundAsAContainsPattern(): void
    {
        self::assertSame(
            ['like' => '%abc%'],
            (new RecordsQueryBuilder($this->table(), 'abc'))->getValues()
        );
    }

    /**
     * The two halves have to agree: every token the SQL declares must be bound, and nothing
     * else may be. This is the invariant that was broken.
     */
    public function testTokensAndBoundValuesAlwaysAgree(): void
    {
        foreach (['', 'abc', '0'] as $term) {
            $builder = new RecordsQueryBuilder($this->table(), $term);

            $tokens = [];
            preg_match_all('/:(\w+)/', $builder->getLike(), $tokens);

            self::assertSame(
                array_values(array_unique($tokens[1])),
                array_keys($builder->getValues()),
                sprintf('mismatch for search term "%s"', $term)
            );
        }
    }

    /**
     * Runs the generated statement against a real connection: the failure was a PDO error at
     * execute() time, which only shows up when something actually executes it.
     */
    public function testGeneratedStatementExecutesWithoutASearchTerm(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, text_field TEXT, int_field INTEGER)');
        $database->insertRecord('records', ['text_field' => 'hello', 'int_field' => 1]);

        $builder = new RecordsQueryBuilder($this->table(), '');

        self::assertCount(1, $database->getRecordsSQL($builder->getLike(), $builder->getValues()));
    }

    public function testGeneratedStatementExecutesWithASearchTerm(): void
    {
        $database = new DatabaseSQL(DatabaseSQL::DRIVER_SQLITE);
        $database->getDBH()->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, text_field TEXT, int_field INTEGER)');
        $database->insertRecord('records', ['text_field' => 'hello', 'int_field' => 1]);
        $database->insertRecord('records', ['text_field' => 'world', 'int_field' => 2]);

        $builder = new RecordsQueryBuilder($this->table(), 'hell');

        self::assertCount(1, $database->getRecordsSQL($builder->getLike(), $builder->getValues()));
    }
}
