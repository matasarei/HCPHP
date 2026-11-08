<?php

// The wiring: constructions here are where they belong.
$container->setFactory('bar', function () {
    return new Foo\Service\BarService();
});
