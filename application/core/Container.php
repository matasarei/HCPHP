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

    /**
     * Factories that have not been resolved yet, name => callable.
     *
     * An entry lives here until the service is first asked for; get() then runs it, stores
     * the object in $collection and drops the factory. Nothing is ever in both.
     *
     * @var callable[]
     */
    private $factories = [];

    public function __construct()
    {
        $this->collection = new Collection();
    }

    /**
     * Whether $name can be resolved, without resolving it.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name): bool
    {
        return $this->collection->offsetExists($name) || isset($this->factories[$name]);
    }

    public function get($name)
    {
        if ($this->collection->offsetExists($name)) {
            return $this->collection->offsetGet($name);
        }

        if (isset($this->factories[$name])) {
            $factory = $this->factories[$name];

            // Dropped before it is invoked: a factory that asks for its own name then finds
            // nothing registered and reports that, instead of recursing until the process
            // runs out of stack.
            unset($this->factories[$name]);

            // set() validates the result, so a factory returning a non-object is rejected on
            // the same terms as a direct set().
            $this->set($name, $factory($this));

            return $this->collection->offsetGet($name);
        }

        throw new InvalidArgumentException(sprintf('Object `%s` is not registered', $name));
    }

    /**
     * Register a service to be built on first use.
     *
     * Init registers every service up front, and eagerly constructing them means a request
     * that only renders a static page still opens a database connection. A factory defers
     * that to the first get(); the result is stored and shared exactly like set().
     *
     * The factory receives this container, so it can resolve its own dependencies, and must
     * return an object.
     *
     * @param string $name
     * @param callable $factory
     */
    public function setFactory(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
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
