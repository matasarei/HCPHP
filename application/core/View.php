<?php
/**
 * HCPHP
 *
 * @package hcphp
 * @author Yevhen Matasar <matasar.ei@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version 20150111
 */

namespace core;

class View extends Template {
    
    protected $_layout;
    
    /**
     * 
     */
    public function __construct($view = '', $layout = 'default') {
        try {
            if(!$view) {
                $view = Application::getController() . '/' . Application::getAction();
            }
            $path = new Path("application/views/{$view}.php", true);
            $this->_path = $path;
        } catch (WrongPathException $e) {
            throw new TemplateNotFoundException("View '{$view}' does not exist!");
        }
        $this->_layout = new Template($layout);
        $this->_template = $view;
    }
    
    /**
     * 
     */
    public function getLayout() {
        return $this->_layout;
    }
    
    
    /**
     * 
     */
    public function setLayout($layout) {
        if ($layout instanceof Template) {
            $this->_layout = $layout;
        } else {
            $this->_layout = new Template($layout);
        }
    }
    
    /**
     * Makes view
     */
    public function make(array $data = null) {
        $this->_layout->set('content', parent::make($data));
        return $this->_layout->make();
    }
    
    /**
     * Display view and exit application (optional)
     * @param array $data template data
     * @param bool $end exit application
     */
    public function display(array $data = null, $end = true) {
        Events::triggerEvent('onDisplayView', [
            'view' => $this
        ]);
        
        echo $this->make($data);
        $end && exit();
    }
    
}