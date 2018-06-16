<?php

/**
 * Index controller
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\Application,
    core\View,
    core\Controller,
    core\Language;

class ControllerIndex extends Controller {
    
    public function actionDefault() {
        $view = new View(); // /views/index/default.php
        
        $lang = new Language();
        
        $view->set('title', $lang->getPhrase('title', ['HCPHP']));
        
        $view->display();
    }
    
    public function actionRoutingTest($param = null) {
        $view = new View('empty'); // /views/empty.php
        
        // debug.
        x($param);
        
        $view->display();
    }
    
    public function actionParamsTest($p1, $p2, $p3) {
        $view = new View('empty'); // /views/empty.php
        
        // debug.
        x([$p1, $p2, $p3]);
        
        $view->display();
    }
    
    /**
     * Error page test
     */
    public function actionError() {
        Application::sendError(Application::ERROR_INTERNAL);
    }
    
    /**
     * Forbidden page test
     */
    public function actionForbidden() {
        Application::sendError(Application::ERROR_FORBIDDEN);
    }
    
}