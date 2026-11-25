#!/usr/bin/env bash
# Builds dist/rewind-<version>.tar.gz (top level folder "rewind") ready to unpack into custom_apps/.
# Usage: bin/pack.sh [output-dir]
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
OUT="${1:-$ROOT/dist}"
VERSION="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml | head -n1)"
[ -n "$VERSION" ] || { echo "cannot read version" >&2; exit 1; }

# refuse to pack broken PHP/JS or failing tests
find lib appinfo -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
for f in js/*.js; do node --check "$f"; done
php tests/run.php >/dev/null
node tests/frontend/run.js >/dev/null
xmllint --noout appinfo/info.xml 2>/dev/null || true

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
DEST="$STAGE/rewind"
mkdir -p "$DEST" "$OUT"
for item in appinfo lib l10n js css img templates LICENSE README.md CHANGELOG.md; do
	[ -e "$item" ] && cp -R "$item" "$DEST/"
done
find "$DEST" -name '.DS_Store' -delete

# hygiene: no personal names, hosts or health exports in the package
REPO_URL="https://github.com/Mikweb2025-design/rewind"   # the only allowed public reference
PATTERN="$(printf 'dan%s|mich%s|mik%s|strato%s' iele elin web server)"
LEAK=0
while IFS= read -r -d '' f; do
	if LC_ALL=C sed -e "s#$REPO_URL##g" -e "s#https://daniele-michelin.com[^) ]*##g" -e "s#\[daniele-michelin.com\]##g" "$f" | grep -qiaE "$PATTERN"; then echo "forbidden reference in: $f" >&2; LEAK=1; fi
	case "$f" in *.zip|*Export.xml|*export_cda.xml) echo "forbidden file: $f" >&2; LEAK=1;; esac
done < <(find "$DEST" -type f -print0)
[ "$LEAK" = 0 ] || exit 1

TGZ="$OUT/rewind-$VERSION.tar.gz"
rm -f "$TGZ"
(cd "$STAGE" && COPYFILE_DISABLE=1 tar --no-xattrs --no-mac-metadata -czf "$TGZ" rewind)
echo "$TGZ"
