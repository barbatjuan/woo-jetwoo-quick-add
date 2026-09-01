#!/usr/bin/env bash
#
# Build the installable zip and the update manifest for the current commit.
#
# The version is read from the plugin header, never passed in: two sources for one
# number is how a manifest ends up advertising a version the zip does not contain, and
# every site then downloads an "update" that changes nothing and offers itself again.
#
#   bin/release.sh https://example.com/updates
#
# Writes into dist/:
#   woo-jetwoo-quick-add-<version>.zip   the package sites download
#   woo-jetwoo-quick-add.json            the manifest they poll
#
# Upload both to the base URL given. The manifest filename stays the same across
# releases so the URL configured on client sites never changes; the zip is versioned so
# an older one can still be fetched.

set -euo pipefail

BASE_URL="${1:-}"
if [ -z "$BASE_URL" ]; then
	echo "usage: bin/release.sh <base-url>    e.g. https://example.com/updates" >&2
	exit 1
fi
case "$BASE_URL" in
	https://*) ;;
	*) echo "error: the base URL must be https — the package is code, and plain HTTP can be rewritten in transit" >&2; exit 1 ;;
esac
BASE_URL="${BASE_URL%/}"

cd "$(dirname "$0")/.."
SLUG="woo-jetwoo-quick-add"

VERSION=$(grep -m1 -E '^\s*\*\s*Version:' "$SLUG.php" | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
if [ -z "$VERSION" ]; then
	echo "error: no Version found in $SLUG.php header" >&2
	exit 1
fi

# A release built from a dirty tree ships something no commit describes.
if [ -n "$(git status --porcelain)" ]; then
	echo "error: working tree is dirty — commit first, so the release matches a commit" >&2
	exit 1
fi

# Refuse to reuse a version. Sites cache by version number; shipping different bytes
# under one they already have means they never see the change.
if git rev-parse "v$VERSION" >/dev/null 2>&1; then
	echo "error: tag v$VERSION already exists — bump the Version header first" >&2
	exit 1
fi

mkdir -p dist
ZIP="dist/$SLUG-$VERSION.zip"
git archive --format=zip --prefix="$SLUG/" -o "$ZIP" HEAD

REQUIRES=$(grep -m1 -E '^\s*\*\s*Requires at least:' "$SLUG.php" | sed -E 's/.*Requires at least:[[:space:]]*//' | tr -d '[:space:]')
REQUIRES_PHP=$(grep -m1 -E '^\s*\*\s*Requires PHP:' "$SLUG.php" | sed -E 's/.*Requires PHP:[[:space:]]*//' | tr -d '[:space:]')

# Subject lines since the last tag, for the "View details" screen. Someone deciding
# whether to press Update on a client's live shop deserves to see what it changes; an
# update notice with nothing behind it gets postponed forever, or applied blind.
PREV_TAG=$(git describe --tags --abbrev=0 2>/dev/null || true)
if [ -n "$PREV_TAG" ]; then
	RANGE="$PREV_TAG..HEAD"
else
	RANGE="HEAD"
fi
CHANGELOG=$(git log --no-merges --format='%s' "$RANGE" | sed 's/[\\"]/\\&/g' | sed 's|^|<li>|; s|$|</li>|' | tr -d '\n')
[ -n "$CHANGELOG" ] || CHANGELOG="<li>No changes recorded.</li>"

cat > "dist/$SLUG.json" <<JSON
{
  "name": "Woo JetWooBuilder Quick Add",
  "slug": "$SLUG",
  "version": "$VERSION",
  "requires": "$REQUIRES",
  "requires_php": "$REQUIRES_PHP",
  "last_updated": "$(date -u '+%Y-%m-%d %H:%M:%S')",
  "homepage": "https://github.com/barbatjuan/$SLUG",
  "download_url": "$BASE_URL/$SLUG-$VERSION.zip",
  "sections": {
    "changelog": "<h4>$VERSION</h4><ul>$CHANGELOG</ul>"
  }
}
JSON

# A manifest that does not parse is worse than none: the site silently stops checking.
if command -v php >/dev/null 2>&1; then
	php -r 'json_decode(file_get_contents($argv[1])); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);' "dist/$SLUG.json" \
		|| { echo "error: generated manifest is not valid JSON" >&2; exit 1; }
fi

echo "built  $ZIP"
echo "built  dist/$SLUG.json  -> $BASE_URL/$SLUG-$VERSION.zip"
echo
echo "next:"
echo "  1. upload both files to $BASE_URL/"
echo "  2. git tag v$VERSION && git push origin v$VERSION"
echo
echo "sites poll the manifest every 12h; 'Check again' on Dashboard -> Updates forces it."
