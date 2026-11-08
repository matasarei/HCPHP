#!/usr/bin/env bash
# shellcheck disable=SC2016  # backticks in single quotes are SQL identifier quotes
# run.sh — the plugin's own tests: its scripts against fixtures whose answers are known.
#
# Usage: plugins/hcphp/tests/run.sh          (from anywhere)
# Exit status is the number of failed assertions, capped at 1.

set -u

here="$(cd "$(dirname "$0")" && pwd)"
plugin="$(cd "$here/.." && pwd)"
repo_root="$(cd "$plugin/../.." && pwd)"
fixtures="$here/fixtures"
bin="$plugin/bin"

passed=0
failed=0

ok()   { passed=$((passed + 1)); }
fail() { failed=$((failed + 1)); printf 'FAIL %s\n' "$*"; }

# assert_eq <what> <expected> <actual>
assert_eq() {
    if [ "$2" = "$3" ]; then ok; else fail "$1: expected '$2', got '$3'"; fi
}

# assert_contains <what> <needle> <haystack>
assert_contains() {
    case "$3" in
        *"$2"*) ok ;;
        *) fail "$1: '$2' not found" ;;
    esac
}

assert_not_contains() {
    case "$3" in
        *"$2"*) fail "$1: '$2' found, should not be" ;;
        *) ok ;;
    esac
}

# fact <census output> <key> — the value of one "key: value" line.
fact() { printf '%s\n' "$1" | sed -n "s/^$2: //p" | head -1; }

section() { printf -- '-- %s\n' "$1"; }

# ---- census.sh --------------------------------------------------------------------------------

section census
out=$("$bin/census.sh" "$fixtures/census/setfactory")
assert_eq "setFactory container" setFactory "$(fact "$out" container_api)"
assert_not_contains "no P0 when lazy" "PROPOSE P0 — the container" "$out"

out=$("$bin/census.sh" "$fixtures/census/factory")
assert_eq "factory() container" factory "$(fact "$out" container_api)"
assert_not_contains "factory() is lazy enough" "PROPOSE P0 — the container" "$out"

out=$("$bin/census.sh" "$fixtures/census/getset")
assert_eq "get/set-only container" none "$(fact "$out" container_api)"
assert_contains "P0 proposed for get/set" "PROPOSE P0 — the container has no lazy factories" "$out"
assert_eq "models files" 1 "$(fact "$out" models_files)"
assert_contains "M proposed" "PROPOSE M" "$out"
assert_eq "legacy base" 'core\Service' "$(fact "$out" legacy_base)"
assert_contains "C proposed" "PROPOSE C" "$out"
assert_eq "inline construction, wiring excluded" 2 "$(fact "$out" inline_construction)"
assert_eq "static bundle init(Container) is wiring" bundle-init "$(fact "$out" registration)"
assert_eq "getInstance calls" 1 "$(fact "$out" get_instance_calls)"
assert_eq "floor from platform pin" 7.4 "$(fact "$out" php_floor)"
assert_eq "lib classes" 2 "$(fact "$out" lib_classes)"
# FooBundle is named only as a namespace in a test: the documented over-count calls it tested.
assert_eq "a class named anywhere in a test counts" 0 "$(fact "$out" lib_classes_untested)"

out=$("$bin/census.sh" "$repo_root")
assert_eq "HCPHP itself is lazy" setFactory "$(fact "$out" container_api)"
assert_eq "HCPHP has no models" 0 "$(fact "$out" models_files)"

"$bin/census.sh" "$fixtures" >/dev/null 2>&1
assert_eq "not an application → exit 2" 2 "$?"

# ---- throwaway repositories -------------------------------------------------------------------

scratch=$(mktemp -d "${TMPDIR:-/tmp}/hcphp-plugin-tests.XXXXXX")
trap 'rm -rf "$scratch"' EXIT

