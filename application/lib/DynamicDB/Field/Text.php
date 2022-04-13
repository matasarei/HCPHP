<?php

namespace DynamicDB\Field;

/**
 * @package    dyamicdb
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Text extends Field
{
    const LENGTH_MAX = 8192;
    const TEXT_FIELD_TYPE = 'TEXT';

    /**
     * Field max length
     *
     * @var int
     */
    protected $length = 255;
    
    /**
     * Maximum supported length is 65535 bytes.
     * Field automatically converts into mediumtext if length more than 21844
     * (for multibyte collation, e.g. UTF-8).
     *
     * @param int $value
     */
    public function setLength(int $value)
    {
        if ($value > self::LENGTH_MAX) {
            $this->length = self::LENGTH_MAX;
        }
        
        $this->length = $value;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function create(): void
    {
        $default = $this->prepareDefault();

        $sql = sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s DEFAULT %s',
            $this->tableName,
            $this->name,
            $this->length > static::LENGTH_MAX ? static::TEXT_FIELD_TYPE : "VARCHAR({$this->length})",
            $default
        );

        if ($this->after) {
            $sql .= " AFTER `{$this->after}`;";
        }

        $this->database->executeSQL($sql);
    }

    public function update(string $rename = null): void
    {
        $default = $this->prepareDefault();

        $name = $this->name;
        $rename ? $this->name = $rename : $rename = $name;

        $sql = sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `%s` `%s` %s DEFAULT %s',
            $this->tableName,
            $name,
            $rename,
            $this->length > static::LENGTH_MAX ? static::TEXT_FIELD_TYPE : "VARCHAR({$this->length})",
            $default
        );

        if ($this->after) {
            $sql .= " AFTER `{$this->after}`;";
        }

        $this->database->executeSQL($sql);
    }
}
