#!/usr/bin/env bash
# schema-guard.sh — fail a refactoring diff that changes the database beyond an online index.
#
# Usage:
#   schema-guard.sh [--base <ref>] [application-root]   check what changed since <ref> (default:
#                                                       the merge base with origin's HEAD branch,
#                                                       else master or main; none → exit 2),
#                                                       committed, staged, unstaged and untracked
#   schema-guard.sh --files <file>...                   check these files as if each were new
#
# The rule (reference/schema-safety.md): the only schema change a refactoring may carry is a
# secondary index, added with ALGORITHM = INPLACE and LOCK = NONE. Anything else fails:
#   - any change to application/config/dynamicdb.json (DynamicDB syncs it on every request, and a
#     field removed there is DROP COLUMN on the next page load);
#   - an existing migration edited or deleted (migrations are append-only);
#   - a PHP migration (it cannot be checked mechanically);
#   - in a new .sql file, any statement other than ALTER TABLE … ADD INDEX/KEY … LOCK = NONE or
#     CREATE INDEX … LOCK = NONE, and any UNIQUE, PRIMARY, FOREIGN, FULLTEXT or SPATIAL index.
#
# Prints one line per finding, then "PASS" or "FAIL (<n>)". Exit 0 on PASS, 1 on FAIL, 2 on a
# usage error.

set -u

findings=0
finding() { printf 'FAIL %s: %s\n' "$1" "$2"; findings=$((findings + 1)); }

# Every statement in a SQL file, comments stripped, whitespace collapsed, upper-cased, one per
# line. Comments go first, over the whole file — a ";" inside a comment must not split a
# statement — then awk with RS=";" splits, which works in every awk, BSD included. Quotes are not
# parsed: a "--" or ";" inside a string literal is mis-read, which fails safe (a finding, never a
# missed one), and an index migration has no string literals.
strip_comments() {
    awk '
        {
            line = $0; out = ""
            while (line != "") {
                if (in_block) {
                    end = index(line, "*/")
                    if (end == 0) { line = ""; break }
                    line = substr(line, end + 2); in_block = 0; out = out " "
                    continue
                }
                start = index(line, "/*")
                dash = match(line, /--([ \t]|$)/) ? RSTART : 0
                hash = index(line, "#")
                first = 0
                if (start && (!first || start < first)) first = start
                if (dash && (!first || dash < first)) first = dash
                if (hash && (!first || hash < first)) first = hash
                if (!first) { out = out line; break }
                out = out substr(line, 1, first - 1)
                if (first == start) { in_block = 1; line = substr(line, start + 2) }
                else { line = "" }
            }
            print out
        }
    ' "$1"
}

statements() {
    strip_comments "$1" | awk '
        BEGIN { RS = ";" }
        {
            text = $0
            gsub(/[ \t\r\n]+/, " ", text)
            sub(/^ /, "", text); sub(/ $/, "", text)
            if (text != "") print toupper(text)
        }
    '
}

