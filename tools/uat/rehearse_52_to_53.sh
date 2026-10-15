#!/usr/bin/env bash
# rehearse_52_to_53.sh - LOCAL rehearsal of the in-place upgrade of UAT from Moodle 5.2 + Sentientia (as deployed) to
# Moodle 5.3 + Sentientia (a later commit). Re-runnable. Touches ONLY the private MariaDB database uatreh52 on
# 127.0.0.1:3308 and files under .../kit-scratch/uatreh/. It never connects to UAT, never uses the network and never
# writes to C:/xampp or to the XAMPP database on 3306.
#
#   bash tools/uat/rehearse_52_to_53.sh <uat-commit> <target-ref> [options]
#
#   <uat-commit>   the Sentientia commit UAT runs today (2026-09-30 deploy: 481252996). Tree A = Moodle 5.2 core + this commit.
#   <target-ref>   what Monday deploys (origin/claude/gap-integration). Tree B = Moodle 5.3 core + this ref.
#
# Options
#   --from PHASE   start at PHASE (default build). Phases, in order:
#                    build     stage tree A (5.2) and tree B (5.3) with tools/packaging/build-standalone.sh --no-zip;
#                              a tree already staged for the same ref is reused (a build takes 20-35 min under AV load)
#                    install   recreate uatreh52, write tree A's config.php, fresh install_database.php, the Stage A
#                              posture step, substrate + task-registration repair
#                    snapshot  record plugin versions, table counts, settings, flags and the schema check of the 5.2 site;
#                              take a logical dump of uatreh52 (the "5.2 site as UAT has it" restore point)
#                    upgrade   [from the dump when --from upgrade] maintenance on, tree B config.php, upgrade.php, maintenance off
#                    checks    schema check, versions / counts / settings / flags before-after, reload of caches, is-pending
#                    smoke     scheduled-task list + 3 executions, repair dry run, import framework list, web render
#                    scan      MySQL 8.4 reserved-word scan of the install.xml / upgrade.php that differ
#                    report    assemble the result files
#   --to PHASE     stop after PHASE (default report)
#   --root DIR     scratch root (must contain "uatreh"; default D:/Claude Local/kit-scratch/uatreh)
#   --no-render    skip the php -S web render in the smoke phase
#   --shutdown-db  shut the private MariaDB down at the end (only if this run started it, or always with --shutdown-db=always)
#
# Environment: PHP_BIN, PHP_INI (default: the WinGet PHP 8.4 + php84-moodle53.ini used by r53f/gate53.sh), BASE52, BASE53,
#              MARIADB_START=1 (default: start the server when 127.0.0.1:3308 does not answer), RENDER_PORT (default 8099).
#
# Exit code: 0 = every hard check passed, 1 = at least one hard check failed (the list is printed at the end), 2 = refused.
# Secrets: the database password lives in the secrets file rehearse_helper.py reads and in the generated config.php of the
# two trees; nothing here prints it. The admin password is a generated test value in .../uatreh/.adminpass.
set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
ROOT="${REHEARSAL_ROOT:-D:/Claude Local/kit-scratch/uatreh}"
FROM="build"; TO="report"; RENDER=1; SHUTDOWN=""
UAT_COMMIT=""; TARGET_REF=""
PHASES=(build install snapshot upgrade checks smoke scan report)

while [ $# -gt 0 ]; do
    case "$1" in
        --from) shift; FROM="${1:-}" ;;
        --to) shift; TO="${1:-}" ;;
        --root) shift; ROOT="${1:-}" ;;
        --no-render) RENDER=0 ;;
        --shutdown-db) SHUTDOWN="own" ;;
        --shutdown-db=always) SHUTDOWN="always" ;;
        -h|--help) sed -n '2,36p' "$0"; exit 0 ;;
        --*) echo "unknown option: $1" >&2; exit 2 ;;
        *) if [ -z "$UAT_COMMIT" ]; then UAT_COMMIT="$1"; elif [ -z "$TARGET_REF" ]; then TARGET_REF="$1"; else echo "too many arguments" >&2; exit 2; fi ;;
    esac
    shift
done
[ -n "$UAT_COMMIT" ] && [ -n "$TARGET_REF" ] || { echo "usage: rehearse_52_to_53.sh <uat-commit> <target-ref> [--from PHASE] [--to PHASE] (see --help)" >&2; exit 2; }
case "$ROOT" in *uatreh*) ;; *) echo "refused: --root must contain 'uatreh' (got $ROOT)" >&2; exit 2 ;; esac

