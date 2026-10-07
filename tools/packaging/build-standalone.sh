#!/usr/bin/env bash
# build-standalone.sh - build the Sentientia LMS "Complete Standalone" package for a Moodle release.
#
#   tools/packaging/build-standalone.sh --target 5.3 [options]      (ADR-033: the cutover target)
#   tools/packaging/build-standalone.sh --target 5.2 [options]      (kept while the UAT runs 5.2)
#
# Replaces build-5.2-standalone.sh (now a thin wrapper) and package-sentientia.ps1 (retired for 5.3:
# it packaged public/ only, which drops the root lib/bundles that Moodle 5.3 needs).
#
# What the recipe does
#   0. PROFILE  per target: tree directory name, release string, vanilla base tree, prerequisites. No literal
#               moodle5.2 / 20260519 survives anywhere: every README line, output name, tar exclude and verify
#               regex is derived from TREE_NAME / RELEASE.
#   1. EXPORT   a git export (git archive of --ref, default HEAD) of exactly the paths that ship. The package
#               holds what is COMMITTED, never what happens to sit in the local 5.1 webroot (the old recipe
#               copied from there, so a fix committed to git did not necessarily reach a package).
#   2. STAGE    a fresh copy of the vanilla Moodle base tree (5.3: the SHA-256 verified extract), never the
#               extract itself, so it stays pristine for diffs. The ROOT config.php (the instance config, with the
#               DB password) is excluded from the copy as well as from the archive.
#               public/config.php is DIFFERENT: since Moodle 5.1 it is core's own tiny loader shim ("Moodle
#               configuration loader": it requires ../config.php, or redirects to install.php when there is none).
#               index.php, r.php, lib/javascript.php and every other entry point require it, so a package WITHOUT it
#               cannot start. It ships, but only when it is byte-identical to the vanilla base file and holds no DB
#               setting; anything else fails the build. (The old 5.2 recipe excluded public/config.php by path,
#               which dropped the shim.)
#   3. OVERLAY  moodle-enhancement/tools/overlay-airpay-customs.ps1 -RepoRoot <export> lays the Sentientia
#               layer on the staged tree (theme, plugins, vendor patches, my/ additions, router .htaccess).
#   4. VERIFY   the staged tree, then the archive, and FAIL THE BUILD (non-zero exit, no final zip) when any
#               check below is not met.
#   5. ZIP      DEPLOY-README.txt + <tree>/ with bsdtar (deflate). GNU tar writes a PLAIN TAR named .zip,
#               and Windows Compress-Archive dies on Moodle's deep paths, so bsdtar it is.
#   6. HASH     size / entries / SHA-256 to pin in UAT-SENTIENTIA-DEPLOY-CHECKLIST.md, then tag the commit.
#
# Verification gates (build fails on any)
#   root config.php count in the archive = 0 (tree-name-aware regex), none in the stage either;
#   public/config.php present and equal to the vanilla loader shim, no DB setting in it;
#   5.3: lib/bundles/bootstrap, lib/bundles/fontawesome/webfonts/fa-solid-900.woff2, lib/components.json and
#        lib/plugins.json present; public/lib/fonts, public/theme/classic and public/my/templates/dropdown.mustache absent;
#   both: public/my/dashboard.php, public/my/switchrole.php, public/.htaccess, public/theme/sentientia/version.php
#        present; public/theme/airpayux and airpay-audit-loginas.php absent;
#   5.3: 0 SENTIENTIA-CORE-MOD in public/lib/setuplib.php (no core edit; the 5.2 ini_get_bool recipe is NOT
#        applied on 5.3) and a few core files byte-identical to the vanilla base;
#   0 mentions of the removed core modal factory AMD module in any shipped amd/build (.js and .map);
#   no `new Mustache_Engine` in shipped PHP (the one allowed instantiation is local_sentientia_emails' mustache_factory).
#
# Prerequisites: Git Bash on Windows, robocopy, powershell (5.1), C:\Windows\System32\tar.exe (bsdtar),
#   sha256sum, git. The 5.3 base is D:\Claude Local\moodle53\moodle (override with --base).
#
# Usage:
#   tools/packaging/build-standalone.sh --target 5.3
#   tools/packaging/build-standalone.sh --target 5.3 --no-zip                 stage + stage verification only
#   tools/packaging/build-standalone.sh --target 5.3 --verify-zip <file.zip>  archive verification only
#
# Options:
#   --target 5.3|5.2        required
#   --date YYYY-MM-DD       stamp used in the output name and README (default today)
#   --ref <git-ref>         what to export (default HEAD)
#   --base <dir>            vanilla Moodle tree to copy (the dir that holds public/ and lib/)
#   --stage-parent <dir>    where per-run stage dirs are created (default per target, under D:\Claude Local)
#   --out-dir <dir>         where the zip is written (default moodle-enhancement/docs/cutover/dist)
#   --tree-name <name>      directory name inside the archive (default moodle5.3 / moodle5.2)
#   --error-base <path>     ErrorDocument base in the generated .htaccess ('' for a docroot vhost = default;
#                           '/moodle' for the dev alias)
#   --with-learnerscript    also ship the vendor blocks learnerscript, reportdashboard, reporttiles. Their report
#                           modals still use the removed modal factory (FX-08), so the factory gate FAILS the
#                           build until they are ported; the flag exists so that gate stays honest.
#   --no-zip                stop after stage verification
#   --verify-zip <file>     verify an existing archive and exit
#   --cleanup               remove this run's stage directory when the build succeeds
#
# Stage directories are left in place by default (a few GB each): inspect them, then pass --cleanup next time
# or delete the printed run dir yourself.
set -euo pipefail
# NOTE: Git Bash's default path conversion stays ON for git.exe (it needs /d/... turned into D:\...), and is
# switched OFF per call for robocopy, tar.exe and powershell via native() below.

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TAR="/c/Windows/System32/tar.exe"     # bsdtar. Git Bash's GNU tar cannot write zip: `tar -a -cf x.zip` silently writes a PLAIN TAR named .zip.
OVERLAY="$REPO/moodle-enhancement/tools/overlay-airpay-customs.ps1"
win() { cygpath -w "$1"; }
# Native Windows programs get ALREADY-Windows paths via win(); MSYS must not also rewrite their /E-style switches
# (it turns /L into L:/ and robocopy dies with exit 16) or a leading-slash value such as --error-base /moodle.
native() { MSYS_NO_PATHCONV=1 "$@"; }

