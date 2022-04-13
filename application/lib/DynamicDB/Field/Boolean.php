<?php

namespace DynamicDB\Field;

/**
 * Integer field definition for dynamic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Boolean extends Field
{
    /**
     * @param bool|int $value
     *
     * @return Field
     */
    public function setDefault($value): Field
    {
        $this->default = (bool)$value;

        return $this;
    }

    public function create(): void
    {
        $sql = sprintf(
            "ALTER TABLE `%s` ADD COLUMN `%s` TINYINT(1) UNSIGNED NOT NULL DEFAULT '%d'",
            $this->tableName,
            $this->name,
            $this->default ? 1 : 0
        );

        $this->after && $sql .= " AFTER `{$this->after}`;";
                
        $this->database->executeSQL($sql);
    }

    public function update(string $rename = null): void
    {
        $name = $this->name;
        $rename ? $this->name = $rename : $rename = $name;

        $sql = "ALTER TABLE `{$this->tableName}`
              CHANGE COLUMN `{$name}` `{$rename}` TINYINT(1) UNSIGNED
           NOT NULL DEFAULT '{$this->default}'";

        $this->after && $sql .= " AFTER `{$this->after}`;";
        
        $this->database->executeSQL($sql);
    }
}
