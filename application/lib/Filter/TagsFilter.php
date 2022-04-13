<?php

namespace Filter;

class TagsFilter implements FilterInterface
{
    const SAFE_TAGS = ['a', 'b', 'h1', 'h3', 'em', 'strong', 'blockquote', 'code', 'del',
        'dd', 'dl', 'dl', 'dt', 'dl', 'em', 'h1', 'h2', 'h3', 'i', 'img', 'kbd',
        'li', 'ol', 'ul', 'ol', 'p', 'pre', 's', 'sup', 'sub', 'strong', 'strike',
        'del', 'ul', 'br', 'hr'];

    public function filter(string $content): string
    {
        return strip_tags($content, '<' . implode('><', self::SAFE_TAGS) . '>');
    }
}
