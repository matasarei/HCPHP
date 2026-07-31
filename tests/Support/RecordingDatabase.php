<?php

namespace Tests\Support;

use core\DatabaseSQL;

/**
 * A real sqlite connection that records the SQL handed to executeSQL() instead of running it.
 *
 * The DynamicDB field classes generate MySQL DDL (ALTER TABLE ... ENUM(...), UNSIGNED, and so
 * on) which sqlite cannot execute and no test should need a MySQL server to check. What
 * matters is the statement they build, so that is what this captures. Reads still go to the
 * real connection, which keeps isExist() and friends honest.
 */
class RecordingDatabase extends DatabaseSQL
{
    /**
     * @var string[]
     */
    private $statements = [];

    /**
     * Canned answers for getResultSQL(), which DatabaseManager uses to ask MySQL whether a
     * table or column already exists. sqlite has no SHOW TABLES, and the point of those tests
     * is the decision taken from the answer, not the answer itself.
     *
     * @var array<string, mixed>|null null leaves getResultSQL alone
     */
    private $schemaAnswers = null;

    public function __construct()
    {
        parent::__construct(DatabaseSQL::DRIVER_SQLITE);
    }

    /**
     * @param array<string, mixed> $answers substring of the query => value to return
     */
    public function answerSchemaQueries(array $answers): void
    {
        $this->schemaAnswers = $answers;
    }

    public function getResultSQL(string $sql, array $conditions = [])
    {
        if ($this->schemaAnswers === null) {
            return parent::getResultSQL($sql, $conditions);
        }

        $this->statements[] = $sql;

        foreach ($this->schemaAnswers as $needle => $value) {
            if (strpos($sql, $needle) !== false) {
                return $value;
            }
        }

        return null;
    }

    public function executeSQL(string $sql, array $values = [])
    {
        $this->statements[] = $sql;

        return 0;
    }

    /**
     * @return string[]
     */
    public function getStatements(): array
    {
        return $this->statements;
    }

    public function getLastStatement(): string
    {
        return end($this->statements) ?: '';
    }

    /**
     * Collapses the runs of whitespace the heredoc-style SQL in these classes is full of, so
     * assertions can be written the way the statement reads.
     */
    public function getLastStatementNormalised(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->getLastStatement()));
    }

    public function reset(): void
    {
        $this->statements = [];
    }
}
