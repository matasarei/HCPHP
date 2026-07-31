<?php

namespace Tests\Unit\Core;

use core\Xml;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Attribute values are data. They are written between quotes, so every quote inside them
 * has to stop being a quote.
 *
 * The original implementation used preg_replace('/\"/', '\"', ...) -- a C/PHP/SQL escape
 * that HTML does not recognise. The browser closed the attribute at the first quote and read
 * whatever followed as further attributes, which made every rendered form field an XSS vector.
 */
class XmlTest extends TestCase
{
    public function testAttributeValueCannotBreakOutAndAddAnotherAttribute(): void
    {
        $html = Xml::tag('input', null, ['value' => '" onfocus=alert(1) x="']);

        // The payload text survives -- inert, inside the value. What must not survive is a
        // literal quote, because that is what would end the attribute and let "onfocus"
        // become one of its own. Only the two delimiters may be real quotes.
        self::assertSame(2, substr_count($html, '"'), 'Only the attribute delimiters may be unescaped quotes.');
        self::assertSame('<input value="&quot; onfocus=alert(1) x=&quot;" />', $html);
    }

    /**
     * @dataProvider dangerousValueProvider
     */
    public function testSpecialCharactersAreEscaped(string $value, string $expected): void
    {
        self::assertSame(
            sprintf('<input value="%s" />', $expected),
            Xml::tag('input', null, ['value' => $value])
        );
    }

    public function dangerousValueProvider(): array
    {
        return [
            'double quote' => ['say "hi"', 'say &quot;hi&quot;'],
            'single quote' => ["it's", 'it&#039;s'],
            'angle brackets' => ['<script>', '&lt;script&gt;'],
            'ampersand' => ['a & b', 'a &amp; b'],
            'entity-looking text' => ['&amp;', '&amp;amp;'],
        ];
    }

    public function testTagNameAndContentAreUnchanged(): void
    {
        // Content is deliberately not escaped: it is inner HTML and callers own it. Pinned
        // here so the contract cannot drift silently.
        self::assertSame(
            '<div class="x"><b>bold</b></div>',
            Xml::tag('div', '<b>bold</b>', ['class' => 'x'])
        );
    }

    public function testArrayValuesAreJoinedThenEscaped(): void
    {
        // core\Debug passes style declarations as an array.
        self::assertSame(
            '<div style="color:red;font: bold 13px &quot;Monaco&quot;" />',
            Xml::tag('div', null, ['style' => ['color:red', 'font: bold 13px "Monaco"']])
        );
    }

    public function testInvalidUtf8DoesNotSilentlyEmptyTheAttribute(): void
    {
        // Without ENT_SUBSTITUTE, htmlspecialchars() returns '' for malformed UTF-8 on PHP 7,
        // which would drop the value and leave a bare attribute instead.
        $html = Xml::tag('input', null, ['value' => "caf\xE9"]);

        self::assertStringContainsString('value="', $html);
        self::assertStringNotContainsString('<input  value  />', $html);
    }

    public function testNumericValuesAreStillRendered(): void
    {
        self::assertSame('<input value="0" />', Xml::tag('input', null, ['value' => 0]));
        self::assertSame('<input size="10" />', Xml::tag('input', null, ['size' => 10]));
    }

    public function testEmptyValueStillProducesABareAttribute(): void
    {
        // Existing behaviour that required/disabled rely on; not part of this fix.
        self::assertSame('<input  required  />', Xml::tag('input', null, ['required' => '']));
    }

    public function testAttributesWithoutValuesKeepTheirOrder(): void
    {
        self::assertSame(
            '<input type="text" name="q" />',
            Xml::tag('input', null, ['type' => 'text', 'name' => 'q'])
        );
    }

    /**
     * Escaping the value cannot save a name that is itself hostile, so a name that is not a
     * plain attribute name is refused outright.
     *
     * @dataProvider invalidNameProvider
     */
    public function testHostileAttributeNameIsRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        Xml::tag('input', null, [$name => 'x']);
    }

    public function invalidNameProvider(): array
    {
        return [
            'injects an attribute' => ['x" onfocus="alert(1)'],
            'contains a space' => ['data foo'],
            'contains an angle bracket' => ['x<y'],
            'starts with a digit' => ['1abc'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider validNameProvider
     */
    public function testOrdinaryAttributeNamesAreAccepted(string $name): void
    {
        self::assertSame(
            sprintf('<input %s="x" />', $name),
            Xml::tag('input', null, [$name => 'x'])
        );
    }

    public function validNameProvider(): array
    {
        return [
            'simple' => ['name'],
            'data attribute' => ['data-bs-toggle'],
            'underscore' => ['_private'],
            'namespaced' => ['xlink:href'],
            'dotted' => ['x.y'],
        ];
    }
}
