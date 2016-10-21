<?php
/**
 * Filters
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161020
 */

namespace core;

/**
 * RegExp filers
 */
class Filters {
    
    /**
     * Satic only
     */
    private function __construct() {}
    private function __clone() {}
    
    
    /* basic filters */
    const INT_NONZERO = "/^[1-9]+([0-9]+)?$/";
    const INT = "/^[0-9]+$/";
    const URL = '/\b[\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/]/usi';
    const EMAIL = "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/";
    
    /**
     * Filter value by regex
     * @param type $value
     * @param type $regex
     */
    static function filter($value, $regex) {
        preg_match($regex, trim($value), $matches, null, 0);
        if ($matches) {
            return array_shift($matches);
        }
        return false;
    }
    
    /**
     * removes scripts from an html content
     * @param string $value html content
     * @return string
     */
    static function noscript($value) {
        return preg_replace("/<script.*<(\/script)?/usi", "", $value, -1);
    }
    
    /**
     * Filter html content
     * @param type $content
     * @return type
     */
    static function html($content, $allowScripts = false) {
        if (!$allowScripts) {
            $content = self::noscript($content);
        }
        
        $url_preg = '/(?<!=[\'"])(\b[\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/usi';
        $links = [];
        
        // replace URLs with html links.
        preg_match_all($url_preg, $content, $links, PREG_SET_ORDER, 0);
        foreach ($links as $link) {
            $url = $link[0];
            $replace_preg = '@' . preg_quote($url) . '@';
            $label = mb_strlen($url) > 64 ? mb_strcut($url, 0, 48) . '...' : $url;
            $content = preg_replace($replace_preg, Html::link($url, $label, [
                'target' => '_blank'
            ]), $content);
        }
        
        return $content;
    }
    
}