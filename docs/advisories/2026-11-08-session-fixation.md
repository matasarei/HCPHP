# ADV-2026-006 — The session id is not changed at login (session fixation)

**Severity:** high (account takeover, given a way to set the victim's session cookie)
**Fixed in HCPHP:** `application/lib/UserBundle/Service/Authenticator.php`, `application/core/Globals.php`
**Fixed in HCPHP by:** `68b8a21` (2026-11-08)
**Affects:** any copy whose login keeps the session id the browser arrived with

---

## What was wrong

```php
// application/lib/UserBundle/Service/Authenticator.php — login()
$this->logout();

$user->setAuthKey(self::generateAuthKey());
$user->setAuthTime(time());
$this->repository->save($user);

Globals::set(self::KEY_COOKIES_AUTH_KEY, $user->getAuthKey(), $remember, $this->loginTime);
```

On an ordinary login `$remember` is false, so `Globals::set()` stores the auth key in
`$_SESSION`. From then on the session id is the user's identity. `session_regenerate_id()`
was called nowhere, so the id the browser had *before* the login became the id of the
logged-in user.

## Impact

Someone who knows a session id the victim will use is logged in as the victim as soon as
the victim logs in. Ways to plant an id: a cookie set from a sibling subdomain, a shared or
kiosk machine, or a proxy that rewrites the cookie. The attacker first gets a real id by
visiting the site; PHP's default `session.use_strict_mode = 0` accepts even an id it never
issued.

ADV-2026-004's cookie flags make an id harder to steal, but they do not stop one being
planted. `logout()` empties the session data but also keeps the id.

## How to tell if you are affected

```bash
grep -rn "session_regenerate_id" application/core application/lib
```

No hit, or a hit that `login()` never reaches, means the copy is affected. The hcphp
plugin's `scan.sh` reports this as `SESSION-REGEN`.

Runtime check, on a local instance only:

1. Request the login page and note the `PHPSESSID` it sets.
2. Log in while sending that same cookie.
3. Request a page that needs a login, sending the cookie from step 1.

If that page loads, the copy is affected. A fixed copy sets a new `PHPSESSID` at step 2, and
the old one is sent back to the login page.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A missing call cannot be matched by a pattern, so this advisory has no `defective`
marker: a copy without the call is reported as "read by hand", never as fixed.

- fixed: `application/lib/UserBundle/Service/Authenticator.php` matches `regenerateSession\(\)|session_regenerate_id\(`

## The fix

A helper in `core\Globals`, beside `init()`, which starts the session:

```php
public static function regenerateSession(): void
{
    // CLI commands and tests run without a session; regenerating one warns.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
```

`Authenticator::login()` calls it after `logout()` has cleared the old state and before the
new auth key is stored:

```php
$this->logout();

Globals::regenerateSession();
```

`true` deletes the old session on the server, so the planted id holds nothing afterwards.

## Porting checklist

- [ ] Call it **after** the credentials are verified. A failed login must leave the session
      alone, or anyone can rotate a victim's session by posting a wrong password.
- [ ] Call it **before** anything is written to the session for the new identity. Data
      written before the call is carried over too, which is harmless, but the order makes
      the intent plain.
- [ ] Guard on `session_status()`. Without the guard, CLI commands and the test suite raise
      a warning, and `phpunit.xml` turns that into a failure.
- [ ] Do the same anywhere else the identity or privilege changes: impersonation, a role
      change, a password reset that logs the user in.
- [ ] Copy `tests/Unit/UserBundle/SessionFixationTest.php`. It plants an id, logs in and
      checks the id changed and the auth key moved with it. A session cannot start once
      output exists, so it runs in a separate process.
- [ ] Probe a local instance as described above. With PHP's OPcache on, wait out
      `opcache.revalidate_freq` after switching code, or the probe runs the previous build.

## Notes

- Turning on `session.use_strict_mode` makes PHP refuse an id it did not issue. That helps,
  but it is not a fix: an attacker plants an id the server did issue.

## Status

Fixed in HCPHP in `68b8a21` (2026-11-08). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-006` there, which checks for the defect by the markers above
and records the result in that repository.
