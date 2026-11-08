# Trap catalog — silent divergences that shipped real bugs

Every entry here failed **silently**: no exception, no error, wrong output. They come from the
migration arcs of a production HCPHP copy, where a green suite was green for **every one** of
roughly thirty such bugs. Pre-flight a slice against this list before it moves anything; after
it lands, grep for each shape. Templates and views are never type-checked and held most of them.

## 1. Accessor drift between an old and a new class

Before moving any caller, enumerate every same-named method on both and compare **arity and
return type**.

- **Arity drift.** Legacy `getTime($format)` formatted; the new one takes no argument. PHP drops
  the extra argument silently and a raw unix timestamp renders. Shipped five times, always in
  templates. Shape: `grep -rnE "getTime\(['\"]" application --include='*.php'`
- **Same name, different return.** Legacy returned a scalar, the new one a collection. An
  object is always truthy, so every `if ($x->getThing('a'))` guard passes. Fix: a **new method
  name**, never a redefined contract — blind migration then fails loudly.
- **Id vs entity.** A legacy property held the id; the new getter returns the hydrated entity,
  or null when not hydrated. Grouping keys collapse, links render as `/controller//`. Match the
  **expression shape**, not variable names: `grep -rnE '\$[a-zA-Z]*->instance\b'`.

## 2. Magic access (`core\MagicObject` and copies of it)

- There is **no `__isset()`**. `empty($obj->prop)` is always true and `isset($obj->prop)` always
  false, whatever the getter returns. Read into a local first.
- HCPHP's `__get` throws on an unknown getter; some copies return null instead. Check which.
- Declaring a typed public `$prop` next to a setter's backing field **bypasses the setter** from
  inside the class. Do not "modernize" a magic class with public properties.
- Magic `->prop` on an adapter that lacks the getter is null (or a throw). Access adapters
  through their interface methods only.

## 3. Hydration (mapper level)

- **Field drop → save wipe.** The new mapper does not read a column back, so any
  load-then-save writes it empty. Guard: a full-column round-trip test with a completeness
  assertion (every column in the fixture) and bidirectional iteration (a column that vanishes
  fails, not only one that changes).
- **Empty hydrated differently.** Legacy left `null`; the new one builds a truthy empty entity
  (id 0). `if ($log->user)` flips meaning. Fix at the hydration boundary, never per template.
- **Not hydrating verbatim.** A skipped column gets re-stamped from request state on the next
  read-modify-write — the reader's user agent written onto hundreds of thousands of rows.
- **Setter misroute.** `setTitle` wired to `setDistance`. Guard: round-trip every scalar setter
  with a **distinct value per field**, and booleans set opposite to their defaults.

## 4. Query semantics

- **Limit.** Legacy `-1` meant no limit; absent-or-`0` meant **100** because `empty(0)` is true.
  A new default of 100 where legacy was unlimited silently strands rows. `OFFSET` without
  `LIMIT` is a MySQL syntax error.
- **Default order drift** (id ASC vs time DESC) reverses pages; row counts cannot see it.
  `MAX(id)` is not "latest by time".
- **Unknown condition keys dropped silently** → an unfiltered read. Once this killed a cron
  cursor. When porting a builder, count supported keys against the legacy ones.
- **Scalar vs array condition.** A scalar not wrapped into the IN-list matches nothing; the page
  still renders. Fix in the builder, not at call sites.
- **An order string cast to an array** (`(array)'DESC'` → `['DESC']`) silently flips a sort, or
  produces ``ORDER BY `t`.`0` `` — invalid SQL. Test the string form callers actually pass.
- **Tie order is plan-dependent, not behaviour.** Do not add tiebreakers to reproduce legacy
  order: it was tried and reverted — fixed one filter combination, broke ten.

## 5. HCPHP infrastructure

- **DynamicDB schema sync** runs on every request from `config/dynamicdb.json`, and removing a
  field issues `ALTER TABLE … DROP COLUMN` (`DynamicDB/Field/Field.php`). A refactor never edits
  that file. `schema-guard.sh` enforces it.
- **Strings that are data.** A class's short name or `getModelName()` output may be persisted
  into rows (audit `event`/`controller` columns) or used as a language key. Renaming the class
  splits history. Pin those strings in a test **before** any move; never rename a short name.
- **Autoloader duality.** `application/models`, `core`, `commands`, `events` may be classmapped;
  `application/lib` is path-autoloaded. Adding under `lib/` needs no dump. **Deleting** a
  classmapped file needs `composer dump-autoload` — in deploys too. A stale classmap makes
  `class_exists()` emit include warnings, which `phpunit.xml` turns into failures.
- **Command and event names.** `./run foo:bar` requires `application/commands/foo_bar.php`
  declaring `FooBarCommand`. File name and class name must agree, or the require succeeds and
  the `new` fails.
- **Import collision** while two stacks coexist: a legacy and a bundle class share a short
  name, and an unqualified reference resolves to whichever is imported. Alias explicitly.
- **Compiled templates** are recompiled by mtime. A deploy that preserves timestamps serves the
  old build: `php run cache:purge` after deploying.
- **Line endings.** An editor that normalises CRLF turns a three-line change into a whole-file
  diff, and rename-similarity — the proof of a verbatim move — is gone.

## 6. Tests that look like coverage

- **Fixture blindness.** A stub answering `null` makes the asserted branch unreachable. A stub
  with `public $instance`, or an anonymous class with `getTime($format)`, hides exactly the
  divergence under test. Once entity shape matters, use real entities.
- **Weak assertions.** `assertStringContainsString('LIMIT 1', $sql)` also matches `LIMIT 100`.
  `expectException(Error::class)` accepts any Error — assert the message.
- **Order leaks.** Moving test files changes run order and flushes hidden global state
  (`core\Path` roots, container entries, `$_SESSION`). Restore state unconditionally in
  `tearDown()`.
- **A test that never failed proves nothing.** Every new test is mutation-checked
  (`verification-kit.md` §5).

## 7. Process traps

- **"While I'm here."** A logic edit inside a move PR destroys the proof that the move was
  verbatim. Separate PR, always.
- **A deferral whose trigger cannot fire.** "Do it when a caller needs X" — when no caller ever
  can, the work silently never happens. Every deferral names a grep-able criterion, and the plan
  checks it can become true.
- **Deleting a "deprecated" method that is still live.** Grep by method name, by magic-property
  access (`->total` reaches `getTotal()`), and by any interface it satisfies.
- **An audit by variable names.** A list of expected names missed three sites, twice. Audit by
  expression shape, and run the audit at least twice.
- **A worker reading state its own request wrote.** Deferring a send to a queue changed what a
  "was it unpinned before?" check saw: zero of ~800 notifications went out for a month. Moving a
  side effect is a behaviour change; it is out of scope for an automated slice.
