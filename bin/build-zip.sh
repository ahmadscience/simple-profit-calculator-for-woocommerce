#!/usr/bin/env bash
# Builds simple-profit-calculator-for-woocommerce.zip for WordPress.org / manual install.
# Files listed in .distignore (GitHub files, docs, this script) are left out.
set -euo pipefail

SLUG="simple-profit-calculator-for-woocommerce"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/../$SLUG.zip}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/$SLUG"
rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$TMP/$SLUG/"
rm -f "$OUT"
( cd "$TMP" && zip -rq "$OUT" "$SLUG" )
echo "Built $OUT"
unzip -l "$OUT" | tail -n +4 | sed '$d' | sed '$d' | awk '{print $4}'
