<?php

namespace core;

/**
 * Explicit opt-in for SQL LIKE matching inside a condition array.
 *
 * Condition values are data, never syntax. A plain string always compiles to `=`,
 * whatever characters it contains. Wrap a value in this class to ask for LIKE:
 *
 *     $database->getRecords('users', ['email' => new Like('%@example.com')]);
 *
 * The pattern passed to the constructor is used verbatim, so the caller owns any
 * wildcards in it. To build a pattern around untrusted input, use the named
 * constructors below -- they escape `%`, `_` and `!` in the input so a user cannot
 * widen the search:
 *
 *     $database->getRecords('users', ['email' => Like::contains($searchTerm)]);
 *
 * Generated SQL always declares `ESCAPE '!'`, so `!` is the escape character in
 * every pattern, raw or built.
 *
 * @package    hcphp
 * @subpackage core
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class Like
{
    const ESCAPE_CHARACTER = '!';

    /**
     * @var string
     */
    private $pattern;

    /**
     * @param string $pattern Used verbatim; wildcards in it are the caller's responsibility
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * Match values containing $value, with wildcards in $value escaped.
     */
    public static function contains(string $value): self
    {
        return new self('%' . self::escape($value) . '%');
    }

    /**
     * Match values starting with $value, with wildcards in $value escaped.
     */
    public static function startsWith(string $value): self
    {
        return new self(self::escape($value) . '%');
    }

    /**
     * Match values ending with $value, with wildcards in $value escaped.
     */
    public static function endsWith(string $value): self
    {
        return new self('%' . self::escape($value));
    }

    /**
     * Neutralise every LIKE metacharacter in a literal value.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE_CHARACTER, '%', '_'],
            [
                self::ESCAPE_CHARACTER . self::ESCAPE_CHARACTER,
                self::ESCAPE_CHARACTER . '%',
                self::ESCAPE_CHARACTER . '_',
            ],
            $value
        );
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function __toString()
    {
        return $this->pattern;
    }
}