TARGET=""; STAMP="$(date +%F)"; REF="HEAD"; BASE=""; STAGE_PARENT=""; OUT_DIR=""; TREE_NAME=""
ERROR_BASE=""; WITH_LS=0; NO_ZIP=0; VERIFY_ZIP=""; CLEANUP=0
while [ $# -gt 0 ]; do
    case "$1" in
        --target)        shift; TARGET="${1:-}" ;;
        --date)          shift; STAMP="${1:-}" ;;
        --ref)           shift; REF="${1:-}" ;;
        --base)          shift; BASE="${1:-}" ;;
        --stage-parent)  shift; STAGE_PARENT="${1:-}" ;;
        --out-dir)       shift; OUT_DIR="${1:-}" ;;
        --tree-name)     shift; TREE_NAME="${1:-}" ;;
        --error-base)    shift; ERROR_BASE="${1:-}" ;;
        --with-learnerscript) WITH_LS=1 ;;
        --no-zip)        NO_ZIP=1 ;;
        --verify-zip)    shift; VERIFY_ZIP="${1:-}" ;;
        --cleanup)       CLEANUP=1 ;;
        -h|--help)       sed -n '2,64p' "$0"; exit 0 ;;
        *) echo "unknown arg: $1 (see --help)" >&2; exit 2 ;;
    esac
    shift
done

