#!/usr/bin/env bash
# Run on the SiteGround server (SSH) from the app folder after every upload.
set -euo pipefail
PHP="${PHP:-php}"

$PHP artisan down --retry=30 || true
$PHP artisan migrate --force
$PHP artisan storage:link || true
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan queue:restart || true
$PHP artisan up
echo "✓ deployed. Check: $PHP artisan about"
