<?php
namespace FooBundle;

class FooBundle
{
    // Wiring, called from Init.php: constructions here are where they belong.
    public static function init(\core\Container $container): void
    {
        $container->set('bar', new Service\BarService());
    }
}
