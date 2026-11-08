# ADV-2026-001 — A condition value containing `%` was silently treated as SQL `LIKE`

**Severity:** critical (authentication bypass, auth-key disclosure, mass update/delete)
**Fixed in HCPHP:** `application/core/DatabaseSQL.php`, `application/core/Like.php`
**Fixed in HCPHP by:** `b14fa15` (2026-07-28)
**Affects:** any copy whose condition builder chooses the operator from the value's contents

---

## What was wrong

`DatabaseSQL::prepareConditions()` inspected each condition **value** and promoted it from `=` to
`LIKE` whenever it contained a `%`:

```php
} elseif (strpos($value, '%') !== false) {
    $prepared[] = sprintf('`%s` LIKE :%s', $name, $name);
```

Any lookup whose value comes from user input therefore became a wildcard search. The auth-key
lookup is the worst case, because the value is a cookie the attacker sets:

```
Globals::get('_auth_key')  ->  findOne(['authkey' => $authKey])  ->  authkey LIKE '%'
```

`LIKE '%'` matches every row, and every `getRecord()` returns `$results[0]` after merely calling
`trigger_error()` on multiple matches. So the request authenticates as the first user in the table.

## Impact

Verified against a running HCPHP instance over HTTP:

```
auth_key=%                                          -> HTTP 200, authenticated
auth_key=7%                                         -> HTTP 200, authenticated
auth_key=k%                                         -> HTTP 302, rejected
auth_key=<the real key>                             -> HTTP 200, authenticated
```

Three separate problems, not one:

1. **Authentication bypass.** `_auth_key=%` logs in as an arbitrary user, no credentials needed.
2. **Auth-key disclosure.** `7%` succeeding and `k%` failing makes the endpoint a prefix oracle:
   16 requests per character, ~640 requests to recover a full 40-character key, which then works
   as a normal durable session.
3. **Mass update and delete.** The same builder serves `deleteRecords()` and `updateRecords()`, so a
   `%` value in a condition matches — and therefore destroys — every row in the table.

`empty($auth_key)` guards do **not** help: `%` is not empty. An `is_string()` guard does not help:
`%` is a string. Multiple matches do not help: the row is returned anyway.

## How to tell if you are affected

```bash
grep -rn "'%')" application/core/          # look for strpos($value, '%') !== false
```

If your condition builder (`DatabaseSQL`, `SqlDatabase`, or `Database`) contains that branch, and
any authentication or lookup passes user input into a condition array, you are affected.

Runtime check against a non-production instance:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -H 'Cookie: _auth_key=%' https://<host>/
```

A page that renders as a logged-in user confirms it.

**Markers** — read by the hcphp plugin's `drift.sh`; paths are relative to the application root,
and a copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core` matches `strpos\(\$value, *'%'\)`
- fixed: `application/core/DatabaseSQL.php` matches `instanceof Like`

## The fix

Choose the operator from the value's **type**, never its contents. A plain string always means `=`,
whatever characters it holds. `LIKE` becomes an explicit opt-in via a small value object.

```php
// application/core/Like.php  (new)
final class Like
{
    const ESCAPE_CHARACTER = '!';
    // __construct(string $pattern) — pattern used verbatim
    // ::contains() / ::startsWith() / ::endsWith() — escape %, _ and ! in untrusted input
}
```

```php
// prepareConditions()
if (is_array($value)) {
    // ... IN (...) -- reject a Like nested in here: SQL has no "IN (LIKE ...)",
    // and binding it silently would match the pattern literally and return
    // quietly wrong rows.
    foreach ($value as $key => $val) {
        if ($val instanceof Like) {
            throw new InvalidArgumentException(
                sprintf('LIKE is not supported inside an IN condition ("%s")', $name)
            );
        }
        // ...
    }
} elseif ($value instanceof Like) {
    $prepared[] = sprintf("`%s` LIKE :%s ESCAPE '%s'", $name, $name, Like::ESCAPE_CHARACTER);
} else {
    $prepared[] = sprintf('`%s` = :%s', $name, $name);
}

// bindValues()
} elseif ($value instanceof Like) {
    $sth->bindValue(':' . $name, $value->getPattern());
}
```

Callers that genuinely wanted a wildcard become explicit:

```php
$database->getRecords('users', ['email' => new Like('%@example.com')]);   // pattern is yours
$database->getRecords('users', ['email' => Like::contains($searchTerm)]); // input is escaped
```

## Porting checklist

- [ ] Find every existing caller that relied on the old implicit behaviour — anywhere a condition
      value could contain `%` — and convert it to `new Like(...)`. **Do this before shipping**, or
      those searches silently become exact-match. `grep -rn "=> *['\"]%" application/`
- [ ] Apply the `prepareConditions()` and `bindValues()` change.
- [ ] Add `Like` (adapt the namespace to your project).
- [ ] Copy the regression tests: `tests/Unit/Core/DatabaseSQLConditionsTest.php` (the bug),
      `tests/Unit/Core/DatabaseSQLLikeTest.php` (the `Like` replacement, including escaping) and
      `tests/Unit/UserBundle/AuthKeyLookupTest.php` (the auth path) in HCPHP. They are written
      against behaviour, not internals, so they port with only names changed.
- [ ] Verify over HTTP with the `curl` check above, on a non-production instance.
- [ ] **Rotate all auth keys after deploying.** Any key that leaked via the prefix oracle stays
      valid otherwise — the fix closes the hole, it does not invalidate stolen keys.

## Notes

- Raw `LIKE` in hand-written SQL is a separate path and is **not** covered by this fix. In HCPHP,
  `RecordsQueryBuilder` still builds (as of 2026-11-08) `'%' . $input . '%'` for `getRecordsSQL()`, so a user typing
  `%` into the search box still matches everything. That is not a privilege bypass (results are
  already scoped to what the user may see), but the same escaping applies — use `Like::escape()`
  on the input. Check your own query builders.
- Generated SQL now always declares `ESCAPE '!'`, so `!` is the escape character inside every
  pattern. `Like::escape()` accounts for this; hand-written patterns should too.

## Status

Fixed in HCPHP in `b14fa15` (2026-07-28). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-001` there, which checks for the defect by the markers above
and records the result in that repository.

A copy that was affected also records whether its auth keys were rotated after the fix.
