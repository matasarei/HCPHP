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
class ModelUser extends Model {
	
    private $_id;
    private $_username;
    private $_firstname;
    private $_middlename;
    private $_lastname;
    private $_email;
    private $_suspended;
    private $_role;
    
	function __construct($id) {
		$user = $this->DB->getRecord('user', array('id'=>$id));
        if ($user) {
            $this->_id = $user->id;
            $this->_username = $user->username;
            $this->_firstname = $user->firstname;
            $this->_middlename = $user->middlename;
            $this->_lastname = $user->lastname;
            $this->_email = $user->email;
            $this->_suspended = (bool)$user->suspended;
            
            $role = $this->DB->getRecord('roles', array('id'=>$user->roleid));
            if ($role) {
                $this->_role = $role;
            } else {
                throw new Exception("Specified role ({$user->roleid}) does not exists!", 1);
            }
        } else {
            throw new Exception("Specified user ({$id}) does not exists!", 1);
        }
	}
    
    function getId() {
        return $this->_id;
    }
    
    function getUserName() {
        return $this->_username;
    }
    
    function getFirstName() {
        return $this->_firstname;
    }
    
    function setMiddleName($firstname) {
        if ($this->DB->updateRecord('user', array('id' => $this->_id, 'firstname' => $firstname))) {
            $this->_firstname = $firstname;
            return true;
        }
        return false;
    }
    
    function getMiddleName() {
        return $this->_middlename;
    }
    
    function setMiddleName($middlename) {
        if ($this->DB->updateRecord('user', array('id' => $this->_id, 'middlename' => $middlename))) {
            $this->_middlename = $middlename;
            return true;
        }
        return false;
    }
    
    function getLastName() {
        return $this->_lastname;
    }
    
    function setLastName($lastname) {
        if ($this->DB->updateRecord('user', array('id' => $this->_id, 'lastname' => $lastname))) {
            $this->_lastname = $lastname;
            return true;
        }
        return false;
    }
    
    function getEmail() {
        return $this->_email;
    }
    
    function setEmail($email) {
        if (!preg_match("/([\w\-]+\@[\w\-]+\.[\w\-]+)/", $email)) {
            throw new Exception("Wrong email adress", 1);
        }
        return (bool)$this->DB->updateRecord('user', array('id' => $this->_id, 'email' => $email));
    }
    
    function isSuspended() {
        return (bool)$this->suspended;
    }
    
    function suspend() {
        return (bool)$this->DB->updateRecord('user', array('id' => $this->_id, 'suspended' => false));
    }
    
    function unsuspend() {
        return (bool)$this->DB->updateRecord('user', array('id' => $this->_id, 'suspended' => true));
    }
    
    function getRole() {
        return $this->_role;
    }
    
    function setRole($name) {
        $role = $this->DB->getRecord('roles', array('name'=>$name));
        if ($role) {
            return (bool)$this->DB->updateRecord('users', array('id' => $this->_id, 'roleid' => $role->id));
        } else {
            throw new Exception("Specified role ({$name}) does not exists!", 1);
        }
    }
    
    function setPassword($password) {
        if(!preg_match('/^(?=.*\d)(?=.*[A-Za-z])[0-9A-Za-z!@#$%_\[\]-]{8,12}$/', $password)) {
            throw new Exception("Specifies password is weak!", 1);
        }
        $record = new stdClass;
        $record->id = $this->_id;
        $record->password = password_hash($password, PASSWORD_DEFAULT);
        return (bool)$this->DB->updateRecord('users', $record);
    }
    
    function hasCapability($cname) {
        $sql = "SELECT *
                  FROM {permissions}
                 WHERE cid
                    IN (SELECT id
                          FROM {capabilities}
                         WHERE name = :cname)
                   AND roleid = :roleid";
       return (bool)$this->DB->getRecordSQL($sql, array('cname'=>$name, 'roleid'=>$this->_roleid));
    }
}