phase_index() { local i; for i in "${!PHASES[@]}"; do [ "${PHASES[$i]}" = "$1" ] && { echo "$i"; return 0; }; done; return 1; }
FROM_I="$(phase_index "$FROM")" || { echo "unknown phase: $FROM" >&2; exit 2; }
TO_I="$(phase_index "$TO")" || { echo "unknown phase: $TO" >&2; exit 2; }
want() { local i; i="$(phase_index "$1")"; [ "$i" -ge "$FROM_I" ] && [ "$i" -le "$TO_I" ]; }

PHP_BIN="${PHP_BIN:-/c/Users/nitin.rajput/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe}"
PHP_INI="${PHP_INI:-D:/Claude Local/moodle53/php84-moodle53.ini}"
BASE52="${BASE52:-D:/Claude Local/moodle-5.2-source/moodle}"
BASE53="${BASE53:-D:/Claude Local/moodle53/moodle}"
MDB_DIR="D:/Claude Local/moodle53"
RENDER_PORT="${RENDER_PORT:-8099}"
WWWROOT="http://localhost/uatreh"
SCRIPTS="$HERE"
HELPER=(python -I "$HERE/rehearse_helper.py")

RUN="$ROOT/run"
mkdir -p "$RUN/logs" "$RUN/snap" "$RUN/dumps" "$RUN/reports" "$RUN/sql"
PROGRESS="$RUN/progress.txt"
RESULTS="$RUN/results.tsv"
FAILS=()
STARTED_DB=0

log()  { echo "[$(date +%H:%M:%S)] $*" | tee -a "$PROGRESS"; }
res()  { printf '%s\t%s\n' "$1" "$2" >> "$RESULTS"; }
hard() { # hard <name> <rc-or-0/1> <detail>: record a hard check
    if [ "$2" -eq 0 ]; then log "PASS  $1 - $3"; res "PASS:$1" "$3"; else log "FAIL  $1 - $3"; res "FAIL:$1" "$3"; FAILS+=("$1"); fi
}
secs() { echo $(( $(date +%s) - $1 )); }
php_in() { local tree="$1"; shift; ( cd "$tree" && "$PHP_BIN" -c "$PHP_INI" -d memory_limit=4G "$@" ); }

SHA_UAT="$(git -C "$REPO" rev-parse --verify "$UAT_COMMIT^{commit}" 2>/dev/null)" || { echo "refused: $UAT_COMMIT is not a commit in $REPO" >&2; exit 2; }
SHA_TGT="$(git -C "$REPO" rev-parse --verify "$TARGET_REF^{commit}" 2>/dev/null)" || { echo "refused: $TARGET_REF is not a commit in $REPO" >&2; exit 2; }

: > "$RUN/.run-started"
log "=== rehearsal start: UAT commit ${SHA_UAT:0:9} -> target ${TARGET_REF} (${SHA_TGT:0:9}); phases $FROM..$TO; root $ROOT"

# ------------------------------------------------------------------------------------------------------------
# 0. preflight (always)
# ------------------------------------------------------------------------------------------------------------
[ -x "$PHP_BIN" ] || [ -f "$PHP_BIN" ] || { log "refused: no PHP at $PHP_BIN"; exit 2; }
PHPV="$("$PHP_BIN" -c "$PHP_INI" -r 'echo PHP_VERSION;')"
log "php: $PHPV  (UAT runs 8.3; both Moodle 5.2 and 5.3 require >= 8.3)"
case "$PHPV" in 8.[3-9].*) ;; *) log "refused: PHP >= 8.3 required, found $PHPV"; exit 2 ;; esac
MIV="$("$PHP_BIN" -c "$PHP_INI" -r 'echo (int)ini_get("max_input_vars");')"
[ "$MIV" -ge 5000 ] || { log "refused: max_input_vars=$MIV < 5000 (Moodle 5.3 stops below it)"; exit 2; }
log "disk: $(df -h "$ROOT" | tail -1 | awk '{print $4 " free of " $2}')"

db_up() { "${HELPER[@]}" ping >/dev/null 2>&1; }
if ! db_up; then
    if [ "${MARIADB_START:-1}" = "1" ]; then
        log "MariaDB 11.4 on 3308 does not answer - starting it"
        ( "$MDB_DIR/mariadb-11.4.13-winx64/bin/mariadbd.exe" --defaults-file="$MDB_DIR/mdb114-data/my.ini" --console >> "$MDB_DIR/mdb114.log" 2>&1 & )
        STARTED_DB=1
        for _ in $(seq 1 30); do sleep 2; db_up && break; done
    fi
    db_up || { log "refused: the private MariaDB on 127.0.0.1:3308 is not reachable"; exit 2; }
