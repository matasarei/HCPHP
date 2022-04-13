<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface DatabaseInterface
{
    /**
     * @param string $collection
     * @param array $conditions
     *
     * @return array|false
     */
    public function getRecord(string $collection, array $conditions);

    /**
     * @param string $collection
     * @param array $record
     *
     * @return string|int Record ID
     */
    public function insertRecord(string $collection, array $record);

    /**
     * @param string $collection
     * @param array $record
     * @param array|string[] $keys
     *
     * @return int Affected records (count)
     */
    public function updateRecord(string $collection, array $record, array $keys = ['id']);

    /**
     * @param string $collection
     * @param array $record
     * @param array|string[] $key
     *
     * @return bool
     */
    public function replaceRecord(string $collection, array $record, array $key = ['id']);

    /**
     * @param string $collection
     * @param array $conditions
     * @param int|null $limit
     *
     * @return array
     */
    public function getRecords(string $collection, array $conditions, int $limit = null): array;

    /**
     * @param string $collection
     * @param array $conditions
     *
     * @return int Affected records (count)
     */
    public function deleteRecords(string $collection, array $conditions);
}
