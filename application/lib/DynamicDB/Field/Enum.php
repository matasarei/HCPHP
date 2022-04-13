<?php

namespace DynamicDB\Field;

/**
 * Text field definition for dymanic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Enum extends Field
{
    private $values = [];

    /**
     * @param array|string $value
     *
     * @return Field
     */
    public function addValue($value): Field
    {
        if (in_array($value, $this->values, true)) {
            return $this;
        }

        $this->values[] = (string)$value;

        return $this;
    }

    protected function prepareDefault(): string
    {
        if (!$this->default && $this->values) {
            return $this->values[0];
        }

        return $this->default;
    }

    public function create(): void
    {
        $default = $this->prepareDefault();

        $values = "'" . implode("','", $this->values) . "'";
        
        $sql = "ALTER TABLE `{$this->tableName}`
                 ADD COLUMN `{$this->name}` ENUM({$values})
                   NOT NULL DEFAULT '{$default}'";
                    
        $this->after && $sql .= " AFTER `{$this->after}`;";
                
        $this->database->executeSQL($sql);
    }

    public function update(string $rename = null): void
    {
        $default = $this->prepareDefault();

        $name = $this->name;
        $rename ? $this->name = $rename : $rename = $name;
        
        $values = "'" . implode("','", $this->values) . "'";
        
        $sql = "ALTER TABLE `{$this->tableName}`
              CHANGE COLUMN `{$name}` `{$rename}`
                       ENUM({$values})
           NOT NULL DEFAULT '{$default}'";

        $this->after && $sql .= " AFTER `{$this->after}`;";
        
        $this->database->executeSQL($sql);
    }
}
