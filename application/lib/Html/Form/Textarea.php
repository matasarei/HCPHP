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

        // Xml::tag() writes content verbatim, because callers compose markup with it. This
        // content is the field's value, so it is data: unescaped, a value of
        // "</textarea><script>..." closed the tag and ran.
        return Html::tag(
            $this->getTagName(),
            Html::escape($this->getValue() ?? $this->getDefault() ?? ''),
            $this->prepareAttributes()
        );
    }

    protected function getTagName(): string
    {
        return 'textarea';
    }
}
