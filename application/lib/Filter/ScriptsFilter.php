<?php

namespace Filter;

/**
 * Removes script and style elements together with their contents.
 *
 * The previous pattern, /<script.*<(\/script)?/si, was greedy and unanchored: it swallowed
 * everything between the first "<script" and the last "<" in the document, and left the
 * remainder of a page mangled while still passing "<img onerror=...>" through untouched.
 *
 * TagsFilter already discards these elements when it runs. This stays as the second line of
 * defence for HtmlFilter's non-strict mode, where the tag pass is skipped.
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ScriptsFilter implements FilterInterface
{
    const REMOVED_ELEMENTS = ['script', 'style', 'iframe', 'object', 'embed', 'applet'];

    public function filter(string $content): ?string
    {
        foreach (self::REMOVED_ELEMENTS as $element) {
            $content = (string)preg_replace(
                [
                    // A complete element, contents and all.
                    sprintf('#<\s*%1$s\b[^>]*>.*?<\s*/\s*%1$s\s*>#is', $element),
                    // An unclosed one: drop the rest of the input rather than leave it open.
                    sprintf('#<\s*%s\b[^>]*>.*$#is', $element),
                    // A stray closing tag.
                    sprintf('#<\s*/\s*%s\s*>#i', $element),
                ],
                '',
                $content
            );
        }

        return $content;
    }
}
