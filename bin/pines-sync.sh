#!/usr/bin/env bash
# Sync Pines UI elements from https://github.com/thedevdojo/pines
# Downloads the full elements/ directory via GitHub tarball (no auth required)
#
# Usage:
#   bash bin/pines-sync.sh          # download / update elements
#   bash bin/pines-sync.sh --check  # print version info and exit

set -euo pipefail

REPO="thedevdojo/pines"
BRANCH="main"
TARBALL_URL="https://github.com/$REPO/archive/refs/heads/$BRANCH.tar.gz"
DEST="themes/demo/assets/pines"
TMP=$(mktemp -d)

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log()  { echo -e "${GREEN}✓${NC} $*"; }
info() { echo -e "${CYAN}→${NC} $*"; }
head() { echo -e "\n${YELLOW}$*${NC}"; }

trap 'rm -rf "$TMP"' EXIT

# Run from project root
cd "$(dirname "$0")/.."

if [[ "${1:-}" == "--check" ]]; then
    if [[ -f "$DEST/index.json" ]]; then
        TOTAL=$(python3 -c "import json; d=json.load(open('$DEST/index.json')); print(d['total'])" 2>/dev/null || echo "?")
        SYNCED_AT=$(python3 -c "import json; d=json.load(open('$DEST/index.json')); print(d.get('synced_at','unknown'))" 2>/dev/null || echo "unknown")
        echo "Pines elements: $TOTAL elements, last synced: $SYNCED_AT"
    else
        echo "Not synced yet. Run: bash bin/pines-sync.sh"
    fi
    exit 0
fi

head "Downloading Pines from GitHub ($REPO @ $BRANCH)..."
info "Fetching tarball..."
curl -sL "$TARBALL_URL" | tar -xz -C "$TMP" --strip-components=1
log "Tarball extracted"

head "Copying elements to $DEST/elements/..."
mkdir -p "$DEST"
rm -rf "$DEST/elements"
cp -r "$TMP/elements" "$DEST/elements"
log "Elements copied"

head "Generating index.json..."
python3 - <<PYEOF
import json, os, re
from datetime import datetime, timezone

dest_elements = "$DEST/elements"
index = []
categories = {}

for fname in sorted(os.listdir(dest_elements)):
    fpath = os.path.join(dest_elements, fname)

    # Skip non-HTML files and example subdirectories
    if not fname.endswith('.html') or os.path.isdir(fpath):
        continue
    if fname in ['explanation_generation.txt']:
        continue

    slug = fname[:-5]
    name = slug.replace('-', ' ').title()

    # Derive category from the slug's first word
    category = slug.split('-')[0]
    categories[category] = categories.get(category, 0) + 1

    # Load JSON metadata if available
    json_path = os.path.join(dest_elements, slug + '.json')
    explanation = ''
    alpine_data = ''
    if os.path.exists(json_path):
        try:
            meta = json.load(open(json_path))
            explanation = meta.get('explanation', '')
            alpine_data = json.dumps(meta.get('data', ''))
        except Exception:
            pass

    # Discover example variants
    examples_dir = os.path.join(dest_elements, slug + '-examples')
    examples = []
    if os.path.isdir(examples_dir):
        examples = sorted([f for f in os.listdir(examples_dir) if f.endswith('.html')])

    index.append({
        'slug': slug,
        'name': name,
        'category': category,
        'explanation': explanation,
        'examples_count': len(examples),
        'examples': examples,
    })

output = {
    'elements': index,
    'total': len(index),
    'categories': categories,
    'synced_at': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
    'source': 'https://github.com/$REPO/tree/$BRANCH/elements',
}

with open("$DEST/index.json", 'w') as f:
    json.dump(output, f, indent=2)

print(f"  Index: {len(index)} elements across {len(categories)} categories")
for cat, count in sorted(categories.items()):
    print(f"    {cat}: {count}")
PYEOF

log "index.json generated"

echo -e "\n${GREEN}Done!${NC} Elements synced to ${DEST}/elements/"
echo -e "Run ${CYAN}bash bin/pines-sync.sh --check${NC} to verify."
echo -e "Re-run this script any time to pull upstream updates."
