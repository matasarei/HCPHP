<?php
/**
 * Menu item
 *
 * @package    hcphp
 * @subpackage demo
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161021
 */

namespace models;

use core\ModelDynamic,
    core\Url;

class MenuItem extends ModelDynamic {
    
    public function setName($name) {
        $this->_data['name'] = trim($name);
    }
    
    public function setUrl($url) {
        $this->_data['url'] = new Url((string)$url);
    }
    
}