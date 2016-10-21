<?php
/**
 * onInit event handler
 *
 * @package hcphp
 * @author Yevhen Matasar <matasar.ei@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \core\Handler,
    \core\Config,
    \core\Template,
    \core\DatabaseSQL;

class onInit extends Handler {
    
    /**
     * 
     * @param type $data
     */
    protected function handle($data) { 
        // Database initialization.
        //$this->initDatabases($data);
        
        // Extend templates parser.
        $this->extendTemplates($data);
    }
    
    /**
     * 
     */
    protected function extendTemplates() {
        Template::addShortcode('html', function($params, $info) {
            if (empty($params[1])) {
                return Template::replaceWithNotice($params, $info);
            }
            
            return "<?php echo core\Filters::html({$params[1]}) ?>";
        });
    }
    
    /**
     * Initialize database connections and make some preperations
     */
    protected function initDatabases() {
        //database config.
        $dbconf = new Config('database', [
            'driver', 'host', 'dbname', 'user', 'pass' => '', 'prefix' => '', 'encoding'
        ]);
        
        //database instance.
        $DB = new DatabaseSQL($dbconf->driver, $dbconf->host, $dbconf->dbname, $dbconf->user, 
                           $dbconf->pass, $dbconf->prefix, $dbconf->encoding);

        //register instance.
        DatabaseSQL::pushInstance('default', $DB);
    }
    
}