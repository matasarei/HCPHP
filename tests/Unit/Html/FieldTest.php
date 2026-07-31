<?php

namespace Tests\Unit\Html;

use core\Template;
use Html\Form\Input;
use Html\Form\Option;
use Html\Form\Select;
use Html\Form\Textarea;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
    // --- the shared Field base, exercised through Input -----------------------------------

    public function testRendersAnInputCarryingItsName(): void
    {
        $html = (new Input('email'))->getHtml();

        self::assertStringStartsWith('<input ', $html);
        self::assertStringContainsString('name="email"', $html);
        self::assertStringContainsString('type="text"', $html);
    }

    public function testIdDefaultsToTheNameWithAPrefix(): void
    {
        self::assertStringContainsString('id="id_email"', (new Input('email'))->getHtml());
    }

    public function testExplicitIdWins(): void
    {
        $html = (new Input('email'))->addAttribute('id', 'custom')->getHtml();

        self::assertStringContainsString('id="custom"', $html);
        self::assertStringNotContainsString('id_email', $html);
    }

    public function testDefaultBecomesTheValue(): void
    {
        self::assertStringContainsString('value="bob"', (new Input('n', null, 'bob'))->getHtml());
    }

    public function testValueOverridesTheDefault(): void
    {
        $html = (new Input('n', null, 'default'))->setValue('actual')->getHtml();

        self::assertStringContainsString('value="actual"', $html);
        self::assertStringNotContainsString('default', $html);
    }

    public function testNoValueAttributeWhenThereIsNeitherValueNorDefault(): void
    {
        self::assertStringNotContainsString('value=', (new Input('n'))->getHtml());
    }

    /**
     * ?? binds looser than !==, so the guard used to read "value ?? (default !== null)" and a
     * falsy value counted as absent. Editing a record whose integer field was 0 showed an
     * empty box, and saving wrote the blank back.
     *
     * @dataProvider falsyValueProvider
     */
    public function testFalsyValuesStillRenderAValueAttribute($value, string $expected): void
    {
        self::assertStringContainsString(
            $expected,
            (new Input('n'))->setValue($value)->getHtml()
        );
    }

    public function falsyValueProvider(): array
    {
        return [
            'string zero' => ['0', 'value="0"'],
            'integer zero' => [0, 'value="0"'],
            // Xml renders an empty non-numeric value as a bare attribute; that is the same
            // path "required" and "disabled" rely on. The point here is that it is present.
            'empty string' => ['', ' value '],
        ];
    }

    public function testFalsyDefaultAlsoSurvives(): void
    {
        self::assertStringContainsString('value="0"', (new Input('n', null, '0'))->getHtml());
    }

    public function testRequiredAddsTheAttribute(): void
    {
        self::assertStringContainsString('required', (new Input('n', null, null, true))->getHtml());
        self::assertTrue((new Input('n', null, null, true))->isRequired());
    }

    public function testDisabledAddsTheAttribute(): void
    {
        $field = new Input('n');
        $field->setDisabled(true);

        self::assertTrue($field->isDisabled());
        self::assertStringContainsString('disabled', $field->getHtml());
    }

    /**
     * isDisabled() called a getDisabled() that does not exist, so asking was a fatal error.
     */
    public function testIsDisabledIsFalseByDefaultRatherThanFatal(): void
    {
        self::assertFalse((new Input('n'))->isDisabled());
    }

    public function testPlaceholderIsRendered(): void
    {
        $html = (new Input('n'))->setPlaceholder('Type here')->getHtml();

        self::assertStringContainsString('placeholder="Type here"', $html);
    }

    /**
     * setTitle() fills the placeholder only while it is still unset.
     */
    public function testTitleSeedsThePlaceholderOnlyOnce(): void
    {
        $field = (new Input('n'))->setTitle('First');
        self::assertSame('First', $field->getPlaceholder());

        $field->setTitle('Second');
        self::assertSame('First', $field->getPlaceholder(), 'an existing placeholder is kept');
    }

    public function testExplicitPlaceholderIsNotOverwrittenByTitle(): void
    {
        $field = (new Input('n'))->setPlaceholder('Hint')->setTitle('Label');

        self::assertSame('Hint', $field->getPlaceholder());
    }

    public function testAccessorsRoundTrip(): void
    {
        $field = (new Input('n'))
            ->setName('renamed')
            ->setTitle('Title')
            ->setDescription('Description')
            ->setPattern('/^a+$/')
            ->setDefault('d')
            ->setRequired(true)
        ;

        self::assertSame('renamed', $field->getName());
        self::assertSame('Title', $field->getTitle());
        self::assertSame('Description', $field->getDescription());
        self::assertSame('/^a+$/', $field->getPattern());
        self::assertSame('d', $field->getDefault());
        self::assertTrue($field->isRequired());
    }

    public function testUnsetAccessorsAreNull(): void
    {
        $field = new Input('n');

        self::assertNull($field->getTitle());
        self::assertNull($field->getDescription());
        self::assertNull($field->getPattern());
        self::assertNull($field->getDefault());
        self::assertNull($field->getValue());
        self::assertNull($field->getPlaceholder());
    }

    public function testAttributesAreStringsAndReadable(): void
    {
        $field = (new Input('n'))->addAttribute('data-count', 5);

        self::assertSame('5', $field->getAttribute('data-count'));
        self::assertSame(['data-count' => '5'], $field->getAttributes());
        self::assertNull($field->getAttribute('absent'));
        self::assertSame('fallback', $field->getAttribute('absent', 'fallback'));
    }

    public function testFieldValuesAreEscaped(): void
    {
        $html = (new Input('n'))->setValue('" onfocus=alert(1) x="')->getHtml();

        self::assertStringNotContainsString('" onfocus=alert(1) x="', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    public function testCastingRendersTheField(): void
    {
        $field = new Input('n');

        self::assertSame($field->getHtml(), (string)$field);
    }

    /**
     * A field carrying a template renders through it instead of building its own markup, and
     * the field is handed to the template as $field.
     */
    public function testATemplateReplacesTheBuiltInRendering(): void
    {
        $source = new \core\Path('application/templates/tests_fixture/field.php');
        $source->mkpath();
        file_put_contents((string)$source, '<?php echo "templated:" . $field->getName(); ?>');

        try {
            $field = new Input('n');
            $field->setTemplate(new Template('tests_fixture/field'));

            self::assertSame('templated:n', $field->getHtml());
        } finally {
            Template::purgeCaches();
            (new \core\Path('application/templates/tests_fixture'))->rmpath(true);
        }
    }

    // --- Input -----------------------------------------------------------------------------

    public function testInputTypeIsSettable(): void
    {
        $field = (new Input('n'))->setType(Input::TYPE_PASSWORD);

        self::assertSame('password', $field->getType());
        self::assertStringContainsString('type="password"', $field->getHtml());
    }

    // --- Textarea ---------------------------------------------------------------------------

    public function testTextareaPutsItsValueInTheBody(): void
    {
        $html = (new Textarea('bio'))->setValue('hello')->getHtml();

        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('>hello</textarea>', $html);
    }

    public function testTextareaFallsBackToItsDefaultThenToEmpty(): void
    {
        self::assertStringContainsString('>d</textarea>', (new Textarea('b', null, 'd'))->getHtml());
        self::assertStringContainsString('></textarea>', (new Textarea('b'))->getHtml());
    }

    public function testTextareaBodyIsEscaped(): void
    {
        $html = (new Textarea('b'))->setValue('</textarea><script>alert(1)</script>')->getHtml();

        self::assertStringNotContainsString('<script>', $html);
    }

    // --- Select and Option ------------------------------------------------------------------

    public function testSelectRendersItsOptions(): void
    {
        $html = (new Select('choice'))
            ->addOption(new Option('a', 'Apple'))
            ->addOption(new Option('b', 'Banana'))
            ->getHtml()
        ;

        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString('<option value="a">Apple</option>', $html);
        self::assertStringContainsString('<option value="b">Banana</option>', $html);
    }

    public function testOptionFallsBackToItsValueAsTheLabel(): void
    {
        $html = (new Select('c'))->addOption(new Option('only'))->getHtml();

        self::assertStringContainsString('>only</option>', $html);
    }

    public function testSelectMarksTheMatchingOptionSelected(): void
    {
        $select = new Select('c');
        $select->addOption(new Option('a'))->addOption(new Option('b'));
        $select->setValue('b');

        $html = $select->getHtml();

        self::assertStringContainsString('<option value="b" selected="selected">', $html);
        self::assertSame(1, substr_count($html, 'selected='));
    }

    public function testSelectFallsBackToItsDefaultForSelection(): void
    {
        $select = new Select('c', null, 'a');
        $select->addOption(new Option('a'))->addOption(new Option('b'));

        self::assertStringContainsString('<option value="a" selected="selected">', $select->getHtml());
    }

    /**
     * The select's own value attribute is meaningless; selection lives on the options.
     */
    public function testSelectItselfCarriesNoValueAttribute(): void
    {
        $select = new Select('c', null, 'a');
        $select->addOption(new Option('a'));

        $tag = substr($select->getHtml(), 0, strpos($select->getHtml(), '>'));

        self::assertStringNotContainsString('value=', $tag);
    }

    public function testMultipleSelectRendersAnArrayName(): void
    {
        $select = (new Select('c'))->setMultiple(true);
        $select->addOption(new Option('a'));

        self::assertTrue($select->isMultiple());
        self::assertStringContainsString('name="c[]"', $select->getHtml());
        self::assertStringContainsString('multiple="multiple"', $select->getHtml());
    }

    public function testMultipleSelectMarksEveryChosenOption(): void
    {
        $select = (new Select('c'))->setMultiple(true);
        $select->addOption(new Option('a'))->addOption(new Option('b'))->addOption(new Option('c'));
        $select->setValue(['a', 'c']);

        $html = $select->getHtml();

        self::assertSame(2, substr_count($html, 'selected='));
        self::assertStringContainsString('<option value="a" selected="selected">', $html);
        self::assertStringContainsString('<option value="c" selected="selected">', $html);
    }

    /**
     * A multiple select always reports an array, so callers can iterate without checking.
     */
    public function testMultipleSelectValueIsAlwaysAnArray(): void
    {
        $select = (new Select('c'))->setMultiple(true);

        self::assertSame([], $select->getValue());

        $select->setValue('not an array');

        self::assertSame([], $select->getValue());
    }

    public function testSingleSelectValueIsTheScalar(): void
    {
        $select = new Select('c');
        $select->setValue('x');

        self::assertSame('x', $select->getValue());
    }

    public function testOptionAccessorsRoundTrip(): void
    {
        $option = new Option('v', 'Title', ['data-x' => '1']);

        self::assertSame('v', $option->getValue());
        self::assertSame('Title', $option->getTitle());
        self::assertSame(['data-x' => '1'], $option->getAttributes());

        $option->setValue('w')->setTitle('Other')->setAttributes(['a' => 'b']);

        self::assertSame('w', $option->getValue());
        self::assertSame('Other', $option->getTitle());
        self::assertSame(['a' => 'b'], $option->getAttributes());
    }

    public function testOptionLabelsAreEscaped(): void
    {
        $html = (new Select('c'))->addOption(new Option('v', '<script>alert(1)</script>'))->getHtml();

        self::assertStringNotContainsString('<script>', $html);
    }
}
