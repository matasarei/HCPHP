# Verification kit — the ladder every slice climbs

The full suite runs after every commit, at the copy's floor PHP. It is **necessary, never
sufficient**: it was green for every one of the ~30 bugs the trap catalog records. The rungs
below are what actually caught them. Which rungs a slice needs is set by its risk class in
`slice-recipes.md`; the commit's `Verified:` section reports each rung run, with real numbers,
and each rung skipped, with the reason.

Throwaway scripts go in the copy's ignored scratch directory (`var/` where it exists — check
`.gitignore`), never in tracked paths. A CLI script must boot the application the way
`./run` does — `Application::setMode(Application::MODE_CLI)` and `Events::triggerEvent('Init')`
in HCPHP — before touching any service, or the container is empty.

## 0. A test database, built from the code — before the first test that needs one

A copy usually has no schema file, so code that touches the database looks untestable. It is
not, and a dump of a production database is never the way in, not even a structure-only one:
the schema a test needs is in the mappers.

- **The base class comes first.** A copy with no base test case for database work gets one,
  from `templates/DatabaseTestCase.php`, in its own pull request and with a test of its own,
  before the first test that needs it. Every test gets a new in-memory sqlite connection,
  registered at every point the code looks the database up (a static registry, a container
  entry, a constructor argument) and removed in `tearDown()`, together with any entity cache.
- **Tables come from the mappers.** Each mapper names its table and turns an entity into a row.
  Build the entity with every field set, take the row the mapper writes (`rowFromMapper()`), and
  create the table from it (`createTableFromRow()`). Each test creates only the tables it
  touches. A column the code reads but no mapper writes is added by name in the test, with a
  comment saying where it is read.
- **Types come from the values.** The layer binds every value as a string, and a TEXT column
  compares as text: `'9' > '50'`. Timestamps, counts and flags get INTEGER. Phone numbers and
  codes stay TEXT, so their leading zeros survive.
- **What sqlite cannot run** stays on a recording double (HCPHP's `tests/Support/RecordingDatabase.php`):
  `SQL_CALC_FOUND_ROWS` / `FOUND_ROWS()`, `ON DUPLICATE KEY UPDATE`, `MATCH ... AGAINST`, MySQL
  DDL, `CONCAT()` on sqlite before 3.44, and questions about the schema itself. Name in the pull
  request the paths that left untested.
- **A class that opens the database in its constructor** is built after the connection is
  registered. One that cannot be pointed anywhere else gets a seam first, a constructor argument
  defaulting to today's behaviour, in its own pull request.
- **A controller action** is stopped at a collaborator by a sentinel exception thrown from a
  double, and the test asserts what the action did up to that point (HCPHP's
  `tests/Unit/RecordsDeleteTest.php`). A render that exits is caught the same way, at the view.
- **The local instance** for §2–§4 is built from the mappers in the same way when no schema
  exists, never from production. Its rows are written through the application's own forms or
  repositories.

A fix is never recorded as untested because the schema is missing. Only a path that needs SQL
sqlite cannot run is, and the pull request names it.

## 1. Suite and lint — every slice

```sh
<exec.prefix> php -l <each changed file>          # at the floor PHP, not the host's
<exec.prefix> <test command>                       # the whole suite, not a filter
```

Quote the result line. Compare the **delta** against the base branch, never the absolute count:
a suite already red on the base is a fact to state, not a reason to stop reading.

`slice-check.sh` runs both, plus the ratchets and the schema guard, and prints one verdict line.

## 2. Page sweep — every slice that touches rendering

Fetch every page that renders the moved code, on the branch **and** on the base, and diff the
**bodies** byte-wise. This found nearly every rendering bug.

- **Commit before checking out the base**, or the working tree carries over and you diff the
  branch against itself.
- Log in first and keep a cookie jar. Every response gets grepped for
  `Fatal error|Uncaught|SQLSTATE|Parse error|Stack trace|Warning:|Notice:` — and the grep is
  **self-tested** against a page known to be broken: an `&nbsp;`-encoded message once matched
  nothing.
- A 200 proves nothing: several paths render errors into a 200 body.
- Watch size deltas in **both** directions. Larger can mean a wrong branch rendered; smaller can
  mean formatted dates degraded to raw integers.
- Pages that are not byte-stable (unordered rows, pages that write on visit): pin a closed date
  window, or compare sorted content. Say which pages were compared which way.
- Debug output off hides errors entirely. Know whether the copy's local config has it on.

## 3. Live parity — every slice that touches a read path with data behind it

Old code vs new code on **real local rows**: compare ordered id lists, whole folded structures,
per-row payloads — not counts. Counts cannot see order drift or swapped rows.

- **SKIP, never PASS, on an empty result.** Two empty results prove nothing. Re-anchor the
  probe on data that actually exercises the branch.
- Record the numbers: "54 checks, 0 failed, 3 SKIP (no archived rows locally)".
- Compare app against app, not app against hand-written SQL: PHP and MySQL disagree about
  time zones.
- No local data at all is a stated limitation, not a pass.

## 4. Write probes — every slice that touches a write path

**Write inside a transaction, then roll back:** begin, run the real writer, re-read the row and
compare **every column**, roll back in `finally`, then prove nothing leaked (`MAX(id)`
unchanged, a targeted `COUNT` of 0).

Drive the real route on branch and base only for writers with **no side effects**. A save that
sends mail, SMS or messages is never driven for verification — pin it at unit level with a
notifier double, and say so in the PR.

## 5. Mutation check — every new test

Revert the production line the test is about (or reintroduce the known bug), run the test, and
confirm **the right assertion** fails, for the right reason. Then restore. Name in the PR which
assertions survive and why. A test that has never been seen failing is not evidence.

For a characterization test written over existing code, mutate the code it pins: change a
constant, drop a branch, swap an order. If nothing goes red, the pin is too loose.

## 6. Round-trip guards — before any mapper or adapter work

- **Mapper round-trip:** a fully populated row → `mapToEntity` → `mapFromEntity`; assert every
  column survives; a **completeness assertion** (every column the mapper writes appears in the
  fixture, so a new column cannot dodge the test); **bidirectional iteration**.
- **Setter round-trip:** every scalar setter gets a **distinct** value; booleans opposite to
  their defaults.

## 7. Probes for "unverifiable" paths

Before recording a path as unverifiable, check whether the risky step (send, write, delete) is
**separable** from the step under test. It usually is:

- **Render probes** — message builders that return strings: build them for real rows, assert
  the output, send nothing.
- **Rolled-back writes** (§4).
- **Unreachable pages** — reproduce the controller's exact expression in a CLI probe against
  real rows, and record that the page itself could not be reached and why.

## 8. Audit greps — after a family's slices land, run twice

Match the **shape of the expression**, never a list of names you expect. Derive one grep per
trap in `trap-catalog.md` that applies, include templates and views, and run the set twice: the
second pass has found sites the first missed, every time it was tried.

After a deletion: `composer dump-autoload`, zero classmap entries for the deleted namespace,
`class_exists()` false for each deleted class, and the CLI still boots (`./run` with no
arguments).

## The `Verified:` section

```
Verified:
- suite: 412 tests, 0 failures (base: 398, 0) at PHP 7.4
- lint: 6 files, PHP 7.4
- ratchet: ConstructionRatchetTest ceilings lowered 14 → 12
- mutation: RatingFinderTest red with the order clause removed (assertSame on ids)
- page sweep: 9 pages, 9 identical to base
- parity: SKIP — no frozen ratings in the local database
- schema guard: clean
```
