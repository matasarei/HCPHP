# ADV-2026-013 — `isImage()` matches names that are not image names

**Severity:** low in HCPHP, which decides nothing on it. Higher in a copy that lets an upload
in, or picks a download's type, on the name check.
**Fixed in HCPHP:** `application/core/Path.php`, `application/core/Url.php`
**Fixed in HCPHP by:** `7c9fde4` (2026-11-09)
**Affects:** any copy whose `Path::isImage()` or `Url::isImage()` uses the pattern
`/.(png|jpeg|…)$/` with the dot unescaped

---

## What was wrong

```php
// application/core/Path.php, isImage() without $strict
return preg_match('/.(png|jpeg|jpg|gif|bmp|webp|svg)$/i', basename((string)$this));

// application/core/Url.php, isImage()
return preg_match('/.(png|jpeg|jpg|gif|bmp|webp|svg)$/i', $this->path);
```

In a regular expression `.` is any character. So the check asked "does the name end in
`png`, `svg`, … preceded by anything", not "is the extension `png`, `svg`, …".
`apng`, `a.phpng` and `a.xsvg` all count as images.

## Impact

HCPHP itself decides nothing on the non-strict check. Copies do, for instance as the only
check on an uploaded icon or avatar:

- a name with no extension at all, or an extension that merely ends in an image extension,
  passes as an image and is stored under that name;
- the list includes `svg`. An SVG is a document that can carry script, so a check that lets an
  SVG in, by name or by content, lets in stored cross-site scripting wherever the file is
  served from the site's origin.

The strict check, `isImage(true)`, reads the content with `finfo`. It is not affected by this
defect. But it accepts `image/svg+xml` as an image too, so it does not settle the SVG point.

## How to tell if you are affected

```bash
grep -nF "'/.(png" application/core/Path.php application/core/Url.php
grep -rn "isImage(" application | grep -v vendor
```

A hit in the first grep (no backslash before the dot) is the defect. The second shows
whether it matters: an upload, a stored file name or a `Content-Type` decided on
`isImage()` without `true`.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core/Path.php` matches `preg_match\('/\.\(png\|jpeg`
- defective: `application/core/Url.php` matches `preg_match\('/\.\(png\|jpeg`
- fixed: `application/core/Path.php` matches `preg_match\('/\\\.\(png\|jpeg`
- fixed: `application/core/Url.php` matches `preg_match\('/\\\.\(png\|jpeg`

## The fix

Escape the dot:

```php
return preg_match('/\.(png|jpeg|jpg|gif|bmp|webp|svg)$/i', basename((string)$this));
```

The same in `Url::isImage()`.

## Porting checklist

- [ ] Escape the dot in both methods.
- [ ] Audit every caller of `isImage()` without `true`. A name is never what lets a file in. An
      upload is decided on its content (see ADV-2026-002: an allow-list of types detected by
      `finfo`, and a stored name the server generates), and a file served from the web root
      must not be one that can run.
- [ ] Decide on SVG for each upload that reaches `isImage()`. If the file is served from the
      site's origin, either refuse SVG or serve it with `Content-Disposition: attachment` and
      a `Content-Security-Policy` that forbids script.
- [ ] Extend your tests with names like `apng`, `a.phpng` and `a.xsvg`. HCPHP's
      `PathFilesystemTest` and `UrlTest` have the cases. They count as images before the fix.

## Status

Fixed in HCPHP in `7c9fde4` (2026-11-09). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-013` there, which checks for the defect by the markers above
and records the result in that repository.
