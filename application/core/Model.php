<?php
/**
 * Model abstract class class
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */

namespace core;

abstract class Model extends Object {
    
    /**
     * every intstance has its id, integer or string
     * @var mixed  
     */
    protected $_id;
    
    /**
     * 
     * @param type $id
     */
    public function setId($id) {
        $this->_id = (string)$id;
    }
    
    /**
     * Get instance id
     * @return mixed Instance indentifier
     */
    public function getId() {
        return $this->_id;
    }
    
}

//models loader
$loader = function($path, $class) {
    $class = preg_replace('/(models\\\)?(s\/)?(model)?/ui', '', $class);
    $path = sprintf('%s/%s.php', $path, $class);
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    return false;
};
Autoloader::add(new Path("application/models"), $loader);