fi
log "database server: $("${HELPER[@]}" ping | tr -d '\r')"
"${HELPER[@]}" serverinfo > "$RUN/logs/serverinfo.txt" 2>&1

# ------------------------------------------------------------------------------------------------------------
# helpers for the two trees
# ------------------------------------------------------------------------------------------------------------
find_stage() { # find_stage <parent> <target> <full sha> -> prints the moodleX.Y tree dir of a matching stage, or nothing
    local m d t
    for m in "$1"/*/.rehearsal-built-from; do
        [ -f "$m" ] || continue
        grep -qx "target=$2" "$m" && grep -qx "ref=$3" "$m" || continue
        d="$(dirname "$m")"
        for t in "$d"/moodle5.*; do
            [ -f "$t/public/version.php" ] && { echo "$t"; return 0; }
        done
    done
    return 1
}

build_stage() { # build_stage <5.2|5.3> <sha> <parent> <base> <must-pass 0|1>
    local target="$1" sha="$2" parent="$3" base="$4" must="$5" out stage tree started rc verify
    out="$RUN/logs/build_$target.log"
    log "build: Moodle $target + ${sha:0:9} (this takes 20-35 min under AV load) -> $parent"
    started="$(date +%s)"
    bash "$REPO/tools/packaging/build-standalone.sh" --target "$target" --ref "$sha" --base "$base" --stage-parent "$parent" --no-zip > "$out" 2>&1
    rc=$?
    stage="$(grep -E 'Stage( left at)?: ' "$out" | tail -1 | sed -E 's/^.*Stage( left at)?: *//')"
    if [ -z "$stage" ] || [ ! -d "$stage" ]; then log "FAIL build $target: no stage directory in $out"; return 1; fi
    verify="OK"
    [ "$rc" -eq 0 ] || verify="$(grep -c '^VERIFY FAIL' "$out") stage-verification failure(s), see $out"
    if [ "$rc" -ne 0 ] && [ "$must" = "1" ]; then log "FAIL build $target: build-standalone.sh exit $rc ($verify)"; return 1; fi
    {
        echo "target=$target"
        echo "ref=$sha"
        echo "base=$base"
        echo "stage_verify=$verify"
        echo "built_by=rehearse_52_to_53.sh ($(date -Is))"
    } > "$stage/.rehearsal-built-from"
    for tree in "$stage"/moodle5.*; do [ -f "$tree/public/version.php" ] && break; done
    log "build $target done in $(secs "$started") s (exit $rc: $verify): $tree"
    res "build_$target" "exit $rc; $verify; $(secs "$started") s"
    return 0
}

