<?php

namespace Html\Form;

class Validator
{
    public function isValid(Field $field, $value): bool
    {
        if (
            $field->isRequired()
            && (
                (is_array($field->getValue()) && count($value) === 0)
                || (!is_numeric($value) && empty($value))
            )
        ) {
            return false;
        }

        if (
            !empty($value)
            && $field->getPattern() !== null
            && !preg_match($field->getPattern(), $value)
        ) {
            return false;
        }

        return true;
    }
}