# ---------------------------------------------------------------------------------------------
# 0. PROFILE
# ---------------------------------------------------------------------------------------------
case "$TARGET" in
    5.3)
        : "${TREE_NAME:=moodle5.3}"
        RELEASE="5.3 (Build: 20261005)"; REL_PREFIX="5.3"
        DEF_BASE="/d/Claude Local/moodle53/moodle"; DEF_STAGE="/d/Claude Local/moodle53-stage"
        PHP_REQ="PHP >= 8.3 (64-bit; intl, soap, sodium, gd, mbstring, xml, zip, curl, opcache); max_input_vars >= 5000 (install and upgrade stop below it)"
        DB_REQ="MySQL 8.4+ (application user on caching_sha2_password), or MariaDB 11.4+, or PostgreSQL 17+ (utf8mb4, innodb_file_per_table, ROW_FORMAT=DYNAMIC on MySQL/MariaDB)"
        UPGRADE_FROM="Moodle 4.4 or later ONLY (a live 4.1.2 site needs the 4.5.x hop first, then 5.3; PHP 8.3 is the one version both hops accept)"
        ROOT_LIB=1; FORBID_SETUPLIB_MOD=1 ;;
    5.2)
        : "${TREE_NAME:=moodle5.2}"
        RELEASE="5.2+ (Build: 20260519)"; REL_PREFIX="5.2"
        DEF_BASE="/c/xampp/htdocs/moodle5.2"; DEF_STAGE="/d/Claude Local/moodle52-stage"
        PHP_REQ="PHP >= 8.3 (intl, soap, sodium, gd, mbstring, xml, zip, curl, opcache); max_input_vars >= 5000"
        DB_REQ="MySQL 8.4 or MariaDB 10.11 (utf8mb4, innodb_file_per_table, ROW_FORMAT=DYNAMIC)"
        UPGRADE_FROM="Moodle 4.4 or later"
        ROOT_LIB=0; FORBID_SETUPLIB_MOD=0 ;;
    *) echo "--target must be 5.3 or 5.2 (got '${TARGET}')" >&2; exit 2 ;;
esac
: "${BASE:=$DEF_BASE}"; : "${STAGE_PARENT:=$DEF_STAGE}"
: "${OUT_DIR:=$REPO/moodle-enhancement/docs/cutover/dist}"
# Tree-name-aware regex: dots escaped, so 'moodle5.3' never matches 'moodle5x3'. It matches the ROOT config.php
# only; public/config.php is core's loader shim and is checked separately (present, vanilla, no DB setting).
TN_RE="${TREE_NAME//./\\.}"
CFG_RE="^${TN_RE}/config\\.php\$"
FAILS=0
bad() { echo "VERIFY FAIL: $1" >&2; FAILS=$((FAILS + 1)); }
ok()  { echo "  ok   $1"; }

