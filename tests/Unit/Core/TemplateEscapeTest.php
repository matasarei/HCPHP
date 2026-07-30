<?php

namespace Tests\Unit\Core;

use core\Template;
use PHPUnit\Framework\TestCase;

/**
 * Templates emit values in two very different situations: as inner HTML that the caller
 * built ({{$content}}, {{$form}}) and as data that came from a user. The first must not be
 * escaped, the second must. There is no way to tell them apart automatically, so escaping is
 * an explicit shortcode and this pins what it does.
 *
 * @covers \core\Template
 */
class TemplateEscapeTest extends TestCase
{
    /**
     * @dataProvider payloadProvider
     */
    public function testEscapeNeutralisesMarkup(string $raw, string $expected): void
    {
        self::assertSame($expected, Template::escape($raw));
    }

    public function payloadProvider(): array
    {
        return [
            'script tag' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'attribute breakout' => ['" onfocus=alert(1) x="', '&quot; onfocus=alert(1) x=&quot;'],
            'single quotes' => ["' onload='alert(1)", '&#039; onload=&#039;alert(1)'],
            'ampersand' => ['a & b', 'a &amp; b'],
            'plain text' => ['hello world', 'hello world'],
        ];
    }

    public function testEscapeHandlesNonStrings(): void
    {
        self::assertSame('0', Template::escape(0));
        self::assertSame('', Template::escape(null));
        self::assertSame('1.5', Template::escape(1.5));
    }

    public function testEscapeDoesNotEmptyMalformedUtf8(): void
    {
        // Without ENT_SUBSTITUTE this returns '' on PHP 7 and the value silently disappears.
        self::assertNotSame('', Template::escape("caf\xE9"));
    }

    public function testEscapeShortcodeCompilesToAnEscapingEcho(): void
    {
        $template = new Template('form/default');
        $compiled = $template->parseEscape(['escape', '$foo'], (object)['file' => 'x', 'line' => 1]);

        self::assertStringContainsString('Template::escape($foo)', $compiled);
        self::assertStringStartsWith('<?php echo', $compiled);
    }
}
