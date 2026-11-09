# ADV-2026-007 — The HTML filter keeps every attribute of the tags it allows

**Severity:** high (stored cross-site scripting wherever user-supplied HTML is rendered through
the filter)
**Fixed in HCPHP:** `application/lib/Filter/TagsFilter.php`, `application/lib/Filter/ScriptsFilter.php`
**Fixed in HCPHP by:** `ef904f1` (2026-07-30)
**Affects:** any copy whose HTML filter relies on `strip_tags()` with an allow-list, or removes
scripts with the pattern `/<script.*<(\/script)?/`

---

## What was wrong

```php
// application/lib/Filter/TagsFilter.php
public function filter(string $content): string
{
    return strip_tags($content, '<' . implode('><', self::SAFE_TAGS) . '>');
}
```

`strip_tags()` drops the tags that are not on the list. It keeps the ones that are **with every
attribute they carry**. `<a>`, `<img>` and `<b>` are on the list, so their event handlers and
`javascript:` URLs passed straight through. The filter let through exactly the payloads it was
there to stop.

```php
// application/lib/Filter/ScriptsFilter.php
return preg_replace("/<script.*<(\/script)?/si", "", $content, -1);
```

The script pass was greedy and unanchored. It removed everything from the first `<script` to
the **last** `<` in the input, so `<p>a</p><script>…</script><p>b</p>` came out as
`<p>a</p>/p>`. An unclosed `<script>alert(1)` with no `<` after it was not removed at all. It
did nothing about `<iframe>`, `<object>`, `<embed>` or `<style>`.

Copies older than the `application/lib/Filter` layout have `core\Filters::html()` instead. It
runs only `Filters::noscript()`, with the same greedy pattern, and filters no tag at all.

## Impact

The filter exists to render HTML that a user wrote: `{{html|$value}}` in a template compiles to
`(new Filter\HtmlFilter())->filter($value)` (`application/events/Init.php`). Whoever can store
such a value can run script in the browser of every visitor who views it, including
administrators:

```html
<img src=x onerror=alert(document.domain)>
<a href="javascript:alert(1)">profile</a>
<b onmouseover=alert(1)>hover me</b>
```

The session cookie is `HttpOnly` (ADV-2026-004), so it cannot be read, but the script acts as the
visitor on every page of the site. It can submit forms, read the CSRF token and change data. If
anyone can register and the content is public, any registered user can reach every visitor.

## How to tell if you are affected

```bash
# the allow-list pass: affected if strip_tags() is the last thing that happens to the content
grep -n 'strip_tags' application/lib/Filter/TagsFilter.php application/core/Filters.php
# the greedy script pass
grep -rn 'preg_replace("/<script' application --include='*.php'
# where the filter reaches a page
grep -rn 'html|' application/templates application/views
```

Runtime check, on a local instance only: store `<img src=x onerror=alert(1)>` in a field that a
page renders with `{{html|…}}`, open that page and view the source. An affected copy prints the
`onerror` attribute. A fixed one prints `<img src="x">`.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/lib/Filter/TagsFilter.php` matches `return strip_tags\(\$content`
- defective: `application/lib/Filter/ScriptsFilter.php` matches `preg_replace\("/<script\.\*<`
- defective: `application/core/Filters.php` matches `preg_replace\("/<script\.\*<`
- fixed: `application/lib/Filter/TagsFilter.php` matches `function rebuildTag\(`
- fixed: `application/lib/Filter/ScriptsFilter.php` matches `REMOVED_ELEMENTS`

## The fix

`TagsFilter` still runs `strip_tags()` first, then **rebuilds every surviving tag from scratch**.
A tag keeps only the attributes on its own list, and a URL only when its scheme is safe:

```php
const SAFE_ATTRIBUTES = [
    'a' => ['href', 'title'],
    'img' => ['src', 'alt', 'title'],
];
const URL_ATTRIBUTES = ['href', 'src'];
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
```

`rebuildTag()` writes each kept value back through
`htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. `isSafeUrl()` decodes entities
and strips control characters and whitespace **before** reading the scheme, because a browser
resolves `java&#115;cript:` and `java<tab>script:` to `javascript:`. A URL with no scheme
(relative, or protocol-relative) is allowed.

`ScriptsFilter` removes `script`, `style`, `iframe`, `object`, `embed` and `applet` elements with
their contents. If an element is never closed, it drops the rest of the input:

```php
foreach (self::REMOVED_ELEMENTS as $element) {
    $content = (string)preg_replace(
        [
            sprintf('#<\s*%1$s\b[^>]*>.*?<\s*/\s*%1$s\s*>#is', $element),
            sprintf('#<\s*%s\b[^>]*>.*$#is', $element),
            sprintf('#<\s*/\s*%s\s*>#i', $element),
        ],
        '',
        $content
    );
}
```

Read the whole of both files at `ef904f1`, not only these excerpts. The attribute parser and the
URL check are part of the fix.

## Porting checklist

- [ ] Find every place the filter runs: the `{{html|…}}` shortcode, and any direct call to
      `HtmlFilter`, `TagsFilter`, `Filters::html()` or `Filters::noscript()`. Each is a page
      where users' HTML is rendered.
- [ ] Find every other `strip_tags()` whose output is printed as markup rather than escaped.
      An allow-list there is the same hole, in a different file.
- [ ] Port `rebuildTag()`, `parseAttributes()` and `isSafeUrl()` into your copy's filter, under
      its own names. In an old-layout copy, that is `core\Filters::html()`, which needs a tag
      pass added, not just a better script pass.
- [ ] Expect stored content to lose `class`, `style`, `id`, `target` and every attribute that
      is not on the list when it is rendered. That is the fix working. If a page depends on
      one of them, add that attribute to `SAFE_ATTRIBUTES` for that tag. Never add an `on*`
      attribute, `style`, or a URL attribute without the scheme check.
- [ ] If your filter also turns bare URLs into links (`Filters::html()` does), make sure it runs
      **after** the tag pass, or that what it inserts survives the tag pass.
- [ ] Copy `tests/Unit/Filter/HtmlFilterTest.php` and run it before the fix. On `ef904f1^` seven
      of its 17 tests fail. The entity-encoded and the tab-in-scheme cases pass even there,
      because the test looks for the literal `javascript:`, so they prove the fix only once it
      is in place.
- [ ] On a local instance, store the three payloads above and view the page source.

## Notes

- A copy that has fixed the tag pass but still has the greedy script pattern is reported as
  `defective`, and is. The tag pass removes `<script>` before the script pass runs, so there the
  pattern mangles content rather than letting script through. It is a hole of its own wherever
  the filter runs **without** the tag pass (`HtmlFilter::filter($content, false)`): there,
  `<iframe>`, `<object>` and `<embed>` pass, and so does an unclosed `<script>`. Fix both
  passes; that is what `ef904f1` did.
- `ef904f1` also added `Template::escape()` and the `{{escape|…}}` shortcode, and escaped the
  values that templates echoed raw. That half is covered in the notes of ADV-2026-003. A copy
  porting this advisory should check that it has both.
- Content stored before the fix is unchanged in the database. The filter runs at output, so
  the fix covers old content as soon as it is deployed. Nothing needs migrating.

## Status

Fixed in HCPHP in `ef904f1` (2026-07-30). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-007` there, which checks for the defect by the markers above
and records the result in that repository.
