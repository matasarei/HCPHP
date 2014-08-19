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
class ControllerSandbox extends Controller {
    
    function actionDefault($a = null, $b = null, $c = null) {
        $data = array('title'=>'Sandbox');
        $a && $data['a'] = (int)$a;
        $b && $data['b'] = (int)$b;
        $c && $data['c'] = $c;
        
        $model = new ModelTest();
        $data['test'] = $model->test;

        $view = new View('sandbox/default');
        $view->render($data);
    }
    
}
