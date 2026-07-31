<?php

namespace Tests\Unit\Core;

use core\Entity;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \core\Entity
 * @covers \core\MagicObject
 */
class EntityTest extends TestCase
{
    public function testIdStartsNull(): void
    {
        self::assertNull((new EntityDouble())->getId());
    }

    public function testSetIdIsFluent(): void
    {
        $entity = new EntityDouble();

        self::assertSame($entity, $entity->setId(7));
        self::assertSame(7, $entity->getId());
    }

    public function testIdAcceptsAString(): void
    {
        self::assertSame('abc', (new EntityDouble())->setId('abc')->getId());
    }

    // --- the MagicObject base ------------------------------------------------------------

    public function testMagicGetCallsTheGetter(): void
    {
        $entity = new EntityDouble();
        $entity->setId(3);

        self::assertSame(3, $entity->id);
    }

    public function testMagicSetCallsTheSetter(): void
    {
        $entity = new EntityDouble();
        $entity->id = 9;

        self::assertSame(9, $entity->getId());
    }

    public function testMagicGetOnAPropertyWithNoGetterThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Undefined getter getNope');

        (new EntityDouble())->nope;
    }

    public function testMagicSetOnAPropertyWithNoSetterThrows(): void
    {
        $entity = new EntityDouble();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Undefined setter setNope');

        $entity->nope = 1;
    }

    /**
     * The name is passed through ucfirst(), so an already-capitalised property resolves to
     * the same accessor rather than to getIId.
     */
    public function testAccessorNameIsCapitalisedOnce(): void
    {
        $entity = new EntityDouble();
        $entity->setId(1);

        self::assertSame(1, $entity->Id);
    }
}

class EntityDouble extends Entity
{
}
