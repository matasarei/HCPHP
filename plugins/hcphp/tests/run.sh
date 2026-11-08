#!/usr/bin/env bash
# run.sh — the plugin's own tests: its scripts against fixtures whose answers are known.
#
# Usage: plugins/hcphp/tests/run.sh          (from anywhere)
# Exit status is the number of failed assertions, capped at 1.

set -u

here="$(cd "$(dirname "$0")" && pwd)"
plugin="$(cd "$here/.." && pwd)"
repo="$(cd "$plugin/../.." && pwd)"
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
assert_eq "getInstance calls" 1 "$(fact "$out" get_instance_calls)"
assert_eq "floor from platform pin" 7.4 "$(fact "$out" php_floor)"
assert_eq "lib classes" 1 "$(fact "$out" lib_classes)"
assert_eq "named by a test" 0 "$(fact "$out" lib_classes_untested)"

out=$("$bin/census.sh" "$repo")
assert_eq "HCPHP itself is lazy" setFactory "$(fact "$out" container_api)"
assert_eq "HCPHP has no models" 0 "$(fact "$out" models_files)"

"$bin/census.sh" "$fixtures" >/dev/null 2>&1
assert_eq "not an application → exit 2" 2 "$?"

# ---- summary ----------------------------------------------------------------------------------

printf '%d passed, %d failed\n' "$passed" "$failed"
[ "$failed" -eq 0 ]
