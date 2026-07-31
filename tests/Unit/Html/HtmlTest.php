<?php

namespace Tests\Unit\Html;

use core\Url;
use Html\Element;
use Html\Html;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppConfig;

/**
 * Html is the layer between application values and Xml's escaping. Xml is well covered, but
 * nothing checked that these helpers actually route their values through it -- which is where
 * an XSS would reappear without a test noticing.
 */
class HtmlTest extends TestCase
{
    // --- link ----------------------------------------------------------------------------

    public function testLinkUsesTheUrlAsBothHrefAndLabelWhenNoNameIsGiven(): void
    {
        $html = Html::link('http://example.com/a');

        self::assertStringContainsString('href="http://example.com/a"', $html);
        self::assertStringContainsString('>http://example.com/a</a>', $html);
    }

    public function testLinkUsesTheGivenName(): void
    {
        self::assertStringContainsString('>Click me</a>', Html::link('/a', 'Click me'));
    }

    public function testLinkGetsADefaultClass(): void
    {
        self::assertStringContainsString('class="hcphp-link"', Html::link('/a'));
    }

    public function testLinkKeepsAnExplicitClass(): void
    {
        $html = Html::link('/a', 'x', ['class' => 'btn']);

        self::assertStringContainsString('class="btn"', $html);
        self::assertStringNotContainsString('hcphp-link', $html);
    }

    public function testLinkAcceptsAUrlObject(): void
    {
        self::assertStringContainsString('href="http://localhost/a"', Html::link(new Url('a')));
    }

    /**
     * The payload that motivated the Xml escaping fix, checked at the layer that actually
     * emits it: the quote must not close the attribute.
     */
    public function testLinkEscapesAnAttributeBreakout(): void
    {
        $html = Html::link('/a', 'name', ['title' => '" onmouseover=alert(1) x="']);

        self::assertStringNotContainsString('onmouseover=alert(1) x="', $html);
        self::assertStringContainsString('&quot; onmouseover=alert(1) x=&quot;', $html);
    }

    public function testLinkEscapesTheHref(): void
    {
        $html = Html::link('/a?b=1&c="x"');

        self::assertStringContainsString('&amp;', $html);
        self::assertStringNotContainsString('c="x"', $html);
    }

    /**
     * With no name the URL becomes the link text. It is data, not caller-supplied markup, so
     * it has to be escaped -- it used to be written raw while the href beside it was escaped,
     * which is what made the gap easy to miss.
     */
    public function testLinkEscapesTheUrlWhenItIsUsedAsTheLabel(): void
    {
        $html = Html::link('/a?x=<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * The label is inserted as markup, not escaped. Callers pass already-rendered HTML here
     * (an icon span, a nested tag), so this pins the contract rather than approving of it.
     */
    public function testLinkLabelIsNotEscaped(): void
    {
        self::assertStringContainsString('<b>bold</b>', Html::link('/a', '<b>bold</b>'));
    }

    // --- image ---------------------------------------------------------------------------

    public function testImageBuildsAnImgWithTheUrlAsSrc(): void
    {
        $html = Html::image('/img/a.png');

        self::assertStringStartsWith('<img ', $html);
        self::assertStringContainsString('src="/img/a.png"', $html);
    }

    public function testImageKeepsExtraAttributes(): void
    {
        $html = Html::image('/a.png', ['alt' => 'Alt text', 'width' => 10]);

        self::assertStringContainsString('alt="Alt text"', $html);
        self::assertStringContainsString('width="10"', $html);
    }

    public function testImageEscapesItsAttributes(): void
    {
        $html = Html::image('/a.png', ['alt' => '"><script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>', $html);
    }

    // --- thumbnail -----------------------------------------------------------------------

    public function testThumbnailWrapsAnImageInALink(): void
    {
        $html = Html::thumbnail('/a.png');

        self::assertStringStartsWith('<a ', $html);
        self::assertStringContainsString('<img ', $html);
        self::assertStringEndsWith('</a>', $html);
        self::assertStringContainsString('target="_blank"', $html);
    }

    public function testThumbnailPointsBothTagsAtTheUrl(): void
    {
        $html = Html::thumbnail('/a.png');

        self::assertSame(2, substr_count($html, '/a.png'), 'href and src');
    }

    public function testThumbnailRoutesImageAttributesToTheImgTag(): void
    {
        $html = Html::thumbnail('/a.png', ['alt' => 'Caption', 'width' => 64]);
        $img = substr($html, strpos($html, '<img'));

        self::assertStringContainsString('alt="Caption"', $img);
        self::assertStringContainsString('width="64"', $img);
    }

    public function testThumbnailRoutesOtherAttributesToTheLink(): void
    {
        $html = Html::thumbnail('/a.png', ['rel' => 'nofollow']);
        $anchor = substr($html, 0, strpos($html, '<img'));

        self::assertStringContainsString('rel="nofollow"', $anchor);
    }

    public function testThumbnailUsesDefaultClassesWhenNoneGiven(): void
    {
        $html = Html::thumbnail('/a.png');

        self::assertStringContainsString('hcphp-thumbnail', $html);
        self::assertStringContainsString('hcphp-thumbnail-image', $html);
    }

    public function testThumbnailUsesAnExplicitClassOnTheLink(): void
    {
        self::assertStringContainsString('class="card"', Html::thumbnail('/a.png', ['class' => 'card']));
    }

    // --- Element -------------------------------------------------------------------------

    public function testElementDefaultsToADiv(): void
    {
        self::assertSame('div', (new Element())->getName());
    }

    public function testElementRendersItsTagContentAndAttributes(): void
    {
        $element = (new Element('span'))
            ->setContent('hello')
            ->setAttribute('class', 'greeting')
        ;

        self::assertSame('span', $element->getName());
        self::assertSame('hello', $element->getContent());
        self::assertSame(['class' => 'greeting'], $element->getAttributes());
        self::assertSame('<span class="greeting">hello</span>', $element->getHtml());
    }

    public function testElementNameIsTrimmed(): void
    {
        self::assertSame('p', (new Element())->setName('  p  ')->getName());
    }

    public function testElementContentStartsNull(): void
    {
        self::assertNull((new Element())->getContent());
    }

    public function testElementEscapesAttributeValues(): void
    {
        $html = (new Element('div'))->setAttribute('data-x', '"><script>')->getHtml();

        self::assertStringNotContainsString('<script>', $html);
    }

    public function testElementCastsToItsHtml(): void
    {
        $element = (new Element('i'))->setContent('x');

        self::assertSame($element->getHtml(), (string)$element);
    }
}
