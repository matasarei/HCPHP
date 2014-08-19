<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar (matasar.ei@gmail.com)
 * @license    
 */

/**
 * 
 */
class Controller404 extends Controller {
    
    function actionDefault() {
        $data = array('title'=>'404: Page not found!');
        
        $view = new View('404/default');
        $view->render($data);
    }
    
}
