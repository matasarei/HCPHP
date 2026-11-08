#!/usr/bin/env bash
# install.sh — install the hcphp skills for OpenCode, so local models can run them.
#
#   plugins/hcphp/install.sh --opencode              into the global OpenCode config
#   plugins/hcphp/install.sh --opencode <app-dir>    into <app-dir>/.opencode, for one application
#
# Claude Code needs none of this: `/plugin install hcphp --marketplace matasarei/HCPHP`.
#
# Run it from a checkout of HCPHP: it copies the plugin to <config>/hcphp/ (reference, templates,
# bin, and HCPHP's docs/advisories for drift.sh), and each skill to <config>/skills/hcphp-<name>/.
# OpenCode wants a skill's `name:` to match its directory and puts no plugin bin/ on PATH, so the
# copies get their name prefixed, /hcphp:<skill> references renamed to hcphp-<skill>, and their
# "Paths." sentences pointed at the absolute plugin root.
# Re-running replaces what an earlier run installed; nothing outside hcphp/ and hcphp-* is touched.

set -eu

src="$(cd "$(dirname "$0")" && pwd)"
repo="$(cd "$src/../.." && pwd)"

[ "${1:-}" = "--opencode" ] || { sed -n '2,13p' "$0" >&2; exit 2; }

if [ -n "${2:-}" ]; then
    [ -d "$2" ] || { echo "install: no such directory: $2" >&2; exit 2; }
    target="$(cd "$2" && pwd)/.opencode"
else
    target="${XDG_CONFIG_HOME:-$HOME/.config}/opencode"
fi
[ "$target" != "/" ] && [ -n "$target" ] || { echo "install: invalid target '$target'" >&2; exit 2; }

root="$target/hcphp"
echo "Installing hcphp to $target"

rm -rf "${root:?}"
mkdir -p "$root" "$target/skills"
cp -R "$src/reference" "$src/templates" "$src/bin" "$root/"
chmod +x "$root"/bin/*.sh

if [ -d "$repo/docs/advisories" ]; then
    cp -R "$repo/docs/advisories" "$root/advisories"
else
    echo "  (no docs/advisories beside the plugin: drift.sh will need --advisories)"
fi

# Skills from an earlier install that no longer exist here.
for installed in "$target"/skills/hcphp-*/; do
    [ -d "$installed" ] || continue
    name="$(basename "$installed")"
    [ -d "$src/skills/${name#hcphp-}" ] || rm -rf "${target:?}/skills/${name:?}"
done

for dir in "$src"/skills/*/; do
    name="$(basename "$dir")"
    dest="$target/skills/hcphp-$name"
    rm -rf "${dest:?}"
    cp -R "$dir" "$dest"
    # Portable in-place edit: write to a temporary file and move it back.
    sed -e "s|^name: $name\$|name: hcphp-$name|" \
        -e "s|/hcphp:\([a-z-]*\)|hcphp-\1|g" \
        -e "s|The plugin root is two directories above this skill's base directory.|The plugin root is \`$root\`.|" \
        -e "s|^Under Claude Code \`bin/\` is on \`PATH\`, so its scripts run by name.\$|Call the scripts by their full path: \`$root/bin/<script>\`.|" \
        "$dest/SKILL.md" > "$dest/SKILL.md.tmp"
    mv "$dest/SKILL.md.tmp" "$dest/SKILL.md"
    echo "  hcphp-$name"
done

git -C "$repo" rev-parse --short HEAD > "$root/VERSION" 2>/dev/null || echo unknown > "$root/VERSION"
installed=""
for dir in "$target"/skills/hcphp-*/; do installed="$installed $(basename "$dir")"; done
echo "Done. Skills:$installed"