# ---------------------------------------------------------------------------------------------
# verification (archive level): everything that can be checked from the file list alone
# ---------------------------------------------------------------------------------------------
verify_archive() {
    local zip="$1" list n
    list="$(mktemp)"
    native "$TAR" -tf "$(win "$zip")" > "$list"
    echo "── archive verification: $(basename "$zip")"
    n="$(grep -cE "$CFG_RE" "$list" || true)"
    [ "$n" = 0 ] && ok "root config.php entries = 0" || bad "root config.php entries = $n (regex $CFG_RE): a dev config.php is in the archive"
    n="$(grep -cxF "${TREE_NAME}/public/config.php" "$list" || true)"
    [ "$n" = 1 ] && ok "has public/config.php (core's loader shim, checked in the stage)" || bad "public/config.php missing from the archive: index.php, r.php and every entry point require it"
    n="$(grep -cE "(^|/)airpay-audit-loginas\\.php\$" "$list" || true)"
    [ "$n" = 0 ] && ok "no airpay-audit-loginas.php" || bad "airpay-audit-loginas.php is in the archive (localhost auto-login as site admin)"
    n="$(grep -cE "/node_modules(/|\$)|(^|/)_stale|\\.log\$" "$list" || true)"
    [ "$n" = 0 ] && ok "no node_modules / _stale-* / *.log" || bad "dev debris in the archive ($n entries)"
    n="$(grep -cE "^${TN_RE}/public/theme/airpayux(/|\$)" "$list" || true)"
    [ "$n" = 0 ] && ok "no public/theme/airpayux" || bad "theme/airpayux is in the archive (not served; broken on 5.2+)"
    for f in "public/my/dashboard.php" "public/my/switchrole.php" "public/.htaccess" "public/theme/sentientia/version.php" "lib/components.json"; do
        grep -qxF "${TREE_NAME}/${f}" "$list" && ok "has ${f}" || bad "missing ${f}"
    done
    if [ "$ROOT_LIB" = 1 ]; then
        for f in "lib/plugins.json" "lib/bundles/fontawesome/webfonts/fa-solid-900.woff2"; do
            grep -qxF "${TREE_NAME}/${f}" "$list" && ok "has ${f}" || bad "missing ${f} (root lib/ must ship beside public/)"
        done
        n="$(grep -cE "^${TN_RE}/lib/bundles/bootstrap/." "$list" || true)"
        [ "$n" -gt 0 ] && ok "has lib/bundles/bootstrap ($n entries)" || bad "missing lib/bundles/bootstrap"
        n="$(grep -cE "^${TN_RE}/public/lib/fonts(/|\$)" "$list" || true)"
        [ "$n" = 0 ] && ok "no public/lib/fonts (5.2 leftover)" || bad "public/lib/fonts present: a stale 5.2 file set, the base is not vanilla 5.3"
        n="$(grep -cE "^${TN_RE}/public/theme/classic(/|\$)" "$list" || true)"
        [ "$n" = 0 ] && ok "no public/theme/classic (removed in 5.3)" || bad "public/theme/classic present: a stale 5.2 file set, the base is not vanilla 5.3"
        n="$(grep -cE "^${TN_RE}/public/my/templates/dropdown\\.mustache\$" "$list" || true)"
        [ "$n" = 0 ] && ok "no my/templates/dropdown.mustache" || bad "my/templates/dropdown.mustache present (nothing references it)"
    fi
    n="$(grep -c . "$list" || true)"
    echo "  archive entries: $n"
    rm -f "$list"
}

if [ -n "$VERIFY_ZIP" ]; then
    [ -f "$VERIFY_ZIP" ] || { echo "no such file: $VERIFY_ZIP" >&2; exit 2; }
    verify_archive "$VERIFY_ZIP"
    [ "$FAILS" = 0 ] && { echo "archive verification PASSED"; exit 0; } || { echo "archive verification FAILED ($FAILS)" >&2; exit 1; }
fi

# ---------------------------------------------------------------------------------------------
# preconditions
# ---------------------------------------------------------------------------------------------
[ -d "$BASE/public" ] && [ -d "$BASE/lib" ] || { echo "base tree $BASE has no public/ + lib/ (is it the dir that holds them?)" >&2; exit 2; }
[ -x "$TAR" ] || { echo "bsdtar not found at $TAR" >&2; exit 2; }
[ -f "$OVERLAY" ] || { echo "overlay script missing: $OVERLAY" >&2; exit 2; }
BASE_REL="$(sed -n "s/^\\\$release[[:space:]]*=[[:space:]]*'\\([^']*\\)'.*/\\1/p" "$BASE/public/version.php" | head -n 1)"
case "$BASE_REL" in
    "$REL_PREFIX"*) ;;
    *) echo "base tree release is '${BASE_REL}', expected one starting with ${REL_PREFIX} for --target ${TARGET}" >&2; exit 2 ;;
esac
[ -f "$BASE/public/config.php" ] || { echo "base tree has no public/config.php (core's loader shim): not a vanilla ${TARGET} tree" >&2; exit 2; }
if grep -qE '\$CFG->(db[a-z]+|dataroot|wwwroot)[[:space:]]*=' "$BASE/public/config.php" \
   || ! grep -q 'Moodle configuration loader' "$BASE/public/config.php"; then
    echo "base public/config.php is not core's loader shim (it sets \$CFG values or lacks the loader header): it may be a dev config. Refusing." >&2
    exit 2