load_state() {
    TREE_A="$(cat "$RUN/treeA" 2>/dev/null)"; TREE_B="$(cat "$RUN/treeB" 2>/dev/null)"
    DATAROOT="$(cat "$RUN/dataroot" 2>/dev/null)"
}
need_state() {
    load_state
    [ -f "$TREE_A/public/version.php" ] && [ -f "$TREE_B/public/version.php" ] || { log "refused: tree A / tree B are not staged (run from the build phase)"; exit 2; }
    [ -n "$DATAROOT" ] || { log "refused: no dataroot recorded (run from the install phase)"; exit 2; }
}
run_snapshots() { # run_snapshots <tree> <tag>   (versions / tables / config / flags, all read-only)
    local tree="$1" tag="$2" cfg
    cfg="$tree/config.php"
    php_in "$tree" "$SCRIPTS/rehearse_versions.php" --config="$cfg" > "$RUN/snap/versions-$tag.tsv" 2> "$RUN/snap/versions-$tag.err"
    echo $? > "$RUN/snap/versions-$tag.rc"
    php_in "$tree" "$SCRIPTS/rehearse_counts.php" --config="$cfg" --what=tables > "$RUN/snap/counts-$tag.tsv" 2> "$RUN/snap/counts-$tag.err"
    php_in "$tree" "$SCRIPTS/rehearse_counts.php" --config="$cfg" --what=config > "$RUN/snap/config-$tag.tsv" 2> "$RUN/snap/config-$tag.err"
    php_in "$tree" "$SCRIPTS/flag_snapshot.php" --config="$cfg" > "$RUN/snap/flags-$tag.txt" 2> "$RUN/snap/flags-$tag.err"
    log "snapshot $tag: $(tail -1 "$RUN/snap/versions-$tag.err") | $(tail -1 "$RUN/snap/counts-$tag.err") | $(tail -1 "$RUN/snap/config-$tag.err") | $(tail -1 "$RUN/snap/flags-$tag.err")"
}
reset_db() {
    cat > "$RUN/sql/reset_uatreh52.sql" <<'SQL'
-- Recreates the rehearsal database and nothing else (guarded by rehearse_helper.py).
DROP DATABASE IF EXISTS `uatreh52`;
CREATE DATABASE `uatreh52` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL
    "${HELPER[@]}" sql "$RUN/sql/reset_uatreh52.sql" >/dev/null || { log "FAIL: could not recreate uatreh52"; exit 1; }
    log "database uatreh52 recreated empty (utf8mb4 / utf8mb4_unicode_ci, as stage-a-install.sh creates it on UAT)"
}
write_config() { # write_config <tree>
    "${HELPER[@]}" config --tree "$1" --dataroot "$DATAROOT" --wwwroot "$WWWROOT" >/dev/null || { log "FAIL: config.php for $1"; exit 1; }
}
guard_config() { # guard_config <tree>: the tree must resolve to uatreh52 on port 3308 and noemailever
    php_in "$1" -r 'define("CLI_SCRIPT", true); require "./config.php"; if ($CFG->dbname !== "uatreh52" || (int)$CFG->dboptions["dbport"] !== 3308 || empty($CFG->noemailever)) { exit(1); } echo "guard ok: ", $CFG->dbname, ":", $CFG->dboptions["dbport"], " noemailever=1 release=", $CFG->release ?? "?", PHP_EOL;' 2>&1 | tail -1
    return "${PIPESTATUS[0]}"
}

# ------------------------------------------------------------------------------------------------------------
# 1. build
# ------------------------------------------------------------------------------------------------------------
if want build; then
    TA="$(find_stage "$ROOT/a52" 5.2 "$SHA_UAT")"
    if [ -z "$TA" ]; then build_stage 5.2 "$SHA_UAT" "$ROOT/a52" "$BASE52" 0 || exit 1; TA="$(find_stage "$ROOT/a52" 5.2 "$SHA_UAT")"; else log "tree A: reusing $TA"; fi
    TB="$(find_stage "$ROOT/b53" 5.3 "$SHA_TGT")"
    if [ -z "$TB" ]; then build_stage 5.3 "$SHA_TGT" "$ROOT/b53" "$BASE53" 1 || exit 1; TB="$(find_stage "$ROOT/b53" 5.3 "$SHA_TGT")"; else log "tree B: reusing $TB"; fi
    [ -n "$TA" ] && [ -n "$TB" ] || { log "FAIL: a tree is missing after the build phase"; exit 1; }
    echo "$TA" > "$RUN/treeA"; echo "$TB" > "$RUN/treeB"
    res tree_A "$TA ($(grep -h '^stage_verify=' "$(dirname "$TA")/.rehearsal-built-from"))"
    res tree_B "$TB ($(grep -h '^stage_verify=' "$(dirname "$TB")/.rehearsal-built-from"))"
fi

