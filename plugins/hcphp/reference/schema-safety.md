# Schema safety — the one database change a refactoring may make

A refactoring changes code, not data. The only schema change a slice may carry is a
**secondary index**, added online, justified by a measured slow query. Everything else — a
column, a type, a default, a rename, a drop, a table — is out of scope for these skills, however
small it looks. A wrong `ALTER` against records of record is not a bug you fix forward.

`bin/schema-guard.sh` enforces this mechanically on every slice's diff.

## What is allowed

```sql
-- migrations/<Y_m_d>_<what>_index.sql
-- Why: <the query, the page or command that runs it, and the measurement>
ALTER TABLE `calendar_event`
    ADD INDEX `idx_event_type_start` (`type`, `start_time`),
    ALGORITHM = INPLACE, LOCK = NONE;
```

or `CREATE INDEX … ON … ALGORITHM = INPLACE LOCK = NONE`.

- **`ALGORITHM = INPLACE, LOCK = NONE` is required.** InnoDB builds a secondary index online by
  default; stating it makes the server **refuse at once** instead of silently taking a table
  lock when it cannot. A refusal is an answer; a locked production table is an incident.
- **Idempotent.** Applying it twice must be harmless. MariaDB supports
  `ADD INDEX IF NOT EXISTS`; on MySQL, guard it in the runner, or check
  `information_schema.statistics` first. Apply it twice locally and say so in the commit.
- **Reversible.** Drop it again locally and confirm the query plan returns to the old one. The
  commit states the `DROP INDEX` that undoes it.
- **Measured.** `EXPLAIN` before and after, on local data, in the commit body. An index nobody
  measured is a write cost with no proven benefit.
- **One index per migration file, one migration per slice (S7).** Never inside a code slice.

## What the guard rejects

| In the diff | Why |
|---|---|
| Any edit to `application/config/dynamicdb.json` | DynamicDB syncs the schema from it **on every request**; a field removed or renamed there becomes `DROP COLUMN` on the next page load |
| `DROP` anything, `TRUNCATE`, `DELETE`, `UPDATE`, `INSERT` | Data and structure loss, or a data migration — not a refactoring |
| `ADD COLUMN`, `MODIFY`, `CHANGE`, `RENAME`, `ALTER … DEFAULT`, `CONVERT TO CHARACTER SET` | A schema change with a behaviour behind it |
| `CREATE TABLE` | A new table is a feature |
| `ADD INDEX` / `CREATE INDEX` without `LOCK = NONE` | May lock the table |
| `ADD UNIQUE` / `ADD PRIMARY KEY` / `ADD FOREIGN KEY` | Constraints can fail on existing data and change write behaviour |
| `ADD FULLTEXT` / `ADD SPATIAL` | Cannot be built with `LOCK = NONE` in all versions; a separate decision |

It reads every `*.sql` file the diff adds or changes, plus PHP files under `migrations/` if the
copy writes migrations in PHP, and the DynamicDB config. Statements are matched case-insensitively
after comments are stripped.

## Things that are not schema changes but behave like them

- **Renaming an entity class or changing `getModelName()`.** Its output may be stored in rows.
  See `trap-catalog.md` §5.
- **Changing a mapper's column list.** A column the mapper stops reading is wiped on the next
  save. Round-trip tests first (`verification-kit.md` §6).
- **Changing a default limit, order or condition key** in a repository. The rows a caller sees
  change with no schema change at all. That is a behaviour change and a separate PR.
- **A bulk script.** Any command that writes across many rows is out of scope for these skills.
  If a refactoring seems to need one, stop: it is a data fix, planned on its own with dry-run by
  default, an expected row count and a recovery path.

## When a slice needs more than an index

It does not belong in the refactoring. The run stops, writes a task file for the change with the
evidence that it is needed, and continues with the next slice that does not depend on it. The
plan marks the blocked slices.
