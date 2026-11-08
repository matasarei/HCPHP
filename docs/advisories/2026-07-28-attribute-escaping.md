# ADV-2026-003 — HTML attribute values escaped for the wrong language

**Severity:** high (cross-site scripting in every rendered form field)
**Fixed in HCPHP:** `application/core/Xml.php`
**Fixed in HCPHP by:** `9daeb69` (2026-07-28)
**Affects:** any copy of HCPHP whose `Xml::prepareAttributes()` still backslash-escapes quotes

---

## What was wrong

`core\Xml::prepareAttributes()` escaped quotes like this:

```php
$value = preg_replace('/\"/', '\"', $value, -1);
```

`\"` is a C, PHP and SQL escape. HTML has no such thing: a backslash before a quote is just a
backslash, and the quote still ends the attribute.

## Impact

Anything rendered through `Html\Form\Field::getHtml()`, `Html::link()`, `Html::image()` or
`Html::thumbnail()` will accept injected attributes. Verified end to end: a record whose text
field held `" onfocus=alert(1) autofocus x="` rendered as

```html
<input ... value="\" onfocus=alert(1) autofocus x=\"" required="required" type="text" />
```

The browser ends `value` at the first quote, then reads `onfocus` and `autofocus` as
attributes of the input — script execution with no user interaction beyond loading the page.

Any field whose value reaches a form is a vector: record data, search terms, usernames,
anything echoed back into an edit form.

## How to tell if you are affected

```bash
grep -n 'preg_replace' application/core/Xml.php
```

A line escaping `\"` is the bug. Runtime check: store `" onfocus=alert(1) x="` in any text
field and open the edit form; `view-source` shows whether the payload closed the attribute.

**Markers** — read by the hcphp plugin's `drift.sh`; paths are relative to the application root,
and a copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core/Xml.php` matches `preg_replace\('/\\"/'`
- fixed: `application/core/Xml.php` matches `htmlspecialchars\(.*ENT_QUOTES`

## The fix

```php
// names are written outside the quotes, so escaping the value cannot save a hostile one
if (!preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/', (string)$name)) {
    throw new InvalidArgumentException(sprintf('Invalid attribute name "%s"', $name));
}

// ...

$string[] = sprintf(
    '%s="%s"',
    $name,
    htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
);
```

Three details that matter:

- **`ENT_QUOTES`**, not the default. Without it single quotes survive, and ported code cannot
  be assumed to use double quotes around every attribute.
- **`ENT_SUBSTITUTE`**. Without it `htmlspecialchars()` returns an **empty string** for
  malformed UTF-8 on PHP 7, silently dropping the value and leaving a bare attribute. Any
  field carrying user-supplied bytes can hit this.
- **The attribute-name guard is broader than the reported bug.** Names are literals throughout
  HCPHP, but `Field::addAttribute()` is public, and a dynamic name injects markup that
  escaping the value cannot prevent. **If your project builds attribute names from data, this
  will now throw** — that is a finding in its own right, not a regression to work around.

## Porting checklist

- [ ] Replace the `preg_replace` line as above.
- [ ] Add the attribute-name guard, then run your test suite and click through the forms — an
      exception here means you have a second, worse problem to fix.
- [ ] Check for **double-escaping** before shipping. In HCPHP nothing that reaches an
      attribute is pre-escaped: `Language::getString()` does apply `htmlspecialchars`, but its
      output is used as tag *content* (button names, option titles), never as an attribute
      value. Confirm the same in your project — `grep -rn "getString(" ` over your form and
      HTML classes — or you will get `&amp;amp;` in placeholders.
- [ ] Copy `tests/Unit/Core/XmlTest.php` (22 cases: the breakout payload, each special
      character, array/style values, numeric and empty values, the invalid-UTF-8 trap, and the
      name guard).
- [ ] Expect URLs in `href`/`src` to gain `&amp;` for query separators. That is correct HTML
      and browsers parse it back to `&`; do not "fix" it.

## Notes

- Tag **content** is deliberately still unescaped — it is inner HTML and the caller owns it.
  `XmlTest::testTagNameAndContentAreUnchanged` pins that contract so it cannot drift silently.
- The `empty($value) && !is_numeric($value)` branch that emits a bare attribute name is
  unchanged; `required` and `disabled` depend on it.
- This does **not** fix templates that echo into attributes directly with `<?= ?>`. In HCPHP
  `templates/default.php:40` (`value="<?= $queryString ?>"`, the navbar search box) is a
  reflected XSS that survives this advisory. It needs an authenticated victim. Fixed in HCPHP
  by `ef904f1` (2026-07-30); check copies for direct `<?= ?>` echoes into attributes.

## Status

Fixed in HCPHP in `9daeb69` (2026-07-28). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-003` there, which checks for the defect by the markers above
and records the result in that repository.