# ------------------------------------------------------------------------------------------------------------
# 2. install the 5.2 site (what UAT is)
# ------------------------------------------------------------------------------------------------------------
if want install; then
    load_state
    [ -f "$TREE_A/public/version.php" ] || { log "refused: tree A not staged"; exit 2; }
    "${HELPER[@]}" adminpass --out "$ROOT/.adminpass" >/dev/null
    # dataroot: the plain 'data' directory when it is empty, else a fresh per-run one (the rules forbid deleting the old)
    DATAROOT="$ROOT/data"
    if [ -n "$(ls -A "$DATAROOT" 2>/dev/null | grep -v '^dev1$')" ]; then DATAROOT="$ROOT/data-$(date +%Y%m%d-%H%M%S)"; fi
    mkdir -p "$DATAROOT"; echo "$DATAROOT" > "$RUN/dataroot"
    log "dataroot: $DATAROOT"
    reset_db
    write_config "$TREE_A"
    guard_config "$TREE_A" || { log "FAIL: config guard on tree A"; exit 1; }
    log "install: fresh install_database.php on 5.2 + ${SHA_UAT:0:9} (an abort inside a plugin means: recreate and redo; never resume with upgrade.php)"
    T0="$(date +%s)"
    php_in "$TREE_A" admin/cli/install_database.php --agree-license --fullname="Sentientia LMS (UAT rehearsal)" --shortname="SENTIENTIA-UAT" \
        --adminuser=admin --adminpass="$(cat "$ROOT/.adminpass")" --adminemail="nitin.rajput@airpay.co.in" > "$RUN/logs/install_A.log" 2>&1
    IRC=$?
    INST_SECS="$(secs "$T0")"
    res install_A_seconds "$INST_SECS"
    TAIL_OK="$(php_in "$TREE_A" -r 'define("CLI_SCRIPT", true); require "./config.php"; echo (!empty($CFG->version) && !empty($CFG->rolesactive) && $DB->get_field("user", "password", ["username" => "admin"]) !== "adminsetuppending") ? "1" : "0";' 2>/dev/null | tail -1)"
    hard "install_5.2_fresh" "$([ "$IRC" -eq 0 ] && [ "$TAIL_OK" = "1" ] && echo 0 || echo 1)" "install_database.php exit $IRC in ${INST_SECS}s, install tail present: $TAIL_OK"
    if [ "$IRC" -ne 0 ] || [ "$TAIL_OK" != "1" ]; then log "STOP: the 5.2 install did not finish cleanly. Read $RUN/logs/install_A.log, fix, rerun --from install."; exit 1; fi
    # Stage A posture (stage-a-install.sh 5b/5c) and the post-install CLIs it runs
    php_in "$TREE_A" "$SCRIPTS/rehearse_posture.php" --config="$TREE_A/config.php" --i-am-the-rehearsal 2>&1 | tail -2 | tee -a "$PROGRESS"
    if [ -f "$TREE_A/public/local/sentientia_core/cli/bootstrap_substrate.php" ]; then
        php_in "$TREE_A" public/local/sentientia_core/cli/bootstrap_substrate.php 2>&1 | tail -2 | tee -a "$PROGRESS"
    fi
    php_in "$TREE_A" admin/cli/purge_caches.php > "$RUN/logs/purge_A.log" 2>&1
    php_in "$TREE_A" public/local/sentientia_platform/cli/repair_task_registrations.php --apply > "$RUN/logs/repair_tasks_A.log" 2>&1
    log "repair_task_registrations --apply (tree A) exit $?: $(tail -1 "$RUN/logs/repair_tasks_A.log" | cut -c1-160)"
    "${HELPER[@]}" summarize-log "$RUN/logs/install_A.log" > "$RUN/logs/install_A.summary.txt" 2>&1
    log "install log summary: $(head -8 "$RUN/logs/install_A.summary.txt" | tr '\n' ' ' | tr -s ' ')"
fi

# ------------------------------------------------------------------------------------------------------------
# 3. snapshot of the 5.2 site + restore point
# ------------------------------------------------------------------------------------------------------------
if want snapshot; then
    need_state
    run_snapshots "$TREE_A" A
    hard "5.2_all_plugins_uptodate" "$(cat "$RUN/snap/versions-A.rc")" "$(tail -1 "$RUN/snap/versions-A.err")"
    php_in "$TREE_A" admin/cli/check_database_schema.php > "$RUN/logs/schema_A.log" 2>&1
    SRC=$?
    hard "5.2_schema_matches_install_xml" "$SRC" "check_database_schema.php (tree A): exit $SRC, $(tail -2 "$RUN/logs/schema_A.log" | tr '\n' ' ' | cut -c1-160)"
    php_in "$TREE_A" admin/cli/upgrade.php --is-pending > /dev/null 2>&1
    PRC=$?
    hard "5.2_no_upgrade_pending" "$([ "$PRC" -eq 0 ] && echo 0 || echo 1)" "upgrade.php --is-pending (tree A) exit $PRC (0 = nothing pending, 2 = pending)"
    "${HELPER[@]}" dump "$RUN/dumps/uatreh52-at-5.2.sql" 2>&1 | tee -a "$PROGRESS"
fi

