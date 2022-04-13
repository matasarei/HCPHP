<?php

namespace UserBundle\Form;

use core\Language;
use Html\Form\Button;
use Html\Form\Form;
use Html\Form\Input;

class LoginFormFactory
{
    /**
     * @var Language
     */
    private $language;

    public function __construct()
    {
        $this->language = Language::getInstance();
    }

    public function createForm(): Form
    {
        $form = new Form();

        $form->addField(new Input('email', $this->language->getString('email')));
        $form->addField(
            (new Input('password', $this->language->getString('password')))
                ->setType('password')
        );

        $form->addButton(new Button($this->language->getString('login'), Button::TYPE_SUBMIT));

        return $form;
    }
}
