<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20151123
 */

namespace core;

class Xml {
    
    /**
     * Satic only
     */
    private function __construct() { }
    private function __clone() { }
    
    static function tag($name, $content = null, $attribs = []) {
        $string = ["<{$name}"];
        //attribs
        if ($attribs) {
            array_push($string, " ", self::_makeAttribs($attribs));
        }
        //content
        if ($content) {
            $string[] = ">{$content}</{$name}>";
        } else {
            $string[] = " />";
        }
        return implode('', $string);
    }
    
    /**
     * 
     * @param type $attribs
     * @return type
     */
    protected static function _makeAttribs($attribs) {
        $string = [];
        foreach ($attribs as $name => $value) {
            if (empty($value)) {
                $string[] = " {$name} ";
            } else {
                is_array($value) && $value = implode(";", $value);
                $value = preg_replace('/\"/', '\"', $value, -1);
                //$value = addslashes($value);
                $string[] = "{$name}=\"{$value}\"";
            }
        }
        return implode(" ", $string);
    }
    
}