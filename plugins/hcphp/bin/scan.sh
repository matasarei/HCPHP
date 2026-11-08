#!/usr/bin/env bash
# scan.sh — candidate security findings in an HCPHP application, one grep per checklist item.
#
# Usage: scan.sh [application-root]
#
# Prints "<ITEM> <path>:<line>: <text>" for each candidate, then a count per item. A candidate is
# a place to read, NOT a finding: reference/security-scan.md says, per item, what confirms it,
# what dismisses it and how to probe it locally. Read-only; bash 3.2+, grep, awk.
#
# Exit 0 always when it ran (candidates are not failures); 2 when this is not an application.

# shellcheck disable=SC2016,SC2317,SC2329  # patterns are literal; tracked_config is called via report
# (SC2317 is what shellcheck before 0.10 calls SC2329)
set -u

cd "${1:-.}" || exit 2
[ -d application ] || { echo "scan: no application/ here — not an HCPHP application" >&2; exit 2; }

LIMIT="${SCAN_LIMIT:-40}"
counts=""

# Source the application owns.
php_files()   { find application -type f -name '*.php' -not -path '*/vendor/*' -not -path '*/cache/*' 2>/dev/null; }
view_files()  { php_files | grep -E '^application/(templates|views)/'; }
ctrl_files()  { php_files | grep -E '^application/controllers/'; }

# each_file <command>...: xargs <command> over the file list on stdin, and nothing for an empty
# list. GNU xargs runs the command once with no file, and grep -L then reports "(standard input)".
each_file() {
    local list
    list=$(cat)
    [ -n "$list" ] || return 0
    printf '%s\n' "$list" | xargs "$@"
}

# A matched line can hold a credential: every quoted value after a key-like name is replaced
# before anything is printed, whichever item matched it.
redact() {
    sed -E "s/((pass|password|secret|token|api_?key|private_?key)[\"']?[[:space:]]*[:=]>?[[:space:]]*)[\"'][^\"']*[\"']/\1\"<redacted>\"/I"
}

# report <ITEM> <file list command> <regex> [grep flags]
report() {
    local item="$1" lister="$2" regex="$3" flags="${4:-}" n
    # shellcheck disable=SC2086  # flags is deliberately word-split
    n=$($lister | xargs grep -HnE $flags -- "$regex" 2>/dev/null | head -n "$LIMIT" | redact \
        | awk -v item="$item" '{ print item " " substr($0, 1, 200) }' | tee /dev/stderr | awk 'END { print NR }')
    counts="$counts$item $n
"
}

# report_files <ITEM> <reason> <file>...: whole files as candidates, line 1
report_files() {
    local item="$1" reason="$2" n=0 f
    shift 2
    for f in "$@"; do
        [ -n "$f" ] || continue
        echo "$item $f:1: $reason" >&2
        n=$((n + 1))
        [ "$n" -ge "$LIMIT" ] && break
    done
    counts="$counts$item $n
"
}

{
# --- output and escaping ------------------------------------------------------------------------
report ECHO-RAW view_files '\{\{\$[A-Za-z_][^}|]*\}\}|<\?= *\$[A-Za-z_]'
report TAG-CONTENT php_files 'Xml::tag\([^;]*(\$_(GET|POST|REQUEST)|Globals::)'

# --- requests that change state -----------------------------------------------------------------
# A POST form in a view with no CSRF token anywhere in the same file.
# shellcheck disable=SC2046
report_files CSRF "POST form with no CSRF token in this file" \
    $(view_files | each_file grep -liE '<form[^>]*method=["'"'"']?post' 2>/dev/null | each_file grep -LiE 'csrf' 2>/dev/null)
# A controller action that writes, with no CSRF-verified form, token check or method check in
# that action. Per action, not per file: a guarded save beside an unguarded delete is the case.
get_state() {
    ctrl_files | while IFS= read -r f; do
        awk -v file="$f" '
            function flush() {
                if (name != "" && writes && !guarded) print "GET-STATE " file ":" line ": " name " writes with no CSRF, form or method check"
            }
            /function +action[A-Za-z0-9_]* *\(/ {
                flush()
                match($0, /action[A-Za-z0-9_]*/); name = substr($0, RSTART, RLENGTH); line = NR
                writes = 0; guarded = 0; next
            }
            /->(save|remove|delete|update|insert)[A-Za-z]* *\(/ { writes = 1 }
            /isPost|REQUEST_METHOD|getData *\(|Csrf::|isValid *\(/ { guarded = 1 }
            END { flush() }
        ' "$f"
    done | head -n "$LIMIT"
}
n=$(get_state | tee /dev/stderr | awk 'END { print NR }')
counts="${counts}GET-STATE $n
"

# --- who may do it ------------------------------------------------------------------------------
# shellcheck disable=SC2046
report_files AUTHZ "controller with no capability check" \
    $(ctrl_files | each_file grep -LE 'checkCapability|hasCapability|isAdmin|requireLogin' 2>/dev/null)
report IDOR php_files "(Globals::(get|optional|filter)\(|\\\$_(GET|POST|REQUEST)\[)['\"]([a-z_]*_)?id['\"]"

# --- SQL ----------------------------------------------------------------------------------------
report LIKE-RAW php_files "LIKE[^;]*['\"] *\. *\\\$|'%' *\. *\\\$[A-Za-z_]"
report SQL-INTERP php_files "\"[^\"]*\b(SELECT|INSERT|UPDATE|DELETE|WHERE)\b[^\"]*\\\$[A-Za-z_]|\b(WHERE|AND|OR|SET)\b[^;'\"]*['\"] *\. *\\\$[A-Za-z_]" -i

# --- files, code, shell -------------------------------------------------------------------------
report UPLOAD php_files 'move_uploaded_file|PATHINFO_EXTENSION|\$_FILES\['
report DANGEROUS php_files '(^|[^>:$A-Za-z_])(eval|extract|unserialize|assert|create_function) *\('
report SHELL php_files '(^|[^>:$A-Za-z_])(exec|shell_exec|system|passthru|proc_open|popen) *\('

# --- secrets and errors -------------------------------------------------------------------------
if git rev-parse --git-dir >/dev/null 2>&1; then
    # tracked, or about to be: untracked files that are not ignored are one `git add` away
    tracked_config() { git ls-files --cached --others --exclude-standard application/config | grep -vE '\.(sample|dist|example)$'; }
else
    tracked_config() { find application/config -type f 2>/dev/null | grep -vE '\.(sample|dist|example)$'; }
fi
report SECRET tracked_config "\"?(pass|password|secret|token|api_?key|private_?key)\"? *[:=]>? *[\"'][^\"']{6,}[\"']" -i
report ERROR-LEAK php_files "(echo|print|die|exit)[^;]*(getMessage|getTraceAsString)\(|display_errors['\"]?, *['\"]?(1|on|true)"

# --- exports and sessions -----------------------------------------------------------------------
report FORMULA php_files 'setCellValue(ByColumnAndRow)?\(|fputcsv\('
report COOKIE php_files "setcookie\([^)]*,[^)]*,[^)]*\)|session_start\(\)"
if ! php_files | xargs grep -qE 'session_regenerate_id' 2>/dev/null; then
    report_files SESSION-REGEN "session_regenerate_id() is called nowhere: login keeps the visitor's session id" \
        "$(php_files | xargs grep -lE 'function +login *\(' 2>/dev/null | head -1)"
else
    counts="${counts}SESSION-REGEN 0
"
fi
} 2>&1

echo "## candidates per item"
printf '%s' "$counts"
exit 0