fi
if [ -e "$BASE/config.php" ]; then
    echo "NOTE: the base tree carries a root config.php; it is excluded from the stage copy and the archive." >&2
fi
echo "target=$TARGET tree=$TREE_NAME release='$RELEASE' base='$BASE' (reports '$BASE_REL') ref=$REF"

RUN_ID="$(date +%Y%m%d-%H%M%S)-$TARGET"
RUN_DIR="$STAGE_PARENT/$RUN_ID"
EXPORT="$RUN_DIR/export"
STAGE_TREE="$RUN_DIR/$TREE_NAME"
PUB="$STAGE_TREE/public"
mkdir -p "$EXPORT"; : > "$RUN_DIR/.sentientia-build-run"
HEAD_SHA="$(git -C "$REPO" rev-parse --short "$REF")"
BRANCH="$(git -C "$REPO" rev-parse --abbrev-ref HEAD 2>/dev/null || echo detached)"

# ---------------------------------------------------------------------------------------------
# 1. EXPORT
# ---------------------------------------------------------------------------------------------
EXPORT_PATHS=(
    moodle-enhancement/local
    moodle-enhancement/blocks
    moodle-enhancement/deploy/moodle-htaccess.template
    theme/sentientia
    payment/gateway/airpay
    admin/tool/certificate
    enrol/sentientiasub
    mod/quiz/accessrule/sentientia_proctoring
    my/dashboard.php
    my/switchrole.php
)
if [ "$WITH_LS" = 1 ]; then EXPORT_PATHS+=(blocks/learnerscript blocks/reportdashboard blocks/reporttiles); fi
echo "── 1. git export of $REF ($HEAD_SHA) -> $EXPORT"
for p in "${EXPORT_PATHS[@]}"; do
    git -C "$REPO" cat-file -e "$REF:$p" 2>/dev/null || { echo "path not in $REF: $p" >&2; exit 2; }
done
DIRTY="$(git -C "$REPO" status --porcelain -- "${EXPORT_PATHS[@]}" | head -n 5 || true)"
if [ -n "$DIRTY" ]; then
    echo "WARNING: uncommitted changes under exported paths. The package holds the COMMITTED content of $REF, not these:" >&2
    echo "$DIRTY" | sed 's/^/    /' >&2
fi
( cd "$EXPORT" && git -c core.autocrlf=false -C "$REPO" archive --format=tar "$REF" -- "${EXPORT_PATHS[@]}" | tar --force-local -xf - )

# ---------------------------------------------------------------------------------------------
# 2. STAGE
# ---------------------------------------------------------------------------------------------
echo "── 2. stage: copy of the vanilla base -> $STAGE_TREE"
native robocopy "$(win "$BASE")" "$(win "$STAGE_TREE")" /E /MT:8 /R:1 /W:1 \
    /XD node_modules .git '_stale-*' \
    /XF '*.log' airpay-audit-loginas.php "$(win "$BASE/config.php")" \
    /NFL /NDL /NJH /NJS /NC /NS /NP >/dev/null || [ $? -lt 8 ]
[ ! -e "$STAGE_TREE/config.php" ] || { echo "root config.php survived into the stage; refusing to continue" >&2; exit 1; }
# public/config.php stays: it is core's loader shim and every entry point requires it. Prove it is still the shim.
[ "$(sha256sum "$PUB/config.php" | cut -d' ' -f1)" = "$(sha256sum "$BASE/public/config.php" | cut -d' ' -f1)" ] \
    || { echo "staged public/config.php differs from the vanilla base file; refusing to continue" >&2; exit 1; }

