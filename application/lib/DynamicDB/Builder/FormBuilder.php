<?php

namespace DynamicDB\Builder;

use core\Language;
use core\Url;
use DateTime;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\Field;
use DynamicDB\Entity\Table;
use DynamicDB\Factory\DynamicRepositoryFactory;
use Html\Form\Button;
use Html\Form\Field as FormField;
use Html\Form\Form;
use Html\Form\Input;
use Html\Form\Option;
use Html\Form\Select;
use Html\Form\Textarea;

class FormBuilder
{
    private $table;
    private $language;
    private $repositoryFactory;

    public function __construct(Table $table, DynamicRepositoryFactory $repositoryFactory)
    {
        $this->table = $table;
        $this->language = Language::getInstance();
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param DynamicEntity|null $entity
     * @param Url|null $cancelUrl
     *
     * @return Form
     *
     * @uses makeInputEnum
     * @uses makeInputFile
     * @uses makeInputRelation
     * @uses makeInputBoolean
     * @uses getRelationDefault
     * @uses makeInputInteger
     * @uses makeInputDateTime
     * @uses makeInputJSON
     * @uses makeInputText
     * @uses makeInputMediumText
     * @uses makeInputLongText
     * @uses makeInputReal
     */
    public function getEditForm(DynamicEntity $entity = null, Url $cancelUrl = null): Form
    {
        $form = new Form();

        foreach ($this->table->getFields() as $field) {
            $default = $this->getDefaultValue($field, $entity);
            $required = null === $field->getDefault();
            $method = 'makeInput' . $field->getType();

            if (method_exists(__CLASS__, $method)) {
                $form->addField($this->$method($field->getName(), $default, $field, $required));

                continue;
            }

            $form->addField($this->makeInputDefault($field->getName(), $default, $field, $required));
        }

        $form->addButton(new Button($this->language->getString('Submit'), Button::TYPE_SUBMIT));
        $form->addButton(
            new Button(
                $this->language->getString('Cancel'),
                Button::TYPE_LINK,
                $cancelUrl
            )
        );

        return $form;
    }

    private function makeInputEnum(string $name, $default, Field $dbField, bool $required): Select
    {
        $field = new Select($name, $dbField->getDescription(), $default, $required);
        $field->setPlaceholder('Select one');

        foreach ($dbField->getValues() as $value) {
            if (preg_match("/\s*%(\w+)%\s*/", $value, $matches)) {
                $field->addOption(
                    new Option(
                        sprintf('%s_%s', $dbField->getName(), $matches[1]),
                        $value
                    )
                );
            } else {
                $field->addOption(new Option($value));
            }
        }

        return $field;
    }

    private function makeInputFile(string $name, $default, Field $field): Input
    {
        return (new Input($name, $field->getDescription(), $default))
            ->setPlaceholder(basename($default))
            ->setType(Input::TYPE_FILE)
        ;
    }

    private function makeInputRelation(string $name, $default, Field $field, bool $required): Select
    {
        /** @var DynamicEntity[] $records */
        $records = $this->repositoryFactory
            ->getRepository($field->getTable())
            ->find([], ['limit' => 100])
        ;

        $input = new Select($name, $field->getDescription(), $default, $required);

        foreach ($records as $record) {
            $input->addOption(new Option($record->getId(), $record->get($field->getField())));
        }

        return $input;
    }

    private function makeInputBoolean(string $name, $default, Field $field): Select
    {
        return (new Select($name, $field->getDescription(), (bool)$default, false))
            ->addOption(new Option(true, $this->language->getString('yes')))
            ->addOption(new Option(false, $this->language->getString('no')))
        ;
    }

    /**
     * Get default relation id
     *
     * @param array $values Values
     * @param mixed $default Default value
     *
     * @return int Relation id
     */
    private function getRelationDefault(array $values, $default): int
    {
        foreach ($values as $key => $value) {
            if ($value == $default) {
                return $key;
            }
        }

        return 0;
    }

    private function makeInputInteger(string $name, $default, Field $info): Input
    {
        return (new Input($name, $info->getDescription(), intval($default), true))
            ->setType(Input::TYPE_NUMBER)
            ->addAttribute('min', 0)
            ->addAttribute('max', $info->getLength() > 1 ? pow($info->getLength(), 8) : 1)
        ;
    }

    private function makeInputReal(string $name, $default, Field $field): Input
    {
        return (new Input($name, $field->getDescription(), floatval($default), true))
            ->setType(Input::TYPE_NUMBER)
            ->addAttribute('min', 0)
            ->addAttribute('max', $field->getLength() > 1 ? pow($field->getLength(), 8) : 1)
            ->addAttribute('step', '0.1')
        ;
    }

    private function makeInputDateTime(string $name, $default, Field $field): Input
    {
        if (!($default instanceof DateTime)) {
            if (is_numeric($default)) {
                $default = (new DateTime())->setTimestamp($default);
            } elseif (!empty($default)) {
                $default = DateTime::createFromFormat($field->getFormat(), $default);
            } else {
                $default = new DateTime();
            }
        }

        return (new Input($name, $field->getDescription(), $default->format('Y-m-d\TH:i'), false))
            ->setType('datetime-local')
        ;
    }

    private function makeInputJSON(string $name, $default, Field $info, bool $required): Input
    {
        return (new Input($name, $info->getDescription(), implode('; ', (array)$default), $required))
            ->setPlaceholder('Input item and press Enter')
            ->addAttribute('class', 'json-field')
        ;
    }

    private function makeInputText(string $name, $default, Field $field, bool $required): FormField
    {
        if ($field->getLength() > 256) {
            return new Textarea($name, (string)$default, $field->getDescription(), $required);
        }

        return $this->makeInputDefault($name, $default, $field, $required);
    }

    private function makeInputMediumText(string $name, $default, Field $info, bool $required): Textarea
    {
        return (new Textarea($name, $info->getDescription(), $default, $required))
            ->addAttribute('class', 'wysiwyg-field')
        ;
    }

    private function makeInputLongText(string $name, $default, Field $info, bool $required): Textarea
    {
        return $this->makeInputMediumText($name, $default, $info, $required);
    }

    private function makeInputDefault(string $name, $default, Field $dbField, bool $required): Input
    {
        return new Input($name, $dbField->getDescription(), $default, $required);
    }

    private function getDefaultValue(Field $info, DynamicEntity $entity = null)
    {
        $default = $info->getDefault();

        if (null === $entity) {
            return $default;
        }

        $name = $info->getName();

        if (Field::TYPE_ENUM === $info->getType()) {
            return $entity->get($name) ?? $default;
        }

        return $entity->$name ?? $default;
    }
}
