<?php

namespace core;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Collection implements Iterator, Countable, ArrayAccess
{
    /**
     * @var array
     */
    private $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function add($element)
    {
        $this->items[] = $element;
    }

    public function contains($element): bool
    {
        return in_array($element, $this->items, true);
    }

    public function valid(): bool
    {
        return $this->current() !== false;
    }

    public function next()
    {
        next($this->items);
    }

    public function current()
    {
        return current($this->items);
    }

    public function rewind()
    {
        reset($this->items);
    }

    public function key()
    {
        return key($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