check_sql() {
    local file="$1" statement
    while IFS= read -r statement; do
        # Whole keywords only: `foreign_id` and `idx_unique_code` are ordinary names.
        if printf '%s' "$statement" | grep -qE '(^|[^A-Z0-9_])(UNIQUE|PRIMARY|FOREIGN|FULLTEXT|SPATIAL)([^A-Z0-9_]|$)'; then
            finding "$file" "constraint or special index, not allowed: $statement"
            continue
        fi
        if printf '%s' "$statement" | grep -qE '^CREATE INDEX( IF NOT EXISTS)? [^ ]+ ON '; then
            :
        elif printf '%s' "$statement" | grep -qE '^ALTER TABLE [^ ]+ ADD (INDEX|KEY) '; then
            # Every clause of the ALTER must be an index or the online options: split on commas
            # outside parentheses and check each one.
            local bad
            bad=$(printf '%s' "$statement" | sed -E 's/^ALTER TABLE [^ ]+ //' | awk '
                {
                    depth = 0; clause = ""
                    for (i = 1; i <= length($0); i++) {
                        c = substr($0, i, 1)
                        if (c == "(") depth++
                        if (c == ")") depth--
                        if (c == "," && depth == 0) { print clause; clause = ""; continue }
                        clause = clause c
                    }
                    print clause
                }' | sed -E 's/^ +//' | grep -vE '^(ADD (INDEX|KEY)( IF NOT EXISTS)? |ALGORITHM *= *INPLACE$|LOCK *= *NONE$)')
            if [ -n "$bad" ]; then
                finding "$file" "ALTER carries more than an index: $(printf '%s' "$bad" | head -1)"
                continue
            fi
        else
            finding "$file" "not an index statement: $statement"
            continue
        fi
        if ! printf '%s' "$statement" | grep -qE 'LOCK *= *NONE'; then
            finding "$file" "index without LOCK = NONE may lock the table: $statement"
        elif ! printf '%s' "$statement" | grep -qE 'ALGORITHM *= *INPLACE'; then
            finding "$file" "index without ALGORITHM = INPLACE: $statement"
        fi
    done < <(statements "$file")
}

# check_file <path> <status>: status is A (added), M (modified), D (deleted), R (renamed).
# In diff mode, SQL and configs under a tests/ or fixtures/ directory are test data: they never
# reach the application's database, and a characterization test may well seed from a .sql file.
# --files mode checks whatever it is given.
# default_branch: origin's HEAD branch, else origin's or the local master or main. Empty when none
# exists: comparing with HEAD instead would check only the uncommitted files and still say PASS.
default_branch() {
    local ref
    ref=$(git symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>/dev/null) && { echo "$ref"; return; }
    for ref in refs/remotes/origin/master refs/remotes/origin/main refs/heads/master refs/heads/main; do
        git rev-parse --verify --quiet "$ref^{commit}" >/dev/null && { echo "$ref"; return; }
    done
}

explicit=0

check_file() {
    local file="$1" status="$2"
    if [ "$explicit" -eq 0 ]; then
        # A tests/ or fixtures/ directory inside a migrations directory is still migrations.
        case "/$file" in
            */migrations/tests/*|*/migrations/*/tests/*|*/migrations/fixtures/*|*/migrations/*/fixtures/*) ;;
            */tests/*|*/fixtures/*) return ;;
        esac
    fi
    case "$file" in
        *config/dynamicdb.json)
            finding "$file" "DynamicDB schema config changed; the sync applies it on every request (DROP COLUMN for a removed field)"
            return ;;
    esac
    case "$file" in
        *.sql|migrations/*|*/migrations/*) ;;
        *) return ;;
    esac
    case "$status" in
        A) ;;
        D) finding "$file" "existing migration deleted; migrations are append-only"; return ;;
        R) finding "$file" "existing migration renamed; migrations are append-only"; return ;;
        *) finding "$file" "existing migration modified; migrations are append-only"; return ;;
    esac
    case "$file" in
        *.sql) check_sql "$file" ;;
        *.php) finding "$file" "PHP migration cannot be checked mechanically; a refactoring adds .sql index migrations only" ;;
        *)     finding "$file" "unrecognised migration file" ;;
    esac
}

if [ "${1:-}" = "--files" ]; then
    shift
    explicit=1
    [ "$#" -gt 0 ] || { echo "usage: schema-guard.sh --files <file>..." >&2; exit 2; }
    for file in "$@"; do
        [ -f "$file" ] || { echo "schema-guard: no such file: $file" >&2; exit 2; }
        check_file "$file" A
    done
else
    base=""
    if [ "${1:-}" = "--base" ]; then
        base="${2:-}"; shift 2 || { echo "usage: schema-guard.sh --base <ref> [root]" >&2; exit 2; }
    fi
    cd "${1:-.}" || exit 2
    git rev-parse --git-dir >/dev/null 2>&1 || { echo "schema-guard: not a git repository" >&2; exit 2; }
    if [ -z "$base" ]; then
        default=$(default_branch)
        [ -n "$default" ] || { echo "schema-guard: no default branch (origin/HEAD, master, main); pass --base <ref>" >&2; exit 2; }
        base=$(git merge-base HEAD "$default" 2>/dev/null)
    fi
    git rev-parse --verify --quiet "$base^{commit}" >/dev/null || { echo "schema-guard: unknown base '$base'" >&2; exit 2; }
    while IFS=$'\t' read -r status file rest; do
        case "$status" in
            R*) check_file "$file" R; check_file "$rest" A ;;
            *)  check_file "$file" "${status:0:1}" ;;
        esac
    done < <(git diff --name-status "$base" --; git ls-files --others --exclude-standard | awk '{ print "A\t" $0 }')
fi

if [ "$findings" -eq 0 ]; then
    echo "PASS"
    exit 0
fi
echo "FAIL ($findings)"
exit 1
