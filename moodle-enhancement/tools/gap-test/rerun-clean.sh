#!/usr/bin/env bash
# Controlled clean re-verification for the gap build.
# 1) redeploy all 9 plugins from the repo (current HEAD + applied fixes) -> webroot
# 2) run the Moodle DB upgrade (content_market version bump -> index migration) + purge
# 3) re-init the PHPUnit test DB from the (fixed) install.xml schema
# 4) run all 9 suites uninterrupted, tee per-plugin logs
# Run from repo root. Local XAMPP only; never touches live.
set -u
REPO="D:/Claude Local/airpay-ld-os"
SRC="$REPO/moodle-enhancement/local"
WEB="C:/xampp/htdocs/moodle5/public/local"
MROOT="C:/xampp/htdocs/moodle5"
LOGD="$REPO/moodle-enhancement/tools/gap-test/reports/phpunit-clean-20260617"
mkdir -p "$LOGD"
PLUGINS="sentientia_skillsai sentientia_authoring sentientia_content_market sentientia_xapi sentientia_talent sentientia_api sentientia_learningpath sentientia_analytics sentientia_assistant"

echo "=== 1. REDEPLOY (repo HEAD + fixes) ==="
for p in $PLUGINS; do
  cp -r "$SRC/$p/." "$WEB/$p/" && echo "deployed $p" || echo "FAIL deploy $p"
done

echo "=== 2. UPGRADE + PURGE ==="
php "$MROOT/admin/cli/upgrade.php" --non-interactive 2>&1 | tail -5
php "$MROOT/admin/cli/purge_caches.php" 2>&1 | tail -2

echo "=== 3. PHPUNIT RE-INIT (rebuild test DB from fixed install.xml) ==="
php "$MROOT/public/admin/tool/phpunit/cli/init.php" 2>&1 | tail -3

echo "=== 4. RUN ALL 9 SUITES ==="
cd "$MROOT" || exit 1
for p in $PLUGINS; do
  ts="local_${p}_testsuite"
  echo "===== RUN $p ====="
  php vendor/bin/phpunit --testsuite "$ts" > "$LOGD/$p.log" 2>&1
  echo "[$p] $(grep -E '^(OK|OK, but|FAILURES|ERRORS|Tests:)' "$LOGD/$p.log" | tail -2 | tr '\n' ' ')"
done
echo "=== CLEAN RE-RUN DONE -> $LOGD ==="
