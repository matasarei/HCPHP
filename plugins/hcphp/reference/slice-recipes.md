# Slice recipes — what `/hcphp:refactor --implement` executes, one per pull request

Load **only the recipe the slice names**. Each one is: when it applies → what it must not do →
steps → the checks that must all say yes → the commit. A step that cannot be done as written is
a stop, not an improvisation.

Risk classes decide the verification (`verification-kit.md`): **safe** = §1 + §5; **risky** =
§1 + §5 + whichever of §2–§4 the touched code needs. A risky slice with no running app stops.

Every slice, every recipe:
- one branch, one responsibility, one pull request; base = the plan's base branch;
- **zero behaviour change** — a found bug becomes a task file, never an edit in this slice;
- line endings preserved per file; no `declare(strict_types=1)`;
- `slice-check.sh` green before the commit; the commit carries a `Verified:` section.

## P0 — port a missing foundation capability (risky)
**When:** the census reports the copy lacks something later slices need (`Container::setFactory`,
`core\Response`, a CSRF helper). **Not:** a refactor of the capability; it lands as HCPHP has it.
1. Stop and hand over to `/hcphp:port <HCPHP commit>`. P0 is ticked when that PR is merged.

## M — move one model family into a bundle (risky: templates)
**When:** the copy still has `application/models/<family>`. **Not:** any SRP, renaming, typing or
docblock edit — the move must be verbatim, so git's rename-similarity proves it.
1. Census the family: every consumer (`grep -rl` by class name in `application/` and `tests/`,
   templates and views included), every `instanceof`, every class name inside a string.
2. Pin the strings that are data (`trap-catalog.md` §5): a test asserting each class's
   short name / `getModelName()` output. Mutation-check it.
3. `git mv` each file to `application/lib/<Name>Bundle/<Entity|Mapper|Repository|Service>/`.
   Leaves first; the family's core classes last.
4. Add the namespace line and update every consumer **in the same commit**: no alias, no
   dual-namespace window.
5. If the models tree is classmapped, `composer dump-autoload`; confirm the old classes are gone
   (`class_exists()` false).
6. Page sweep every page that renders the family (§2).
**Checks:** rename similarity ≥ 90% for every moved file (`git diff -M --stat`); no logic line
changed (`git diff -M` shows only namespace/use lines); suite green; sweep identical.

## C — retire the legacy base stack (risky)
**When:** M has emptied `application/models`, and `core\Model`, `core\Service`, `core\MapperSQL`
or `core\iMapper` remain. **Not:** a redesign of what the services inherited.
1. For each service extending `core\Service`: copy the inherited members it actually uses into
   it, verbatim, and drop the `extends`. One commit per service.
2. Same for mappers on `MapperSQL` / `iMapper` → `core\MapperInterface`.
3. When nothing extends them: delete the base classes, the classmap entry and any
   `Autoloader::addPath(… 'models')`, in a **pure-deletion** commit. `composer dump-autoload`.
**Checks:** `grep -rnE 'extends +\\?core\\(Service|Model|MapperSQL)\b' application` empty; CLI boots;
suite green; sweep of one page per family identical.

## S0 — characterize (safe)
**When:** before any S1–S5 touches code that has no test pinning it. **Not:** fixing what it
finds — a wrong behaviour is pinned as it is and flagged in the PR.
1. Pick the public methods the next slice will move. For each, write a test through the
   **public entry point** asserting today's output: exact values, exact order, exact SQL where
   the method builds SQL (`tests/Support/RecordingDatabase.php` in HCPHP records it).
2. Use real entities, not anonymous stubs (`trap-catalog.md` §6).
3. Mutation-check each test (§5) and record which mutation turned it red.
**Checks:** new tests green on the unchanged code; each one seen red under a mutation; no
production line changed (`git diff --stat` shows only `tests/`).

## S1 — extract one responsibility verbatim (safe if it does not render, else risky)
**When:** S0 has pinned the method. **Not:** cleaning the body on the way.
1. Create `<Bundle>/Manager/<Responsibility>.php` holding the old body **as it was**. Its
   collaborators become **required constructor parameters**.
2. Register it in the wiring (`setFactory`), lazily.
3. The old method becomes `return $this-><manager>-><method>(...);`. The facade receives the
   manager through its constructor if the facade is already injected; otherwise through the one
   seam the plan names, removed later by S5.
4. Write the new class's **own test**, built from plain doubles, mutation-checked. The S0 pin
   only covers it while the facade still delegates.
**Checks:** S0 tests unchanged and green; the moved body is token-identical to the old one
(diff the two method bodies); the new test was seen red; suite green.

## S2 — storage down into the repository (risky: mapper)
**When:** a service caches entities, hydrates rows, or translates page/limit/sort itself.
1. Round-trip guards first (§6) if the family's mapper has none.
2. Move the cache, hydration or translation into the repository, verbatim.
3. The service method becomes a delegation.
**Checks:** the limit quirks pinned (`-1`, `0`, absent → `trap-catalog.md` §4); parity on real
rows (§3); round-trip green; suite green.

## S3 — request reads out of services (safe)
**When:** a service or manager reads `Globals::` / `$_POST` / `$_GET`.
1. The method takes the input as an array parameter. Inside, each `Globals::optional($k, $d)`
   becomes the same coercion over the array (`Globals::filter($input[$k] ?? $d, $d)`), so types
   arrive exactly as before.
2. The controller reads the request and passes the array.
**Checks:** a test drives the method with a plain array; existing tests green; `grep Globals::`
in the class is empty.

## S4 — side effects out of appliers (risky)
**When:** mail/SMS/message sends sit inside a form applier or after-save. **Not:** queueing or
deferring them — that changes timing and is a behaviour change, out of scope.
1. Extract the sends verbatim into a `<Family>NotificationDispatcher`, called at the same point.
**Checks:** a notifier double asserts the same messages, in the same order, with the same
arguments; nothing is sent during verification.

## S5 — constructor injection (risky: rendering)
**When:** a class builds its collaborators (`new X(`, `X::getInstance()`, `getContainer()`
inside a class), or callers build a service inline.
1. Make each collaborator a required constructor parameter; delete the `?? new` fallbacks.
2. Register it with `setFactory` (lazy); resolve its dependencies from the container.
3. Replace inline `new XService(` at composition roots with a container lookup — controllers
   via their `$container`, templates/commands/events via `Application::getContainer()`.
4. Add the class to `ConstructionRatchetTest` (from `templates/`), or lower its ceiling to the
   new count.
**Checks:** the ratchet green at the lowered ceiling; a boot test resolves every registered id
without error (catches cycles); a page sweep of every page that built the class inline.

## S6 — pure deletion (safe)
**When:** the census shows a method or class with zero callers.
1. Prove zero callers three ways: grep by method name, by magic property (`->total` reaches
   `getTotal()`), and by any interface it satisfies. Strings and templates included.
2. Delete in a commit that deletes and does nothing else.
**Checks:** the three greps quoted in the commit; `git diff --stat` shows only deletions; suite
green; `composer dump-autoload` if a classmapped file went.

## S7 — one online index (risky: schema)
**When:** a measured slow query in a page or command the plan names. Follow `schema-safety.md`.
**Checks:** `schema-guard.sh` clean; `EXPLAIN` before/after in the commit; applied twice and
dropped again locally.

## The commit

```
<recipe id> <family>: <what moved, verb first>

<What was mechanical, with counts. What was NOT mechanical, and why.
What was deliberately left alone.>

Ruling: <a decision the plan did not make> — <why> — <cost if wrong>

Verified:
- <one line per rung run, with numbers; one per rung skipped, with the reason>
```
