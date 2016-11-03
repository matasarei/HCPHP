<?php

/**
 * HCPHP
 * Template processor 
 * (experimental preprocessing features!)
 *
 * @package hcphp
 * @author  Yevhen Matasar <matasar.ei@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version 20160801
 */

namespace core;

use core\Exception;

class Template extends Object {

    protected $_data = [];
    protected $_path;
    protected $_template;
    private static $_shortcodes = [];
    private static $_filters = [];
    protected $_useShortcodes = true;
    
    /**
     * 
     * @param type $val
     */
    public function setUseShortcode($val) {
        $this->_useShortcodes = (bool)$val;
    }
    
    /**
     * 
     * @return type
     */
    public function getPath() {
        return $this->_path;
    }

    /**
     * Example: new Tempalte('new_template') for 'templates/new_tempalte.php'
     * @param string $template Template name
     * @throws TemplateNotFoundException Erorr if wrong template name or does not exist
     */
    public function __construct($template) {
        $this->_path = new Path("application/templates/{$template}.php");

        if (!file_exists($this->_path)) {
            throw new TemplateNotFoundException("Template '{$template}' does not exist!");
        }

        $this->_template = $template;
    }

    /**
     * returns all template variables
     */
    public function getData() {
        return $this->_data;
    }

    /**
     * Set array of values (assoc array 'var'=>'value')
     */
    public function setData($data) {
        $this->_checkArg($data, array('array', 'object'));
        if (is_object($data)) {
            $data = (array)$data;
        }
        
        $this->_data = array_merge($this->_data, $data);
    }

    /**
     * Set template variable
     */
    public function set($name, $value) {
        $this->_data[$name] = $value;
        return true;
    }
    
    /**
     * Push value to an array
     */
    public function push($name, $value, $index = null) {
        if (isset($this->_data[$name])) {
            $current = $this->_data[$name];
            if (!is_array($current)) {
                $newval = [$current];
            }
        } else {
            $newval = [];
        }
        $index ? $newval[$index] = $value : $newval[] = $value;
        $this->_data[$name] = $newval;
        return true;
    }

    /**
     * Get template variable
     */
    public function get($name) {
        if (isset($this->_data[$name])) {
            return $this->_data[$name];
        }
        return null;
    }

    /**
     * Magic getter
     * Returns class prop or template variable (prop priority)
     * @param type $name
     * @return type
     */
    public function __get($name) {
        try {
            return parent::__get($name);
        } catch (UndefinedGetterException $ex) {
            return $this->get($name);
        }
    }

    /**
     * Parse and return result
     *
     * @param mixed Include data
     */
    public function make(array $data = null) {
        $cache = new Path("/cache/templates/{$this->_template}.tmp");

        if (!(is_readable($cache) && filemtime($cache) > filemtime($this->_path))) {
            $contents = file_get_contents($this->_path);
            $cache = new Path("/cache/templates/{$this->_template}.tmp");
            $cache->mkpath();
            file_put_contents($cache, $this->parse($contents));
        }
        $this->_path = $cache;

        $data && $this->data = $data;
        extract($this->_data, EXTR_OVERWRITE);
        ob_start();
        include ($this->_path);
        return ob_get_clean();
    }

    /**
     * Removes all caches (needs access to the cahce directory)
     * @return bool Result
     */
    public static function purgeCaches($path = '/cache/templates/') {
        $caches = new Path($path);
        return $caches->rmpath(true);
    }

    /**
     * Parse template
     */
    private function parse($contents) {
        // remove comments.
        $contents = preg_replace("/\{\*.*\*\}/Uus", "", (string)$contents, -1);
        
        
        $matches = [];
        if ($this->_useShortcodes) {
            // fetch all shortcodes.
            preg_match_all("/{{\s*(.*)\s*}}/Uus", $contents, $matches, PREG_SET_ORDER, 0);
            
            // explode to lines.
            $lines = preg_split("/\n/", $contents, -1, null);
        }

        // process each shortcode.
        foreach ($matches as $match) {
            $pattern = '#' . preg_quote($match[0]) . '#';
            $info = new \stdClass();
            $info->file = $this->_path;
            
            $extracted = array_keys(preg_grep($pattern, $lines));
            $info->line = $extracted ? array_shift($extracted) + 1 : false;
            
            //replace slash(\) by 'end'
            $match = preg_replace("/^\s*\/(.*)/", "end\$1", $match, -1);

            //get function name and params
            $params = preg_split("@\|@s", $match[1], -1, null);
            
            //method name
            $method = "parse{$params[0]}";
            
            //predefined shortcodes
            if (preg_match('@(\$[A-Za-z]|^[A-Z0-9_]{2,})@', $params[0])) {
                $replacement = $this->parseEcho([$params[0]], $info);
                
            //custom shortcodes (hooks)
            } elseif (isset(self::$_shortcodes[$params[0]])) {
                $func = self::$_shortcodes[$params[0]];
                $replacement = $func($params, $info);
                
            } elseif (method_exists($this, $method)) {
                $replacement = $this->$method($params, $info);
                
            } else {
                $replacement = $this->replaceWithNotice($params, $info);
            }
            
            //replace in template
            $contents = preg_replace($pattern, $replacement, $contents, -1);
        }
        
        // remove whitespaces (production only, ingnores in debug mode) and return.
        return Debug::isOn() ? $contents : preg_replace("@\s+@s", " ", $contents, -1);
    }
    
