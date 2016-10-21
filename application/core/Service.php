<?php
/**
 * Service is a part of MVC concept. 
 * Services manupulates with models and mappers to
 * retrive and persist models data.
 * 
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160516
 */

namespace core;

/**
 * Service (MVC) absract class
 */
abstract class Service extends Object {
    
    /**
     * @var \core\iMapper 
     */
    protected $_mapper;
    
    
    /**
     * Find model by identifier
     * @param mixed $id Identifier (string / numeric)
     * @return \core\Model Model instance
     */
    abstract public function findById($id);
    
    /**
     * Find records using conditions
     * @param array $conditions Search conditions
     * @return array Found instances
     */
    abstract public function find(array $conditions = [], array $params = []);
    
    /**
     * Find one record
     * @param array $conditions
     * @param array $params
     * @return \models\User
     */
    public function findOne(array $conditions, array $params = []) {
        $params['limit'] = 1; 
        $found = $this->find($conditions, $params);
        return $found ? array_shift($found) : null;
    }
    
    /**
     * Save model data
     * @param \core\Model $instance Instance to save
     * @return boolean Result flag
     */
    public function save($instance) {
        if ($instance->id) {
            return $this->_mapper->update($instance);
        }
        return (bool)$this->_mapper->insert($instance);
    }
    
    /**
     * @param \core\Model $instance Instance to remove
     * @return boolean Result flag
     */
    public function remove($instance) {
        if ($instance->id) {
            return $this->_mapper->remove($instance->id);
        }
        return false;
    }
    
}