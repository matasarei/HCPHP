<?php

namespace Tests\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Reaches private members from a test without tripping over the version spread.
 *
 * setAccessible() is required on PHP 7.4, does nothing from 8.1, and is deprecated from 8.5 --
 * so calling it unconditionally warns on the newest supported version and skipping it fails on
 * the oldest. The guard belongs in one place rather than at every call site.
 */
final class Reflect
{
    /**
     * @param object|string $target object or class name
     */
    public static function method($target, string $name): ReflectionMethod
    {
        $method = new ReflectionMethod($target, $name);

        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        return $method;
    }

    /**
     * @param object|string $target object or class name
     */
    public static function property($target, string $name): ReflectionProperty
    {
        $property = new ReflectionProperty($target, $name);

        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property;
    }

    /**
     * Read a static property.
     *
     * @return mixed
     */
    public static function getStatic(string $class, string $name)
    {
        return self::property($class, $name)->getValue();
    }

    /**
     * @param mixed $value
     */
    public static function setStatic(string $class, string $name, $value): void
    {
        self::property($class, $name)->setValue(null, $value);
    }

    /**
     * Invoke a private or protected method.
     *
     * @param object|string $target
     *
     * @return mixed
     */
    public static function call($target, string $name, array $args = [])
    {
        return self::method($target, $name)->invokeArgs(is_object($target) ? $target : null, $args);
    }

    public static function classOf(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }
}
