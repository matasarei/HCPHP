<?php

namespace Filter;

/**
 * Reduces arbitrary HTML to a small, inert subset.
 *
 * strip_tags() alone is not enough: it removes the tags that are not allowed, but it keeps
 * every attribute of the tags that are. So <a> and <img> survived carrying their event
 * handlers and javascript: URLs, which is the whole payload.
 *
 * After the tag pass every surviving tag is therefore rebuilt from scratch, keeping only the
 * attributes it is explicitly allowed and only URLs with a safe scheme.
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class TagsFilter implements FilterInterface
{
    const SAFE_TAGS = ['a', 'b', 'h1', 'h3', 'em', 'strong', 'blockquote', 'code', 'del',
        'dd', 'dl', 'dl', 'dt', 'dl', 'em', 'h1', 'h2', 'h3', 'i', 'img', 'kbd',
        'li', 'ol', 'ul', 'ol', 'p', 'pre', 's', 'sup', 'sub', 'strong', 'strike',
        'del', 'ul', 'br', 'hr'];

    /**
     * The only attributes a tag may keep. Everything else -- every on* handler, style, class,
     * id -- is dropped.
     */
    const SAFE_ATTRIBUTES = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title'],
    ];

    /**
     * Attributes holding a URL, whose scheme has to be checked.
     */
    const URL_ATTRIBUTES = ['href', 'src'];

    /**
     * Schemes a URL attribute may use. No scheme at all -- a relative or protocol-relative
     * URL -- is also fine.
     */
    const SAFE_SCHEMES = ['http', 'https', 'mailto', 'ftp'];

    public function filter(string $content): string
    {
        $content = strip_tags($content, '<' . implode('><', self::SAFE_TAGS) . '>');

        return (string)preg_replace_callback(
            '#<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9]*)([^>]*)>#',
            function (array $match): string {
                return $this->rebuildTag($match[1] === '/', strtolower($match[2]), $match[3]);
            },
            $content
        );
    }

    private function rebuildTag(bool $closing, string $name, string $rawAttributes): string
    {
        if ($closing) {
            return sprintf('</%s>', $name);
        }

        $allowed = self::SAFE_ATTRIBUTES[$name] ?? [];

        if (!$allowed) {
            return sprintf('<%s>', $name);
        }

        $kept = [];

        foreach ($this->parseAttributes($rawAttributes) as $attribute => $value) {
            if (!in_array($attribute, $allowed, true)) {
                continue;
            }

            if (in_array($attribute, self::URL_ATTRIBUTES, true) && !$this->isSafeUrl($value)) {
                continue;
            }

            $kept[] = sprintf(
                '%s="%s"',
                $attribute,
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return $kept ? sprintf('<%s %s>', $name, implode(' ', $kept)) : sprintf('<%s>', $name);
    }

    /**
     * @param string $raw
     *
     * @return array attribute name (lower case) => raw value
     */
    private function parseAttributes(string $raw): array
    {
        $matches = [];
        $attributes = [];

        preg_match_all(
            '#([a-zA-Z_:][a-zA-Z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#',
            $raw,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $value = '';

            foreach ([2, 3, 4] as $group) {
                if (isset($match[$group]) && $match[$group] !== '') {
                    $value = $match[$group];

                    break;
                }
            }

            $attributes[strtolower($match[1])] = $value;
        }

        return $attributes;
    }

    /**
     * A browser decodes entities and drops control characters before deciding which scheme a
     * URL uses, so "java&#115;cript:" and "java<tab>script:" both execute. Both are
     * normalised here before the scheme is read.
     */
    private function isSafeUrl(string $url): bool
    {
        $normalised = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalised = (string)preg_replace('/[\x00-\x20\x7f]/', '', $normalised);

        if ($normalised === '') {
            return false;
        }

        $matches = [];

        // No scheme means relative or protocol-relative, which is safe.
        if (!preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*):#', $normalised, $matches)) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::SAFE_SCHEMES, true);
    }
}
