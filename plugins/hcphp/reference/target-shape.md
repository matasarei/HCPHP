# Target shape — what a refactored HCPHP application looks like

The shape every refactoring slice moves toward. It is the one a production copy reached after a
year of draining god-classes, expressed in HCPHP's own terms. Read the application's own
`CLAUDE.md` first: where it differs from this file, the application wins, and the plan says so.

## Where code lives

Domain code lives in bundles, `application/lib/<Name>Bundle/`. HCPHP's `UserBundle` is the
reference layout:

| Directory | Holds | Never holds |
|---|---|---|
| `Service/<Name>Service.php` | **A thin facade**: the public API controllers, forms and templates already call. Each method is a one-line delegation or a few lines of glue | SQL, a cache, row → entity hydration, page/limit/sort translation, `Globals::` reads, spreadsheets, notifier calls |
| `Repository/` | Reads and writes that **return entities**, never rows. Paging, counts, eviction of any cache | Domain decisions |
| `Query/` | SQL: conditions, joins, order — when a repository needs more than `core\Repository` gives | Anything but SQL |
| `Mapper/` | Row ↔ entity (`core\MapperInterface`) | Queries |
| `Manager/` | Domain logic, **one responsibility per class**: form apply, field sync, diff building, dispatchers, finders | Wiring itself up |
| `Entity/` | State and accessors | Persistence, requests |
| `Form/` | Form builders and their factories | Saving |
| `Export/` | Spreadsheets | |
| `Access/` | Who may see or change what | |
| `Exception/` | The bundle's own exception types | |

`application/controllers`, `templates`, `views`, `events` and `commands` are the **outer layer**.
They take services from the container. They hold no domain logic.

Why the facades stay: callers keep working unchanged, so a family can be drained one
responsibility at a time without touching its call sites. New code calls Repositories and
Managers directly. Migrating every legacy caller onto them is a non-goal.

## How things are built

**Constructor injection.** Every collaborator is a **required** constructor parameter. A service
or manager:

- never calls `new` on a collaborator;
- never calls `Application::getContainer()`;
- has no `?? new` fallback and no lazy `$this->x ?? $this->x = new X()` seam;
- holds no per-call state between calls — a shared instance must not remember the last `find()`.

**Registration.** In HCPHP, services are registered in `application/events/Init.php` with
`$container->setFactory('<id>', function ($container) { return new X($container->get('<dep>')); })`.
Factories are lazy: nothing is built until first `get()`, and the result is shared afterwards.
A copy may instead give each bundle an `initialize(Container)` method called from `Init.php`; the
census reports which. **Match the copy.** Never mix the two styles in one application.

Service ids are snake_case strings, `<kind>_<name>`: `repository_user`, `user_manager`,
`auth_checker`. Find the copy's convention in `Init.php` and follow it.

**Order matters** where a factory resolves another service eagerly. A comment beside each
registration says why it sits where it does.

**Composition roots** are the only places that look services up:

- controllers, through the `Container` their constructor receives (`core\Controller`);
- templates, views, commands and events, through `Application::getContainer()`.

**A copy without `setFactory()`** (only `get`/`set`) cannot express lazy wiring. Porting the lazy
container from HCPHP is slice **P0**, through `/hcphp:port`, before any DI slice.

## What "done" means for one service

1. Every public method is a one-line delegation or a few lines of glue.
2. It holds no identity cache, no hydration, no page/limit/sort translation, no `Globals::`
   read, no spreadsheet, no notifier call and no SQL string.
3. Its constructor lists every collaborator, with no defaults. It contains no `new` of a
   collaborator, no `getContainer()` and no lazy seam. It is registered in `Init.php` (or its
   bundle), and nothing outside the wiring writes `new XService(`.
4. It holds no per-call state.
5. Its docblock says what it is — *"Facade kept for legacy callers; logic lives in …"* — and it
   carries no `@deprecated` unless a replacement is named.

## What keeps it in place

Architecture held by memory decays. These are **tests**, generated from `templates/`:

| Test | Fails on |
|---|---|
| `ConstructionRatchetTest` | A new `new XService(` / `new XRepository(` outside the wiring, counted per class. Ceilings only go down; lower one in the PR that removes a construction |
| `<Bundle>WiringTest` | A service the wiring no longer registers, or registers eagerly |
| Each extracted class's own test | The class misbehaving once the facade stops delegating to it |

A small model cannot hold the architecture in context. A red ratchet tells it exactly what it
broke and where. That is the point of writing the rules as tests.

## Size

Not a hard limit, a direction: a class over ~300 lines or a method over ~50 is a candidate for a
slice, and the census lists them. A ratchet on the largest sizes, lowered as slices land, keeps
them from growing back.

## HCPHP conventions that override generic advice

- **No `declare(strict_types=1)`**, in any file. `Globals::filter()` is a deliberate coercion
  contract every controller depends on; strict types in one file changes what it receives.
- **Line endings are per file.** Many files are CRLF and there is no `.gitattributes`. Check
  `file <path>` and preserve what is there, or the diff becomes the whole file.
- **`DateTime` (mutable) in DynamicDB** — match the file you are in.
- **Private helpers** are `_prefixed` in older core files and not in newer ones. Match the file.
- **The floor PHP is the copy's runtime**, not the manifest's. Lint and test at it.
- **Templates do not auto-escape** in HCPHP: `{{$var}}` is raw. A copy may differ — read its
  `Template`. Moving template code never changes what is escaped.
