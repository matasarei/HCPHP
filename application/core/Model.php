<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */

/**
 * 
 */
class Model extends Object {
    
    protected $DB;
    
    /**
     * 
     */
    function __construct() {
        $config = new Config('database', array('host' => 'localhost', 'dbname', 'user', 
                                       'pass' => '', 'prefix' => '', 'encoding'=>'utf8'));
        $this->DB = new DBManager('mysql', $config->host, $config->dbname,$config->user, 
                                  $config->pass, $config->prefix, $config->encoding);
    }
}