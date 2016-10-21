<?php
/**
 * Extended exception handler
 * 
 * @package    hcphp
 * @subpackage core
 * @author     Yevhen Matasar <matasar.ei@gmail.com>
 * @version    20162010
 */
namespace core;

class Exception extends \Exception {
    
    const ERROR_INTERNAL = 'internal_error';
    const ERROR_DEFAULT = self::ERROR_INTERNAL;
    
    /**
     * 
     * @param string $error Code error or error message
     * @param string $message Use error as message instead of error code
     * @param array  $lparams Language params
     */
    public function __construct($error, $code = 0, $lparams = []) {
        
        // use as message
        if ($code) {
            $error_code = static::ERROR_DEFAULT;
        } else {
            $error_code = $error;
        }
        
        // check error code.
        $valid = preg_match("/^[a-z0-9_]+$/i", $error_code);
        if (!$valid) {
            $error_code = 'internal_error';
            trigger_error('Wrong error code. Latin chars, numbers and underscores only!');
        }
        
        // set error message.
        $lang = new Language();
        parent::__construct($code ? $error : $lang->getString($error_code, $lparams), $code);
        
        // event params.
        $event = ['error_code' => $error_code, 'message' => (string)$this];
        
        // redirect to error page if debug is off.
        if (Debug::isOn()) {
            Events::triggerEvent('internalError', $event);
            
        } else {
            Events::triggerEvent('internalError', $event, true); // write to log.
            $redirect_url = new Url($valid ? "500/{$error_code}/" : '500/');
            Application::redirect($redirect_url, Application::ERROR_INTERNAL);
        }
    }
}