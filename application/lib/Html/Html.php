<?php

namespace Html;

use core\Url;
use core\Xml;

/**
 * @package    hcphp
 * @subpackage html
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Html extends Xml
{
    /**
     * @param Url|string $url
     * @param string $name
     * @param array $attribs
     *
     * @return string
     */
    static function link($url, string $name = '', array $attribs = []): string
    {
        $attribs['href'] = (string)$url;
        empty($attribs['class']) && $attribs['class'] = 'hcphp-link';
        $html = '<a ' . self::prepareAttributes($attribs) . '>';

        return $name ? "{$html}{$name}</a>" : "{$html}{$attribs['href']}</a>";
    }
    
    /**
     * @param Url|string $url
     * @param array $attribs
     *
     * @return string
     */
    static function thumbnail($url, array $attribs = []): string
    {
        $imgAttr = [
            'src' => (string)$url,
            'class' => empty($attribs['class']) ? 'hcphp-thumbnail-image' : ''
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
        
        $html = '<a ' . self::prepareAttributes($linkAttr) . ">";

        return "{$html}<img " . self::prepareAttributes($imgAttr) . '></a>';
    }
    
    /**
     * @param Url|string $url
     * @param array $attribs
     *
     * @return string
     */
    static function image($url, array $attribs = []): string
    {
        $attribs['src'] = (string)$url;

        return '<img ' . self::prepareAttributes($attribs) . '>';
    }
}
