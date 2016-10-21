<?php
/**
 * Menu mapper
 *
 * @package    hcphp
 * @subpackage demo
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161021
 */

namespace models;

use core\iMapper,
    core\Exception,
    core\Path;

class MenuMapper implements iMapper {
    
    public function fetchWithId($id) {
        return $this->fetchWith(['id' => $id]);
    }
    
    /**
     * Return menus data with conditions
     * @param array $conditions Search conditions
     * @param array $params Optional params
     * @return array data
     * @throws Exception
     */
    public function fetchWith(array $conditions, array $params = array()) {
        if (empty($conditions['id'])) {
            throw new Exception('e_instance_id_required');
        }
       
        $path = new Path("/data/menu/{$conditions['id']}.json", true);
        $content = file_get_contents($path);
        if (!$content) {
            throw new Exception('e_read_file', [$path]);
        }
        
        $items = json_decode($content);
        if (!is_array($items)) {
            throw new Exception('e_wrong_file_format', [$path]);
        }
        
        $data = new \stdClass();
        $data->id = $conditions['id'];
        $data->items = $items;
        return [$data];
    }
    
    /**
     * 
     * @param \models\Menu $instance
     * @param \stdClass $data
     */
    public static function applyData(&$instance, $data) {
        $instance->id = $data->id;
        $i = 0;
        foreach ($data->items as $itemData) {
            $item = new MenuItem();
            $item->id = $i;
            $item->name = $itemData->name;
            $item->url = $itemData->path;
            $instance->addItem($item);
            $i++;
        }
    }
    
    public function insert(&$instance) {
        throw new Exception('e_not_implemented');
    }
    
    public function update(&$instance) {
        throw new Exception('e_not_implemented');
    }
    
    public function remove($id) {
        throw new Exception('e_not_implemented');
    }
    
}