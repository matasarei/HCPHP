<?php

namespace Tests\Unit\Filter;

use Filter\HtmlFilter;
use Filter\TagsFilter;
use PHPUnit\Framework\TestCase;

/**
 * The tag allow-list used strip_tags(), which keeps every attribute of the tags it allows.
 * So <a> and <img> survived with their event handlers and javascript: URLs intact, and the
 * "filter" passed the classic payloads straight through.
 */
class HtmlFilterTest extends TestCase
{
    /**
     * @var HtmlFilter
     */
    private $filter;

    protected function setUp(): void
    {
        $this->filter = new HtmlFilter();
    }

    /**
     * @dataProvider xssPayloadProvider
     */
    public function testPayloadLosesItsTeeth(string $payload, string $mustNotContain): void
    {
        $filtered = (string)$this->filter->filter($payload);

        self::assertStringNotContainsStringIgnoringCase($mustNotContain, $filtered);
    }

    public function xssPayloadProvider(): array
    {
        return [
            'img onerror' => ['<img src=x onerror=alert(1)>', 'onerror'],
            'svg onload' => ['<svg onload=alert(1)>', 'onload'],
            'a javascript url' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'a JaVaScRiPt url' => ['<a href="JaVaScRiPt:alert(1)">x</a>', 'javascript:'],
            'a data url' => ['<a href="data:text/html,<script>alert(1)</script>">x</a>', 'data:'],
            'entity-encoded scheme' => ['<a href="java&#115;cript:alert(1)">x</a>', 'javascript:'],
            'whitespace in scheme' => ["<a href=\"java\tscript:alert(1)\">x</a>", 'javascript:'],
            'onmouseover on b' => ['<b onmouseover=alert(1)>hi</b>', 'onmouseover'],
            'style expression' => ['<b style="background:url(javascript:alert(1))">x</b>', 'javascript:'],
            'script element' => ['<script>alert(1)</script>', '<script'],
            'iframe' => ['<iframe src="//evil.test"></iframe>', '<iframe'],
        ];
    }

    public function testHarmlessMarkupSurvives(): void
    {
        $filtered = (string)$this->filter->filter('<b>bold</b> and <i>italic</i>');

        self::assertStringContainsString('<b>bold</b>', $filtered);
        self::assertStringContainsString('<i>italic</i>', $filtered);
    }

    public function testSafeLinksKeepTheirDestination(): void
    {
        $filtered = (string)$this->filter->filter('<a href="https://example.com/x?a=1&b=2">link</a>');

        self::assertStringContainsString('https://example.com/x?a=1', $filtered);
        self::assertStringContainsString('link', $filtered);
    }

    public function testRelativeLinksAreKept(): void
    {
        $filtered = (string)$this->filter->filter('<a href="/records/1/">link</a>');

        self::assertStringContainsString('/records/1/', $filtered);
    }

    public function testImageKeepsSourceAndAlt(): void
    {
        $filtered = (string)(new TagsFilter())->filter('<img src="/x.png" alt="a picture" onerror="alert(1)">');

        self::assertStringContainsString('/x.png', $filtered);
        self::assertStringContainsString('a picture', $filtered);
        self::assertStringNotContainsStringIgnoringCase('onerror', $filtered);
    }

    public function testTextContentIsPreserved(): void
    {
        self::assertStringContainsString(
            'plain words',
            (string)$this->filter->filter('plain words')
        );
    }

    public function testClosingTagsSurvive(): void
    {
        $filtered = (string)(new TagsFilter())->filter('<p>one</p><p>two</p>');

        self::assertSame(2, substr_count($filtered, '</p>'));
    }
}
