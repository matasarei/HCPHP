<?php

namespace Html\Form;

use Html\Html;

class Textarea extends Field
{
    public function getHtml(): string
    {
        if ($this->template) {
            $this->template->set('field', $this);

            return $this->template->make();
        }

        return Html::tag(
            $this->getTagName(),
            $this->getValue() ?? $this->getDefault() ?? '',
            $this->prepareAttributes()
        );
    }

    protected function getTagName(): string
    {
        return 'textarea';
    }
}