# ------------------------------------------------------------------------------------------------------------
# 4. the upgrade
# ------------------------------------------------------------------------------------------------------------
if want upgrade; then
    need_state
    if [ "$FROM" = "upgrade" ]; then
        [ -f "$RUN/dumps/uatreh52-at-5.2.sql" ] || { log "refused: no 5.2 dump to restore from (run the snapshot phase first)"; exit 2; }
        reset_db
        "${HELPER[@]}" restore "$RUN/dumps/uatreh52-at-5.2.sql" 2>&1 | tee -a "$PROGRESS"
    fi
    log "maintenance mode ON (tree A, as the runbook does before the code swap)"
    php_in "$TREE_A" admin/cli/maintenance.php --enable 2>&1 | tail -1 | tee -a "$PROGRESS"
    write_config "$TREE_B"
    guard_config "$TREE_B" || { log "FAIL: config guard on tree B"; exit 1; }
    log "upgrade: Moodle 5.3 + ${SHA_TGT:0:9} over the 5.2 database (admin/cli/upgrade.php --non-interactive, no --allow-unstable)"
    T0="$(date +%s)"
    php_in "$TREE_B" admin/cli/upgrade.php --non-interactive > "$RUN/logs/upgrade_B.log" 2>&1
    URC=$?
    UP_SECS="$(secs "$T0")"
    res upgrade_B_seconds "$UP_SECS"
    hard "5.3_upgrade_exit_0" "$URC" "upgrade.php exit $URC in ${UP_SECS}s; $(grep -E 'completed successfully|Upgrade failed|rror' "$RUN/logs/upgrade_B.log" | tail -1 | cut -c1-140)"
    "${HELPER[@]}" summarize-log "$RUN/logs/upgrade_B.log" > "$RUN/logs/upgrade_B.summary.txt" 2>&1
    log "upgrade log summary: $(head -9 "$RUN/logs/upgrade_B.summary.txt" | tr '\n' ' ' | tr -s ' ')"
    grep -E '^-->' "$RUN/logs/upgrade_B.log" | sed 's/^--> *//' > "$RUN/snap/upgraded_components.txt"
    log "components that ran upgrade steps: $(wc -l < "$RUN/snap/upgraded_components.txt")"
    if [ "$URC" -ne 0 ]; then log "STOP: the upgrade failed. Read $RUN/logs/upgrade_B.log. To retry from the 5.2 site: rerun --from upgrade."; exit 1; fi
    php_in "$TREE_B" admin/cli/maintenance.php --disable 2>&1 | tail -1 | tee -a "$PROGRESS"
    php_in "$TREE_B" admin/cli/purge_caches.php > "$RUN/logs/purge_B.log" 2>&1
    log "purge_caches (tree B) exit $?"
fi

# ------------------------------------------------------------------------------------------------------------
# 5. checks on the 5.3 site
# ------------------------------------------------------------------------------------------------------------
if want checks; then
    need_state
    php_in "$TREE_B" admin/cli/check_database_schema.php > "$RUN/logs/schema_B.log" 2>&1
    SRC=$?
    hard "5.3_schema_matches_install_xml" "$SRC" "check_database_schema.php (tree B): exit $SRC, $(tail -2 "$RUN/logs/schema_B.log" | tr '\n' ' ' | cut -c1-200)"
    run_snapshots "$TREE_B" B
    hard "5.3_all_plugins_uptodate_db_eq_disk" "$("${HELPER[@]}" versions-diff "$RUN/snap/versions-A.tsv" "$RUN/snap/versions-B.tsv" > "$RUN/snap/versions-diff.txt" 2>&1; echo $?)" "$(grep -E '^== after-upgrade' "$RUN/snap/versions-diff.txt" | tr '\n' ' ' | cut -c1-240)"
    "${HELPER[@]}" counts-diff "$RUN/snap/counts-A.tsv" "$RUN/snap/counts-B.tsv" > "$RUN/snap/counts-diff.txt" 2>&1
    CRC=$?
    hard "5.3_no_data_rows_lost" "$CRC" "$(grep -E '^(tables|rows):' "$RUN/snap/counts-diff.txt" | tr '\n' ' ') ; $(grep -E '^== tables whose row count FELL' "$RUN/snap/counts-diff.txt")"
    "${HELPER[@]}" config-diff "$RUN/snap/config-A.tsv" "$RUN/snap/config-B.tsv" > "$RUN/snap/config-diff.txt" 2>&1
    CFRC=$?
    hard "5.3_posture_settings_unchanged" "$CFRC" "theme / forcelogin / enablemyhome / frontpage ...: $(grep -c 'CHANGED$' "$RUN/snap/config-diff.txt") changed"
    diff "$RUN/snap/flags-A.txt" "$RUN/snap/flags-B.txt" > "$RUN/snap/flags-diff.txt" 2>&1
    CHANGED_FLAGS="$(grep -c '^<' "$RUN/snap/flags-diff.txt")"
    NEW_NOT_OFF="$(grep '^>' "$RUN/snap/flags-diff.txt" | grep -vc 'default=off resolved=off overrides=-')"
    hard "5.3_flags_stay_off" "$([ "$CHANGED_FLAGS" -eq 0 ] && [ "$NEW_NOT_OFF" -eq 0 ] && echo 0 || echo 1)" "flags before $(wc -l < "$RUN/snap/flags-A.txt"), after $(wc -l < "$RUN/snap/flags-B.txt"); existing flag lines changed: $CHANGED_FLAGS; new flags not default-off: $NEW_NOT_OFF"
    php_in "$TREE_B" admin/cli/upgrade.php --is-pending > /dev/null 2>&1
    PRC=$?
    hard "5.3_no_upgrade_pending" "$([ "$PRC" -eq 0 ] && echo 0 || echo 1)" "upgrade.php --is-pending (tree B) exit $PRC"
    php_in "$TREE_B" admin/cli/cfg.php --name=release 2>&1 | tail -1 > "$RUN/snap/core_release_B.txt"
    log "core release on the upgraded database: $(cat "$RUN/snap/core_release_B.txt")"
