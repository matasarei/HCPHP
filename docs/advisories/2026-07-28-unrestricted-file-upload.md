# ADV-2026-002 — Unrestricted file upload leads to remote code execution

**Severity:** critical (remote code execution)
**Fixed in HCPHP:** `application/lib/DynamicDB/Validator/`, `DynamicDB/Mapper/FileMapper.php`,
`DynamicDB/Manager/FileManager.php`, `docker/nginx/default.conf`, `.htaccess`
**Fixed in HCPHP by:** `5b6a705`, `e21fdb0` (2026-07-28)
**Affects:** any copy that derives a stored filename's extension from the uploaded
filename, and stores uploads inside the document root

---

## What was wrong

`FileManager::saveFiles()` built the stored file's extension from the **client-supplied**
filename:

```php
sprintf('shared/dynamicdb/%d/%s.%s', $id, $field->getName(), pathinfo($file->getName(), PATHINFO_EXTENSION))
```

There was no extension allow-list, no MIME check and no size limit. The repository root is the
document root, and nginx executed `location ~ \.php$` with no exclusion for the upload
directory.

## Impact

Verified end to end against a running instance. Uploading `shell.php` through the ordinary
record form produced `shared/dynamicdb/2/file_field.php`, reachable and executed over HTTP:

```
GET /shared/dynamicdb/2/file_field.php  ->  200, "PWNED"
```

Any user with `edit_records` gets arbitrary code execution as the web server user. The upload
form is the only prerequisite.

## How to tell if you are affected

```bash
# 1. is the stored extension taken from the client filename?
grep -rn "PATHINFO_EXTENSION" application/ --include="*.php" | grep -v core/Path.php

# 2. where does move_uploaded_file put things, and is that inside the document root?
grep -rn "move_uploaded_file" application/ --include="*.php"

# 3. can the web server execute what lands there?
grep -n "location" docker/nginx/*.conf
```

Runtime check on a non-production instance — plant a file and try to reach it:

```bash
printf '<?php echo "EXECUTED";' > <upload-dir>/probe.php
curl -s https://<host>/<upload-path>/probe.php   # anything but 403/404 is a finding
```

**Markers** — read by the hcphp plugin's `drift.sh`; paths are relative to the application root,
and a copy whose code matches neither shape is reported as "read by hand", never as fixed:

- defective: `application/lib/DynamicDB/Manager/FileManager.php` matches `^ *pathinfo\(\$file->getName\(\), *PATHINFO_EXTENSION\)`
- fixed: `application/lib/DynamicDB/Validator/FileUploadValidator.php` matches `finfo`
- fixed: `docker/nginx/default.conf` matches `location \^~ /shared/`

## The fix

Four layers. Layer 1 is the fix; the others exist so a mistake in one is not an RCE again.

**1. A validator, at the boundary.** New `DynamicDB\Validator\FileUploadValidator`, called from
`FileMapper::mapToEntity()` — the single place an upload becomes a `File`. It checks, in order:

- extension against a **deny-list** first (`php`, `phtml`, `phar`, `htaccess`, `sh`, `svg`, …),
  so `image.jpg.php` cannot slip past;
- extension against an **allow-list**, so anything unrecognised is refused;
- size against a limit (50 MB default);
- the MIME type **re-detected from the file's own bytes** with `finfo` — `$_FILES['type']` is
  sent by the browser and is never consulted;
- the leading bytes against ELF/PE/Mach-O signatures;
- the **whole file** for `<?php` and `<?=`.

**2. The stored extension comes from the validated file**, lower-cased, never the raw client
suffix.

**3. `FileManager` re-validates** immediately before the move, so a caller that constructs a
`File` by hand still cannot write something executable.

**4. Web-server guards.** Uploads are reached through the download controller, never by URL:

```nginx
# note "^~": it stops nginx testing the regex locations below, so a stored file
# can never fall through to "location ~ \.php$"
location ^~ /shared/ {
    deny all;
    return 404;
}
```

```apache
# before the app rewrite, which lets requests matching a real file through
RewriteRule ^shared/ - [F,L]
```

### Two things not to copy from older validators

Some copies already carry an older `FileUploadValidator`. Its structure is the basis for this
one, but two of its decisions are wrong and must not spread:

- **It allowed `svg`.** SVG executes script when rendered inline, and `Path::isImage()` treats
  it as an image. It is on the deny-list here.
- **It rejected files containing `<script`, `eval(`, `exec(`, `system(` or `base64_decode(`.**
  Ordinary PDFs, every ZIP-based Office document, and any plain-text file discussing code hit
  those constantly. A validator that rejects real documents is one that gets switched off in
  production. Only the checks that mean something are kept: the PHP open tag and executable
  magic bytes.

It also trusted `$file->getType()` — the browser-supplied MIME — before falling back to
`finfo`. That order is backwards; the client value is not evidence.

## Porting checklist

- [ ] Add the validator and its exception, adapted to your namespaces. Review the allow-list
      against what your users actually upload — too strict and someone will disable it.
- [ ] Call it where uploads enter (`FileMapper`, `DocumentRepository`, `ImageRepository`,
      whichever applies) **and** again before `move_uploaded_file()`.
- [ ] Stop deriving the stored extension from the client filename.
- [ ] Add the web-server guard for your upload directory. **The nginx config is baked into the
      image by `ADD` — rebuild it (`docker compose up -d --build nginx`), or the guard silently
      does nothing.** This cost a debugging cycle here; the config looked right and the exploit
      still worked.
- [ ] Copy the tests: `tests/Unit/DynamicDB/FileUploadValidatorTest.php` (23 cases including
      the double extension, the polyglot JPEG and the false-positive guards),
      `FileMapperTest.php` and `FileManagerTest.php`.
- [ ] **Sweep existing storage for planted files** — this fix stops new ones, it does not
      remove what is already there:
      `find <upload-dir> -type f \( -name '*.php*' -o -name '*.sh' -o -name '.htaccess' \)`
- [ ] Verify on a non-production instance with the `curl` check above.

## Notes

- `move_uploaded_file()` only accepts files that genuinely arrived via HTTP POST, which makes
  the storage path untestable. `FileManager` now takes an optional mover callable so tests can
  observe the move without performing it; production behaviour is unchanged.
- `FileManager::deleteFiles()` is still a no-op — its `unlink()` is commented out, so files
  survive record deletion. Fixed in HCPHP by `cf3ea77`; not part of this advisory.
- Uploads still live inside the document root, protected by validator plus server guards. The
  structural fix — a `public/` document root, or storage on a volume outside it — remains open
  and would make the guards unnecessary.

## Status

Fixed in HCPHP in `5b6a705` and `e21fdb0` (2026-07-28). Each application built on HCPHP tracks its own status in its
own repository: run `/hcphp:port ADV-2026-002` there, which checks for the defect by the markers above
and records the result in that repository.

A copy that was affected also records whether its existing storage was swept for planted files.
