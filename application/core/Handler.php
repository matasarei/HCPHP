<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Handler
{
    /**
     * Construct and run handler
     *
     * @param MagicObject $data Event data
     */
    public function __construct($data = null)
    {
        $this->handle($data);
    }

    /**
     * @param MagicObject $data
     *
     * @return mixed
     */
    abstract protected function handle($data);
}
