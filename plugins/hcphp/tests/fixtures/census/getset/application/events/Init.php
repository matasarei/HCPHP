<?php
// Wiring: constructions here are where they belong and are not counted.
$container->set('bar', new FooBundle\Service\BarService(new BazRepository()));
