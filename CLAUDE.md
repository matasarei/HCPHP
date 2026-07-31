# HCPHP — notes for Claude

A small in-house MVC framework (2014). It is a **reference implementation, not a dependency**:
a project built on it takes a copy of the foundation and owns it from then on. There is no
package to require and no version to upgrade, so a change landing here is a reference to port
rather than a release — and a copy that has been diverging for years cannot take a patch blind.

The repository holds the **foundation** — `application/core` and `application/lib`, the part
that gets copied — and a **demo application** — `controllers`, `views`, `templates`, `commands`
and `events` — which exists to show the foundation working and is replaced wholesale in a real
project. Nothing here is production code.

Because a fix has to be carried across by hand, the test suite and the commit messages are the
deliverable as much as the diff: they are what makes a change explicable to whoever ports it.

**Porting a change out of here is [PORTING.md](PORTING.md)** — the procedure, including the
caller audit and the runtime-version check that a file-level copy skips. If you are asked to
update an application built on this framework, follow that file rather than improvising.

## Commands

```shell
# install
docker compose exec fpm composer install

# test
docker run --rm -v "$PWD":/app -w /app php:7.4-cli php application/lib/vendor/bin/phpunit

# lint
docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c \
  "find application index.php -name '*.php' -not -path '*/vendor/*' -print0 \
   | xargs -0 -n1 php -l >/dev/null && php -l run"

# run: http://localhost:8080/
docker compose up -d
```

No build step.

Run everything **inside a container**: the project floor is PHP 7.4 and the host's PHP will not
be. 7.4 is the version most likely to reject what newer ones accept, so it is the one to check
locally; CI runs 7.4 through 8.5 on every push (`.github/workflows/tests.yml`).

`phpunit.xml` turns warnings, notices and stray output into failures. A version that merely
complains fails the build — that is deliberate, and how the 8.1 null-argument and 8.5
`setAccessible()` deprecations were found.

<!-- toolkit:begin family-rules -->

## PHP conventions

### Style

- `declare(strict_types=1);` at the top of every new file.
- **4 spaces**, Unix line endings, no trailing whitespace, no closing `?>`.
- **Strict comparison** — `===` and `!==`. `==` hides type juggling bugs.
- **Type hints and return types** on everything new, including `void`. Nullable types are
  explicit (`?string`), never implied by a `null` default.
- **`DateTimeImmutable`**, not `DateTime` — a mutable date passed into a method and modified
  there is a bug that reproduces only under specific ordering.
- Short array syntax `[]`. Trailing comma on multi-line arrays and argument lists.
- **Names are words, not abbreviations** — `$entityManager`, not `$em`. Interfaces end in
  `Interface`.
- **Return early.** No `else` after a `return`; no assignments inside conditions.
- Comments explain **why**, never what. A comment restating the code is noise that goes stale.
- Prefer composition over inheritance. Keep models thin — business logic belongs in services,
  and services should not hold request-scoped state.

### Database

- **Placeholders for every variable.** Never interpolate into SQL, not even an integer you
  "know" is safe — the next caller will not know it.
- Prefer the project's repository or mapper API over raw SQL. Where raw SQL is genuinely needed,
  it still uses bound parameters.
- Iterate large result sets rather than loading them whole.
- Watch for **N+1 queries** inside loops over user-scale data.

### Security

Treat every one of these as a blocker, not a preference.

- **Escape at output.** Establish which layer escapes and state it in this file. If the
  templates do **not** escape automatically, then every value interpolated into HTML must be
  escaped at the point of output — `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` — and the
  reviewer's default assumption must be that an un-escaped echo is a hole.
- **Escape per context.** HTML body, HTML attribute, URL parameter, JavaScript and CSS each need
  different escaping. Putting an un-encoded value into a `<script>` block or an `href` is an
  injection even when the HTML body would have been fine.
- **CSRF on every state-changing request.** A token bound to the session, verified server-side,
  on every POST/PUT/DELETE. If the framework has no helper, that is a gap to fix once in the
  shared layer, not to work around per form.
