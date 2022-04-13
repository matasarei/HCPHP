<?php

namespace DynamicDB\Field;

use core\DatabaseSQL;

/**
 * Abstract field definition for dynamic database
 * 
 * @package    hcphp
 * @copyright  2018 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Field
{   
    /**
     * @var string
     */
    protected $tableName;
    
    /**
     * Field name
     *
     * @var string
     */
    protected $name;
    
    /**
     * Database interface
     *
     * @var DatabaseSQL
     */
    protected $database;
    
    /**
     * Default value
     *
     * @var string|int|bool|null
     */
    protected $default = null;
    
    /**
     * Insert after field
     *
     * @var string
     */
    protected $after = null;

    public function __construct(DatabaseSQL $DB, string $table, string $name)
    {
        $this->database = $DB;
        $this->tableName = $table;
        $this->name = $name;
    }

    public function setAfter(string $fieldName): self
    {
        $this->after = $fieldName;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isExist(): bool
    {
        $sql = "SHOW COLUMNS FROM {$this->tableName} WHERE Field LIKE '{$this->name}'";

        return (bool)$this->database->getResultSQL($sql);
    }

    abstract public function create(): void;
    abstract public function update(string $rename = null): void;

    /**
     * Remove field
     * WARNING! REMOVES DATA!
     */
    public function remove() 
    {
        $this->database->executeSQL("ALTER TABLE `{$this->tableName}` DROP COLUMN `{$this->name}`;");
    }

    /**
     * @param string|int|bool $value
     *
     * @return self
     */
    public function setDefault($value): self
    {
        $this->default = $value;

        return $this;
    }

    /**
     * @param string|int $value
     *
     * @return self
     */
    public function addValue($value): self
    {
        if ($this->default) {
            $this->default .= ";{$value}";

            return $this;
        }

        $this->default = $value;

        return $this;
    }

    /**
     * @return string|int|bool|null
     */
    public function getDefault()
    {
        return $this->default;
    }

    protected function prepareDefault(): string
    {
        if ($this->default) {
            return "'" . preg_replace("/'/", "\'", $this->default, -1) . "'";
        }

        return 'NULL';
    }
}