# A stub php: `php -l <file>` fails on a file containing SYNTAX_ERROR, passes otherwise.
mkdir -p "$scratch/stub"
cat > "$scratch/stub/php" <<'STUB'
#!/usr/bin/env bash
if [ "${1:-}" = "-l" ]; then
    if grep -q SYNTAX_ERROR "$2"; then echo "PHP Parse error: in $2"; exit 255; fi
    echo "No syntax errors detected in $2"; exit 0
fi
exit 0
STUB
chmod +x "$scratch/stub/php"
stub_exec="env PATH=$scratch/stub:\$PATH"

# new_repo <name>: a repository whose base commit holds an LF file, a CRLF file and a migration.
new_repo() {
    local dir="$scratch/$1"
    mkdir -p "$dir/application" "$dir/migrations"
    printf '<?php\n\nclass A\n{\n}\n' > "$dir/application/a.php"
    printf '<?php\r\n\r\nclass B\r\n{\r\n}\r\n' > "$dir/application/b.php"
    printf 'ALTER TABLE `t` ADD INDEX `i` (`c`), ALGORITHM = INPLACE, LOCK = NONE;\n' > "$dir/migrations/old.sql"
    git -C "$dir" init -q
    # The tests compare against "master" whatever init.defaultBranch says on this machine.
    git -C "$dir" symbolic-ref HEAD refs/heads/master
    git -C "$dir" -c user.name=t -c user.email=t@example.invalid add -A
    git -C "$dir" -c user.name=t -c user.email=t@example.invalid commit -qm base
    git -C "$dir" checkout -qb slice
    echo "$dir"
}

# ---- schema-guard.sh --------------------------------------------------------------------------

