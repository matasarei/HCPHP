<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2015 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20150107
 */

namespace core;

/**
 * 
 */
class Language {
    
    /**
     * Default language code
     * @var string
     */
    protected static $_default = 'en';
    
    /**
     *
     * @var array Strings
     */
    protected static $_strings = [];
    
    /**
     * 
     * @param type $lang
     */
    public static function setDefault($lang) {
        static::$_default = (string)$lang;
        static::_loadStrings(static::$_default);
    }
    
    /**
     * 
     * @return type
     */
    public static function getDefault() {
        return static::$_default;
    }
    
    /**
     * 
     * @param type $lang
     */
    protected static function _loadStrings($lang) {
        if (empty(static::$_strings[$lang])) {
            $path = new Path("application/lang/{$lang}.json", true);
            $strings = json_decode(file_get_contents($path));
            if (!$strings) {
                throw new \Exception('Wrong file format or file is corrupted', 1);
            }
            static::$_strings[$lang] = $strings;
        }
    }
    
    /**
     * Current language code
     * @var type 
     */
    protected $_code;
    
    /**
     * Strings for current language code
     * @var type 
     */
    protected $_current;

    /**
     * 
     */
    public function __construct($lang = null) {
        $this->setLanguage($lang ? $lang : self::$_default);
    }
    
    /**
     * 
     * @param type $name
     * @return type
     */
    public function __get($name) {
        return $this->getString($name);
    }
    
    /**
     * 
     * @param type $name
     * @param type $value
     */
    public function __set($name, $value) {
        $this->setString($name, $value);
    }
    
    /**
     * 
     * @param type $name
     * @param type $value
     * @param type $global
     */
    public function setString($name, $value, $global = false) {
        $this->_current->$name = (string)$value;
        if ($global) {
            static::$_strings[$this->_code]->$name = $this->_current->$name;
        }
    }

    /**
     * 
     * @param type $name
     * @param array $args
     * @return type
     */
    public function getString($name, array $args = []) {
        if (empty($this->_current->$name)) {
            trigger_error("String '{$name}' in '{$this->_code}' does not exist");
            return "%{$name}%";
        }
        
        if ($args) {
            return vsprintf($this->_current->$name, $args);
        }
        return $this->_current->$name;
    }
    
    /**
     * Language code
     * @param string $code
     */
    public function setLanguage($code) {
        $name = (string)$code;
        static::_loadStrings($name);
        $this->_current = static::$_strings[$name];
        $this->_code = $name;
    }
    
    /**
     * 
     * @return type
     */
    public function getLanguage() {
        return $this->_code;
    }
    
    /**
     * Get language name with code
     * @param type $code Language code
     */
    static function getName($code) {
        $name = (string)$code;
        static::$_strings[$name]->$name;
    }
    
    /**
     * @param type $name String name
     * @param type $args String args
     * @param type $lang Language code (optional)
     */
    static function string($name, $args = [], $lang = null) {
        !$lang && $lang = self::$_default;
        
        if (empty(self::$_strings[$lang])) {
            self::_loadStrings($lang);
        }
        
        if (isset(self::$_strings[$lang]->$name)) {
            $string = self::$_strings[$lang]->$name;
            return $args ? vsprintf($string, $args) : $string;
        }
        return "%{$name}%";
    }
}

/**
 * Language tamplate shortcode
 * Syntax: {{lang|string_name[|args][|language_code]}}
 * Examples: 
 *  {{lang|name|['some', 'args', 123, 123]}}
 *  {{lang|name||en}}
 */
Template::addShortcode('lang', function($params, $info) {
    $quotesPattern = "@[\'\"](.*)[\'\"]@";
    
    if (empty($params[1])) {
        return Template::replaceWithNotice($params, $info);
    } else {
        $params[1] = preg_replace($quotesPattern, "$1", $params[1], -1);
    }
    
    $return = "<?php echo \core\Language::string('{$params[1]}'";
    
    // string args.
    if (empty($params[2])) {
        $return .= ', []';
    } else {
        $return .= ", {$params[2]}";
    }
    
    // language code.
    if (isset($params[3])) {
        $return .= ", '" . preg_replace($quotesPattern, "$1", $params[3], -1) . "'";
    }
    
    return "{$return}); ?>";
});