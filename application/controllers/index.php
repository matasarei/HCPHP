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
class ControllerIndex extends Controller {
    
    function actionDefault() {
        $data = new stdClass;
        $data->title = 'Index';

        $view = new View('index/default');
        $view->render($data);
    }
    
}
