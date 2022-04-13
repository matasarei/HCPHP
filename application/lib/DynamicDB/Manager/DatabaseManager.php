<?php

namespace DynamicDB\Manager;

use core\Config;
use core\DatabaseSQL;
use DynamicDB\Field\Field;
use DynamicDB\Field\Integer;
use RuntimeException;
use stdClass;

/**
 * DynamicDB: database abstraction layer for HCPHP
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DatabaseManager
{
    const TABLE_DEFAULT = 'default';
    const TABLE_SERVICE = 'service';

    private $database;
    private $config;
    
    /**
     * @param DatabaseSQL $DB
     * @param Config $config
     */
    public function __construct(DatabaseSQL $DB, $config = 'dynamicdb')
    {
        $config = new Config($config, ['tables']);
        
        $this->database = $DB;
        $this->config = $config;
    }

    public function initialize()
    {
        if (!$this->database->getResultSQL('SHOW TABLES LIKE "{dynamicdb}"')) {
            $sql = 'create table dynamicdb
                (
                    id           int unsigned auto_increment primary key,
                    tablename    varchar(32)  not null,
                    type         varchar(16)  not null,
                    timecreated  int unsigned not null,
                    timemodified int unsigned not null
                );'
            ;

            $this->database->executeSQL($sql);
        }

        $lastUpdated = $this->database->getResultSQL('SELECT MAX(timemodified) FROM {dynamicdb}');

        if ($lastUpdated < $this->config->getTimeModified()) {
            $this->updateTables();
        }
    }

    /**
     * Update tables
     */
    public function updateTables() 
    {
        foreach ($this->config->tables as $table) {
            
            if (empty($table->name)) {
                trigger_error("Empty table name, skipping...");
                continue;
            }
            
            $exist = $this->database->getResultSQL("SHOW TABLES LIKE '{{$table->name}}'");
            
            if ($exist) {
                $this->database->updateRecords('dynamicdb', [
                    'timemodified' => time(),
                    'type' => $table->type ?? self::TABLE_DEFAULT
                ], [
                    'tablename' => $table->name
                ]);
            } else {
                $this->createTable($table->name, $table->type ?? self::TABLE_DEFAULT);
            }
            
            foreach ($table->fields as $fieldInfo) {
                $this->alterTable($table->name, $fieldInfo);
            }
        }
    }
    
    /**
     * @param string $table
     * @param stdClass $fieldInfo
     * 
     * @throws RuntimeException
     */
    private function alterTable(string $table, stdClass $fieldInfo)
    {
        $class = "\\DynamicDB\\Field\\{$fieldInfo->type}";

        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('Field type "%s" is not supported', $fieldInfo->type));
        }

        /** @var Field|Integer $field */
        $field = new $class($this->database, $table, $fieldInfo->name);

        if (isset($fieldInfo->values)) {
            foreach ($fieldInfo->values as $value) {
                $field->addValue($value);
            }
        } elseif (!empty($fieldInfo->length)) {
            $field->setLength($fieldInfo->length);
        }

        if (isset($fieldInfo->default)) {
            $field->setDefault($fieldInfo->default);
        }

        if ($field->isExist()) {
            $field->update();
            
            return true;
            
        } elseif (!empty($fieldInfo->oldname)) {
            $field->setName($fieldInfo->oldname);

            if ($field->isExist()) {
                $field->update($fieldInfo->name);
            }

            return true;
        }

        $field->create();
        
        return true;
    }

    private function createTable(string $name, string $type = self::TABLE_DEFAULT): bool
    {
        $serviceFields = "
            `position` INT(4) UNSIGNED NOT NULL DEFAULT '0',
        ";

        if ($type !== self::TABLE_SERVICE) {
            $serviceFields = "
                `timecreated` INT(10) UNSIGNED NOT NULL,
                `timemodified` INT(10) UNSIGNED NOT NULL,
            ";
        }
        
        $sql = "CREATE TABLE `{$name}` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            {$serviceFields}
            PRIMARY KEY (`id`)
        )";

        //creating new table and check result.
        $this->database->executeSQL($sql);
        
        if ($this->database->getResultSQL("SHOW TABLES LIKE '{{$name}}'")) {
            //if created insert record to master table.
            $exits = $this->database->getRecord('dynamicdb', ['tablename' => $name]);
            
            if ($exits) { // updating exist record.
                $this->database->updateRecords('dynamicdb', [
                    'type'         => $type,
                    'timemodified' => time()
                ], [
                    'tablename' => $name
                ]);
                
            } else { // insert new record.
                $this->database->insertRecord('dynamicdb', [
                    'tablename'    => $name, 
                    'type'         => $type,
                    'timecreated'  => time(),
                    'timemodified' => time()
                ]);
            }
            
            return true;
        }
        
        trigger_error("Can't create new table");
        
        return false;
    }
}
