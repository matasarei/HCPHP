# Security scan — what each `scan.sh` candidate means, and how to settle it

`scan.sh` prints **candidates**: places a grep matched. A candidate becomes a **finding** only
when the code read by hand confirms it, and, where it can be done safely on the local instance,
a probe shows it. Each finding is reported with its severity, the `path:line` and the quoted line
that prove it, and the probe's result or the reason there was none. A candidate that does not
hold is dismissed with the line that answers it. **Never probe anything but the local instance.**

Severity: **critical** — code execution, authentication bypass, mass data loss; **high** —
injection reachable by a user, another user's data, account takeover; **medium** — needs an
unusual precondition or a logged-in victim; **low** — defence in depth.

HCPHP facts that decide many of these: templates do **not** auto-escape (`{{$var}}` is raw;
`{{escape|$var}}` escapes); `Xml::tag()` escapes attributes but **not content**;
`Html\Form\Form::getData()` verifies the CSRF token; `AuthChecker::checkCapability()` is the
permission check; `Globals::filter()` coerces types. A copy may have changed any of these — read
its version before relying on one.

| Item | Confirm when | Dismiss when | Probe (local only) |
|---|---|---|---|
| **ECHO-RAW** | the echoed value can hold user input (a record field, a request parameter, a name) and reaches HTML unescaped | the value is markup built by code (`{{$content}}`, `{{$form}}`), a constant, or escaped upstream — show where | store `"><script>alert(1)</script>` in the field, view source |
| **TAG-CONTENT** | request data or record text is passed as `Xml::tag()` **content** | it is escaped first (`Xml::escape`, `htmlspecialchars`) | as ECHO-RAW |
| **CSRF** | a POST form whose handler changes state and verifies no token | the form is built by `Html\Form\Form` elsewhere, or its handler checks `Csrf::isValid()` | submit the form from a page on another origin with the session cookie |
| **GET-STATE** | the action writes, deletes or sends on a GET — a link, an image or a redirect can trigger it for a logged-in user | the write is behind a verified form, a token or a method check the grep missed — quote it | `curl -b <cookie jar> <url>` with GET, then check the row |
| **AUTHZ** | an action reachable by URL acts without checking the capability for that action | the controller checks in a constructor or a shared before-hook — quote it — or the action is public by design | call it as a user without the capability |
| **IDOR** | an id from the request loads an object that is then shown or changed without checking it belongs to the caller | ownership or a capability that covers all objects is checked — quote it | as user A, request user B's id |
| **LIKE-RAW** | user input goes into a LIKE pattern unescaped, so `%` and `_` act as wildcards | the input is escaped (`Like::contains()`, `Like::escape()`), or wildcard search is intended and scoped | search for `%` and compare with the results for a literal |
| **SQL-INTERP** | a variable reaches the SQL text rather than a bound parameter, and any caller can make it hold user input | the value is an internal identifier from config or code (a table name), whitelisted — show the whitelist | a quote in the input; an SQL error or changed results confirms |
| **UPLOAD** | the stored name or extension comes from the client, the type is taken from `$_FILES['type']`, or the file lands where the web server can execute it | content is validated (finfo, deny/allow lists), the name is generated, the directory is denied to the web server | ADV-2026-002's probe: plant a `.php` in the upload directory, request it |
| **DANGEROUS** | `eval`, `extract` on request data, `unserialize` on anything a user can influence, `assert` with a string, `create_function` | `unserialize` with `allowed_classes` restricted, `extract` on internal data only (the template engine's own) — quote it | none needed: confirmed by reading |
| **SHELL** | a shell call whose arguments include any outside value | the arguments are constants, or each is passed through `escapeshellarg()` | none: confirm by reading |
| **SECRET** | a real credential in a tracked (or about-to-be-tracked) file | a placeholder, a sample, or a file the ignore rules cover — `git check-ignore -v` | none: **never print the value**, only the file and key |
| **ERROR-LEAK** | an exception message, stack trace or SQL reaches the HTTP response | it is written to a log, or printed only in CLI mode — quote the guard | trigger the error on the local instance, read the body |
| **FORMULA** | user-controlled text is written to a spreadsheet or CSV cell without forcing a string type, so `=`, `+`, `-`, `@` execute on open | the cell is set explicitly as a string (`setCellValueExplicit(..., DataType::TYPE_STRING)`) or the value is prefixed | export a record whose name is `=1+1`, open the file |
| **COOKIE** | a cookie or the session cookie is set without `HttpOnly` and `SameSite` (and `Secure` on HTTPS) | options are set — ADV-2026-004's shape | `curl -si` the login page and read `Set-Cookie` |
| **SESSION-REGEN** | the session id is not regenerated at login, while the session carries identity or the CSRF token | identity lives only in a cookie that login rotates **and** nothing session-held matters — rare; say why | read the session cookie before and after logging in on the local instance |

## After the scan

1. **Every candidate gets a verdict**: finding (severity), dismissed (the answering line), or
   unreadable (why). None is left as "probably fine".
2. **Route each finding** (`/hcphp:port` step 4):
   - **only in this application** → its own fix, its own pull request, test-first;
   - **also in HCPHP** — the same code exists in HCPHP's `application/core` or `application/lib`
     → a backport: the fix goes to HCPHP first with a new generic advisory and markers, then
     here by `/hcphp:port`. Check with `grep` in an HCPHP checkout, not by assumption.
3. **Record the scan** in `docs/security/scan-<YYYY-MM-DD>.md` in **this application's** repository:
   the command, the counts per item, every verdict. It is private to the application; nothing
   from it goes into HCPHP except a generic advisory.

## What the scan cannot see

Grep finds shapes. It misses a check that happens somewhere it does not look, and logic errors
with no telltale call — a capability checked for the wrong action, a price taken from the
request. Read the controllers that handle money, permissions and personal data in full, whatever
the counts say, and record that you did.
