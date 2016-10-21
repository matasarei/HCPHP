<?php
/**
 * Event handler
 * 
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20160526
 */

namespace core;

abstract class Handler extends Object {
    
    /**
     * Construct and run handler
     * @param \stdClass $data Event data
     */
    public function __construct(\stdClass $data = null) {
        $this->handle($data);
    }
    
    /**
     * Handle operations implements here
     */
    abstract protected function handle($data);
    
}