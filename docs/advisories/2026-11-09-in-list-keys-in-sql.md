# ADV-2026-009 — The keys of an IN-list array are written into the SQL text

**Severity:** critical (SQL injection on MySQL wherever a list of values comes from a request)
**Fixed in HCPHP:** `application/core/DatabaseSQL.php`
**Fixed in HCPHP by:** `9adee1d` (2026-11-09)
**Affects:** any copy whose `prepareConditions()` / `_prepareConditions()` names an IN
placeholder after the value array's key

---

## What was wrong

An array condition compiles to `IN (...)`. Each placeholder was named after the array's
**key**, and that name was written into the SQL text:

```php
// application/core/DatabaseSQL.php, prepareConditions()
foreach ($value as $key => $val) {
    $inConditions[] = sprintf(':%s%s', $name, $key);
}
$prepared[] = sprintf('%s IN (%s)', $name, implode(', ', $inConditions));

// bindValues()
foreach ($value as $key => $val) {
    $sth->bindValue(sprintf(':%s%s', $name, $key), $val);
}
```

Older copies have the same code as `":{$cname}{$key}"` in `_prepareConditions()` and
`_bindValues()`.

For a list (`[0 => …, 1 => …]`) the keys are harmless digits. But the keys are data, and they
are not always digits. `json_decode()` turns a JSON object into an array with the object's keys,
and `array_filter()` keeps whatever keys it was given. So a request body of

```json
{"ids": {"0": 2, "0) OR (1=1": 2}}
```

passed on as `['id' => $input['ids']]` compiles to

```sql
… WHERE id IN (:id0, :id0) OR (1=1)
```

## Impact

PDO's MySQL driver **emulates prepares by default**. With emulation, PDO substitutes the values
itself and checks only that the number of bindings matches the number of placeholders, which
the attacker controls. The injected text runs. Verified against MySQL 8 at PHP 7.4 with HCPHP's
own `DatabaseSQL`:

- `getRecords()` with the payload above returned **every row**;
- `updateRecords()` with it **rewrote every row**;
- `{"0": 999, "0) OR (SUBSTRING((SELECT authkey FROM users WHERE id=1),1,7)='secret_'": 999}`
  returned rows when the guess was right and none when it was wrong. That is enough to read any
  column of any table: auth keys, API tokens, password hashes.

`deleteRecords()` builds its `WHERE` clause the same way. Any endpoint that passes a
request-supplied list into a condition is a way in. Without an account, if that endpoint is
public.

SQLite binds natively and refuses the statement at `execute()`. A copy running on SQLite gets
errors, not injection, but should be fixed all the same.

The **column name** of a condition was written into the SQL text too: unquoted in the `IN`
branch, and inside backticks, unescaped, in the others. Names are literals in HCPHP. A copy
that builds conditions from request keys (`getRecords($table, $_GET)`) has the same hole there.

## How to tell if you are affected

```bash
grep -nE 'sprintf\(.:%s%s., \$name, \$key\)|\{\$cname\}\{\$key\}' application/core/DatabaseSQL.php
```

A hit is the defect. Then find the lists that come from a request. Look for `json_decode`,
`array_filter` or `Globals::` / `$_POST` / `$_GET` values passed into `getRecords`,
`getRecord`, `getValues`, `updateRecords` or `deleteRecords` as an array value:

```bash
grep -rnE "(getRecords|getRecord|getValues|updateRecords|deleteRecords)\(" application \
  | grep -v vendor
```

Runtime check, on a local instance with MySQL only: send a list as a JSON object with a
second key of `0) OR (1=1` to an endpoint that takes a list of ids. An affected copy acts on
every row, or answers with `SQLSTATE[42000]` for a key with broken syntax. A fixed copy acts
only on the listed ids.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/core/DatabaseSQL.php` matches `sprintf\(':%s%s', \$name, \$key\)`
- defective: `application/core/DatabaseSQL.php` matches `\{\$cname\}\{\$key\}`
- fixed: `application/core/DatabaseSQL.php` matches `array_values\(\$value\) as \$position`

## The fix

Number the placeholders **by position**, on both sides. A plain list keeps exactly the names
it had:

```php
// prepareConditions()
foreach (array_values($value) as $position => $val) {
    $inConditions[] = sprintf(':%s%d', $name, $position);
}

// bindValues()
foreach (array_values($value) as $position => $val) {
    $sth->bindValue(sprintf(':%s%d', $name, $position), $val);
}
```

And refuse a column name that is not an identifier, before anything is prepared:

```php
if (!is_string($name) || !preg_match('/^\w+$/', $name)) {
    throw new InvalidArgumentException(
        sprintf('A condition column must be a plain identifier, got "%s"', $name)
    );
}
```

A name that is not `\w+` never produced a valid placeholder (`:users.id` is not one), so the
check refuses nothing that worked.

## Porting checklist

- [ ] Change **both** methods. If only the SQL side is renumbered, the bindings no longer match
      and every keyed list fails. If only the bind side is renumbered, the hole stays.
- [ ] Look for other places that build placeholder names from array keys: query builders,
      mappers, raw `IN (` statements assembled by hand. `grep -rn "IN (" application`.
- [ ] Audit the callers of the raw-SQL methods (`getRecordsSQL`, `executeSQL`, …) that bind an
      array. They share `bindValues()`. A hand-written `IN (:ids0, :ids1)` with a plain list is
      unchanged. One that relied on **named** keys (`:idsa`) must be rewritten.
- [ ] Check that no caller passes a condition name with a dot, a hyphen or a space. Any such
      call never worked, and now it throws. `grep` the `getRecords(`-family calls for
      `'[a-z_]+\.[a-z_]+' =>`.
- [ ] Copy `tests/Unit/Core/DatabaseSQLInListTest.php`. It records the SQL text through a
      subclass, so it needs no MySQL. Run it before the fix: the key test fails with
      `… WHERE id IN (:id0, :id0) OR (1=1)` in the message.
- [ ] Probe a MySQL instance locally as above. `pdo_sqlite` will not show the injection.
- [ ] Consider turning emulation off (`PDO::ATTR_EMULATE_PREPARES => false`) as defence in
      depth. Do it as its own change: it alters how some types come back from MySQL.

## Notes

- Positional names can still collide with another condition's name: a list under `id` makes
  `:id1`, and so does a scalar condition on a column named `id1`. That was true before, with
  keys, and is a correctness bug rather than an injection. It is not part of this fix.
- An empty array still compiles to `IN ()`, which MySQL rejects. That is unchanged.

## Status

Fixed in HCPHP in `9adee1d` (2026-11-09). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-009` there, which checks for the defect by the markers above
and records the result in that repository.
