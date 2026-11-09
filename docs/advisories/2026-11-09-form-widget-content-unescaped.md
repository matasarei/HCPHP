# ADV-2026-008 — Textarea values and select option labels are written into the page unescaped

**Severity:** high (stored cross-site scripting in any form that shows a stored value or a
label taken from data)
**Fixed in HCPHP:** `application/lib/Html/Form/Textarea.php`, `application/lib/Html/Form/Select.php`
**Fixed in HCPHP by:** `dd6c1a1` (2026-07-31)
**Affects:** any copy whose `Textarea` or `Select` passes a value or a label to `Html::tag()` as
content without escaping it

---

## What was wrong

`Xml::tag()` escapes attributes (ADV-2026-003) and writes `$content` **verbatim**. That is
deliberate: callers build markup with it, and `Select` builds its `<option>` list that way. Two
form widgets passed **data** to it as content:

```php
// application/lib/Html/Form/Textarea.php
return Html::tag(
    $this->getTagName(),
    $this->getValue() ?? $this->getDefault() ?? '',
    $this->prepareAttributes()
);
```

```php
// application/lib/Html/Form/Select.php
$content .= Html::tag('option', $option->getTitle() ?? $option->getValue(), $attributes);
```

## Impact

A textarea's value is whatever was saved last. A value of

```html
</textarea><script>alert(1)</script>
```

closes the element and runs when anyone opens the edit form. The same goes for an option's
label. A relation field's options are titled with a column value read straight from the
database, so anyone who can write that column can put script into every form that lists it.

The victim is whoever opens the form, often an administrator reviewing someone else's record.

## How to tell if you are affected

```bash
grep -n "Html::tag" application/lib/Html/Form/Textarea.php application/lib/Html/Form/Select.php
```

Read each hit. Affected if the content argument is the value or the option's title with no
`Html::escape()` / `Xml::escape()` / `htmlspecialchars()` around it. Copies with a different
widget layout have the same defect wherever a field class passes a value or a label as the
content argument of `Html::tag()` or `Xml::tag()`. Grep all of their form classes for it.

Runtime check, on a local instance only: save `</textarea><script>alert(1)</script>` in a field
that renders as a textarea and open its edit form. Then do the same with a value that a select
uses as an option label. An affected copy runs the script. A fixed one shows the text in the
box, or as the label.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/lib/Html/Form/Textarea.php` matches `^[[:space:]]*\$this->getValue\(\) \?\? \$this->getDefault\(\)`
- defective: `application/lib/Html/Form/Select.php` matches `Html::tag\('option', \$option->getTitle\(\)`
- fixed: `application/lib/Html/Form/Textarea.php` matches `Html::escape\(\$this->getValue\(\)`
- fixed: `application/lib/Html/Form/Select.php` matches `Html::escape\(\$option->getTitle\(\)`

## The fix

Escape the data, and only the data. Escape the value, not the markup around it:

```php
// Textarea::getHtml()
return Html::tag(
    $this->getTagName(),
    Html::escape($this->getValue() ?? $this->getDefault() ?? ''),
    $this->prepareAttributes()
);
```

```php
// Select::getHtml(): $content is markup being accumulated, so only the label is escaped
$content .= Html::tag(
    'option',
    Html::escape($option->getTitle() ?? $option->getValue()),
    $attributes
);
```

`Html::escape()` is `Xml::escape()`:
`htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. It arrived in HCPHP in
`df1e403`. If your copy lacks it, add it to `Xml`, or call `htmlspecialchars()` with those
flags.

## Porting checklist

- [ ] **Audit the callers for double-escaping first.** Every caller that already escapes a
      label before handing it to `Select` will now print `&amp;amp;`. In HCPHP that was
      `FormBuilder::makeInputBoolean()`: `Language::getString()` escapes by default, so its
      yes/no labels are now asked for raw, with `getString('yes', [], false)`. Grep your copy
      for `new Option(` and for `setValue(` on textareas, and convert each caller that
      pre-escapes in the same change.
- [ ] Check every other field class, not only these two. Any widget that passes a value or a
      label to `Html::tag()` as content has the same defect.
- [ ] Do **not** escape inside `Xml::tag()` itself. Its callers compose markup with it, and
      escaping there would print every nested tag as text. The contract (attributes escaped,
      content verbatim) is pinned by `XmlTest::testTagNameAndContentAreUnchanged`.
- [ ] Copy `testTextareaBodyIsEscaped` and `testOptionLabelsAreEscaped` from
      `tests/Unit/Html/FieldTest.php`. Run them before the fix: on `dd6c1a1^` both fail with
      `<script>` in the rendered HTML.
- [ ] On a local instance, save the payload above in a textarea field and in an option label,
      then open the form.

## Notes

- A copy that strips tags from the value before saving it (for example in a setter) may not be
  exploitable today: `strip_tags()` removes `</textarea>`. Fix the widget anyway. The next
  caller will not strip, and escaping at output is the rule.
- `dd6c1a1` also guards `Globals::post()` against a missing `$_SERVER['REQUEST_METHOD']`, which
  warns in a CLI process. That change matters for running the tests, not for security.

## Status

Fixed in HCPHP in `dd6c1a1` (2026-07-31). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-008` there, which checks for the defect by the markers above
and records the result in that repository.