section schema-guard
out=$("$bin/schema-guard.sh" --files "$fixtures/schema/pass/"*.sql)
assert_eq "online indexes pass" PASS "$out"
for f in "$fixtures"/schema/fail/*.sql "$fixtures/schema/fail/application/config/dynamicdb.json"; do
    "$bin/schema-guard.sh" --files "$f" >/dev/null
    status=$?
    assert_eq "rejects $(basename "$f")" 1 "$status"
done
out=$("$bin/schema-guard.sh" --files "$fixtures/schema/fail/index_with_column.sql")
assert_contains "names the extra clause" "ADD COLUMN" "$out"

repo=$(new_repo guard)
cp "$fixtures/schema/pass/"*.sql "$repo/migrations/"
out=$("$bin/schema-guard.sh" --base master "$repo")
assert_eq "git mode: new index migration passes" PASS "$out"
printf 'ALTER TABLE `t` DROP INDEX `i`;\n' >> "$repo/migrations/old.sql"
mkdir -p "$repo/application/config" && printf '{}\n' > "$repo/application/config/dynamicdb.json"
out=$("$bin/schema-guard.sh" --base master "$repo")
assert_contains "git mode: edited migration fails" "old.sql: existing migration modified" "$out"
assert_contains "git mode: untracked dynamicdb.json fails" "dynamicdb.json: DynamicDB" "$out"
assert_contains "git mode: verdict" "FAIL (2)" "$out"
mkdir -p "$repo/tests/fixtures" && printf 'DELETE FROM `t`;\n' > "$repo/tests/fixtures/seed.sql"
out=$("$bin/schema-guard.sh" --base master "$repo")
assert_contains "git mode: test fixtures are not migrations" "FAIL (2)" "$out"
# The skip is for test data, not for any path with a tests/ in it.
repo=$(new_repo nested)
mkdir -p "$repo/migrations/tests" "$repo/tests/fixtures/migrations"
printf 'ALTER TABLE `t` DROP INDEX `i`;\n' > "$repo/migrations/tests/drop.sql"
printf 'DELETE FROM `t`;\n' > "$repo/tests/fixtures/migrations/seed.sql"
out=$("$bin/schema-guard.sh" --base master "$repo")
assert_contains "a migrations/tests/ directory is still checked" "migrations/tests/drop.sql" "$out"
assert_not_contains "tests/fixtures/migrations/ is still test data" "seed.sql" "$out"

# No --base and no origin/HEAD (a clone that never set it): the committed change is still
# compared with the default branch, never with HEAD — that would check only the dirty tree.
repo=$(new_repo nobase)
printf 'ALTER TABLE `t` DROP INDEX `i`;\n' > "$repo/migrations/new.sql"
git -C "$repo" -c user.name=t -c user.email=t@example.invalid add -A
git -C "$repo" -c user.name=t -c user.email=t@example.invalid commit -qm drop
out=$("$bin/schema-guard.sh" "$repo" 2>&1)
assert_contains "no origin/HEAD: a committed DROP is still found" "FAIL (1)" "$out"
out=$("$bin/slice-check.sh" --exec "$stub_exec" --test true "$repo" 2>&1)
assert_contains "slice-check, no origin/HEAD: a committed DROP is still found" "FAIL schema" "$out"
git -C "$repo" branch -qm master trunk
"$bin/schema-guard.sh" "$repo" >/dev/null 2>&1
assert_eq "no default branch at all → exit 2, not a PASS" 2 "$?"
"$bin/slice-check.sh" --exec "$stub_exec" --test true "$repo" >/dev/null 2>&1
assert_eq "slice-check, no default branch at all → exit 2" 2 "$?"

# ---- slice-check.sh ---------------------------------------------------------------------------

section slice-check
repo=$(new_repo clean)
printf '<?php\n\nclass A\n{\n    public function b(): int\n    {\n        return 1;\n    }\n}\n' > "$repo/application/a.php"
out=$("$bin/slice-check.sh" --exec "$stub_exec" --test "echo OK '(3 tests, 3 assertions)'" --base master "$repo")
assert_eq "clean slice: verdict" PASS "$(printf '%s\n' "$out" | tail -1)"
assert_contains "clean slice: lint counted" "PASS lint (1 files)" "$out"
assert_contains "clean slice: suite summary quoted" "PASS suite: OK (3 tests, 3 assertions)" "$out"

repo=$(new_repo dirty)
printf '<?php\n\ndeclare(strict_types=1);\n\nclass A\n{\n}\n' > "$repo/application/a.php"
printf '<?php\n\nclass B\n{\n}\n' > "$repo/application/b.php"
printf '<?php\nSYNTAX_ERROR\n' > "$repo/application/c.php"
printf 'ALTER TABLE `t` DROP COLUMN `c`;\n' > "$repo/migrations/new.sql"
out=$("$bin/slice-check.sh" --exec "$stub_exec" --test "echo 'FAILURES! Tests: 3, Failures: 1.'; false" --base master "$repo")
status=$?
assert_eq "dirty slice: exit 1" 1 "$status"
assert_contains "dirty slice: lint" "FAIL lint: application/c.php" "$out"
assert_contains "dirty slice: strict types" "FAIL strict-types" "$out"
assert_contains "dirty slice: line endings" "application/b.php(crlf→lf)" "$out"
assert_contains "dirty slice: schema" "FAIL schema" "$out"
assert_contains "dirty slice: suite" "FAIL suite (exit 1): FAILURES! Tests: 3, Failures: 1." "$out"
assert_eq "dirty slice: verdict" "FAIL (5)" "$(printf '%s\n' "$out" | tail -1)"

"$bin/slice-check.sh" --exec "$stub_exec" --base master "$repo" >/dev/null 2>&1
assert_eq "no test command → exit 2" 2 "$?"

# ---- drift.sh ---------------------------------------------------------------------------------

section drift
adv="$fixtures/drift/advisories"
out=$("$bin/drift.sh" --advisories "$adv" "$fixtures/drift/fixed")
assert_contains "fixed shape → fixed" "ADV-2026-901 fixed" "$out"
out=$("$bin/drift.sh" --advisories "$adv" "$fixtures/drift/defective")
assert_contains "defective shape → defective, with the line" "ADV-2026-901 defective — application/core/Thing.php:6:" "$out"
out=$("$bin/drift.sh" --advisories "$adv" "$fixtures/drift/diverged")
assert_contains "neither shape → read by hand" "ADV-2026-901 read-by-hand — application/core/Thing.php does not match the fixed shape" "$out"
# Built here: an application/ with nothing in it is an empty directory, which git does not keep.
mkdir -p "$scratch/absent/application"
out=$("$bin/drift.sh" --advisories "$adv" "$scratch/absent")
assert_contains "missing file → read by hand" "ADV-2026-901 read-by-hand — application/core/Thing.php is absent" "$out"
assert_contains "no markers → read by hand, never fixed" "ADV-2026-902 read-by-hand — the advisory has no markers" "$out"
"$bin/drift.sh" --advisories "$adv" "$fixtures/drift/fixed" >/dev/null
assert_eq "anything not fixed → exit 1" 1 "$?"
out=$("$bin/drift.sh" --advisories "$repo_root/docs/advisories" "$repo_root")
assert_contains "HCPHP's own advisories hold on HCPHP" "fixed 4, defective 0, read-by-hand 0" "$out"
for a in "$repo_root"/docs/advisories/*.md; do
    n=$(grep -cE '^- (defective|fixed): `' "$a")
    assert_eq "$(basename "$a") has markers" yes "$([ "$n" -ge 2 ] && echo yes || echo "no ($n)")"
done

# ---- scan.sh ----------------------------------------------------------------------------------

section scan
out=$("$bin/scan.sh" "$fixtures/scan" 2>&1)
for item in ECHO-RAW TAG-CONTENT CSRF GET-STATE AUTHZ IDOR LIKE-RAW SQL-INTERP UPLOAD DANGEROUS SHELL SECRET ERROR-LEAK FORMULA COOKIE SESSION-REGEN; do
    n=$(printf '%s\n' "$out" | sed -n "s/^$item \([0-9][0-9]*\)$/\1/p")
    assert_eq "scan finds the planted $item" yes "$([ "${n:-0}" -ge 1 ] && echo yes || echo "no (${n:-none})")"
done
assert_contains "GET-STATE names the action" "item.php:5: actionSave writes" "$out"
assert_not_contains "a secret's value is never printed" "hunter2" "$out"
assert_contains "the secret's file and key are" 'SECRET application/config/secrets.json:2:    "password": "<redacted>"' "$out"
# No POST form and no controller: GNU xargs runs grep once on empty stdin, which reports
# "(standard input)" as a file — a candidate that does not exist.
bare="$scratch/bare"
mkdir -p "$bare/application/lib"
printf '<?php\nclass Bare {}\n' > "$bare/application/lib/Bare.php"
out=$("$bin/scan.sh" "$bare" 2>&1)
assert_not_contains "no forms, no controllers: no phantom candidate" "(standard" "$out"
assert_contains "no forms: CSRF 0" "CSRF 0" "$out"
assert_contains "no controllers: AUTHZ 0" "AUTHZ 0" "$out"

# ---- install.sh and the size budgets ----------------------------------------------------------

section install
app="$scratch/app"
mkdir -p "$app"
out=$("$plugin/install.sh" --opencode "$app" 2>&1)
assert_eq "install exits 0" 0 "$?"
installed="$(cd "$app" && pwd)/.opencode"
for name in port refactor-plan refactor-run; do
    skill="$installed/skills/hcphp-$name/SKILL.md"
    assert_eq "hcphp-$name installed" yes "$([ -f "$skill" ] && echo yes || echo no)"
    assert_eq "hcphp-$name renamed" "name: hcphp-$name" "$(sed -n 2p "$skill")"
    assert_contains "hcphp-$name points at the plugin root" "The plugin root is \`$installed/hcphp\`." "$(cat "$skill")"
    assert_not_contains "hcphp-$name has no Claude Code paths" "two directories above this skill's base directory" "$(cat "$skill")"
    assert_not_contains "hcphp-$name has no /hcphp: references" "/hcphp:" "$(cat "$skill")"
done
assert_eq "scripts executable" yes "$([ -x "$installed/hcphp/bin/census.sh" ] && echo yes || echo no)"
assert_eq "advisories copied" yes "$([ -n "$(ls "$installed/hcphp/docs/advisories/"*.md 2>/dev/null)" ] && echo yes || echo no)"
assert_eq "PORTING.md two directories above them" yes "$([ -f "$installed/hcphp/PORTING.md" ] && echo yes || echo no)"
out=$("$installed/hcphp/bin/drift.sh" "$repo_root" 2>&1 | head -1)
assert_contains "installed drift.sh finds its advisories" "$installed/hcphp/docs/advisories" "$out"
mkdir -p "$installed/skills/hcphp-retired" "$installed/skills/other"
"$plugin/install.sh" --opencode "$app" >/dev/null 2>&1
assert_eq "a retired hcphp skill is removed" no "$([ -d "$installed/skills/hcphp-retired" ] && echo yes || echo no)"
assert_eq "someone else's skill is kept" yes "$([ -d "$installed/skills/other" ] && echo yes || echo no)"
"$plugin/install.sh" >/dev/null 2>&1
assert_eq "no --opencode → exit 2" 2 "$?"

# A small model reads a skill and its references beside the code; reference/small-model.md.
section budgets
for f in "$plugin"/reference/*.md; do
    lines=$(awk 'END { print NR }' "$f")
    assert_eq "$(basename "$f") ≤ 200 lines" yes "$([ "$lines" -le 200 ] && echo yes || echo "no ($lines)")"
done
for f in "$plugin"/skills/*/SKILL.md; do
    lines=$(awk 'END { print NR }' "$f")
    assert_eq "$(basename "$(dirname "$f")") SKILL.md ≤ 250 lines" yes "$([ "$lines" -le 250 ] && echo yes || echo "no ($lines)")"
