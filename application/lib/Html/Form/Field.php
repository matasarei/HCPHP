<?php

namespace Html\Form;

use core\Template;
use Html\Html;
use Html\HtmlInterface;
use Html\TemplateAware;

abstract class Field implements  HtmlInterface, TemplateAware
{
    protected $name;
    protected $title;
    protected $default;
    protected $required = false;
    protected $template;
    protected $placeholder = null;
    protected $pattern = null;
    protected $description = null;
    protected $attributes = [];
    protected $disabled = false;

    /**
     * @var array|string|null
     */
    protected $value = null;

    public function __construct(
        string $name,
        string $title = null,
        $default = null,
        bool $required = false,
        string $template = null
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->default = $default;
        $this->required = $required;

        if ($template !== null) {
            $this->template = new Template($template);
        }
    }

    /**
     * @param string $pattern Regex pattern
     *
     * @return self
     */
    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        if (empty($this->placeholder)) {
            $this->placeholder = $title;
        }

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setDescription(string $val): self
    {
        $this->description = $val;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setValue($val): self
    {
        $this->value = $val;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setDefault(string $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setRequired(bool $val): self
    {
        $this->required = $val;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setPlaceholder(string $value): self
    {
        $this->placeholder = $value;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setDisabled(bool $val)
    {
        $this->disabled = $val;
    }

    public function isDisabled(): bool
    {
        return $this->getDisabled();
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    abstract protected function getTagName(): string;

    /**
     * @param string $name
     * @param string|int $value
     *
     * @return self
     */
    public function addAttribute(string $name, $value): self
    {
        $this->attributes[$name] = (string)$value;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, string $default = null): ?string
    {
        return $this->attributes[$name] ?? $default;
    }

    protected function prepareAttributes(): array
    {
        $attributes = $this->attributes;
        $attributes['id'] = $this->getAttribute('id', 'id_' . $this->name);
        $attributes['name'] = $this->name;

        if ($this->placeholder !== null) {
            $attributes['placeholder'] = $this->placeholder;
        }

        if ($this->value ?? $this->default !== null) {
            $attributes['value'] = $this->value ?? $this->default;
        }

        if ($this->disabled) {
            $attributes['disabled'] = 'disabled';
        }

        if ($this->required) {
            $attributes['required'] = 'required';
        }

        return $attributes;
    }

    public function getHtml(): string
    {
        if ($this->template) {
            $this->template->set('field', $this);

            return $this->template->make();
        }

        return Html::tag($this->getTagName(), null, $this->prepareAttributes());
    }

    public function __toString(): string
    {
        return $this->getHtml();
    }
}