- **Authorisation on every entry point.** Check the permission where the action happens, not
  only where the link is drawn. Hiding a button is not access control, and an object id in a URL
  is not proof the caller owns that object.
- **Passwords** via `password_hash()` / `password_verify()`. Never a bare hash, never a
  home-made scheme.
- **Sessions**: regenerate the id on login and privilege change; cookies `HttpOnly`, `Secure`
  and `SameSite`.
- **Uploads**: validate by inspecting content, not the client-supplied name or MIME header.
  Store outside the web root, or in a path that cannot execute. Generate the stored filename —
  never reuse what the user sent.
- **Never** `eval()`, `extract()` on request data, `unserialize()` on anything a user can
  influence, or a shell call built from user input.
- **Secrets live in configuration that is not committed.** Confirm the ignore rules actually
  cover them, and that no credential ever reaches a log or an error page.
- **Errors**: log the detail, show the user nothing but a generic message. Stack traces and SQL
  in a response are a disclosure.
- **Exports carry a formula-injection risk** — a spreadsheet cell starting `=`, `+`, `-` or `@`
  executes on open. Write user-controlled text as an explicit string type.

### Changing data in bulk

Any script that writes across many rows:

- **dry-run by default**, applying only behind an explicit flag;
- **safe to run twice** — the second run is a no-op, not a duplicate;
- **bounded scope** and an **expected-row-count assertion** that aborts when reality disagrees;
- a stated recovery path before it runs.

A wrong `WHERE` clause against records of record is not a bug you fix forward.

### Dependencies

- Commit the lock file alongside the manifest, always in the same change.
- Treat an **end-of-life PHP version as a standing security finding**, not a style note — no
  amount of careful code compensates for an interpreter that no longer receives patches.

<!-- toolkit:end family-rules -->

## Where this project differs from the block above

Verified against the code, not assumed. Follow these over the generic rules.

- **No `declare(strict_types=1)` anywhere**, and do not add it to one file in isolation:
  `Globals::filter()` is a deliberate coercion contract that every controller depends on.
- **61 of 161 tracked PHP files use CRLF**, with no `.gitattributes` to normalise them.
  Check `file <path>` before editing and preserve what is there, or the diff becomes the whole
  file.
- `DateTime` (mutable) is used throughout `DynamicDB`. Match the file.
- Older `core` files prefix private helpers with `_`; newer ones do not. Both are current —
  match the file you are in.
- **PHP 7.4 is EOL** and is still the declared floor, because copies of this foundation are
  running on it — at least one on **7.1**. A known standing finding, not an oversight; raising
  the floor here would emit syntax those deployments cannot parse.

## What is true here

- **Templates do not auto-escape.** `{{$var}}` is raw on purpose — `{{$content}}` and
  `{{$form}}` are rendered markup. Use `{{escape|$value}}`, or `Template::escape()` /
  `Xml::escape()` (`ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8) in PHP.
- **Attributes are escaped, tag content is not.** `Xml::tag()` escapes attributes via
  `prepareAttributes()` but writes `$content` verbatim, because callers compose markup with it.
  Passing user data as content is an injection — see `Textarea` and `Select`.
- **CSRF is handled**: `Html\Form\Form` emits the token and `getData()` verifies it, throwing
  `InvalidFormException`. Hand-rolled forms need `core\Csrf::getToken()` / `Csrf::isValid()`.
- **Authorisation** is `AuthChecker::checkCapability($name, $context)` against
  `config/access.json`, called per controller action.
- **Credentials** live in `application/config/{database,default}.json` — gitignored, with
  `.sample` files committed. The test bootstrap creates `default.json` from the sample when it
  is absent and removes it again.
- **Never hand-edit**: `application/lib/vendor`, `cache/` (compiled templates), `coverage/`,
  `shared/dynamicdb` (uploads).
- **After deploying**: run `php run cache:purge`. Templates are recompiled by mtime, so a deploy
  that preserves timestamps serves the previous build. `DatabaseManager::initialize()` also syncs
  the schema from `config/dynamicdb.json` on **every** request.
