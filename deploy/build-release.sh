#!/usr/bin/env bash
# Builds a production release bundle for hosts without Node/Composer (e.g. SiteGround shared hosting).
# Usage: ./deploy/build-release.sh            → release/emotion-release-YYYYmmdd-HHMM.zip
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M)"
WORK="$(mktemp -d)"
OUT="$ROOT/release"
mkdir -p "$OUT"

echo "→ exporting committed files to $WORK"
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$WORK"

echo "→ installing production PHP dependencies"
composer install --working-dir="$WORK" --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --quiet

echo "→ building front-end assets"
(cd "$WORK" && npm ci --silent && npm run build --silent)

echo "→ trimming the bundle"
rm -rf "$WORK/node_modules" "$WORK/tests" "$WORK/docs/screenshots" "$WORK/.github" "$WORK/docker" "$WORK/Dockerfile" "$WORK/docker-compose.yml" "$WORK/release"
rm -f "$WORK/.env"
cp "$ROOT/deploy/siteground/env.example" "$WORK/.env.siteground.example"
cp "$ROOT/deploy/siteground/post-deploy.sh" "$WORK/post-deploy.sh"
cp "$ROOT/deploy/siteground/cron.txt" "$WORK/cron.txt"
cp "$ROOT/deploy/siteground/root.htaccess" "$WORK/root.htaccess"
chmod +x "$WORK/post-deploy.sh"

ZIP="$OUT/emotion-release-$STAMP.zip"
echo "→ zipping to $ZIP"
(cd "$WORK" && zip -qr "$ZIP" . -x '*.DS_Store')
rm -rf "$WORK"
echo "✓ $(du -h "$ZIP" | cut -f1) $ZIP"
