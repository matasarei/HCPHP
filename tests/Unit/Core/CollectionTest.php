<?php

namespace Tests\Unit\Core;

use core\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \core\Collection
 */
class CollectionTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $collection = new Collection();

        self::assertCount(0, $collection);
        self::assertSame([], $collection->getItems());
    }

    public function testWrapsAnExistingArray(): void
    {
        $collection = new Collection(['a', 'b']);

        self::assertCount(2, $collection);
        self::assertSame(['a', 'b'], $collection->getItems());
    }

    public function testAddAppends(): void
    {
        $collection = new Collection(['a']);
        $collection->add('b');

        self::assertSame(['a', 'b'], $collection->getItems());
    }

    /**
     * contains() is a strict comparison, so a matching value of another type is not a match.
     */
    public function testContainsIsStrict(): void
    {
        $collection = new Collection([1, '2']);

        self::assertTrue($collection->contains(1));
        self::assertTrue($collection->contains('2'));
        self::assertFalse($collection->contains('1'));
        self::assertFalse($collection->contains(2));
        self::assertFalse($collection->contains(99));
    }

    public function testIteratesInOrder(): void
    {
        $seen = [];

        foreach (new Collection(['a', 'b', 'c']) as $key => $value) {
            $seen[$key] = $value;
        }

        self::assertSame(['a', 'b', 'c'], $seen);
    }

    public function testIterationCanBeRestarted(): void
    {
        $collection = new Collection(['a', 'b']);

        foreach ($collection as $ignored) {
            // drain it
        }

        self::assertSame(['a', 'b'], iterator_to_array($collection));
    }

    public function testCurrentAndKeyTrackThePointer(): void
    {
        $collection = new Collection(['x', 'y']);
        $collection->rewind();

        self::assertSame('x', $collection->current());
        self::assertSame(0, $collection->key());
        self::assertTrue($collection->valid());

        $collection->next();

        self::assertSame('y', $collection->current());
        self::assertSame(1, $collection->key());

        $collection->next();

        self::assertFalse($collection->valid());
    }

    public function testArrayAccess(): void
    {
        $collection = new Collection(['a' => 1]);

        self::assertTrue(isset($collection['a']));
        self::assertFalse(isset($collection['b']));
        self::assertSame(1, $collection['a']);

        $collection['b'] = 2;
        self::assertSame(2, $collection['b']);

        unset($collection['a']);
        self::assertFalse(isset($collection['a']));
    }

    public function testMissingOffsetReturnsNullRatherThanWarning(): void
    {
        self::assertNull((new Collection())['nope']);
    }

    /**
     * valid() asks whether current() is false, so a stored false ends iteration early. Pinned
     * because Repository::findOne() reads current() to mean "the first result or nothing".
     */
    public function testAStoredFalseTerminatesIteration(): void
    {
        $collection = new Collection([false, 'unreachable']);
        $collection->rewind();

        self::assertFalse($collection->valid());
    }
}
