<?php
/**
 * 500 page
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\Controller,
    core\View,
    core\Template;

class Controller500 extends Controller {
    
    public function actionDefault() {
        $view = new View();
        $view->layout = new Template('empty');
        $view->display();
    }
    
}