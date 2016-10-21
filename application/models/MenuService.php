<?php
/**
 * Menu service
 *
 * @package    hcphp
 * @subpackage demo
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161021
 */

namespace models;

use core\Service;

class MenuService extends Service {
    
    public function __construct() {
        // Prepare mapper.
        $this->_mapper = new MenuMapper();
    }
    
    /**
     * Find menu with conditions
     * @param array $conditions Search conditions
     * @param type $params Optional params
     * @return \models\Menu
     */
    public function find(array $conditions = [], array $params = []) {
        $data = $this->_mapper->fetchWith($conditions);
        $instances = [];
        foreach ($data as $menuData) {
            $instance = new Menu;
            $this->_mapper->applyData($instance, $menuData);
            $instances[] = $instance;
        }
        
        return $instances;
    }
    
    /**
     * Find menu with id (name)
     * @param type $id ID
     * @return \models\Menu
     */
    public function findById($id) {
        return $this->findOne(['id' => $id]);
    }
 
    /**
     * Get main menu
     * @return \models\Menu
     */
    public function getMainMenu() {
        return $this->findOne(['id' => 'main']);
    }
}