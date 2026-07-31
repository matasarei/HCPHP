<?php

namespace Tests\Unit\Html;

use core\Csrf;
use core\Url;
use Html\Form\Button;
use Html\Form\Exception\InvalidDataException;
use Html\Form\Exception\InvalidFormException;
use Html\Form\Form;
use Html\Form\Input;
use Html\Form\Select;
use Html\Form\Validator;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppConfig;

/**
 * @covers \Html\Form\Form
 * @covers \Html\Form\Button
 * @covers \Html\Form\Validator
 * @covers \Html\Form\Exception\InvalidDataException
 */
class FormTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        AppConfig::ensure();
    }

    public static function tearDownAfterClass(): void
    {
        AppConfig::release();
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Globals::post() asks REQUEST_METHOD whether this is a POST; there is no such header in
     * a CLI process, so the tests that submit a form have to say so.
     */
    private function asPostRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];
        unset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Built without a template so the assertions are about Form's own markup rather than the
     * shipped one.
     */
    private function form(string $method = Form::METHOD_POST): Form
    {
        return new Form($method, null, null);
    }

    // --- rendering -------------------------------------------------------------------------

    public function testRendersAFormTagCarryingItsMethod(): void
    {
        $html = $this->form()->getHtml();

        self::assertStringStartsWith('<form ', $html);
        self::assertStringContainsString('method="POST"', $html);
    }

    public function testActionIsRenderedOnlyWhenSet(): void
    {
        self::assertStringNotContainsString('action=', $this->form()->getHtml());

        $form = $this->form()->setAction('/submit');

        self::assertStringContainsString('action="/submit"', $form->getHtml());
        self::assertSame('/submit', $form->getAction());
    }

    public function testFieldsAreRenderedInsideTheForm(): void
    {
        $html = $this->form()->addField(new Input('email'))->getHtml();

        self::assertStringContainsString('name="email"', $html);
    }

    /**
     * The token is what stops a cross-site POST, so it has to be in the markup by default.
     */
    public function testCsrfTokenIsRenderedAsAHiddenField(): void
    {
        $html = $this->form()->getHtml();

        self::assertStringContainsString('name="session_key"', $html);
        self::assertStringContainsString('type="hidden"', $html);
    }

    public function testCsrfTokenCanBeClearedForAFormThatDoesNotNeedOne(): void
    {
        $form = $this->form()->setSessionKey(null);

        self::assertNull($form->getSessionKey());
        self::assertStringNotContainsString('session_key', $form->getHtml());
    }

    public function testAccessorsRoundTrip(): void
    {
        $form = $this->form()->setMethod(Form::METHOD_GET)->setDescription('Sign in');

        self::assertSame('GET', $form->getMethod());
        self::assertSame('Sign in', $form->getDescription());
        self::assertNull($this->form()->getDescription());
    }

    public function testFieldsAndButtonsAreReadable(): void
    {
        $field = new Input('a');
        $button = new Button('Go');
        $form = $this->form()->addField($field)->addButton($button);

        self::assertSame([$field], $form->getFields());
        self::assertSame([$button], $form->getButtons());
    }

    public function testCastingRendersTheForm(): void
    {
        $form = $this->form();

        self::assertSame($form->getHtml(), (string)$form);
    }

    public function testTheShippedTemplateRenders(): void
    {
        $form = new Form(Form::METHOD_POST, '/a');
        $form->addField(new Input('email', 'Email'));
        $form->addButton(new Button('Submit', Button::TYPE_SUBMIT));

        $html = $form->getHtml();

        self::assertStringContainsString('<form', $html);
        self::assertStringContainsString('name="email"', $html);
    }

    // --- reading submitted data --------------------------------------------------------------

    public function testGetDataReturnsNullWhenNothingWasPosted(): void
    {
        self::assertNull($this->form()->addField(new Input('a'))->getData());
    }

    public function testGetDataReadsPostedValues(): void
    {
        $form = $this->form()->addField(new Input('email'));
        $token = $form->getSessionKey();

        $this->asPostRequest();
        $_POST = ['email' => 'bob@example.com', Form::KEY_SESSION => $token];
        $_REQUEST = $_POST;

        self::assertSame(['email' => 'bob@example.com'], $form->getData());
    }

    public function testGetDataRejectsAMissingToken(): void
    {
        $form = $this->form()->addField(new Input('email'));

        $this->asPostRequest();
        $_POST = ['email' => 'bob@example.com'];
        $_REQUEST = $_POST;

        $this->expectException(InvalidFormException::class);

        $form->getData();
    }

    public function testGetDataRejectsAWrongToken(): void
    {
        $form = $this->form()->addField(new Input('email'));

        $this->asPostRequest();
        $_POST = ['email' => 'x', Form::KEY_SESSION => 'not-the-token'];
        $_REQUEST = $_POST;

        $this->expectException(InvalidFormException::class);

        $form->getData();
    }

    /**
     * A GET form carries no token, so it is read without one.
     */
    public function testGetFormsSkipTheTokenCheck(): void
    {
        $form = $this->form(Form::METHOD_GET)->addField(new Input('q'));
        $_REQUEST = ['q' => 'search term'];

        self::assertSame(['q' => 'search term'], $form->getData());
    }

    public function testFileFieldsAreReadFromTheUploadArray(): void
    {
        $field = (new Input('doc'))->setType(Input::TYPE_FILE);
        $form = $this->form()->addField($field);
        $token = $form->getSessionKey();

        $upload = ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 1];
        $_FILES = ['doc' => $upload];
        $this->asPostRequest();
        $_POST = [Form::KEY_SESSION => $token];
        $_REQUEST = $_POST;

        self::assertSame(['doc' => $upload], $form->getData());
    }

    public function testMissingRequiredFieldRaisesInvalidData(): void
    {
        $field = new Input('email', null, null, true);
        $form = $this->form()->addField($field);
        $token = $form->getSessionKey();

        $this->asPostRequest();
        $_POST = [Form::KEY_SESSION => $token];
        $_REQUEST = $_POST;

        try {
            $form->getData();
            self::fail('an empty required field should have been refused');
        } catch (InvalidDataException $exception) {
            // Keyed by field name, so a field reported twice appears once.
            self::assertSame(['email' => $field], $exception->getFields());
        }
    }

    // --- Validator ---------------------------------------------------------------------------

    public function testValidatorAcceptsAnythingForAnOptionalField(): void
    {
        $validator = new Validator();

        self::assertTrue($validator->isValid(new Input('a'), ''));
        self::assertTrue($validator->isValid(new Input('a'), null));
        self::assertTrue($validator->isValid(new Input('a'), 'x'));
    }

    public function testValidatorRefusesAnEmptyRequiredField(): void
    {
        $required = new Input('a', null, null, true);

        self::assertFalse((new Validator())->isValid($required, ''));
        self::assertFalse((new Validator())->isValid($required, null));
    }

    /**
     * "0" is a real answer to a required numeric field and must not read as absent.
     */
    public function testValidatorAcceptsZeroForARequiredField(): void
    {
        $required = new Input('a', null, null, true);

        self::assertTrue((new Validator())->isValid($required, '0'));
        self::assertTrue((new Validator())->isValid($required, 0));
    }

    public function testValidatorRefusesAnEmptyRequiredMultipleSelect(): void
    {
        $select = (new Select('a', null, null, true))->setMultiple(true);

        self::assertFalse((new Validator())->isValid($select, []));
        self::assertTrue((new Validator())->isValid($select, ['x']));
    }

    public function testValidatorAppliesThePattern(): void
    {
        $field = (new Input('a'))->setPattern('/^\d+$/');

        self::assertTrue((new Validator())->isValid($field, '123'));
        self::assertFalse((new Validator())->isValid($field, 'abc'));
    }

    public function testPatternIsNotAppliedToAnEmptyOptionalValue(): void
    {
        $field = (new Input('a'))->setPattern('/^\d+$/');

        self::assertTrue((new Validator())->isValid($field, ''));
    }

    // --- Button ------------------------------------------------------------------------------

    public function testSubmitButtonRendersAButtonTag(): void
    {
        $button = new Button('Send', Button::TYPE_SUBMIT);

        self::assertSame('submit', $button->getType());
        self::assertStringContainsString('<button', $button->getHtml());
        self::assertStringContainsString('type="submit"', $button->getHtml());
        self::assertStringContainsString('Send', $button->getHtml());
    }

    public function testLinkButtonRendersAnAnchor(): void
    {
        $button = new Button('Cancel', Button::TYPE_LINK, '/back');

        self::assertStringContainsString('<a ', $button->getHtml());
        self::assertStringContainsString('href="/back"', $button->getHtml());
    }

    public function testButtonDefaultsToALink(): void
    {
        self::assertSame(Button::TYPE_LINK, (new Button('x'))->getType());
    }

    public function testLinkButtonWithNoUrlPointsAtTheCurrentPage(): void
    {
        self::assertStringContainsString('href="#"', (new Button('x'))->getHtml());
    }

    public function testButtonAcceptsAUrlObject(): void
    {
        $button = new Button('x', Button::TYPE_LINK, new Url('a/b'));

        self::assertSame('http://localhost/a/b', $button->getUrl());
    }

    public function testButtonUrlIsNullWhenNotGiven(): void
    {
        self::assertNull((new Button('x'))->getUrl());
    }

    public function testButtonAccessorsRoundTrip(): void
    {
        $button = new Button('x');
        $button->setName('renamed');
        $button->setType(Button::TYPE_SUBMIT);
        $button->setUrl('/somewhere');

        self::assertSame('renamed', $button->getName());
        self::assertSame('submit', $button->getType());
        self::assertSame('/somewhere', $button->getUrl());
    }

    public function testButtonCastsToItsHtml(): void
    {
        $button = new Button('x', Button::TYPE_SUBMIT);

        self::assertSame($button->getHtml(), (string)$button);
    }

    public function testButtonAttributesAreRendered(): void
    {
        $button = new Button('x', Button::TYPE_SUBMIT, null, ['class' => 'btn-primary']);

        self::assertStringContainsString('class="btn-primary"', $button->getHtml());
    }

    // --- InvalidDataException ------------------------------------------------------------------

    public function testInvalidDataExceptionCollectsFields(): void
    {
        $exception = new InvalidDataException();

        self::assertSame([], $exception->getFields());

        $a = new Input('a');
        $exception->addField($a);
        $exception->addField($a);

        self::assertSame(['a' => $a], $exception->getFields(), 'keyed by name, so no duplicates');
    }

    public function testCsrfTokenIsStableWithinASession(): void
    {
        self::assertSame(Csrf::getToken(), Csrf::getToken());
    }
}
