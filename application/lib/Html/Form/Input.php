<?php

namespace Html\Form;

class Input extends Field
{
    const TYPE_TEXT = 'text';
    const TYPE_PASSWORD = 'password';
    const TYPE_EMAIL = 'email';
    const TYPE_FILE = 'file';
    const TYPE_COLOR = 'color';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_RADIO = 'radio';
    const TYPE_NUMBER = 'number';

    protected $type = self::TYPE_TEXT;

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    protected function prepareAttributes(): array
    {
        $attributes = parent::prepareAttributes();
        $attributes['type'] = $this->type;

        return $attributes;
    }

    protected function getTagName(): string
    {
        return 'input';
    }
}
