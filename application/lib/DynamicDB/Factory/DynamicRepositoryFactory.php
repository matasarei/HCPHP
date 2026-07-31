<?php

namespace DynamicDB\Factory;

use core\Cache;
use core\DatabaseSQL;
use DynamicDB\Repository\DynamicRepository;
use DynamicDB\Repository\TableRepository;
use RuntimeException;

class DynamicRepositoryFactory
{
    private $database;
    private $tableRepository;

    public function __construct(DatabaseSQL $database, TableRepository $tableRepository)
    {
        $this->database = $database;
        $this->tableRepository = $tableRepository;
    }

    public function getRepository(string $tableName): DynamicRepository
    {
        $cached = Cache::get($this->getCacheKey($tableName));

        if ($cached instanceof DynamicRepository) {
            return $cached;
        }

        $table = $this->tableRepository->get($tableName);

        if ($table === null) {
            // $table is null on this branch, which is how it got here: the message named the
            // thing that was missing rather than the table that was asked for.
            throw new RuntimeException(sprintf('Missing configuration for table %s', $tableName));
        }

        $repository = new DynamicRepository($this->database, $table);
        Cache::set($this->getCacheKey($tableName), $repository);

        return $repository;
    }

    private function getCacheKey(string $tableName): string
    {
        return 'dynamic_repository_' . $tableName;
    }
}
