<?php

namespace core;

use RuntimeException;

/**
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Config
{
    private $vars = [];
    private $timeModified;
    
    function __construct($config, array $vars)
    {
        $path = new Path(
            sprintf('application/config/%s.json', $config),
            true
        );
        $config = json_decode(file_get_contents($path));
        
        $this->timeModified = filemtime($path);
        
        foreach ($vars as $var => $default) {
            if (is_numeric($var)) {
                $var = $default;
                $default = null;
            }
            $this->_set($config, $var, $default);
        } 
    }

    private function _set($data, $name, $default = null) 
    {
        if (isset($data->$name)) {
            $this->vars[$name] = $data->$name;
        } else if($default !== null) {
            $this->vars[$name] = $default;
        } else {
            throw new RuntimeException(sprintf("Can't set '%s'. Please check config file!", $name));
        }
    }
    
    function getTimeModified()
    {
        return $this->timeModified;
    }

    function isEmpty(string $name)
    {
        return empty($this->get($name));
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set(string $name, $value)
    {
        $this->__set($name, $value);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->__get($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value)
    {
        $this->vars[$name] = $value;
    }

    function __get(string $name)
    {
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        }

        return null;
    }

    public function getArray(string $name): array
    {
        return json_decode(json_encode($this->get($name)), true);
    }
}
