<?php

namespace Html\Form;

use core\Template;
use core\Url;
use Html\Html;
use Html\HtmlInterface;
use Html\TemplateAware;

class Button implements HtmlInterface, TemplateAware
{
    const TYPE_SUBMIT = 'submit';
    const TYPE_LINK = 'link';

    /**
     * @var string|null
     */
    protected $url;

    /**
     * @var string|null
     */
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Template|null
     */
    protected $template;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @param string $name
     * @param string|null $type
     * @param string|Url|null $url
     * @param array $attributes
     */
    public function __construct(
        string $name,
        string $type = null,
        $url = null,
        array $attributes = []
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->attributes = $attributes;
        $this->setUrl($url);
    }

    function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @param Url|string|null $url
     */
    function setUrl($url)
    {
        $this->url = $url === null ? null : (string)$url;
    }

    function getUrl(): ?string
    {
        return $this->url;
    }

    function getType(): string
    {
        return $this->type ?? self::TYPE_LINK;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
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

    public function getHtml(): string
    {
        if ($this->template !== null) {
            return $this->template->make(['button' => $this]);
        }

        if ($this->getType() === self::TYPE_SUBMIT) {
            return Html::tag('button', $this->name, array_merge($this->attributes, ['type' => 'submit']));
        }

        return Html::link($this->url ?? '#', $this->name, $this->attributes);
    }

    public function __toString()
    {
        return $this->getHtml();
    }
}
