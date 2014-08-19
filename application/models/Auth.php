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
class ModelAuth extends Model {
    
    static function isLoggedIn() {
        return (bool)Session::uid();
    }
    
    function logIn($username, $password) {
        $user = $this->DB->getRecord('user', array('username' => $username));
        !$user && $user = $this->DB->getRecord('user', array('email' => $username));

        if ($user && password_verify($password, $user->password)) {
            Session::uid($user->id);
            return true;
        }
        return false;
    }
    
    static function logOut() {
        Session::reload();
        return true;
    }
    
    function getCurrentUser() {
        return Session::uid();
    }
    
    function getLoginData() {
        if (isset($_REQUEST['username'], $_REQUEST['password'])) {
            $data = new stdClass;
            $data->username = $_REQUEST['username'];
            $data->password = $_REQUEST['password'];
            return $data;
        }
        return false;
    }
}

