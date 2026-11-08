#!/usr/bin/env bash
# census.sh — read-only facts about an HCPHP application, for planning a refactoring.
#
# Usage: census.sh [application-root]        (default: the current directory)
#
# Prints "key: value" facts first — one per line, stable keys, so a script or a small model can
# read them without interpretation — then ranked lists. Writes nothing, runs no PHP, needs only
# bash 3.2+, grep, awk, find and wc, so it runs on a macOS host as well as in a container.
#
# Every number is a grep count over source text, not a parse. Where that is an approximation the
# key says so (`~`). The skills quote these numbers; they never recount by reading.

set -u

root="${1:-.}"
if [ ! -d "$root/application" ]; then
    echo "census: no application/ directory under '$root' — not an HCPHP application" >&2
    exit 2
fi
cd "$root" || exit 2

LIST_LIMIT="${CENSUS_LIST_LIMIT:-15}"

# Source files the application owns: vendor, caches and runtime directories excluded.
php_files() {
    find application -type f -name '*.php' \
        -not -path '*/vendor/*' -not -path 'application/lib/vendor/*' \
        -not -path '*/cache/*' 2>/dev/null
}

count_lines() { awk 'END { print NR }'; }

# Occurrences (not files) of an extended regex across files read from stdin, one path per line.
count_matches() {
    local pattern="$1" total=0 n f
    while IFS= read -r f; do
        n=$(grep -cE "$pattern" "$f" 2>/dev/null)
        total=$((total + ${n:-0}))
    done
    echo "$total"
}

fact() { printf '%s: %s\n' "$1" "$2"; }

# ---- the foundation this copy has -----------------------------------------------------------

container_api="missing"
if [ -f application/core/Container.php ]; then
    if grep -qE 'function +setFactory *\(' application/core/Container.php; then
        container_api="setFactory"
    elif grep -qE 'function +factory *\(' application/core/Container.php; then
        container_api="factory"
    else
        container_api="none"
    fi
fi

registration="init"
if php_files | grep '^application/lib/' | xargs grep -lE 'function +initialize *\( *(\\?core\\)?Container' 2>/dev/null | grep -q .; then
    registration="bundle-initialize"
fi

legacy=""
for base in Model ModelDynamic Service MapperSQL iMapper; do
    [ -f "application/core/$base.php" ] && legacy="$legacy core\\$base"
done
legacy="${legacy# }"

models_files=$(find application/models -type f -name '*.php' 2>/dev/null | count_lines)
bundles=$(find application/lib -maxdepth 1 -type d -name '*Bundle' 2>/dev/null | count_lines)
csrf="absent"
[ -f application/core/Csrf.php ] && csrf="present"
response="absent"
[ -f application/core/Response.php ] && response="present"

# The floor: composer's platform pin wins, then the require constraint.
floor="unknown"
if [ -f composer.json ]; then
    floor=$(grep -A3 '"platform"' composer.json | grep -oE '"php" *: *"[^"]+"' | head -1 | sed -E 's/.*: *"([^"]+)"/\1/')
    [ -z "$floor" ] && floor=$(grep -oE '"php" *: *"[^"]+"' composer.json | head -1 | sed -E 's/.*: *"([^"]+)"/\1/')
    [ -z "$floor" ] && floor="unknown"
fi

dynamicdb="absent"
[ -f application/config/dynamicdb.json ] && dynamicdb="present"

# ---- construction and coupling --------------------------------------------------------------

# Wiring is where construction belongs; it is not counted against anyone.
not_wiring() { grep -vE '^application/events/Init\.php$'; }

construct_re='new +\\?([A-Za-z_][A-Za-z0-9_]*\\)*[A-Za-z0-9_]*(Service|Repository|Mapper|Manager) *\('
inline_new=$(php_files | not_wiring | count_matches "$construct_re")
get_instance=$(php_files | count_matches '::getInstance *\(')
# Inside the library a container lookup is service location; in the outer layer it is expected.
container_in_lib=$(php_files | grep '^application/lib/' | count_matches 'getContainer *\( *\)')
container_outer=$(php_files | grep -vE '^application/lib/' | count_matches 'getContainer *\( *\)')
# shellcheck disable=SC2016  # the $_POST in the pattern is literal
globals_in_lib=$(php_files | grep -E '^application/(lib|models)/' | xargs grep -lE 'Globals::|\$_(POST|GET|REQUEST|COOKIE|SESSION)\b' 2>/dev/null | count_lines)
sql_re="[\"'] *(SELECT |INSERT +INTO |UPDATE +[a-zA-Z_\`]+ +SET |DELETE +FROM )"
sql_outside=$(php_files | grep -E '^application/(lib|models)/' | grep -vE '/(Mapper|Repository|Query|Database)[A-Za-z]*(/|\.php$)|/core/' | xargs grep -liE "$sql_re" 2>/dev/null | count_lines)
model_name=$(php_files | count_matches 'getModelName *\(')

