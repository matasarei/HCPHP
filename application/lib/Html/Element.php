<?php

namespace Html;

/**
 * @package    hcphp
 * @subpackage html
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Element
{
    protected $tagName;
    protected $attributes = [];
    protected $content = null;

    public function __construct(string $tagName = 'div')
    {
        $this->tagName = $tagName;
    }

    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setName(string $name): self
    {
        $this->tagName = trim($name);

        return $this;
    }

    public function getName(): string
    {
        return $this->tagName;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getHtml(): string
    {
        return Html::tag($this->tagName, $this->content, $this->attributes);
    }

    public function __toString()
    {
        return $this->getHtml();
    }
}
