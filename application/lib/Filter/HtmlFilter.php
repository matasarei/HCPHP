<?php

namespace Filter;

class HtmlFilter implements FilterInterface
{
    /**
     * @var TagsFilter
     */
    private $tagsFilter;

    /**
     * @var ScriptsFilter
     */
    private $scriptFilter;

    public function __construct(
        FilterInterface $tagsFilter = null,
        FilterInterface $scriptFilter = null
    ) {
        $this->tagsFilter = $tagsFilter ?? new TagsFilter();
        $this->scriptFilter = $scriptFilter ?? new ScriptsFilter();
    }

    public function filter(string $content, bool $strict = true): ?string
    {
        if ($strict) {
            $content = $this->tagsFilter->filter($content);
        }

        $content = $this->scriptFilter->filter($content);

        return nl2br(preg_replace('/\\\r?\\\n/', "\n", $content, -1));
    }
}