# ---------------------------------------------------------------------------------------------
# 3. OVERLAY
# ---------------------------------------------------------------------------------------------
echo "── 3. overlay (repo mode) -> $PUB"
OVERLAY_ARGS=(-RepoRoot "$(win "$EXPORT")" -Target "$(win "$PUB")" -LogPath "$(win "$RUN_DIR/overlay-log.txt")")
[ -n "$ERROR_BASE" ] && OVERLAY_ARGS+=(-ErrorBase "$ERROR_BASE")
[ "$WITH_LS" = 1 ] && OVERLAY_ARGS+=(-WithLearnerscript)
native powershell -NoProfile -ExecutionPolicy Bypass -File "$(win "$OVERLAY")" "${OVERLAY_ARGS[@]}" | tail -n 4

# ---------------------------------------------------------------------------------------------
# 4a. VERIFY the staged tree (content checks the archive listing cannot do)
# ---------------------------------------------------------------------------------------------
echo "── 4a. stage verification"
SHIPPED=()
for d in "$PUB"/local/sentientia_* "$PUB"/blocks/sentientia_* "$PUB/theme/sentientia" "$PUB/payment/gateway/airpay" \
         "$PUB/enrol/sentientiasub" "$PUB/mod/quiz/accessrule/sentientia_proctoring" "$PUB/admin/tool/certificate"; do
    [ -d "$d" ] && SHIPPED+=("$d")
done
if [ "$WITH_LS" = 1 ]; then for b in learnerscript reportdashboard reporttiles; do [ -d "$PUB/blocks/$b" ] && SHIPPED+=("$PUB/blocks/$b"); done; fi
NPLUGINS="$(ls -d "$PUB"/local/sentientia_*/ 2>/dev/null | wc -l)"
[ "$NPLUGINS" -ge 30 ] && ok "$NPLUGINS local_sentientia_* plugins staged" || bad "only $NPLUGINS local_sentientia_* plugins staged (expected 40+)"
[ -f "$PUB/theme/sentientia/version.php" ] || bad "theme/sentientia missing from the stage"

n="$(find "$STAGE_TREE" -maxdepth 4 -name airpay-audit-loginas.php 2>/dev/null | wc -l)"
[ "$n" = 0 ] && ok "no airpay-audit-loginas.php" || bad "airpay-audit-loginas.php is in the stage"
[ ! -d "$PUB/theme/airpayux" ] && ok "no theme/airpayux" || bad "theme/airpayux is in the stage (not served; broken on 5.2+)"

if [ "$FORBID_SETUPLIB_MOD" = 1 ]; then
    n="$(grep -c 'SENTIENTIA-CORE-MOD' "$PUB/lib/setuplib.php" || true)"
    [ "$n" = 0 ] && ok "lib/setuplib.php carries no SENTIENTIA-CORE-MOD (the 5.2 ini_get_bool guard is NOT applied on 5.3)" \
                 || bad "lib/setuplib.php carries $n SENTIENTIA-CORE-MOD marker(s): on 5.3 the guard is retired and the polyfill alone is fatal"
    # A few core files must be byte-identical to the vanilla base: the overlay adds files, it edits no core file.
    for f in public/lib/setuplib.php public/lib/setup.php public/lib/classes/component.php public/lib/moodlelib.php public/lib/weblib.php public/version.php; do
        if [ "$(sha256sum "$STAGE_TREE/$f" | cut -d' ' -f1)" = "$(sha256sum "$BASE/$f" | cut -d' ' -f1)" ]; then :; else bad "core file $f differs from the vanilla base"; fi
    done
    ok "core integrity spot check done (setuplib, setup, component, moodlelib, weblib, version)"
fi

