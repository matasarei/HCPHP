<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface MapperInterface
{
    /**
     * @param Entity|object $entity
     *
     * @return array
     */
    public function mapFromEntity($entity): array;

    /**
     * @param array $data
     *
     * @return Entity
     */
    public function mapToEntity(array $data);
}
