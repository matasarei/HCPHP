<?php

namespace Html\Form;

use core\Globals;
use core\Template;
use Html\Form\Exception\InvalidDataException;
use Html\Form\Exception\InvalidFormException;
use Html\Html;
use Html\HtmlInterface;
use Html\TemplateAware;

class Form implements HtmlInterface, TemplateAware
{
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';
    const KEY_SESSION = 'session_key';

    /**
     * @var Field[]
     */
    protected $fields = [];
    protected $buttons = [];
    protected $method;
    protected $action;
    protected $description = null;
    protected $template = null;
    protected $sessionKey = null;
    protected $validator;

    public function __construct(
        string $method = self::METHOD_POST,
        string $action = null,
        ?string $templateName = 'form/default',
        Validator $validator = null
    ) {
        $this->method = $method;
        $this->action = $action;

        if ($templateName !== null) {
            $this->template = new Template($templateName);
        }

        $this->validator = $validator ?? new Validator();
        $this->sessionKey = Globals::get('PHPSESSID');
    }

    public function addField(Field $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    public function addButton(Button $button): self
    {
        $this->buttons[] = $button;

        return $this;
    }

    /**
     * @return Button[]
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    public function setSessionKey($sessionKey): self
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    public function getSessionKey(): ?string
    {
        return $this->sessionKey;
    }

    /**
     * @return array|null
     *
     * @throws InvalidDataException
     * @throws InvalidFormException
     */
    public function getData(): ?array
    {
        if ($this->getMethod() === self::METHOD_POST) {
            if (!Globals::post()) {
                return null;
            }

            if (
                $this->sessionKey !== null
                && $this->sessionKey !== Globals::optional(self::KEY_SESSION)
            ) {
                throw new InvalidFormException('Wrong session key provided');
            }
        }

        $data = [];
        $exception = new InvalidDataException();

        foreach ($this->fields as $field) {
            if (($field instanceof Input) && $field->getType() === Input::TYPE_FILE) {
                $value = Globals::file($field->getName());
            } else {
                $value = Globals::optional($field->getName(), $field->getValue());
            }

            if (!$this->validator->isValid($field, $value)) {
                $exception->addField($field);
            }

            $data[$field->getName()] = $value;
        }

        if (count($exception->getFields()) > 0) {
            throw $exception;
        }

        return $data;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getHtml(): string
    {
        if ($this->template !== null) {
            return $this->template->make(['form' => $this]);
        }

        $content = '';
        $options = [
            'method' => $this->method,
        ];

        if ($this->action !== null) {
            $options['action'] = $this->action;
        }

        if ($this->sessionKey !== null) {
            $content .= Html::tag('input', null, [
                'name' => self::KEY_SESSION,
                'type' => 'hidden',
                'value' => $this->sessionKey,
            ]);
        }

        foreach ($this->fields as $field) {
            $content .= $field->getHtml();
        }

        return Html::tag('form', $content, $options);
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function setDescription($description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function __toString()
    {
        return $this->getHtml();
    }
}
