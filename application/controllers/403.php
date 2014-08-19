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
class Controller403 extends Controller {
    
    function actionDefault() {
        $data = array('title'=>'403: Access forbidden!');
        
        $view = new View('403/default');
        $view->render($data);
    }
    
}