fi

# ------------------------------------------------------------------------------------------------------------
# 6. smoke: scheduled tasks, CLIs, web render
# ------------------------------------------------------------------------------------------------------------
if want smoke; then
    need_state
    php_in "$TREE_B" admin/cli/scheduled_task.php --list > "$RUN/logs/tasks_list_B.log" 2>&1
    log "scheduled_task.php --list: $(grep -c . "$RUN/logs/tasks_list_B.log") lines, $(grep -c 'local_sentientia' "$RUN/logs/tasks_list_B.log") Sentientia tasks"
    res tasks_listed_sentientia "$(grep -c 'local_sentientia' "$RUN/logs/tasks_list_B.log")"
    # Three Sentientia tasks that send no mail and call nothing outside the database (read in the code, 2026-10-15)
    TASKS=('\local_sentientia_api\task\cleanup' '\local_sentientia_xapi\task\purge_old_statements' '\local_sentientia_platform\task\publish_cron_health')
    for t in "${TASKS[@]}"; do
        php_in "$TREE_B" admin/cli/scheduled_task.php --execute="$t" --force > "$RUN/logs/task_$(echo "$t" | tr -c 'A-Za-z0-9\n' '_').log" 2>&1
        TRC=$?
        hard "task_$t" "$TRC" "scheduled_task.php --execute exit $TRC: $(tail -2 "$RUN/logs/task_$(echo "$t" | tr -c 'A-Za-z0-9\n' '_').log" | tr '\n' ' ' | cut -c1-170)"
    done
    php_in "$TREE_B" public/local/sentientia_platform/cli/repair_task_registrations.php > "$RUN/logs/repair_tasks_B_dry.log" 2>&1
    log "repair_task_registrations dry run (tree B) exit $?: $(grep -iE 'WOULD FAIL|problem|0 ' "$RUN/logs/repair_tasks_B_dry.log" | tail -1 | cut -c1-160)"
    if [ -f "$TREE_B/public/local/sentientia_platform/cli/import_bizlms.php" ]; then
        php_in "$TREE_B" public/local/sentientia_platform/cli/import_bizlms.php --list > "$RUN/logs/import_list_B.log" 2>&1
        log "import_bizlms.php --list exit $?: $(grep -iE 'importer\(s\) registered|registry_invalid' "$RUN/logs/import_list_B.log" | tr '\n' ' ' | cut -c1-160)"
    fi
    php_in "$TREE_B" public/local/sentientia_platform/cli/cron_health.php > "$RUN/logs/cron_health_B.log" 2>&1
    log "cron_health.php exit $?: $(tail -1 "$RUN/logs/cron_health_B.log" | cut -c1-160)"
    if [ "$RENDER" = "1" ]; then
        # Web render on PHP's built-in server (127.0.0.1 only): proves the 5.3 web entry points, the theme and the router
        # boot on the upgraded database. Slow on the first request (no opcache, AV load); each request waits up to 280 s.
        URL="http://127.0.0.1:$RENDER_PORT"
        ( cd "$TREE_B/public" && REHEARSAL_WWWROOT="$URL" "$PHP_BIN" -c "$PHP_INI" -d memory_limit=2G -S "127.0.0.1:$RENDER_PORT" -t "$TREE_B/public" > "$RUN/logs/php_server.log" 2>&1 & echo $! > "$RUN/php_server.pid" )
        sleep 3
        for path in /login/index.php /index.php /course/index.php /admin/index.php; do
            code="$(curl -s -o "$RUN/logs/render$(echo "$path" | tr '/' '_').html" -w '%{http_code}' --max-time 280 "$URL$path" 2>/dev/null)"
            body="$RUN/logs/render$(echo "$path" | tr '/' '_').html"
            nbad="$(grep -ciE 'Fatal error|Parse error|Stack trace|Exception - |Error code:' "$body" 2>/dev/null)"
            ndbg="$(grep -ciE '<b>(Warning|Notice|Deprecated)</b>|Debugging:|debuginfo' "$body" 2>/dev/null)"
            log "render $path -> HTTP $code, $(wc -c < "$body" 2>/dev/null) bytes, fatal-error markers $nbad, warning/debug markers $ndbg"
            res "render$path" "HTTP $code; fatal markers $nbad; debug markers $ndbg"
        done
        taskkill //PID "$(cat "$RUN/php_server.pid" 2>/dev/null)" //F >/dev/null 2>&1
        # the built-in server runs under a different PID than the subshell on Windows: stop it by listening port
        for pid in $(netstat -ano 2>/dev/null | grep -E "127\.0\.0\.1:$RENDER_PORT .*LISTENING" | awk '{print $5}' | sort -u); do taskkill //PID "$pid" //F >/dev/null 2>&1; done
    fi
