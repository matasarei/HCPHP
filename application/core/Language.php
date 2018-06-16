<?php


namespace core;

/**
 * Translations
 *
 * @package    hcphp
 * @copyright  2015 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Language {
    
    /** @var string Default language code */
    protected static $_default = 'en';
    
    /** @var array Phrases */
    protected static $_strings = [];
    
    /** @var string Current language code ISO 693-1 */
    protected $_code;
    
    /** @var array Phrases for current language code */
    protected $_current;
    
    /**
     * Set default language code
     * @param string $lang
     */
    public static function setDefault($lang) {
        static::$_default = (string)$lang;
        static::_loadStrings(static::$_default);
    }
    
    /**
     * Get default language 
     * @return type
     */
    public static function getDefault() {
        return static::$_default;
    }
    
    /**
     * Load phrases
     * @param type $lang
     */
    protected static function _loadStrings($lang) {
        if (empty(static::$_strings[$lang])) {
            $path = new Path("application/lang/{$lang}.json", true);
            $strings = json_decode(file_get_contents($path));
            if (empty($strings)) {
                throw new \Exception('Wrong file format or file is corrupted', 1);
            }
            static::$_strings[$lang] = $strings;
        }
    }

    /**
     * @param string $lang Language ISO code (optional)
     */
    public function __construct($lang = null) {
        $this->setCode($lang ? $lang : self::$_default);
    }
    
    /**
     * Magit getter
     * @param string $name phrase name
     * @return string Phrase
     */
    public function __get($name) {
        return $this->getPhrase($name);
    }
    
    /**
     * Set phrase
     * @param string $name Prase name
     * @param string $value Prase
     * @param boolean $global Set global (affects all instances)
     */
    public function setPhrase($name, $value, $global = false) {
        $this->_current->$name = (string)$value;
        if ($global) {
            static::$_strings[$this->_code]->$name = $this->_current->$name;
        }
    }

    /**
     * Get phrase
     * @param string $name Phrase name
     * @param array $args sprintf arguments
     * @return string Phrase
     */
    public function getPhrase($name, array $args = []) {
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
     * Set language code
     * @param string $code
     */
    public function setCode($code) {
        $name = (string)$code;
        static::_loadStrings($name);
        $this->_current = static::$_strings[$name];
        $this->_code = $name;
    }
    
    /**
     * Get language code
     * @return type
     */
    public function getCode() {
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
     * Get phrase (static alt to getPhrase)
     * @param string $name String name
     * @param array $args String args
     * @param string $lang Language code (optional)
     */
    static function phrase($name, $args = [], $lang = null) {
        if (empty($lang)) {
            $lang = self::$_default;
        }
        
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
    
    $return = "<?php echo \core\Language::phrase('{$params[1]}'";
    
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