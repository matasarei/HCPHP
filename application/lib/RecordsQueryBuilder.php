<?php

use DynamicDB\Builder\QueryBuilderInterface;
use DynamicDB\Entity\Table;

class RecordsQueryBuilder implements QueryBuilderInterface
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var string|null
     */
    private $like;

    public function __construct(Table $table, string $like)
    {
        $this->table = $table;
        $this->like = $like;
    }

    public function getLike(): string
    {
        $query = 'SELECT * FROM records';

        if (empty($this->like)) {
            return $query;
        }

        $parts = [];

        foreach ($this->table->getFields() as $field) {
            $parts[] = sprintf('%s LIKE :like', $field->getName());
        }

        return $query . ' WHERE ' . implode(' OR ', $parts);
    }

    public function getValues(): array
    {
        // Must mirror getLike(): with no search term it emits no ":like" token, and binding a
        // value the statement has no token for is an error, not a no-op.
        if (empty($this->like)) {
            return [];
        }

        return [
            'like' => '%' . $this->like . '%',
        ];
    }
}
