#!/usr/bin/env bash
# drift.sh — is each HCPHP security advisory fixed in this application, by its markers?
#
# Usage: drift.sh [--advisories <dir>] [application-root]
#
# Each advisory in HCPHP's docs/advisories/ carries marker lines:
#   - defective: `<path>` matches `<extended regex>`
#   - fixed: `<path>` matches `<extended regex>`
# <path> is relative to the application root; a directory is searched recursively (*.php).
#
# Verdict per advisory:
#   defective     a defective marker matches — the line is printed;
#   fixed         no defective marker matches and every fixed marker does;
#   read-by-hand  neither: the code has diverged from both shapes, or a path is missing. A person
#                 (or /hcphp:port) reads the code; it is never assumed fixed.
#
# Advisories are found in: --advisories <dir>; $HCPHP_ADVISORIES; <plugin root>/docs/advisories
# (an OpenCode install); the Claude Code marketplace clone; the HCPHP checkout the plugin sits in.
#
# Exit 0 when every advisory is fixed, 1 when any is defective or needs reading, 2 on error.

set -u

here="$(cd "$(dirname "$0")" && pwd)"
plugin="$(cd "$here/.." && pwd)"
dir=""

if [ "${1:-}" = "--advisories" ]; then
    dir="${2:-}"; shift 2 || { echo "usage: drift.sh [--advisories <dir>] [root]" >&2; exit 2; }
fi

if [ -z "$dir" ]; then
    for candidate in \
        "${HCPHP_ADVISORIES:-}" \
        "$plugin/docs/advisories" \
        "$HOME/.claude/plugins/marketplaces/hcphp/docs/advisories" \
        "$plugin/../../docs/advisories"; do
        if [ -n "$candidate" ] && [ -d "$candidate" ]; then dir="$candidate"; break; fi
    done
fi
[ -n "$dir" ] && [ -d "$dir" ] || { echo "drift: no advisories found; pass --advisories <HCPHP>/docs/advisories" >&2; exit 2; }
dir="$(cd "$dir" && pwd)"

cd "${1:-.}" || exit 2
[ -d application ] || { echo "drift: no application/ here — not an HCPHP application" >&2; exit 2; }

# first matching line of <regex> under <path>, as path:line: text — empty when none
first_match() {
    local path="$1" regex="$2"
    if [ -d "$path" ]; then
        # find + grep rather than grep --include, which BusyBox's grep does not have
        find "$path" -type f -name '*.php' -not -path '*/vendor/*' -exec grep -HnE "$regex" {} + 2>/dev/null | head -1
    elif [ -f "$path" ]; then
        grep -nE "$regex" "$path" 2>/dev/null | head -1 | sed "s|^|$path:|"
    fi
}

fixed=0; defective=0; unclear=0
echo "# advisories from $dir"

for advisory in "$dir"/*.md; do
    [ -f "$advisory" ] || continue
    title=$(sed -n '1s/^# *//p' "$advisory")
    id=$(printf '%s' "$title" | grep -oE '^ADV-[0-9]{4}-[0-9]{3}')
    [ -n "$id" ] || id=$(basename "$advisory" .md)

    # shellcheck disable=SC2016  # the backticks are markdown, matched literally
    markers=$(sed -nE 's/^- (defective|fixed): `([^`]+)` matches `(.+)`$/\1	\2	\3/p' "$advisory")
    if [ -z "$markers" ]; then
        echo "$id read-by-hand — the advisory has no markers"
        unclear=$((unclear + 1)); continue
    fi

    verdict=""; evidence=""; missing=""
    while IFS='	' read -r kind path regex; do
        hit=$(first_match "$path" "$regex")
        if [ "$kind" = defective ] && [ -n "$hit" ]; then
            verdict=defective; evidence="$hit"; break
        fi
        if [ "$kind" = fixed ] && [ -z "$hit" ]; then
            [ -e "$path" ] && missing="${missing:-$path does not match the fixed shape}" \
                           || missing="${missing:-$path is absent}"
        fi
    done <<EOF
$markers
EOF

    if [ "$verdict" = defective ]; then
        echo "$id defective — $(printf '%s' "$evidence" | cut -c1-160)"
        defective=$((defective + 1))
    elif [ -z "$missing" ]; then
        echo "$id fixed"
        fixed=$((fixed + 1))
    else
        echo "$id read-by-hand — $missing"
        unclear=$((unclear + 1))
    fi
done

echo "fixed $fixed, defective $defective, read-by-hand $unclear"
[ "$defective" -eq 0 ] && [ "$unclear" -eq 0 ]
