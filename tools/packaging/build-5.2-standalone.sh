#!/usr/bin/env bash
# build-5.2-standalone.sh — rebuild the Sentientia LMS 5.2 "Complete Standalone" package.
#
# Encodes the recipe actually used for the 2026-06-19 / 2026-08-03 / 2026-09-10 packages
# (MOODLE-5.2-RECONCILIATION-PLAN.md §"P2 + P6" and §"Package refresh"):
#
#   1. SYNC   repo → local XAMPP webroot (the overlay's source). Plugins come from the
#              moodle-enhancement/ tree — the tree UAT actually runs (verified 2026-09-09:
#              UAT's local_sentientia_org hash-matches ME 1.4.x, not top-level 1.5.x);
#              the theme is single-tree at the repo top level; blocks from ME.
#   2. OVERLAY XAMPP webroot → the local Moodle 5.2 tree with
#              moodle-enhancement/tools/overlay-airpay-customs.ps1 (repairs stale AMD names,
#              logs collisions, copies core-adjacent BizLMS files).
#   3. ZIP     the 5.2 tree as  DEPLOY-README.txt + moodle5.2/…  (same layout as before)
#              MINUS config.php (root AND public/ — the 2026-08-03 zip leaked the dev one),
#              node_modules, _stale-* dev dirs and *.log. bsdtar is used because Windows
#              Compress-Archive dies on Moodle's deep paths; NOTE bsdtar's zip writer stores
#              uncompressed unless --options zip:compression=deflate (the first 2026-09-10
#              attempt came out at 519 MB instead of ~160 MB).
#   4. HASH    print size / entries / SHA-256 to pin in UAT-SENTIENTIA-DEPLOY-CHECKLIST.md
#              and MOODLE-5.2-RECONCILIATION-PLAN.md, then tag the commit.
#
# Prereqs: Git Bash on Windows, robocopy, powershell, C:\Windows\System32\tar.exe (bsdtar), sha256sum;
#          C:\xampp\htdocs\moodle5\public (5.1 dev webroot) and C:\xampp\htdocs\moodle5.2
#          (5.2 staging tree, core 5.2+ Build 20260519) present.
#
# Usage:  tools/packaging/build-5.2-standalone.sh [--skip-sync] [--skip-overlay] [--date YYYY-MM-DD]
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
XAMPP_PUBLIC="/c/xampp/htdocs/moodle5/public"
TREE52_PARENT="/c/xampp/htdocs"           # contains moodle5.2/
TAR="/c/Windows/System32/tar.exe"         # bsdtar. Git Bash's GNU tar cannot write zip: `tar -a -cf x.zip` silently writes a PLAIN TAR named .zip (519 MB on 2026-09-10).
STAMP="$(date +%F)"
SKIP_SYNC=0; SKIP_OVERLAY=0
while [ $# -gt 0 ]; do
    case "$1" in
        --skip-sync) SKIP_SYNC=1 ;;
        --skip-overlay) SKIP_OVERLAY=1 ;;
        --date) shift; STAMP="$1" ;;
        *) echo "unknown arg: $1" >&2; exit 2 ;;
    esac
    shift
done
DIST="$REPO/moodle-enhancement/docs/cutover/dist"
OUT="$DIST/Sentientia-LMS-5.2-Complete-Standalone-$STAMP.zip"
STAGE="$(mktemp -d)"; trap 'rm -rf "$STAGE"' EXIT
HEAD_SHA="$(git -C "$REPO" rev-parse --short HEAD)"

