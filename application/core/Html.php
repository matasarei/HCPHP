<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20161021
 */

namespace core;

use core\Xml,
    core\Url;

class Html extends Xml {
    
    /**
     * 
     * @param Url $url
     * @param string $name
     * @param array $attribs
     */
    static function link($url, $name = '', array $attribs = []) {
        $attribs['href'] = (string)$url;
        empty($attribs['class']) && $attribs['class'] = 'hcphp-link';
        $html = '<a ' . self::_makeAttribs($attribs) . '>';
        return $name ? "{$html}{$name}</a>" : "{$html}{$attribs['href']}</a>";
    }
    
    /**
     * 
     * @param Url $url
     * @param array $attribs
     * @return type
     */
    static function thumbnail(Url $url, array $attribs = []) {
        $imgAttr = [
            'src' => (string)$url,
            'class' => empty($attribs['class']) ? 'hcphp-thumbnail__image' : ''
        ];
        $linkAttr = [
            'href'   => (string)$url,
            'class'  => empty($attribs['class']) ? 'hcphp-thumbnail' : $attribs['class'],
            'target' => '_blank'
        ];
        
        foreach ($attribs as $name => $value) {
            if (in_array($name, ['width', 'heigth', 'alt'])) {
                $imgAttr[$name] = $value;
            } else {
                $linkAttr[$name] = $value; 
            }
        }
        
        $html = '<a ' . self::_makeAttribs($linkAttr) . ">";
        return "{$html}<img " . self::_makeAttribs($imgAttr) . '></a>';
    }
    
    /**
     * 
     * @param Url $url
     * @param array $attribs
     * @return type
     */
    static function image(Url $url, array $attribs = []) {
        $attribs['src'] = (string)$url;
        return '<img ' . self::_makeAttribs($attribs) . '>';
    }
    
    /**
     * 
     * @param array $items
     * @param type $type
     * @param type $attributes
     * @return type
     */
    public static function htmlList(array $items, $type = 'ul', $attributes = array()) {
        $html = '';
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $html .= self::tag('li', $key . self::htmlList($item, $type));
            } else if ($type === 'dl') {
                $html .= self::tag('dt', $key);
                $html .= self::tag('dd', $item);
            } else {
                $html .= self::tag('li', $item);
            }
        }
        return self::tag($type, $html, $attributes);
    }
    
}