#!/usr/bin/env bash
# slice-check.sh — the yes/no checks every refactoring slice must pass before it is committed.
#
# Usage: slice-check.sh [--exec "<prefix>"] [--test "<command>"] [--base <ref>] [application-root]
#
#   --exec   how PHP runs here, prepended to every PHP command — the application's container at
#            its floor version, e.g. 'docker compose exec -T fpm' or
#            'docker run --rm -v "$PWD":/app -w /app php:7.4-cli'. Empty means the host's php.
#   --test   the full test command, run through --exec, e.g. 'php application/lib/vendor/bin/phpunit'.
#   --base   what the slice is compared with (default: the merge base with origin's HEAD branch,
#            else master or main; none → exit 2).
#
# Without --exec/--test it reads exec.prefix and test from .claude/repo-profile.json (needs jq).
#
# Checks, each printed as "PASS <check> …" or "FAIL <check> …":
#   lint          php -l on every PHP file the slice adds or changes, at the --exec runtime
#   strict-types  no `declare(strict_types=1)` added, where the base has none anywhere
#   line-endings  no changed file switched between CRLF and LF
#   schema        schema-guard.sh: nothing beyond an online index
#   suite         the whole test command exits 0
# The last line is the verdict: "PASS" or "FAIL (<n>)". Exit 0 on PASS, 1 on FAIL, 2 on usage.

set -u

here="$(cd "$(dirname "$0")" && pwd)"
exec_prefix=""
exec_given=0
test_cmd=""
base=""

while [ "$#" -gt 0 ]; do
    case "$1" in
        --exec) exec_prefix="${2-}"; exec_given=1; shift 2 ;;
        --test) test_cmd="${2-}"; shift 2 ;;
        --base) base="${2-}"; shift 2 ;;
        -h|--help) sed -n '2,23p' "$0"; exit 0 ;;
        -*) echo "slice-check: unknown option $1" >&2; exit 2 ;;
        *) break ;;
    esac
done

cd "${1:-.}" || exit 2
git rev-parse --git-dir >/dev/null 2>&1 || { echo "slice-check: not a git repository" >&2; exit 2; }

profile=.claude/repo-profile.json
if [ -f "$profile" ] && command -v jq >/dev/null 2>&1; then
    [ "$exec_given" -eq 0 ] && exec_prefix=$(jq -r '.exec.prefix // ""' "$profile")
    [ -z "$test_cmd" ] && test_cmd=$(jq -r '.test // ""' "$profile")
fi
[ -n "$test_cmd" ] || { echo "slice-check: no test command; pass --test or add one to $profile" >&2; exit 2; }

# default_branch: origin's HEAD branch, else origin's or the local master or main. Empty when none
# exists: comparing with HEAD instead would check only the uncommitted files and still say PASS.
default_branch() {
    local ref
    ref=$(git symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>/dev/null) && { echo "$ref"; return; }
    for ref in refs/remotes/origin/master refs/remotes/origin/main refs/heads/master refs/heads/main; do
        git rev-parse --verify --quiet "$ref^{commit}" >/dev/null && { echo "$ref"; return; }
    done
}

if [ -z "$base" ]; then
    default=$(default_branch)
    [ -n "$default" ] || { echo "slice-check: no default branch (origin/HEAD, master, main); pass --base <ref>" >&2; exit 2; }
    base=$(git merge-base HEAD "$default" 2>/dev/null)
fi
git rev-parse --verify --quiet "$base^{commit}" >/dev/null || { echo "slice-check: unknown base '$base'" >&2; exit 2; }

failures=0
pass() { printf 'PASS %s\n' "$*"; }
fail() { printf 'FAIL %s\n' "$*"; failures=$((failures + 1)); }

# The prefix is a shell fragment from the developer's own profile (it may hold "$PWD"), so it
# is evaluated; the file name is quoted so a path cannot inject.
run() { eval "$exec_prefix $1"; }

# Files the slice adds or changes (not deletions), committed or not, plus untracked ones.
changed=$( { git diff --name-only --diff-filter=ACMR "$base" --; git ls-files --others --exclude-standard; } | sort -u)
changed_php=$(printf '%s\n' "$changed" | grep -E '\.php$|^run$' || true)

# ---- lint ------------------------------------------------------------------------------------
lint_failed=""
lint_count=0
while IFS= read -r file; do
    [ -n "$file" ] && [ -f "$file" ] || continue
    lint_count=$((lint_count + 1))
    if ! output=$(run "php -l $(printf '%q' "$file")" 2>&1); then
        lint_failed="$lint_failed $file"
        printf '%s\n' "$output" | grep -vE '^$' | head -3 | sed 's/^/    /'
    fi
done <<EOF
$changed_php
EOF
if [ -n "$lint_failed" ]; then fail "lint:$lint_failed"; else pass "lint ($lint_count files)"; fi

# ---- strict types ----------------------------------------------------------------------------
if git grep -qE 'declare\(strict_types' "$base" -- '*.php' 2>/dev/null; then
    pass "strict-types (the base already uses it; not enforced)"
else
    added=$( { git diff "$base" -- '*.php'; git ls-files --others --exclude-standard -- '*.php' | while IFS= read -r f; do sed 's/^/+/' "$f"; done; } \
        | grep -E '^\+.*declare\(strict_types' || true)
    if [ -n "$added" ]; then fail "strict-types: declare(strict_types=1) added; this codebase relies on coercion"; else pass "strict-types"; fi
fi

# ---- line endings ----------------------------------------------------------------------------
switched=""
while IFS= read -r file; do
    [ -n "$file" ] && [ -f "$file" ] || continue
    git cat-file -e "$base:$file" 2>/dev/null || continue
    was=lf; now=lf
    git show "$base:$file" | grep -q $'\r' && was=crlf
    grep -q $'\r' "$file" && now=crlf
    [ "$was" = "$now" ] || switched="$switched ${file}(${was}→${now})"
done <<EOF
$changed
EOF
if [ -n "$switched" ]; then fail "line-endings:$switched"; else pass "line-endings"; fi

# ---- schema ----------------------------------------------------------------------------------
if guard=$("$here/schema-guard.sh" --base "$base" . 2>&1); then
    pass "schema"
else
    printf '%s\n' "$guard" | grep '^FAIL ' | sed 's/^/    /'
    fail "schema"
fi

# ---- suite -----------------------------------------------------------------------------------
suite_output=$(run "$test_cmd" 2>&1)
suite_status=$?
summary=$(printf '%s\n' "$suite_output" | grep -E '^(OK|FAILURES|ERRORS|Tests:)|tests?, .*assertions?' | tail -1)
[ -n "$summary" ] || summary=$(printf '%s\n' "$suite_output" | grep -vE '^\s*$' | tail -1)
if [ "$suite_status" -eq 0 ]; then pass "suite: $summary"; else fail "suite (exit $suite_status): $summary"; fi

if [ "$failures" -eq 0 ]; then
    echo "PASS"
    exit 0
fi
echo "FAIL ($failures)"
exit 1
