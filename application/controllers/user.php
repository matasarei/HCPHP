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
class ControllerUser extends Controller {
    
    function actionDefault() {
        Application::redirect(new Url);
    }
    
    function actionLogin() {
        $data = new stdClass;
        $data->title = 'Login page';

        $auth = new ModelAuth;
        $data->loginData = $auth->loginData;
        if ($data->loginData) {
            if ($auth->logIn($data->loginData->username, $data->loginData->password)) {
                Application::redirect(new Url);
            } else {
                $data->error = 'Wrong username or password';
            }
        }

        $view = new View('/user/login');
        $view->render($data);
    }
    
    function actionLogout() {
        ModelAuth::logOut();
        Application::redirect(new Url);
    }
    
    function signup() {

    }   
}