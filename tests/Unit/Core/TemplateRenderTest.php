<?php

namespace Tests\Unit\Core;

use core\Path;
use core\Template;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The shortcode parser compiles {{...}} into PHP before the template is included, so a
 * mistake here becomes generated code rather than a caught error.
 */
class TemplateRenderTest extends TestCase
{
    private const DIR = 'application/templates/tests_render';

    protected function setUp(): void
    {
        Template::purgeCaches();
        $this->removeFixtures();
    }

    protected function tearDown(): void
    {
        Template::purgeCaches();
        $this->removeFixtures();
    }

    private function removeFixtures(): void
    {
        $dir = new Path(self::DIR);

        if (is_dir((string)$dir)) {
            $dir->rmpath(true);
        }
    }

    /**
     * Writes a template and returns its name.
     */
    private function template(string $name, string $body): string
    {
        $path = new Path(sprintf('%s/%s.php', self::DIR, $name));
        $path->mkpath();
        file_put_contents((string)$path, $body);

        return 'tests_render/' . $name;
    }

    private function render(string $body, array $data = []): string
    {
        static $counter = 0;
        $name = $this->template('t' . (++$counter), $body);

        return (new Template($name))->make($data);
    }

    // --- construction and data ------------------------------------------------------------

    public function testMissingTemplateIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        new Template('tests_render/no_such_template');
    }

    public function testGetPathPointsAtTheSource(): void
    {
        $name = $this->template('path', 'x');

        self::assertStringContainsString('tests_render', (string)(new Template($name))->getPath());
    }

    public function testDataIsAvailableAsVariables(): void
    {
        self::assertSame('Hello Bob', $this->render('Hello <?= $name ?>', ['name' => 'Bob']));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $template = new Template($this->template('data', 'x'));
        $template->set('a', 1);

        self::assertSame(1, $template->get('a'));
        self::assertSame(1, $template->a);
        self::assertNull($template->get('absent'));
        self::assertSame(['a' => 1], $template->getData());
    }

    public function testSetDataMergesRatherThanReplaces(): void
    {
        $template = new Template($this->template('merge', 'x'));
        $template->setData(['a' => 1])->setData(['b' => 2]);

        self::assertSame(['a' => 1, 'b' => 2], $template->getData());
    }

    public function testCastingRendersTheTemplate(): void
    {
        $template = new Template($this->template('cast', 'output'));

        self::assertSame('output', (string)$template);
    }

    // --- shortcodes ---------------------------------------------------------------------------

    public function testEchoShortcodePrintsAVariable(): void
    {
        self::assertSame('Bob', $this->render('{{$name}}', ['name' => 'Bob']));
    }

    /**
     * {{$var}} is deliberately unescaped: templates emit already-rendered markup through it.
     */
    public function testEchoShortcodeDoesNotEscape(): void
    {
        self::assertSame('<b>x</b>', $this->render('{{$html}}', ['html' => '<b>x</b>']));
    }

    public function testEscapeShortcodeEscapes(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $this->render('{{escape|$value}}', ['value' => '<script>alert(1)</script>'])
        );
    }

    public function testConstantsArePrinted(): void
    {
        self::assertSame(PHP_EOL, $this->render('{{PHP_EOL}}'));
    }

    /**
     * HCPHP has four shortcodes -- echo, escape, template and lang -- and control flow is
     * written as ordinary PHP. The downstream forks grew {{if}} and {{foreach}}; this pins
     * that HCPHP deliberately did not, so a template copied from one of them fails loudly
     * rather than rendering the literal braces into the page.
     */
    public function testControlFlowShortcodesAreNotSupported(): void
    {
        self::assertSame('%shortcode%', @$this->render('{{if|$flag}}'));
        self::assertSame('%shortcode%', @$this->render('{{foreach|$items as $i}}'));
    }

    public function testControlFlowIsWrittenAsPlainPhp(): void
    {
        self::assertSame(
            'abc',
            $this->render('<?php foreach ($items as $i): ?>{{$i}}<?php endforeach; ?>', ['items' => ['a', 'b', 'c']])
        );
    }

    public function testCommentsAreRemoved(): void
    {
        self::assertSame('ab', $this->render('a{* not output *}b'));
    }

    /**
     * A leading ! escapes the shortcode so it can be shown rather than run.
     */
    public function testEscapedShortcodeIsPrintedLiterally(): void
    {
        self::assertSame('{{$name}}', $this->render('!{{$name}}', ['name' => 'Bob']));
    }

    public function testNestedTemplateIsRendered(): void
    {
        $inner = $this->template('inner', 'INNER');

        self::assertSame('[INNER]', $this->render(sprintf('[{{template|%s}}]', $inner)));
    }

    public function testLangShortcodeReadsALanguageString(): void
    {
        // 'yes' is in the shipped en.json; the point is that the shortcode compiles and
        // resolves rather than the exact wording.
        self::assertNotSame('', $this->render("{{lang|'yes'}}"));
    }

    public function testCustomShortcodeIsUsed(): void
    {
        Template::addShortcode('tests_upper', function (array $params) {
            return sprintf('<?php echo strtoupper(%s) ?>', $params[1]);
        });

        self::assertSame('BOB', $this->render('{{tests_upper|$name}}', ['name' => 'Bob']));
    }

    /**
     * An unknown shortcode is reported rather than emitted as broken PHP.
     */
    public function testUnknownShortcodeIsReplacedWithANotice(): void
    {
        self::assertSame('%shortcode%', @$this->render('{{no_such_shortcode|x}}'));
    }

    public function testShortcodesCanBeTurnedOff(): void
    {
        $name = $this->template('raw', '{{$name}}');
        $template = new Template($name);
        $template->useShortCodes(false);

        self::assertFalse($template->isUsesShortCodes());
        self::assertSame('{{$name}}', $template->make(['name' => 'Bob']));
    }

    // --- compilation cache ------------------------------------------------------------------------

    public function testTheCompiledTemplateIsReusedUntilTheSourceChanges(): void
    {
        $name = $this->template('cached', 'first');

        self::assertSame('first', (new Template($name))->make());

        $source = new Path(sprintf('%s/cached.php', self::DIR));
        file_put_contents((string)$source, 'second');
        touch((string)$source, time() + 10);

        self::assertSame('second', (new Template($name))->make());
    }
}
