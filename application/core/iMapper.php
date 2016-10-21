<?php
/**
 * Mapper is a part of MVC concept.
 * Mapper map data to a model instance. 
 * Includes data manipulating logic like SQL queries.
 * Should be used in services.
 * 
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */

namespace core;

/**
 * Mapper (MVC) iterface
 */
interface iMapper {
    
    /**
     * Map data with identifier
     * @param mixed $id Instance identifier
     */
    public function fetchWithId($id);
    
    /**
     * 
     * @param array $conditions
     * @param array $params
     */
    public function fetchWith(array $conditions, array $params = []);
    
    /**
     * Apply data to a model instance from object or array
     * @param \core\Model $instance Model instance
     * @param mixed $data Array or object
     */
    public static function applyData(&$instance, $data);
    
    /**
     * 
     * @param type $instance
     */
    public function insert(&$instance);
    
    /**
     * 
     * @param type $instance
     */
    public function update(&$instance);

    /**
     * 
     * @param int instance id
     */
    public function remove($id);
    
}