done

# ---- PHP templates ----------------------------------------------------------------------------

# The host's php when there is one (CI sets up the floor version); otherwise the floor in a
# container; otherwise these are skipped, and the summary says so.
section templates
skipped=0
if command -v php >/dev/null 2>&1; then
    php_run() { (cd "$repo_root" && "$@"); }
elif command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    php_run() { docker run --rm -v "$repo_root":/app -w /app php:7.4-cli "$@"; }
else
    php_run() { return 127; }
fi

if php_run php -v >/dev/null 2>&1; then
    for f in "$plugin"/templates/*.php "$here"/php/*.php; do
        relative="${f#"$repo_root"/}"
        out=$(php_run php -l "$relative" 2>&1)
        assert_contains "lint $(basename "$f")" "No syntax errors" "$out"
    done
    out=$(grep -l 'declare(strict_types' "$plugin"/templates/*.php || true)
    assert_eq "no template declares strict_types" "" "$out"

    if [ -f "$repo_root/application/lib/vendor/bin/phpunit" ]; then
        out=$(php_run php application/lib/vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php \
            --fail-on-warning --fail-on-risky plugins/hcphp/tests/php 2>&1)
        assert_contains "template tests" "OK (" "$out"
        case "$out" in *"OK ("*) ;; *) printf '%s\n' "$out" | tail -20 ;; esac
    else
        skipped=$((skipped + 1)); echo "SKIP template tests: run composer install first"
    fi
else
    skipped=$((skipped + 1)); echo "SKIP templates: neither php nor docker is available"
fi

# ---- summary ----------------------------------------------------------------------------------

printf '%d passed, %d failed, %d skipped\n' "$passed" "$failed" "$skipped"
[ "$failed" -eq 0 ]
