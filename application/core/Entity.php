<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Entity extends MagicObject
{
    /**
     * @var string|int|null
     */
    protected $id = null;

    /**
     * @param $id string|int|null
     *
     * @return self
     */
    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string|int|null
     */
    public function getId()
    {
        return $this->id;
    }
}
