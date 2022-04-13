<?php

namespace Html\Form;

use Html\Html;

class Select extends Field
{
    /**
     * @var Option[]
     */
    protected $options = [];
    protected $multiple = false;

    public function setMultiple(bool $multiple): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function addOption(Option $option): self
    {
        $this->options[] = $option;

        return $this;
    }

    protected function prepareAttributes(): array
    {
        $attributes = parent::prepareAttributes();
        unset($attributes['value']);

        if ($this->isMultiple()) {
            $attributes['multiple'] = 'multiple';
            $attributes['name'] = $this->name . '[]';
        }

        return $attributes;
    }

    /**
     * @return array|string|null
     */
    public function getValue()
    {
        if ($this->isMultiple()) {
            return is_array($this->value) ? $this->value : [];
        }

        return $this->value;
    }

    public function getHtml(): string
    {
        if ($this->template) {
            $this->template->set('field', $this);

            return $this->template->make();
        }

        $content = '';
        $value = $this->getValue() ?? $this->getDefault();

        foreach ($this->options as $option) {
            $attributes = $option->getAttributes();
            $attributes['value'] = $option->getValue();

            if ($this->isMultiple() && in_array($option->getValue(), $value, true)) {
                $attributes['selected'] = 'selected';
            } elseif ($option->getValue() === $value) {
                $attributes['selected'] = 'selected';
            }

            $content .= Html::tag('option', $option->getTitle() ?? $option->getValue(), $attributes);
        }

        return Html::tag($this->getTagName(), $content, $this->prepareAttributes());
    }

    protected function getTagName(): string
    {
        return 'select';
    }
}
