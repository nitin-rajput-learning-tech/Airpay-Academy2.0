#!/usr/bin/env bash
# scripts/sync-pwa.sh — Sync PWA assets from Moodle into www/ (LOCAL BUNDLE MODE ONLY)
#
# In REMOTE URL mode (the production default), this script is NOT needed.
# The native app loads everything from https://www.airpay.academy directly.
#
# USAGE: bash scripts/sync-pwa.sh [--moodle-url <url>]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
WWW_DIR="$PROJECT_DIR/www"
MOODLE_LOCAL_URL="${MOODLE_LOCAL_URL:-http://localhost:8080/moodle}"

while [[ $# -gt 0 ]]; do
  case $1 in
    --moodle-url) MOODLE_LOCAL_URL="$2"; shift 2 ;;
    --help|-h) echo "Usage: $0 [--moodle-url <url>]"; exit 0 ;;
    *) echo "Unknown argument: $1"; exit 1 ;;
  esac
done

log() { echo "[sync-pwa] $*"; }
warn() { echo "[sync-pwa] WARNING: $*" >&2; }
die() { echo "[sync-pwa] ERROR: $*" >&2; exit 1; }

command -v curl >/dev/null 2>&1 || die "curl is required"
command -v npx >/dev/null 2>&1 || die "npx (Node.js 18+) is required"

log "Ensuring www/ directory exists..."
mkdir -p "$WWW_DIR"

# Fetch manifest — PHP-generated (per-tenant branding, VAPID key injection)
log "Fetching PWA manifest from $MOODLE_LOCAL_URL/local/sentientia_pwa/manifest.php ..."
if curl -sf --max-time 10 --output "$WWW_DIR/manifest.webmanifest" \
    "$MOODLE_LOCAL_URL/local/sentientia_pwa/manifest.php"; then
  log "manifest.webmanifest written ($(wc -c < "$WWW_DIR/manifest.webmanifest") bytes)"
else
  warn "Could not fetch manifest.php — is XAMPP running? Skipping manifest."
fi

# Fetch service worker — PHP-generated (VAPID key + cache version injected)
log "Fetching service worker from $MOODLE_LOCAL_URL/local/sentientia_pwa/sw.php ..."
if curl -sf --max-time 10 --output "$WWW_DIR/sw.js" \
    "$MOODLE_LOCAL_URL/local/sentientia_pwa/sw.php"; then
  log "sw.js written ($(wc -c < "$WWW_DIR/sw.js") bytes)"
else
  warn "Could not fetch sw.php — skipping service worker."
fi

# Copy static offline fallback page (does not need PHP processing)
REPO_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
OFFLINE_SRC="$REPO_ROOT/moodle-enhancement/local/sentientia_pwa/offline.html"
if [[ -f "$OFFLINE_SRC" ]]; then
  log "Copying offline.html..."
  cp "$OFFLINE_SRC" "$WWW_DIR/offline.html"
  log "offline.html copied"
else
  warn "offline.html not found at $OFFLINE_SRC — skipping"
fi

# Run Capacitor sync — copies www/ into android/assets/public/ and ios/App/App/public/
log "Running npx cap sync..."
cd "$PROJECT_DIR"
npx cap sync

log "Done. www/ contents synced into native projects."
log "Next: npx cap open android  OR  npx cap open ios"
log "NOTE: This script is for LOCAL BUNDLE mode only."
