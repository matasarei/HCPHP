<?php
namespace core;
final class Container
{
    public function get(string $name) {}
    public function setFactory(string $name, callable $factory): void {}
}