    /**
     * Replace shortcode with notice to warn developer.
     * @param type $params
     * @param type $info
     * @return type
     */
    public static function replaceWithNotice($params, $info) {
        $msg = "Parse error: wrong syntax or shortcode does not defined in {$info->file}";
        trigger_error($info->line ? "{$msg} on line: {$info->line}" : $msg);
        
        if (Debug::isOn()) {
            return '{{' . implode('|', $params) . '}}';
        }
        return "%shortcode%";
    }

    /**
     * @param string $params vars to echo
     * @return string PHP ready code
     */
    public function parseEcho(array $params, $info) {
        if (empty($params)) {
            return $this->replaceWithNotice($params, $info);
        }
        
        return "<?php echo {$params[0]} ?>";
    }

    /**
     * Parse if condition
     * @param string $params Statement params
     * @return string PHP ready code
     */
    public function parseIf(array $params, $info) {
        if (empty($params[1])) {
            return $this->replaceWithNotice($params, $info);
        }

        // condition only
        if (empty($params[2]) && empty($params[3])) {
            return "<?php if($params[1]): ?>";
        }
        
        // consequent or alternative exists
        empty($params[2]) && $params[2] = "''";
        $return = "<?php if({$params[1]}) { {$params[2]}; }";
        return empty($params[3]) ? "{$return} ?>" : "{$return} else { {$params[3]}; } ?>";
    }
    
    /**
     * endif closing tag
     * @return string
     */
    public function parseEndIf() {
        return "<?php endif; ?>";
    }
    
    /**
     * endif closing tag
     * @return string
     */
    public function parseElse($params) {
        if (!empty($params[1])) {
            return "<?php elseif({$params[1]}) :?>";
        }
        
        return "<?php else: ?>";
    }

    /**
     * Parse foreach statement
     * syntax example 1: {{foreach var, key in array}}...{{/foreach}}
     * syntax example 2: {{foreach array as var, key}}...{{/foreach}}
     * @param string $params Statement params
     * @param stdClass $info Process info
     * @return string PHP ready code (null if failed)
     */
    public function parseForEach($params, $info) {
        if (empty($params[1])) {
            return $this->replaceWithNotice($params, $info);
        }
        
        $foreach = "foreach({$params[1]} as";
        $keyval = empty($params[3]) ? "{$params[2]}" : "{$params[2]} => {$params[3]}";
        return "<?php {$foreach} {$keyval}): ?>";
    }

    /**
     * foreach statement closing tag
     * @return string closing tag
     */
    public function parseEndForEach() {
        return "<?php endforeach; ?>";
    }

    /**
     * Adds hook in parser. Can override parser functions.
     * @param type $name Hook name
     * @param callable $func Anonimous function: function(string $params[, stdClass $info])
     */
    public static function addShortcode($name, callable $func) {
        self::$_shortcodes[$name] = $func;
    }

    /**
     * Adds filter in parser.
     * @param type $name Filter name
     * @param callable $func Anonimous function: function(string $params[, stdClass $info])
     */
    public static function addFilter($name, callable $func) {
        self::$_filters[$name] = $func;
    }
}

/**
 * Template shortcode
 * syntax: {{template|name[|data_array]}}
 * examples: 
 *  {{template|some_name}}
 *  {{template|'some_name'|['var' => 'value']}}
 *  {{template|'/path/to/template_name'}}
 */
Template::addShortcode('template', function($params, $info) {
    // check template name.
    if (empty($params[1])) {
        return Template::replaceWithNotice($params, $info);
    } else {
        $params[1] = preg_replace("@[\'\"](.*)[\'\"]@", "$1", $params[1], -1);
    }
    
    // random template name.
    $name = '$template' . rand();

    // new tamplate instance.
    $return = "{$name} = new \core\Template('{$params[1]}'); ";
    
    // if data array defined.
    if (!empty($params[2])) {
        $return .= "{$name}->setData({$params[2]}); ";
    }
    
    return "<?php {$return} echo {$name}->make(); ?>";
});

class TemplateNotFoundException extends Exception {
    const ERROR_DEFAULT = 'template_not_found';
    
    public function __construct($error, $code = 0, $lparams = []) {
        if ($code < 1) {
            $code = 1;
        }
        parent::__construct($error, $code, $lparams);
    }
}