fi

# ------------------------------------------------------------------------------------------------------------
# 7. MySQL 8.4 reserved-word scan of the schema changes in the range
# ------------------------------------------------------------------------------------------------------------
if want scan; then
    load_state
    python -I "$HERE/mysql84_reserved_scan.py" --base "$SHA_UAT" --head "$SHA_TGT" ${TREE_B:+--moodle-root "$TREE_B"} > "$RUN/snap/reserved_scan.txt" 2>&1
    SRC=$?
    hard "mysql84_reserved_words" "$SRC" "$(grep -E '^RESULT:' "$RUN/snap/reserved_scan.txt" | cut -c1-200)"
    python -I "$HERE/mysql84_reserved_scan.py" --base "$SHA_UAT" --head "$SHA_TGT" --list-words > "$RUN/snap/reserved_words.txt" 2>&1
fi

# ------------------------------------------------------------------------------------------------------------
# 8. report
# ------------------------------------------------------------------------------------------------------------
if want report; then
    RID="$(date +%Y%m%d-%H%M%S)"
    OUT="$RUN/reports/$RID"
    mkdir -p "$OUT"
    cp -f "$RESULTS" "$OUT/results.tsv" 2>/dev/null
    for f in versions-diff.txt counts-diff.txt config-diff.txt flags-diff.txt reserved_scan.txt reserved_words.txt upgraded_components.txt; do cp -f "$RUN/snap/$f" "$OUT/$f" 2>/dev/null; done
    for f in install_A.summary.txt upgrade_B.summary.txt serverinfo.txt tasks_list_B.log repair_tasks_B_dry.log import_list_B.log cron_health_B.log; do cp -f "$RUN/logs/$f" "$OUT/$f" 2>/dev/null; done
    { echo "UAT commit  $SHA_UAT"; echo "target ref  $TARGET_REF = $SHA_TGT"; echo "tree A      $(cat "$RUN/treeA" 2>/dev/null)"; echo "tree B      $(cat "$RUN/treeB" 2>/dev/null)";
      echo "php         $PHPV"; echo "database    $(tr -d '\r' < "$RUN/logs/serverinfo.txt" | head -1)"; echo; echo "hard checks:"; grep -E '^(PASS|FAIL):' "$RESULTS" | sed 's/\t/  -  /'; } > "$OUT/SUMMARY.txt"
    cat "$OUT/SUMMARY.txt"
    log "result files: $OUT"
fi

# ------------------------------------------------------------------------------------------------------------
if { [ "$SHUTDOWN" = "own" ] && [ "$STARTED_DB" = "1" ]; } || [ "$SHUTDOWN" = "always" ]; then
    log "shutting the private MariaDB down"
    python "$MDB_DIR/mdb114_admin.py" shutdown 2>&1 | tail -1 | tee -a "$PROGRESS"
fi
if [ "${#FAILS[@]}" -gt 0 ]; then log "=== rehearsal end: ${#FAILS[@]} hard check(s) FAILED: ${FAILS[*]}"; exit 1; fi
log "=== rehearsal end: every hard check of the phases that ran passed"
exit 0