if [ "${#SHIPPED[@]}" -gt 0 ]; then
    # No shipped amd/build may name the removed legacy modal factory AMD module (.js and .map).
    HITS="$(grep -rIl --include='*.js' --include='*.map' 'core/modal_factory' "${SHIPPED[@]}" 2>/dev/null | grep '/amd/build/' || true)"
    [ -z "$HITS" ] && ok "no shipped amd/build names core/modal_factory" || { bad "shipped amd/build still names core/modal_factory (removed in Moodle 5.2): $(echo "$HITS" | wc -l) file(s)"; echo "$HITS" | head -n 8 | sed 's/^/    /' >&2; }
    # No shipped PHP may INSTANTIATE the legacy Mustache class (comments may name it); local_sentientia_emails' factory is the one allowed instantiation.
    HITS="$(grep -rInE --include='*.php' 'new[[:space:]]+\\?Mustache_Engine' "${SHIPPED[@]}" 2>/dev/null | grep -v 'sentientia_emails/.*mustache_factory' || true)"
    [ -z "$HITS" ] && ok "no shipped PHP uses Mustache_Engine (except the emails factory)" || { bad "new Mustache_Engine in shipped PHP (the class is gone on 5.2+)"; echo "$HITS" | head -n 8 | sed 's/^/    /' >&2; }
    HITS="$(grep -rIl --include='*.js' 'theme_airpayux' "$PUB/theme/sentientia/amd/build" 2>/dev/null || true)"
    [ -z "$HITS" ] && ok "no stale theme_airpayux module names in theme amd/build" || bad "stale theme_airpayux names in theme/sentientia/amd/build"
fi
if [ "$FAILS" != 0 ]; then
    echo "stage verification FAILED ($FAILS). Nothing was zipped. Stage left at: $RUN_DIR" >&2
    exit 1
fi
echo "stage verification passed"
if [ "$NO_ZIP" = 1 ]; then echo "--no-zip: stopping. Stage: $RUN_DIR"; exit 0; fi

# ---------------------------------------------------------------------------------------------
# 5. README + ZIP
# ---------------------------------------------------------------------------------------------
mkdir -p "$OUT_DIR"
OUT="$OUT_DIR/Sentientia-LMS-$TARGET-Complete-Standalone-$STAMP.zip"
THEME_VER="$(grep -oE 'version\s*=\s*[0-9]+' "$PUB/theme/sentientia/version.php" | grep -oE '[0-9]+' | head -n 1)"
echo "── 5. DEPLOY-README.txt + zip -> $OUT"
cat > "$RUN_DIR/DEPLOY-README.txt" <<EOF
SENTIENTIA LMS $TARGET - COMPLETE STANDALONE PACKAGE
=======================================================
Package : $(basename "$OUT")
Built   : $STAMP from Airpay-Academy2.0 $BRANCH @ $HEAD_SHA (git export of $REF; uncommitted changes are NOT in it)
Base    : Moodle $RELEASE, public/-split layout. Serve <extract-dir>/$TREE_NAME/public as the DocumentRoot.
          The root lib/ directory beside public/ (lib/bundles: Bootstrap, Font Awesome, React, design system on 5.3)
          MUST stay where it is: PHP reads it, and icons and all React/ESM UI fail without it.
Layer   : theme_sentientia $THEME_VER, $NPLUGINS local_sentientia_* plugins, sentientia_* blocks, tool_certificate (with the
          recorded vendor patches), paygw_airpay, enrol_sentientiasub, quizaccess_sentientia_proctoring,
          my/dashboard.php + my/switchrole.php, a router .htaccess
SHA-256 : see docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md (verify the download)

HARD PREREQUISITES
  PHP      : $PHP_REQ
  Database : $DB_REQ
  Web      : Apache 2.4 + mod_rewrite (every URL that is not a real file or directory goes to r.php; the shipped
             public/.htaccess does it, AllowOverride All) + mod_proxy_fcgi with ProxySet flushpackets=on for live SSE;
             set \$CFG->routerconfigured = true in config.php once the rewrite works.
  Storage  : a writable dataroot OUTSIDE the web root, new for this instance (never shared with another Moodle).
  Upgrade source: $UPGRADE_FROM.

