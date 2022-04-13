<?php

namespace core;

use InvalidArgumentException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Container
{
    /**
     * @var Collection
     */
    private $collection;

    public function __construct()
    {
        $this->collection = new Collection();
    }

    public function get($name)
    {
        if (!$this->collection->offsetExists($name)) {
            throw new InvalidArgumentException(sprintf('Object `%s` is not registered', $name));
        }

        return $this->collection->offsetGet($name);
    }

    public function set($name, $object)
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(
                sprintf('Wrong value provided. Expected object, %s given.', gettype($object))
            );
        }

        $this->collection->offsetSet($name, $object);
    }
}
