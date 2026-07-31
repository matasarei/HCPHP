<?php

namespace DynamicDB\Field;

/**
 * Text field definition for dymanic database
 * 
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Real extends Field
{
    private $length = '';
    private $unsigned = false;
    protected $default = 0;

    public function setLength(float $value): self
    {
        $real = $value;
        $integer = floor($real);
        $fractional = ($real - $integer) * 10;
        
        if ($integer > 255) {
            $integer = 255;
            trigger_error('Max integer part length is 255');
        }
        
        // Unreachable as the length is encoded. $fractional is a fraction times ten, so it is
        // always under 10 and the guard can never fire; a DECIMAL(10,35) cannot be asked for
        // in the first place. Left in place because the limit is real and the encoding is what
        // needs revisiting -- but nothing here clamps anything today.
        //
        // It also used to assign to $integer, so if it ever had fired it would have rewritten
        // DECIMAL(12,40) as DECIMAL(30,40): still invalid, and now wrong about the whole part.
        if ($fractional > 30) {
            $fractional = 30;
            trigger_error('Max fractional part length is 30');
        }
        
        $this->length = "{$integer},{$fractional}";

        return $this;
    }

    public function getLength(): float
    {
        return floatval($this->length);
    }

    /**
     * @param float $value
     *
     * @return Field
     */
    public function setDefault($value): Field
    {
        $this->default = (float)$value;

        return $this;
    }

    /**
     * @param float $value
     *
     * @return Field
     */
    public function addValue($value): Field
    {
        $this->default += (float)$value;

        return $this;
    }

    public function getDefault() 
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
                 ADD COLUMN `{$this->name}` DECIMAL({$this->length}) {$sign}
                   NOT NULL DEFAULT '{$this->default}'";

        if ($this->after) {
            $sql .= " AFTER `{$this->after}`;";
        }

        $this->database->executeSQL($sql);
    }

    public function update(?string $rename = null): void
    {
        $name = $this->name;
        $rename ? $this->name = $rename : $rename = $name;

        $sign = $this->unsigned ? 'UNSIGNED' : '';

        $sql = "ALTER TABLE `{$this->tableName}`
              CHANGE COLUMN `{$name}` `{$rename}`
               DECIMAL({$this->length}) {$sign}
           NOT NULL DEFAULT '{$this->default}'";

        if ($this->after) {
            $sql .= " AFTER `{$this->after}`;";
        }

        $this->database->executeSQL($sql);
    }
}