NOT IN THE PACKAGE (on purpose)
  The instance config.php at the tree root (supply your own from config-dist.php; a template is
  moodle-enhancement/deploy/config-sentientia53.php.template), node_modules/, _stale-*/, *.log, theme/airpayux (not served),
  airpay-audit-loginas.php, the retired ini_get_bool polyfill.
  public/config.php IS in the package: it is Moodle's own loader (it requires ../config.php, or sends a fresh site to
  install.php). Leave it alone.
  No Moodle core file is edited: the package only ADDS files (my/dashboard.php, my/switchrole.php and public/.htaccess are
  new on 5.3). Do NOT put an ini_get_bool() polyfill in config.php and do NOT patch lib/setuplib.php (5.3: fatal).

FRESH INSTALL
  1. Extract into a NEW, empty directory. chown to the web user.
  2. Create config.php from config-dist.php (dirroot, dataroot, wwwroot, routerconfigured). No polyfill.
  3. sudo -u www-data php admin/cli/install_database.php --agree-license ...; if interrupted run tools/uat/finish_install.php
     (never resume with upgrade.php). Posture: forcelogin=0, enablemyhome=1, frontpage='', theme=sentientia.
  4. admin/cli/purge_caches.php; start cron. Run moodle-enhancement/deploy/render_smoke_53.sh against the site (gate check).

UPGRADE (clean-directory method; NEVER extract over the live root: stale files of the old release stay behind,
  e.g. theme/classic, the blocks/timeline AMD and templates, public/lib/fonts)
  1. Back up the database and the old code tree. Put the site in maintenance mode.
  2. Move the old code tree aside (do not delete it yet).
  3. Extract this package into a clean directory and copy back ONLY config.php (and any local customisation you keep on purpose).
  4. php admin/cli/upgrade.php --non-interactive; php admin/cli/purge_caches.php; run cron once.
  5. Smoke: render_smoke_53.sh; open /admin/index.php and the Environment page (router, max_input_vars).
History since the previous package: git log <previous-tag>..HEAD
EOF
OUT_PART="$OUT.partial"
rm -f "$OUT_PART"
( cd "$RUN_DIR" && native "$TAR" --format zip --options zip:compression=deflate -cf "$(win "$OUT_PART")" \
    --exclude "$TREE_NAME/config.php" \
    --exclude "$TREE_NAME/_stale-*" --exclude "$TREE_NAME/public/theme/airpayux" \
    --exclude '*/node_modules' --exclude '*.log' \
    DEPLOY-README.txt "$TREE_NAME" )

# ---------------------------------------------------------------------------------------------
# 4b. VERIFY the archive; only a passing archive gets its final name
# ---------------------------------------------------------------------------------------------
FAILS_BEFORE="$FAILS"
verify_archive "$OUT_PART"
RD="$(native "$TAR" -tf "$(win "$OUT_PART")" | grep -cxF 'DEPLOY-README.txt' || true)"
[ "$RD" = 1 ] && ok "DEPLOY-README.txt at the archive root" || bad "DEPLOY-README.txt not at the archive root"
if [ "$FAILS" != "$FAILS_BEFORE" ]; then
    echo "ARCHIVE VERIFICATION FAILED. Do NOT distribute: $OUT_PART (stage: $RUN_DIR)" >&2
    exit 1
fi
mv -f "$OUT_PART" "$OUT"

# ---------------------------------------------------------------------------------------------
# 6. HASH
# ---------------------------------------------------------------------------------------------
echo "── 6. hash"
printf 'file=%s\nbytes=%s\nsha256=%s\n' "$OUT" "$(stat -c %s "$OUT")" "$(sha256sum "$OUT" | cut -d' ' -f1)"
echo "Pin name + SHA-256 in docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md, then:"
echo "  git tag -a v<next>-sentientia-$TARGET-package-$STAMP -m 'package $STAMP' && git push origin --tags"
if [ "$CLEANUP" = 1 ] && [ -f "$RUN_DIR/.sentientia-build-run" ] && [ "${RUN_DIR#"$STAGE_PARENT"/}" != "$RUN_DIR" ]; then
    rm -rf "$RUN_DIR"; echo "stage removed: $RUN_DIR"
else
    echo "stage kept: $RUN_DIR   (re-run with --cleanup to remove it on success)"
fi
