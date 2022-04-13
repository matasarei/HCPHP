<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface RepositoryInterface
{
    /**
     * @param int|string $id
     *
     * @return Entity
     */
    public function get($id);

    /**
     * @param Entity $entity
     */
    public function save($entity);

    /**
     * @param Entity $entity
     */
    public function remove($entity);

    public function find(array $conditions = [], array $params = []): Collection;
}
