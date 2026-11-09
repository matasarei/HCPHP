# ADV-2026-010 — The language code from the request names a file

**Severity:** medium (any visitor chooses which JSON file becomes the page's strings, or makes
any page fail with a 500)
**Fixed in HCPHP:** `application/core/Language.php`, `application/core/Application.php`
**Fixed in HCPHP by:** `04dc727` (2026-11-09)
**Affects:** any copy whose `Application::start()` passes `$_REQUEST['l']` to `Language`
unchecked

---

## What was wrong

```php
// application/core/Application.php, start()
Language::setDefaultLanguageCode(rtrim(
    empty($_REQUEST['l']) ? $config->get('lang') : $_REQUEST['l'],
    '/'
));

// application/core/Language.php, __construct()
$path = new Path(sprintf('application/lang/%s.json', $this->languageCode));
```

Older copies have `$lang = empty($_REQUEST['l']) ? $config->lang : $_REQUEST['l'];` and
`new Path("application/lang/{$lang}.json", true)`.

The language code is request data, and it became part of a file path. Nothing checked that it
was a language code.

## Impact

Verified against HCPHP's own `Language` at PHP 7.4:

- `?l=../config/database` loads `application/config/database.json`, the database credentials,
  **as the page's strings**. So does any JSON file the PHP process can read:
  `?l=../../composer`, `?l=../lib/vendor/composer/installed`, and `?l=..\config\routing`,
  because `Path` turns backslashes into slashes. A template asking for a string the file does
  not have prints `%name%`. A template asking for one it does have prints that value. Whether
  that leaks a credential depends on which string names a copy's templates use. Check yours.
- If an attacker can store a `.json` file anywhere the process can read it, an upload for
  instance, they choose every string on the page.
- `?l=zz`, or any code with no file behind it, throws `UnexpectedValueException` on every page
  that asks for a string. That is a 500 on demand, for any visitor, on any page. In debug mode
  the message carries the requested text back into the page.

Over HTTP on a local instance, HCPHP's login page answered `?l=zz` with a 500. With
`?l=../config/routing` it answered 200, with its button and fields labelled `%login%`,
`%email%` and `%password%`.

`$_REQUEST` holds the query string and the POST body, and the cookies too unless
`request_order` says otherwise. So a planted cookie keeps the effect going.

## How to tell if you are affected

```bash
grep -nE "_REQUEST\['l'\]" application/core/Application.php
grep -n "application/lang/" application/core/Language.php
```

Affected if the request value reaches `Language` with no check that it is a code and that the
language exists.

Runtime check, on a local instance only: open `/?l=zz` and `/?l=../config/routing`. An
affected copy answers the first with a 500 (or an exception in debug mode). The second renders
with `%name%` in place of its strings. A fixed copy renders both in the default language.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core/Application.php` matches `empty\(\$_REQUEST\['l'\]\) \? \$config`
- fixed: `application/core/Language.php` matches `function isValidCode\(`
- fixed: `application/core/Application.php` matches `Language::chooseCode\(`

## The fix

A language code is a code and nothing else, and the request gets one only if it exists:

```php
// Language
public static function isValidCode(string $languageCode): bool
{
    return preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $languageCode) === 1;
}

public static function exists(string $languageCode): bool
{
    return self::isValidCode($languageCode) && is_readable(self::path($languageCode));
}

public static function chooseCode($requested, string $fallback): string
{
    if (!is_string($requested)) {
        return $fallback;
    }

    $requested = rtrim($requested, '/');

    return self::exists($requested) ? $requested : $fallback;
}
```

```php
// Application::start()
Language::setDefaultLanguageCode(
    Language::chooseCode($_REQUEST['l'] ?? null, (string)$config->get('lang'))
);
```

The constructor also refuses an invalid code before it builds a path. It throws the same
`UnexpectedValueException` a missing language always threw, so existing callers need no change.

## Porting checklist

- [ ] Find every place a language code comes from outside: `?l=`, a cookie, a user's saved
      preference, a URL segment, the `Accept-Language` header. Each goes through the same
      choice.
- [ ] Widen or narrow the code pattern to the files in your `application/lang/`. List them
      first. A copy with `uk.json` and `en.json` needs nothing more than two letters, and one
      with `pt-BR.json` needs the suffix.
- [ ] If your `Language` caches instances per code, the check must run before the cache is
      keyed. Otherwise every distinct `?l=` value still creates an entry.
- [ ] If your copy catches the exception from `Language` around `setDefaultLanguageCode()`,
      keep that. But choosing the fallback up front means the request can no longer reach it.
- [ ] Copy `tests/Unit/Core/LanguageCodeTest.php`. Run it before the fix: the `../` cases fail
      because the files load.
- [ ] On a local instance, open the two URLs above.

## Notes

- `Application::start()` exits through `Application::stop()`, so the tests drive the decision
  through `Language::chooseCode()` rather than through a request.
- In debug mode HCPHP escapes what it prints. A copy whose debug output is not escaped turns
  the `?l=` message into reflected cross-site scripting, and should not run with debug on in
  production in any case.

## Status

Fixed in HCPHP in `04dc727` (2026-11-09). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-010` there, which checks for the defect by the markers above
and records the result in that repository.
