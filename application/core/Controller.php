<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Controller
{
    /**
     * @var Language
     */
    protected $language;

    /**
     * @var Container
     */
    protected $container;

    function __construct(Container $container)
    {
        $this->container = $container;
        $this->language = Language::getInstance();
    }
}
