<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141109
 */

namespace core;

/**
 * 
 */
abstract class Controller extends Object {
    protected $_name;
    protected $_action;
    protected $_lang;
            
    function __construct($name, $action) {
        Events::triggerEvent('onLoadController', [
            'name' => $name, 
            'action' => $action
        ]);
        $this->_action = $action;
        //$this->_lang = Application::Language();
    }
    
    function getAction() {
        return $this->_action;
    }
    
    function getName() {
        return $this->_name;
    }
    
    function getLang() {
        return $this->_lang;
    }
}