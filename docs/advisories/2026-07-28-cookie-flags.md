# ADV-2026-004 — Session and auth cookies carry no security flags

**Severity:** high (turns any XSS into account takeover; enables CSRF)
**Fixed in HCPHP:** `application/core/Globals.php`
**Fixed in HCPHP by:** `93cf24e` (2026-07-28)
**Affects:** any copy whose `Globals` writes cookies with the positional `setcookie()` form

---

## What was wrong

```php
// application/core/Globals.php
setcookie($name, $value, time() + $time, '/');
```

No `HttpOnly`, no `Secure`, no `SameSite`. And `Globals::init()` called `session_start()` with
default parameters, so `PHPSESSID` was unflagged too.

## Impact

- **No `HttpOnly`** — any script on the page can read the session and auth cookies. Combined
  with ADV-2026-003 (or any other XSS) this is full account takeover rather than a defaced page.
- **No `SameSite`** — cookies ride along on cross-site requests, so a third-party page can act
  as the logged-in user. This matters more than usual here because the CSRF token is the
  session id and is skipped entirely when the cookie is absent (fixed in HCPHP by `b49f456`).
- **No `Secure`** — cookies are sent over plain HTTP, exposing them to anyone on the path.

**Check which cookie actually matters in your project first.** In HCPHP, `Authenticator::login()`
passes `$remember` as the "write a cookie" flag, so on an ordinary login the auth key goes into
`$_SESSION` and the only cookie is `PHPSESSID`. The session cookie, set by `session_start()`,
is the one carrying identity — and it is the one a fix aimed only at `Globals::set()` would
miss entirely.

## How to tell if you are affected

```bash
grep -n "setcookie(\|session_start(" application/core/Globals.php
```

Runtime check:

```bash
curl -s -i https://<host>/user/login/ | grep -i set-cookie
```

Anything without `HttpOnly` and `SameSite` is affected.

## The fix

Use the options-array form of `setcookie()` (PHP 7.3+), and set the session parameters before
the session starts:

```php
const COOKIE_SAMESITE = 'Lax';

public static function getCookieOptions(int $expires, bool $secure): array
{
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => self::COOKIE_SAMESITE,
    ];
}

// session_set_cookie_params() does NOT take the same array: the key is "lifetime",
// a duration, and passing "expires" raises a warning and is then ignored.
public static function getSessionCookieOptions(bool $secure): array
{
    $options = self::getCookieOptions(0, $secure);
    unset($options['expires']);

    return ['lifetime' => 0] + $options;
}
```

Applied at three call sites: `set()` (cookie branch), `reset()` (deletion), and `init()`
(`session_set_cookie_params()` before `session_start()`).

**`secure` is conditional**, taken from the existing `Application::isHttpsEnabled()`. Forcing it
true breaks every plain-HTTP development stack, which is how the flag ends up deleted again.

**`SameSite=Lax`**, not `Strict`: Lax blocks the cross-site POST and subresource vectors while
still letting a user who follows a link from outside arrive logged in. `Strict` makes them
appear logged out, which reads as a bug and invites someone to turn it off.

## Porting checklist

- [ ] Add both option builders and use them at all three call sites. **Do not skip `init()`** —
      it is the half that protects the session cookie.
- [ ] Watch the array shape. Passing `getCookieOptions()` to `session_set_cookie_params()`
      raises `Unrecognized key 'expires'` and then silently proceeds *without the flags*. This
      cost a cycle here: the unit tests passed and only running a CLI command surfaced it.
- [ ] Deletion must repeat the same flags and path, or the browser keeps the original cookie.
      Also stop passing `null` as the cookie value — deprecated on PHP 8.1+.
- [ ] Confirm nothing reads these cookies from JavaScript before setting `HttpOnly`:
      `grep -rn "document.cookie" application/`
- [ ] Copy `tests/Unit/Core/GlobalsCookieTest.php`. Note it pins the *accepted key set* for the
      session options rather than calling `session_set_cookie_params()` for real — that
      function refuses once any output exists, so a live call fails for the wrong reason under
      PHPUnit.
- [ ] Verify with `curl -i` against a real instance, not just the unit tests.

## Notes

- This does not address auth-key generation or lifetime (keys were `sha1($id . time())`, never
  expired, and survived logout; fixed in HCPHP by `09fa0fd`), nor the CSRF token being the
  session id (fixed by `b49f456`). Port those separately.
- HCPHP's login form has no `remember_me` field, so `$data['remember_me']` in
  `controllers/user.php:103` is always undefined and the auth-key cookie is currently only ever
  deleted. Applications built on HCPHP may use that path, so the `set()` change matters there
  even though its effect here is limited to `PHPSESSID`.

## Status

Fixed in HCPHP in `93cf24e` (2026-07-28). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-004` there, which checks for the defect by the markers above
and records the result in that repository.