# ---- size and tests -------------------------------------------------------------------------

app_files=$(php_files | count_lines)
large_classes=$(php_files | xargs wc -l 2>/dev/null | awk '$2 != "total" && $1 > 300' | count_lines)

test_files=0
[ -d tests ] && test_files=$(find tests -type f -name '*Test.php' 2>/dev/null | count_lines)

# A library class counts as tested when some test file names it as a whole word. That over-counts
# (a test that only imports it), never under-counts, so the list is a floor of the real gap.
untested_list=$(php_files | grep '^application/lib/' | while IFS= read -r f; do
    cls=$(basename "$f" .php)
    if [ ! -d tests ] || ! grep -rqwF "$cls" tests 2>/dev/null; then
        echo "$f"
    fi
done)
untested=0
[ -n "$untested_list" ] && untested=$(printf '%s\n' "$untested_list" | count_lines)
lib_classes=$(php_files | grep '^application/lib/' | count_lines)

crlf=$(php_files | xargs grep -l $'\r' 2>/dev/null | count_lines)

# ---- facts ----------------------------------------------------------------------------------

echo "# census of $(pwd)"
echo "## facts"
fact container_api "$container_api"
fact registration "$registration"
fact legacy_base "${legacy:-none}"
fact models_files "$models_files"
fact bundles "$bundles"
fact csrf_helper "$csrf"
fact response_class "$response"
fact php_floor "$floor"
fact dynamicdb_config "$dynamicdb"
fact app_php_files "$app_files"
fact crlf_files "$crlf"
fact inline_construction "$inline_new"
fact get_instance_calls "$get_instance"
fact container_lookups_in_lib "$container_in_lib"
fact container_lookups_outer "$container_outer"
fact request_reads_in_lib_files "$globals_in_lib"
fact sql_outside_storage_files "$sql_outside"
fact model_name_calls "$model_name"
fact classes_over_300_lines "$large_classes"
fact test_files "$test_files"
fact lib_classes "$lib_classes"
fact lib_classes_untested "$untested"

# ---- what the facts imply -------------------------------------------------------------------

echo "## proposals"
proposed=0
propose() { echo "PROPOSE $1 — $2"; proposed=1; }
[ "$container_api" != "setFactory" ] && [ "$container_api" != "factory" ] \
    && propose P0 "the container has no lazy factories (container_api: $container_api); port HCPHP's Container::setFactory() before any S5 slice"
[ "$csrf" = "absent" ] && propose P0 "no core\\Csrf; run /hcphp:port to check how this copy protects state-changing requests"
[ "$models_files" -gt 0 ] && propose M "application/models still holds $models_files files; move each family into a bundle before SRP work"
[ -n "$legacy" ] && propose C "legacy base classes remain ($legacy); retire them after the models are moved"
[ "$inline_new" -gt 0 ] && propose S5 "$inline_new inline constructions outside the wiring; add a ConstructionRatchetTest at today's counts"
[ "$untested" -gt 0 ] && propose S0 "$untested of $lib_classes library classes are named by no test; characterize before moving them"
[ "$model_name" -gt 0 ] && propose PIN "getModelName() is called $model_name times; pin its outputs before any move (trap-catalog §5)"
[ "$proposed" -eq 0 ] && echo "none"

# ---- ranked lists ---------------------------------------------------------------------------

echo "## largest files (lines)"
php_files | xargs wc -l 2>/dev/null | awk '$2 != "total"' | sort -rn | head -n "$LIST_LIMIT"

echo "## longest functions (~lines, declaration to next declaration)"
php_files | while IFS= read -r f; do
    awk -v file="$f" '
        /function +[A-Za-z_][A-Za-z0-9_]* *\(/ {
            if (name != "") print NR - start, file ":" start, name
            match($0, /function +[A-Za-z_][A-Za-z0-9_]*/)
            name = substr($0, RSTART + 9, RLENGTH - 9); gsub(/ /, "", name); start = NR
        }
        END { if (name != "") print NR - start + 1, file ":" start, name }
    ' "$f"
done | sort -rn | awk '$1 > 50' | head -n "$LIST_LIMIT"

echo "## inline construction by file"
php_files | not_wiring | while IFS= read -r f; do
    n=$(grep -cE "$construct_re" "$f" 2>/dev/null)
    [ "${n:-0}" -gt 0 ] && echo "$n $f"
done | sort -rn | head -n "$LIST_LIMIT"

echo "## library classes no test names"
[ -n "$untested_list" ] && printf '%s\n' "$untested_list" | head -n "$LIST_LIMIT"

if [ "$models_files" -gt 0 ]; then
    echo "## model families (files)"
    find application/models -type f -name '*.php' | awk -F/ '{ print (NF > 3 ? $3 : "(root)") }' \
        | sort | uniq -c | sort -rn
fi

exit 0
