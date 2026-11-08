<?php
namespace FooBundle\Service;

class BarService
{
    public function find()
    {
        $repository = new BazRepository();
        $other = new \Other\QuxRepository();
        $config = Settings::getInstance();
        // new NotCountedHelper() is not a Service, Repository, Mapper or Manager.
        return new NotCountedHelper();
    }
}
