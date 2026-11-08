<?php

namespace App\Controller;

use Foo\Service\BarService;
use Foo\Repo\{BazRepository, QuxRepository as Qux};
use function strlen;

class RatingController
{
    use LoggerManager;

    public function index($class)
    {
        $bar = new BarService();
        $qux = new Qux();
        $thing = new \Other\ThingService();
        $baz = new BazRepository;
        $commented = new /* a comment between new and the name */ BazRepository();
        $logger = new LoggerManager();
        $settings = Settings::getInstance();

        // new FakeService() in a comment is not counted
        $text = 'new FakeService() in a string is not counted';
        $dynamic = new $class();
        $self = new static();
        $anonymous = new class {
        };
        $own = self::getInstance();

        $callback = function () use ($bar) {
            return new LocalService();
        };

        return strlen($text);
    }
}