echo "── 1. sync repo → XAMPP webroot ($([ $SKIP_SYNC = 1 ] && echo skipped || echo running))"
if [ "$SKIP_SYNC" = 0 ]; then
    win() { cygpath -w "$1"; }
    for d in "$REPO"/moodle-enhancement/local/*/; do
        n="$(basename "$d")"
        case "$n" in Claude|airpay_ratings) continue ;; esac     # scratch dir / legacy plugin never served
        robocopy "$(win "$d")" "$(win "$XAMPP_PUBLIC/local/$n")" /E /MT:8 /R:1 /W:1 /NFL /NDL /NJH /NJS /NC /NS /NP >/dev/null || [ $? -lt 8 ]
    done
    for d in "$REPO"/moodle-enhancement/blocks/*/; do
        robocopy "$(win "$d")" "$(win "$XAMPP_PUBLIC/blocks/$(basename "$d")")" /E /MT:8 /R:1 /W:1 /NFL /NDL /NJH /NJS /NC /NS /NP >/dev/null || [ $? -lt 8 ]
    done
    cp -p "$REPO"/moodle-enhancement/blocks/*.php "$XAMPP_PUBLIC/blocks/" 2>/dev/null || true
    robocopy "$(win "$REPO/theme/sentientia")" "$(win "$XAMPP_PUBLIC/theme/sentientia")" /E /MT:8 /R:1 /W:1 /NFL /NDL /NJH /NJS /NC /NS /NP >/dev/null || [ $? -lt 8 ]
fi

echo "── 2. overlay XAMPP → moodle5.2 ($([ $SKIP_OVERLAY = 1 ] && echo skipped || echo running))"
if [ "$SKIP_OVERLAY" = 0 ]; then
    mkdir -p "/d/Claude Local/moodle-5.2-diffs"
    powershell -NoProfile -ExecutionPolicy Bypass -File "$(cygpath -w "$REPO/moodle-enhancement/tools/overlay-airpay-customs.ps1")" | tail -4
fi

echo "── 3. DEPLOY-README.txt + zip → $OUT"
THEME_VER="$(grep -oE 'version\s*=\s*[0-9]+' "$TREE52_PARENT/moodle5.2/public/theme/sentientia/version.php" | grep -oE '[0-9]+')"
PLUGINS="$(ls -d "$TREE52_PARENT"/moodle5.2/public/local/sentientia_*/ | wc -l)"
cat > "$STAGE/DEPLOY-README.txt" <<EOF
SENTIENTIA LMS 5.2 - COMPLETE STANDALONE PACKAGE
================================================
Package : $(basename "$OUT")
Built   : $STAMP from Airpay-Academy2.0 claude/gap-integration @ $HEAD_SHA
Base    : Moodle 5.2+ (Build: 20260519), public/-split layout — serve <extract-dir>/moodle5.2/public
Layer   : theme_sentientia $THEME_VER, $PLUGINS local_sentientia_* plugins, sentientia_* blocks,
          tool_certificate, paygw_airpay, quizaccess_sentientia_proctoring, BizLMS core-adjacent files
SHA-256 : see docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md (verify the download)

HARD PREREQUISITES: PHP 8.3 (intl, soap, sodium, gd, mbstring, xml, zip, curl, opcache);
MySQL 8.4 or MariaDB 10.11 (utf8mb4, innodb_file_per_table, ROW_FORMAT=DYNAMIC);
Apache 2.4 + mod_rewrite (+ mod_proxy_fcgi with ProxySet flushpackets=on for live SSE);
a writable dataroot OUTSIDE the web root.

EXCLUDED: config.php (supply your own from config-dist.php), node_modules/, _stale-*/, *.log.

FRESH INSTALL: extract; chown www-data; config.php (dirroot, dataroot, wwwroot, routerconfigured);
  sudo -u www-data php admin/cli/install_database.php --agree-license ...; if interrupted run
  tools/uat/finish_install.php (never resume with upgrade.php); posture forcelogin=0, enablemyhome=1,
  frontpage='', theme=sentientia; router rewrite block from deploy/moodle-htaccess.template; purge caches; cron.
UPGRADE (e.g. UAT): back up tree + DB; extract over the root keeping config.php;
  admin/cli/upgrade.php --non-interactive; admin/cli/purge_caches.php.
History since the previous package: git log <previous-tag>..HEAD
EOF
rm -f "$OUT"
( cd "$TREE52_PARENT" && "$TAR" -a --options zip:compression=deflate -cf "$(cygpath -w "$OUT")" \
    --exclude 'moodle5.2/config.php' --exclude 'moodle5.2/public/config.php' \
    --exclude 'moodle5.2/_stale-*' --exclude '*/node_modules' --exclude '*.log' \
    -C "$(cygpath -w "$STAGE")" DEPLOY-README.txt -C "$(cygpath -w "$TREE52_PARENT")" moodle5.2 )

echo "── 4. verify + hash"
LIST="$STAGE/list.txt"; "$TAR" -tf "$(cygpath -w "$OUT")" > "$LIST"
printf 'entries=%s files=%s config.php=%s _stale=%s node_modules=%s readme_at_root=%s\n' \
    "$(wc -l < "$LIST")" "$(grep -vc '/$' "$LIST")" \
    "$(grep -cE '^moodle5.2/(public/)?config\.php$' "$LIST")" "$(grep -c '_stale' "$LIST")" \
    "$(grep -c node_modules "$LIST")" "$(grep -c '^DEPLOY-README.txt$' "$LIST")"
printf 'bytes=%s\nsha256=%s\n' "$(stat -c %s "$OUT")" "$(sha256sum "$OUT" | cut -d' ' -f1)"
echo "Pin name + SHA-256 in UAT-SENTIENTIA-DEPLOY-CHECKLIST.md and MOODLE-5.2-RECONCILIATION-PLAN.md, then:"
echo "  git tag -a v<next>-sentientia-5.2-package-$STAMP -m 'package $STAMP' && git push origin --tags"
