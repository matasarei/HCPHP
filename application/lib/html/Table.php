<?php
/**
 * HCPHP
 *
 * @package    hcphp
 * @subpackage html
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141120
 */

namespace html;

use core\Object,
    core\Html;

/**
 * 
 */
class Table extends Object {
    
    private $_head = [];
    private $_data = [];
    private $_caption;
    private $_uniqStyles = 2;
    private $_attributes = [];
    
    /**
     * 
     * @param array $data Table data (assoc array: data[row][cell])
     */
    public function __construct(array $data = []) {
        $data && $this->_data = $data;
    }
    
    /**
     * returns head cells
     * @return array
     */
    public function getHead() {
        return $this->_head;
    }
    
    /**
     * Set head cells
     * @param array $value cells array
     */
    public function setHead(array $value) {
        $this->_head = $value;
    }
    
    /**
     * Get table data
     * @return array
     */
    public function getData() {
        return $this->_data;
    }
    
    /**
     * Set table data
     * @param array $value Table data (assoc array: data[row][cell])
     */
    public function setData(array $value) {
        $this->_data = $value;
    }
    
    /**
     * Set table caption
     * @param type $caption
     */
    public function setCaption($caption) {
        $this->_caption = trim($caption);
    }
    
    /**
     * Returns table caption
     * @return type
     */
    public function getCaption() {
        return $this->_caption;
    }
    
    /**
     * Set count of uniq styles
     * @param int $count
     */
    public function setUniqStyles($count) {
        $this->_uniqStyles = (int)$count;
    }
    
    /**
     * Returns count of uniq styles
     * @return int
     */
    public function getUniqStyles() {
        return $this->_uniqStyles;
    }
    
    /**
     * Set attributes
     * @param array $attributes Assoc array
     */
    public function setAttributes(array $attributes) {
        $this->_attributes = $attributes;
    }
    
    /**
     * Add attribute name
     * @param string $name attrbute name
     * @param string $value attribute value
     */
    public function addAttribute($name, $value) {
        $this->_attributes[$name] = $value;
    }
    
    /**
     * Remove table attribute
     * @param string $name attrib name
     * @return boolean
     */
    public function removeAttribute($name) {
        if (isset($this->_attributes[$name])) {
            unset($this->_attributes[$name]);
            return true;
        }
        return false;
    }
    
    /**
     * Get table attributes
     * @return type
     */
    public function getAttributes() {
        return $this->_attributes;
    }
    
    /**
     * Add row
     * @param array $row row data
     */
    public function addRow(array $row) {
        $this->_data[] = $row;
    }
    
    /**
     * Make html code
     * @return string html code
     */
    public function make() {
        if (!empty($this->_caption)) {
            $html = Html::tag('caption', $this->_caption);
        } else {
            $html = '';
        }
        $thead = $this->makeRow($this->_head, 'th');
        $thead && $html .= Html::tag('thead', $thead);
        $tbody = '';
        $styles = 1;
        foreach($this->_data as $row) {
            $attributes = ['class'=>"r{$styles}"];
            $tbody .= Html::tag('tr', $this->makeRow($row, 'td', $attributes));
            ++$styles && $styles > $this->_uniqStyles && $styles = 1;
        }
        $tbody && $html .= Html::tag('tbody', $tbody);
        if ($html) {
            return Html::tag('table', $html, $this->_attributes);
        }
        return null;
    }
    
    /**
     * Make row (html)
     * @param array $row row data
     * @param string $tag tag name
     * @param array $attributes row container attribues
     * @return string html code
     */
    private function makeRow(array $row, $tag, array $attributes = []) {
        $html = '';
        foreach ($row as $key => $cell) {
            if (is_array($cell)) {
                isset($cell['class']) && $cell['class'] .= " {$attributes['class']}";
                $html .= Html::tag($tag, $key, $cell);
            } else {
                $html .= Html::tag($tag, $cell, $attributes);
            }
        }
        return $html;
    }
    
    public function __toString() {
        try {
            return $this->make();
        } catch (\Exception $ex) {
            return '';
            trigger_error($ex->getMessage());
        }
    }
}