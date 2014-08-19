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
class Writer {
	
    /**
     * Satic only
     */
    private function __construct() { }
    private function __clone() { }
    
    /**
     * 
     */
    static public function tag($tag, $content, $args = array()) {
        $out = "<{$tag}";
        $args && $out .= ' ' . self::makeArgs($args);
        return "{$out}>{$content}</{$tag}>";
    }
    
    static public function makeArgs($args) {
        $prepared = array();
        foreach ($args as $key => $value) {
            $prepared[] = "{$key} = '{$value}'";
        }
        return implode(' ', $prepared);
    }
    
}

