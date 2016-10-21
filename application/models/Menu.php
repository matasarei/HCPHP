<?php
/**
 * Menu
 *
 * @package    hcphp
 * @subpackage demo
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161021
 */

namespace models;

use core\Model,
    core\Url;

class Menu extends Model {
    
    private $_items = [];
    
    /**
     * 
     * @param \core\MenuItem $item
     */
    public function addItem(MenuItem $item) {
        $this->_items[$item->id] = $item;
    }
    
    /**
     * 
     * @param type $id
     */
    public function removeItem($id) {
        unset($this->_items[$id]);
    }
    
    /**
     * 
     */
    public function removeItems() {
        $this->_items = [];
    }
    
    /**
     * 
     * @return type
     */
    public function getItems() {
        return $this->_items;
    }
    
    
}