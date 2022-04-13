<?php

namespace UserBundle\Entity;

use core\Entity;

class Role extends Entity
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    public function __construct(string $name, string $description)
    {
        $this->setId($name);

        $this->name = $name;
        $this->description = $description;
    }

    function getName(): string
    {
        return $this->name;
    }
    
    function getDescription(): string
    {
        return $this->description;
    }
    
    function __toString()
    {
        return $this->name;
    }
}
