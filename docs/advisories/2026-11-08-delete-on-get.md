# ADV-2026-005 — Records are deleted by a plain GET, with no CSRF token

**Severity:** high (a link, image or redirect deletes data as any logged-in editor)
**Fixed in HCPHP:** `application/controllers/records.php`, `application/views/records/view.php`,
`application/templates/dynamic_db/table_items.php`
**Fixed in HCPHP by:** `98a63bc` (2026-11-08)
**Affects:** any copy whose delete action runs on a GET, or is reached by an `<a href>` link

---

## What was wrong

```php
// application/controllers/records.php
public function actionDelete(string $id): ?Response
{
    if (!$this->authChecker->checkCapability('edit_records')) {
        return new Response('Access denied', Response::STATUS_FORBIDDEN);
    }

    $entity = $this->repository->get($id);
    $this->entityManager->delete($entity);
```

```html
<!-- application/views/records/view.php, application/templates/dynamic_db/table_items.php -->
<a href="/records/{{escape|$entity->id}}/delete" class="btn btn-outline-danger">Delete</a>
```

The capability check was the only guard. The request method was never checked, and no CSRF
token was asked for. `Html\Form\Form` verifies a token, but this action used no form.

## Impact

Any page the editor's browser loads can delete records with the editor's session. An image
tag on another site is enough, as is a link in an email or a redirect:

```html
<img src="https://<host>/records/12/delete/">
```

`SameSite=Lax` (ADV-2026-004) does **not** stop it. Lax still sends the cookie on a top-level
GET navigation, and a link or a redirect is exactly that. Record ids are sequential, so one
page can loop over them and delete every record.

The same shape anywhere else is the same hole: an action that writes, deletes, sends or
changes a setting when it is reached by a link.

## How to tell if you are affected

```bash
# links to a delete (or any state-changing) action
grep -rnE '<a [^>]*href="[^"]*/(delete|remove)/?"' application/views application/templates
# actions that delete with no method or token check — read each hit
grep -rnE 'function action[A-Za-z]*(Delete|Remove)' application/controllers
```

Runtime check, on a local instance only, logged in as an editor:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -b <cookie jar> http://localhost:<port>/records/<id>/delete/
```

If you get a redirect and the record is gone, the copy is affected. A fixed copy answers `405`.

**Markers.** The hcphp plugin's `drift.sh` reads these. Paths are relative to the application
root. A copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/views` matches `<a [^>]*href="[^"]*/delete/?"`
- defective: `application/templates` matches `<a [^>]*href="[^"]*/delete/?"`
- fixed: `application/controllers/records.php` matches `Csrf::isValid\(Globals::post\(`
- fixed: `application/templates/dynamic_db/table_items.php` matches `<form method="post" action="/records/`

## The fix

The action refuses anything but a POST that carries the session's form token. It reads the
token from `$_POST` only:

```php
if (!Globals::post()) {
    return new Response('Method not allowed', Response::STATUS_METHOD_NOT_ALLOWED);
}

if (!Csrf::isValid(Globals::post(Form::KEY_SESSION))) {
    return new Response('Invalid form token', Response::STATUS_FORBIDDEN);
}
```

Each Delete link becomes a small form. It uses the same field name as `Html\Form\Form`, so
`Csrf` has a single token:

```php
<form method="post" action="/records/{{escape|$entity->id}}/delete/" class="btn-group">
    <input type="hidden" name="<?= Form::KEY_SESSION; ?>" value="<?= Template::escape(Csrf::getToken()); ?>">
    <button type="submit" class="btn btn-outline-danger">Delete</button>
</form>
```

`Response::STATUS_METHOD_NOT_ALLOWED` (405) is new in `core\Response`.

## Porting checklist

- [ ] Find every action that changes state, not only `actionDelete`. Grep the controllers for
      `->save(`, `->remove(`, `->delete(` and `->update(`, and the views for `<a href` to each
      such URL. The hcphp plugin's `scan.sh` lists them as `GET-STATE` candidates.
- [ ] Guard each one in the action itself: the method first, then the token. Hiding or
      restyling the link protects nothing.
- [ ] Read the token from `$_POST` (`Globals::post()`), not `Globals::optional()`. The latter
      reads `$_REQUEST`, which also takes the query string.
- [ ] Turn every link to the action into a form with the token. A link left behind now answers
      `405`, which a person notices, rather than failing silently.
- [ ] Your copy must already have `core\Csrf` (HCPHP `b49f456`). If its form token is still the
      session id, port that first.
- [ ] Copy `tests/Unit/RecordsDeleteTest.php`. It covers four cases: a GET, a POST without the
      token, a POST with a wrong token, and a POST with the right one. The redirect after a
      delete exits the process, so the last test stops the action with a sentinel exception
      thrown from the repository mock.
- [ ] On a local instance, check each guarded action with GET, with no token and with a wrong
      token: each must leave the data unchanged.

## Notes

- `actionDelete` still passes `null` to `EntityManager::delete()` when the id does not exist,
  which is a `TypeError` and a 500. That is a robustness bug, not a security one, and is not
  part of this fix.

## Status

Fixed in HCPHP in `98a63bc` (2026-11-08). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-005` there, which checks for the defect by the markers above
and records the result in that repository.
