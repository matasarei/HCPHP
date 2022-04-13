<?php

namespace core;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class MapperInterfaceSQL implements MapperInterface
{
    /**
     * @var DatabaseSQL
     */
    protected $DB = null;

    /**
     * @var string
     */
    protected $dbAlias = 'default';

    /**
     * Mapper constructor
     */
    function __construct(DatabaseSQL $database)
    {
        $this->DB = $database;
    }

    function getDatabase()
    {
        return $this->DB;
    }
}
