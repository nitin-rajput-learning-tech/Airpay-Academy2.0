#!/bin/bash
# Airpay Academy production deploy — idempotent file copy.
#
# Copies moodle-enhancement/local|theme|blocks → public/local|theme|blocks
# using rsync --checksum so unchanged files are skipped.
#
# Re-run safe: a second invocation is a no-op.

set -euo pipefail

# Resolve paths from the script location.
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"
SRC_BASE="$REPO_ROOT/moodle-enhancement"
DST_BASE="$REPO_ROOT/public"

# Override for non-standard layouts (e.g. cron or alt path).
if [ -n "${MOODLE_PUBLIC:-}" ]; then
    DST_BASE="$MOODLE_PUBLIC"
fi

if [ ! -d "$SRC_BASE" ]; then
    echo "ERROR: source not found: $SRC_BASE" >&2
    exit 1
fi
if [ ! -d "$DST_BASE" ]; then
    echo "ERROR: destination not found: $DST_BASE — set MOODLE_PUBLIC env var" >&2
    exit 1
fi

echo "═══════════════════════════════════════════════════════════════════"
echo "Airpay Academy deploy"
echo "  source:      $SRC_BASE"
echo "  destination: $DST_BASE"
echo "  branch:      $(git -C "$REPO_ROOT" rev-parse --abbrev-ref HEAD)"
echo "  commit:      $(git -C "$REPO_ROOT" rev-parse --short HEAD)"
echo "═══════════════════════════════════════════════════════════════════"

deploy_dir() {
    local subpath="$1"
    local src="$SRC_BASE/$subpath"
    local dst="$DST_BASE/$subpath"

    if [ ! -d "$src" ]; then
        return 0
    fi

    mkdir -p "$dst"
    rsync -a --checksum --itemize-changes \
        --exclude=".git" --exclude="*.bak" --exclude="*.orig" \
        "$src/" "$dst/" | grep -E "^>f|^>d" | sed "s|^|  |"
}

# 1. Local plugins.
for plugin_path in "$SRC_BASE/local"/*/; do
    plugin=$(basename "$plugin_path")
    deploy_dir "local/$plugin"
done

# 2. Theme.
deploy_dir "theme/airpayux"

# 3. Blocks (if any have been customised).
if [ -d "$SRC_BASE/blocks" ]; then
    for block_path in "$SRC_BASE/blocks"/*/; do
        block=$(basename "$block_path")
        deploy_dir "blocks/$block"
    done
fi

echo
echo "═══════════════════════════════════════════════════════════════════"
echo "OK: file deploy complete."
echo
echo "Next steps (run manually unless you trust the rest):"
echo "  cd $REPO_ROOT"
echo "  sudo -u www-data php public/admin/cli/upgrade.php --non-interactive"
echo "  sudo -u www-data php public/admin/cli/cfg.php --name=themerev --set=\$(date +%s)"
echo "  sudo -u www-data php public/admin/cli/cfg.php --name=jsrev --set=\$(date +%s)"
echo "  sudo -u www-data php public/admin/cli/purge_caches.php"
echo "  sudo systemctl reload apache2"
echo "═══════════════════════════════════════════════════════════════════"
