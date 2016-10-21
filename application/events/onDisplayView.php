<?php
/**
 * HCPHP
 *
 * @package hcphp
 * @author Yevhen Matasar <matasar.ei@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version 20161020
 */

use core\Handler,
    models\MenuService;

class onDisplayView extends Handler {
    
    /**
     * 
     * @param type $data
     */
    protected function handle($data) {
        $menuService = new MenuService();
        $menu = $menuService->getMainMenu();
        
        $view = $data->view;
        $view->layout->set('mainMenu', $menu->items);
    }
    
}