# ADV-2026-012 — `Url(true)` can point off-site, from the request path

**Severity:** medium (an open redirect on the site's own domain, wherever a copy sends the
user to `Url(true)`)
**Fixed in HCPHP:** `application/core/Url.php`
**Fixed in HCPHP by:** `637ffaf` (2026-11-09)
**Affects:** any copy whose `Url` constructor runs the absolute-URL parse on the current
request's path

---

## What was wrong

`new Url(true)` means "the URL of this request". The constructor took the request path, then
treated it like any other string it is given: if it looked like an absolute URL, it was parsed
as one.

```php
// application/core/Url.php, __construct()
if ($path === true) {
    $request = Application::getCurrentPath();
    $path = ltrim((string)parse_url($request, PHP_URL_PATH), '/\\');
    // ...
}

$url = filter_var($path, FILTER_VALIDATE_URL);

if ($url) {
    $this->scheme = parse_url($url, PHP_URL_SCHEME);
    $this->host = parse_url($url, PHP_URL_HOST);
    // ...
```

The request path is whatever the client asked for. A request for `/http://evil.example:80/`
has the path `http://evil.example:80/` once the leading slash is trimmed, so `Url(true)`
pointed at `evil.example`.

Older copies run `Filters::filter($path, Filters::URL)` there instead. Its pattern is not
anchored, so on PHP 7.1 and 7.2 any path that merely **contains** a URL is enough:
`/records/http://evil.example/x` gives `http://evil.example/x`. From PHP 7.3 that pattern no
longer compiles (PCRE2 rejects `[\w-?…]`), so the filter matches nothing at all.

## Impact

Nothing in HCPHP redirects to `Url(true)` today. Copies do: "send the user back to where they
were" after login, after a permission error, after a form. Wherever they do, a link like

```
https://<your site>/http://evil.example:80/
```

lands the user on `evil.example` after they log in. The link shows the real domain, so it is
good material for phishing. Wherever `Url(true)` is printed rather than redirected to
(canonical links, pagination, "share this page"), the page links off-site.

With no port in the URL, HCPHP threw a `TypeError` instead. See the notes.

## How to tell if you are affected

```bash
grep -nE '\$url = (filter_var\(\$path, FILTER_VALIDATE_URL\)|Filters::filter\(\$path, Filters::URL\))' \
  application/core/Url.php
grep -rnE 'Url\(true\)' application | grep -v vendor
```

The first grep shows the defect. The second shows where it matters: each `Url(true)` that is
redirected to, stored as a back URL or printed as a link.

Runtime check, on a local instance only, on a page that redirects back after login: log out,
open `http://localhost:<port>/http://evil.example:80/`, and log in. An affected copy ends on
`evil.example`. A fixed one stays on the local site.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core/Url.php` matches `\$url = filter_var\(\$path, FILTER_VALIDATE_URL\)`
- defective: `application/core/Url.php` matches `\$url = Filters::filter\(\$path, Filters::URL\)`
- fixed: `application/core/Url.php` matches `\$isCurrentRequest \? false`

## The fix

The current request is always on this site. Its path never goes through the absolute-URL
parse:

```php
$isCurrentRequest = $path === true;

if ($isCurrentRequest) {
    // ... take the request path, as before
}

$url = $isCurrentRequest ? false : filter_var($path, FILTER_VALIDATE_URL);
```

The path then stays a path, on the host, scheme and port `Application` reports.

## Porting checklist

- [ ] Port the change to the constructor, under your copy's names.
- [ ] Find every `Url(true)` and every redirect to a URL built from the request: a `backurl`
      parameter, a session value, the `Referer` header. Each one is a redirect target the
      client can influence. Before redirecting to a stored URL, check that its host is this
      site's.
- [ ] Copies on PHP 7.1 or 7.2 with the `Filters::URL` shape: check every other caller of
      `Filters::filter(…, Filters::URL)` too. Unanchored, it finds a URL anywhere in the
      string.
- [ ] Copy `tests/Unit/Core/UrlCurrentRequestTest.php`. It feeds the path through the `q`
      parameter, which `Application::getCurrentPath()` falls back to in the CLI. Run it before
      the fix: three cases point off-site, and the portless one throws.
- [ ] Probe the login back-redirect locally, as above.

## Notes

- **`new Url('https://example.org/x')` throws `TypeError`** in HCPHP, for any absolute URL
  without a port: `setPort()` takes `int` and receives `null` from `parse_url()`. The same
  happens in `Application::backUrl()`, which builds a `Url` from the `Referer` header, so an
  ordinary referrer makes it throw. That is a robustness defect, not a security one, and is not
  part of this fix.
- `Url(true)` drops the query string of the request when the path comes from the `q`
  parameter. That is unchanged.

## Status

Fixed in HCPHP in `637ffaf` (2026-11-09). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-012` there, which checks for the defect by the markers above
and records the result in that repository.
