<?php

namespace DynamicDB\Field;

/**
 * Integer field definition for dynamic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Integer extends Field
{
    const TYPE_BIG = 'BIGINT';
    const TYPE_MEDIUM = 'MEDIUMINT';
    const TYPE_DEFAULT= 'INT';
    const TYPE_TINY = 'TINYINT';

    protected $length = 11;
    protected $default = 0;
    protected $type = self::TYPE_DEFAULT;
    protected $unsigned = false;

    public function setUnsigned(bool $val): self
    {
        $this->unsigned = $val;

        return $this;
    }

    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function setLength(int $value): self
    {
        if ($value > 10) {
            $this->type = self::TYPE_BIG;
        } elseif ($value > 7) {
            $this->type = self::TYPE_DEFAULT;
        } elseif ($value > 3) {
            $this->type = self::TYPE_MEDIUM;
        } else {
            if ($value == 1) {
                $this->unsigned = false;
            }
            $this->type = self::TYPE_TINY;
        }

        $this->length = $value;

        return $this;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @param int $value
     *
     * @return self
     */
    public function setDefault($value): Field
    {
        $this->default = (int)$value;

        return $this;
    }

    public function addValue($value): Field
    {
        $this->default += $value;

        return $this;
    }

    public function getDefault(): int
    {
        return $this->default;
    }

    protected function prepareDefault(): string
    {
        return $this->default;
    }

    public function create(): void
    {
        $sign = $this->unsigned ? 'UNSIGNED' : '';
        
        $sql = "ALTER TABLE `{$this->tableName}`
                 ADD COLUMN `{$this->name}` {$this->type}({$this->length}) {$sign}
                   NOT NULL DEFAULT '{$this->default}'";
                    
        if ($this->after) {
            $sql .= " AFTER `{$this->after}`;";
        }
                
        $this->database->executeSQL($sql);
    }

    public function update(string $rename = null): void
    {
        $name = $this->name;
        $rename ? $this->name = $rename : $rename = $name;
        
        $sign = $this->unsigned ? 'UNSIGNED' : '';

        $sql = "ALTER TABLE `{$this->tableName}`
              CHANGE COLUMN `{$name}` `{$rename}`
               {$this->type}({$this->length}) {$sign}
           NOT NULL DEFAULT '{$this->default}'";

        $this->after && $sql .= " AFTER `{$this->after}`;";
        
        $this->database->executeSQL($sql);
    }
}
