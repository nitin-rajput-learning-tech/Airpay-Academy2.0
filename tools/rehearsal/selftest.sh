#!/usr/bin/env bash
# selftest.sh -- tests of the rehearsal kit's own safety rules and helpers. No database and no Moodle needed (bash, PHP and
# the usual coreutils); every kit script is run in DRY mode or its functions are called directly.
#
#   bash tools/rehearsal/selftest.sh
#
# What it proves (each line below is a test; the Stage B tools review of 2026-10-08 added the database name guard, the live endpoint, the dump scan, the recovery helpers of steps 04, 06 and 09, the state rotation and the cache configuration):
#   * every script passes bash -n;
#   * the env policy refuses: a database that is not on the allow-list, a system or production-looking database in the allow-list,
#     an empty allow-list, a production hostname in the database host or the wwwroot;
#   * the preflight refuses a config.php that points at another database, has noemailever off, names another wwwroot, or holds a
#     production hostname in any string, and accepts a config written by lib/make_config.sh;
#   * a config.php written by make_config.sh round-trips a hostile password, parses as PHP, loads when its database is allow-listed
#     and exits (REHEARSAL GUARD) when it is not; a config.php the kit did not write is never overwritten;
#   * the file store comparison finds missing and extra content hashes and ignores sentinel files;
#   * judge() stops on exit 2 unless the written acceptance is referenced;
#   * unpack_tree refuses a wrong SHA-256 and a "zip" that is really a tar, and unpacks a good archive;
#   * step 01 in --execute mode against a stand-in mysql client (fix round 3): RESTORE_DONE_BY_HAND with RESTORE_DB_DUMP is refused; a
#     restore the kit started and did not complete is refused whatever RESTORE_DONE_BY_HAND says, and is cleared only by the kit
#     seeing the database empty; a plain hand restore is adopted;
#   * run_all.sh --list and a DRY --only run work; no Windows path or drive letter is hard-coded in the kit.
# Exit 0 = every test passed.

set -uo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
T="$(mktemp -d "${TMPDIR:-/tmp}/rehearsal-selftest.XXXXXX")"
trap 'rm -rf "$T"' EXIT
PASS=0
FAILS=0

ok() { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAILS=$((FAILS + 1)); printf '  FAIL  %s\n' "$1"; [ -n "${2:-}" ] && printf '%s\n' "$2" | head -n 12 | sed 's/^/        /'; return 0; }

# base_env FILE [KEY=value ...]: a valid env file; later lines override (the file is sourced top to bottom).
base_env() {
    local f="$1"
    shift
    {
        printf 'REHEARSAL_WORK=%s/work\nWEB_USER=\nPHP_BIN=php\nMYSQL_BIN=mysql\n' "$T"
        printf 'CODE_45_DIR=%s/moodle45\nCODE_5X_DIR=%s/moodle5\nMOODLEDATA=%s/moodledata\n' "$T" "$T" "$T"
        printf 'DB_TYPE=mysqli\nDB_HOST=127.0.0.1\nDB_NAME=stageb_selftest\nDB_USER=rehearsal\nDB_PASS_FILE=%s/db.pass\n' "$T"
        printf 'REHEARSAL_DB_ALLOWLIST="stageb_selftest"\nREHEARSAL_WWWROOT=https://rehearsal.example.invalid\n'
        printf 'PRODUCTION_HOSTNAMES="airpay.academy"\n'
        local kv
        for kv in "$@"; do
            printf '%s\n' "$kv"
        done
    } > "$f"
    chmod 600 "$f"
}

# kit SCRIPT ENV [args]: run a kit script (DRY), keep output in $OUT and the status in $RC.
OUT=""
RC=0
kit() {
    local script="$1" env="$2"
    shift 2
    OUT="$(bash "$KIT/$script" --env "$env" "$@" 2>&1)"
    RC=$?
}

# in_kit FUNCTION [args]: run a function of this file in a subshell that has the kit libraries loaded; prints its output,
# then rc=N (the subshell stops at die, so the status has to be read outside it).
in_kit() {
    local out rc
    out="$( ( . "$KIT/lib/make_config.sh"; "$@" ) 2>&1 )"
    rc=$?
    printf '%s\nrc=%s\n' "$out" "$rc"
}

expect_refused() {
    # expect_refused "name" ENV SCRIPT "pattern"
    kit "$3" "$2"
    if [ "$RC" -ne 0 ] && printf '%s' "$OUT" | grep -q "$4"; then ok "$1"; else bad "$1 (rc ${RC}, wanted a refusal matching '$4')" "$OUT"; fi
}

printf 'syntax\n'
for f in "$KIT"/*.sh "$KIT"/lib/*.sh; do
    if bash -n "$f" 2> /dev/null; then ok "bash -n $(basename "$f")"; else bad "bash -n $(basename "$f")"; fi
done
for f in "$KIT"/lib/*.php; do
    if php -l "$f" > /dev/null 2>&1; then ok "php -l $(basename "$f")"; else bad "php -l $(basename "$f")"; fi
done

printf 'env policy (preflight, DRY)\n'
base_env "$T/ok.env"
kit 00_preflight.sh "$T/ok.env"
if [ "$RC" = 0 ] && printf '%s' "$OUT" | grep -q 'preflight passed'; then ok "a valid env passes the preflight"; else bad "a valid env passes the preflight (rc ${RC})" "$OUT"; fi

base_env "$T/a.env" "DB_NAME=other_db"
expect_refused "a database not on the allow-list is refused" "$T/a.env" 00_preflight.sh 'not on the rehearsal allow-list'
base_env "$T/b.env" 'REHEARSAL_DB_ALLOWLIST="moodle"' "DB_NAME=moodle"
expect_refused "the system database name 'moodle' is refused even when listed" "$T/b.env" 00_preflight.sh 'production or system database'
base_env "$T/c.env" 'REHEARSAL_DB_ALLOWLIST="airpay_prod"' "DB_NAME=airpay_prod"
expect_refused "a production-looking database name is refused even when listed" "$T/c.env" 00_preflight.sh 'production or system database'
base_env "$T/d.env" 'REHEARSAL_DB_ALLOWLIST=""'
expect_refused "an empty allow-list is refused" "$T/d.env" 00_preflight.sh 'REHEARSAL_DB_ALLOWLIST'
base_env "$T/e.env" "REHEARSAL_WWWROOT=https://www.airpay.academy"
expect_refused "the production wwwroot is refused" "$T/e.env" 00_preflight.sh 'production host'
base_env "$T/f.env" "DB_HOST=prod-db.cluster.airpay.academy"
expect_refused "a production database host is refused" "$T/f.env" 00_preflight.sh 'production host'
base_env "$T/g.env" "REHEARSAL_WWWROOT=https://rehearsal.example.invalid/"
expect_refused "a wwwroot with a trailing slash is refused" "$T/g.env" 00_preflight.sh 'trailing slash\|must not end'
base_env "$T/h.env" 'MOODLEDATA=C:\moodledata'
expect_refused "a Windows data path is refused" "$T/h.env" 00_preflight.sh 'not an absolute POSIX path'
base_env "$T/i.env" "CODE_5X_DIR=$T/moodle45"
expect_refused "the two code trees may not be one directory" "$T/i.env" 00_preflight.sh 'same directory'

printf 'config.php checks (preflight, DRY)\n'
# A config written by the kit: render it through make_config.sh's own function.
printf 'p@ss'"'"'w"ord\\&$x`y' > "$T/db.pass"
chmod 600 "$T/db.pass"
mkdir -p "$T/moodle45" "$T/moodle5/public"
write_cfg() {
    # write_cfg FILE [sed expression]: a kit-style config, optionally edited.
    (
        # shellcheck disable=SC1091
        . "$KIT/lib/make_config.sh"
        KIT_CONFIG_MARKER="REHEARSAL-KIT-CONFIG"
        DB_TYPE=mysqli DB_HOST=127.0.0.1 DB_NAME=stageb_selftest DB_USER=rehearsal DB_PREFIX=mdl_ DB_PORT="" DB_COLLATION=utf8mb4_unicode_ci
        DB_PASS_FILE="$T/db.pass" REHEARSAL_WWWROOT=https://rehearsal.example.invalid MOODLEDATA="$T/moodledata" DIVERT_EMAILS_TO=""
        REHEARSAL_DB_ALLOWLIST="stageb_selftest" PRODUCTION_HOSTNAMES="airpay.academy" REHEARSAL_WORK="$T/work"
        render_config 45
    ) > "$1" 2> /dev/null
    if [ -n "${2:-}" ]; then
        sed -i "$2" "$1"
    fi
}
write_cfg "$T/moodle45/config.php"
kit 00_preflight.sh "$T/ok.env"
if [ "$RC" = 0 ] && printf '%s' "$OUT" | grep -q 'points only at the rehearsal'; then ok "a kit-written config.php is accepted"; else bad "a kit-written config.php is accepted (rc ${RC})" "$OUT"; fi

write_cfg "$T/moodle45/config.php" "s/noemailever = true;/noemailever = false;/"
expect_refused "a config with noemailever off is refused" "$T/ok.env" 00_preflight.sh 'noemailever'
write_cfg "$T/moodle45/config.php" "s/CFG->dbname    = 'stageb_selftest'/CFG->dbname    = 'moodle'/"
expect_refused "a config that points at another database is refused" "$T/ok.env" 00_preflight.sh 'allow-list'
write_cfg "$T/moodle45/config.php" "s#rehearsal.example.invalid#www.airpay.academy#"
expect_refused "a config with the production wwwroot is refused" "$T/ok.env" 00_preflight.sh 'wwwroot'
write_cfg "$T/moodle45/config.php" "s#^\\\$CFG->admin .*#\$CFG->smtphosts = 'mail.airpay.academy:587';#"
expect_refused "a production hostname in any config string is refused" "$T/ok.env" 00_preflight.sh 'production host'
rm -f "$T/moodle45/config.php"

printf 'make_config.sh\n'
write_cfg "$T/cfg.php"
want="p@ss'w\"ord\\&\$x\`y"
got="$(SOURCE_BASELINE_PHP="$KIT/../../moodle-enhancement/local/sentientia_platform/cli/source_baseline.php" php "$KIT/lib/config_probe.php" "$T/cfg.php" --get=dbpass 2> /dev/null)"
if [ "$got" = "$want" ]; then ok "a hostile password round-trips through the generated config.php"; else bad "password round trip: got '${got}'"; fi
if php -l "$T/cfg.php" > /dev/null 2>&1; then ok "the generated config.php is valid PHP"; else bad "the generated config.php is valid PHP"; fi
# The guard, executed: a stub lib/setup.php stands in for Moodle.
mkdir -p "$T/guard/lib"
printf '<?php\n' > "$T/guard/lib/setup.php"
cp "$T/cfg.php" "$T/guard/config.php"
res="$(php -r 'define("CLI_SCRIPT", true); require $argv[1]; echo "loaded";' "$T/guard/config.php" 2>&1)"
if [ "$res" = "loaded" ]; then ok "the guard lets the allow-listed database load"; else bad "the guard lets the allow-listed database load" "$res"; fi
sed -i "s/CFG->dbname    = 'stageb_selftest'/CFG->dbname    = 'somewhere_else'/" "$T/guard/config.php"
res="$(php -r 'define("CLI_SCRIPT", true); require $argv[1]; echo "loaded";' "$T/guard/config.php" 2>&1; echo "rc=$?")"
if printf '%s' "$res" | grep -q 'REHEARSAL GUARD' && printf '%s' "$res" | grep -q 'rc=1'; then ok "the guard exits when the database is not allow-listed"; else bad "the guard exits when the database is not allow-listed" "$res"; fi
cp "$T/cfg.php" "$T/guard/config.php"
sed -i "s#rehearsal.example.invalid#www.airpay.academy#" "$T/guard/config.php"
res="$(php -r 'define("CLI_SCRIPT", true); require $argv[1]; echo "loaded";' "$T/guard/config.php" 2>&1; echo "rc=$?")"
if printf '%s' "$res" | grep -q 'production host' && printf '%s' "$res" | grep -q 'rc=1'; then ok "the guard exits when the wwwroot names a production host"; else bad "the guard exits when the wwwroot names a production host" "$res"; fi
# A foreign config is never overwritten.
printf '<?php\n$CFG->dbname = "x";\n' > "$T/foreign.php"
t_foreign() {
    EXECUTE=1 STEP_ID=t DB_PASS_FILE="$T/db.pass" DB_NAME=stageb_selftest REHEARSAL_DB_ALLOWLIST=stageb_selftest
    write_kit_file "$T/foreign.php" "<?php new" 022 "test"
}
res="$(in_kit t_foreign)"
if printf '%s' "$res" | grep -q 'not written by the kit' && printf '%s' "$res" | grep -q 'rc=1' && grep -q '"x"' "$T/foreign.php"; then
    ok "a config.php the kit did not write is not overwritten"
else bad "a config.php the kit did not write is not overwritten" "$res"; fi

printf 'file store comparison\n'
(
    # shellcheck disable=SC1091
    . "$KIT/lib/common.sh"
    mkdir -p "$T/fd/aa/bb" "$T/fd/11/22" "$T/fd/de/ad"
    : > "$T/fd/aa/bb/aabbccddeeff00112233445566778899aabbccdd"
    : > "$T/fd/11/22/1122334455667788990011223344556677889900"
    : > "$T/fd/de/ad/deadbeefdeadbeefdeadbeefdeadbeefdeadbeef"
    : > "$T/fd/warning.txt"
    : > "$T/fd/aa/bb/not-a-hash.tmp"
    # the database knows aabb..., 1122... and a hash that is not on disk
    printf '%s\n' aabbccddeeff00112233445566778899aabbccdd 1122334455667788990011223344556677889900 \
        ffeeddccbbaa99887766554433221100ffeeddcc | expected_paths | LC_ALL=C sort -u > "$T/db.txt"
    disk_paths "$T/fd" | LC_ALL=C sort -u > "$T/disk.txt"
    [ "$(comm_only_first "$T/db.txt" "$T/disk.txt")" = "ff/ee/ffeeddccbbaa99887766554433221100ffeeddcc" ] || exit 11
    [ "$(comm_only_second "$T/db.txt" "$T/disk.txt")" = "de/ad/deadbeefdeadbeefdeadbeefdeadbeefdeadbeef" ] || exit 12
    [ "$(wc -l < "$T/disk.txt" | tr -d ' ')" = 3 ] || exit 13
) && ok "missing and extra content hashes are found; warning.txt and stray files are ignored" || bad "file store comparison (exit $?)"

printf 'judge()\n'
t_judge() {
    EXECUTE=0 STEP_ID=t ACCEPT_UNPROVEN="$1" ACCEPT_UNPROVEN_REF="$2"
    judge "x" "$3" > /dev/null 2>&1
}
rc_of() { tail -n 1 | sed 's/^rc=//'; }
[ "$(in_kit t_judge 0 "" 2 | rc_of)" = 2 ] && ok "exit 2 stops without a written acceptance" || bad "exit 2 without acceptance"
[ "$(in_kit t_judge 1 TICKET-1 2 | rc_of)" = 0 ] && ok "exit 2 is accepted with ACCEPT_UNPROVEN=1 and a reference" || bad "exit 2 with acceptance"
[ "$(in_kit t_judge 1 "" 2 | rc_of)" = 2 ] && ok "ACCEPT_UNPROVEN=1 without a reference does not accept" || bad "acceptance without a reference"
[ "$(in_kit t_judge 1 TICKET-1 1 | rc_of)" = 1 ] && ok "exit 1 (drift) always stops, even with an acceptance" || bad "exit 1 stops"
[ "$(in_kit t_judge 0 "" 0 | rc_of)" = 0 ] && ok "exit 0 passes" || bad "exit 0 passes"

printf 'unpack_tree()\n'
mkdir -p "$T/src/moodle/admin/cli"
printf '<?php\n' > "$T/src/moodle/version.php"
printf '<?php\n' > "$T/src/moodle/admin/cli/upgrade.php"
tar -C "$T/src" -czf "$T/core.tar.gz" moodle
sha="$(sha256sum "$T/core.tar.gz" | cut -d ' ' -f 1)"
t_unpack() {
    EXECUTE=1 STEP_ID=t LOG_DIR="$T/logs" TIMINGS_FILE="$T/logs/timings.tsv"
    mkdir -p "$LOG_DIR"
    unpack_tree "$1" "$2" "$3" version.php "test tree"
}
run_unpack() { in_kit t_unpack "$@"; }
res="$(run_unpack "$T/core.tar.gz" "0000" "$T/dest1")"
if printf '%s' "$res" | grep -q 'expected 0000' && printf '%s' "$res" | grep -q 'rc=1'; then ok "a wrong SHA-256 is refused"; else bad "a wrong SHA-256 is refused" "$res"; fi
res="$(run_unpack "$T/core.tar.gz" "$sha" "$T/dest2")"
if printf '%s' "$res" | grep -q 'rc=0' && [ -f "$T/dest2/version.php" ] && [ -f "$T/dest2/admin/cli/upgrade.php" ]; then ok "a good archive is unpacked to the tree root"; else bad "a good archive is unpacked" "$res"; fi
res="$(run_unpack "$T/core.tar.gz" "$sha" "$T/dest2")"
if printf '%s' "$res" | grep -q 'not unpacking'; then ok "unpacking is skipped when the tree is already there (idempotent)"; else bad "unpack idempotence" "$res"; fi
cp "$T/core.tar.gz" "$T/fake.zip"
res="$(run_unpack "$T/fake.zip" "$sha" "$T/dest3")"
if printf '%s' "$res" | grep -q 'GNU tar trap' && printf '%s' "$res" | grep -q 'rc=1'; then ok "a tar named .zip is refused"; else bad "a tar named .zip is refused" "$res"; fi
mkdir -p "$T/dest4/junk"
res="$(run_unpack "$T/core.tar.gz" "$sha" "$T/dest4")"
if printf '%s' "$res" | grep -q 'refusing to unpack over it' && printf '%s' "$res" | grep -q 'rc=1'; then ok "a non-empty directory without code is not unpacked over"; else bad "non-empty directory refusal" "$res"; fi

printf 'orchestrator\n'
res="$(bash "$KIT/run_all.sh" --list 2>&1)"
if [ "$(printf '%s\n' "$res" | wc -l | tr -d ' ')" = 13 ]; then ok "run_all.sh --list shows 13 steps"; else bad "run_all.sh --list" "$res"; fi
res="$(bash "$KIT/run_all.sh" --env "$T/ok.env" --only 00,12 2>&1)"
if printf '%s' "$res" | grep -q 'finished: every selected step ok (DRY mode)'; then ok "run_all.sh --only 00,12 runs in DRY mode"; else bad "run_all.sh --only 00,12" "$res"; fi
res="$(bash "$KIT/run_all.sh" --env "$T/a.env" --only 00 2>&1; echo "rc=$?")"
if printf '%s' "$res" | grep -q 'rc=1'; then ok "run_all.sh stops with the failed step's exit code"; else bad "run_all.sh failure exit code" "$res"; fi

printf 'database name guard (Stage B tools review)\n'
for n in airpayprod sentientia_uat AirpayProd stageb_uat_copy my_prod_copy production live_db; do
    if [ "$(in_kit db_name_denied "$n" | tail -n 1)" = "rc=0" ]; then ok "'${n}' can never be a rehearsal database"; else bad "'${n}' can never be a rehearsal database"; fi
done
for n in stageb_rehearsal stageb_selftest rehearsal_april; do
    if [ "$(in_kit db_name_denied "$n" | tail -n 1)" = "rc=1" ]; then ok "'${n}' is an acceptable rehearsal database name"; else bad "'${n}' is an acceptable rehearsal database name"; fi
done
base_env "$T/ap.env" 'REHEARSAL_DB_ALLOWLIST="airpayprod"' "DB_NAME=airpayprod"
expect_refused "airpayprod (production's own database name) is refused even when listed" "$T/ap.env" 00_preflight.sh 'production or system database'
base_env "$T/ua.env" 'REHEARSAL_DB_ALLOWLIST="sentientia_uat"' "DB_NAME=sentientia_uat"
expect_refused "sentientia_uat (UAT's database) is refused even when listed" "$T/ua.env" 00_preflight.sh 'production or system database'

printf 'the live database endpoint\n'
base_env "$T/ep.env" "PRODUCTION_DB_ENDPOINT=airpay-live.cluster-c1x.ap-south-1.rds.amazonaws.com" "DB_HOST=airpay-live.cluster-c1x.ap-south-1.rds.amazonaws.com"
expect_refused "a DB_HOST that is the live database endpoint is refused" "$T/ep.env" 00_preflight.sh 'production host'
base_env "$T/ep2.env"
OUT="$(bash "$KIT/00_preflight.sh" --env "$T/ep2.env" --execute 2>&1)"
RC=$?
if [ "$RC" -ne 0 ] && printf '%s' "$OUT" | grep -q 'PRODUCTION_DB_ENDPOINT is empty'; then ok "--execute refuses to start while PRODUCTION_DB_ENDPOINT is empty"; else bad "--execute refuses an empty PRODUCTION_DB_ENDPOINT (rc ${RC})" "$OUT"; fi
base_env "$T/ep3.env" "PRODUCTION_DB_ENDPOINT=CHANGE_ME"
expect_refused "a placeholder PRODUCTION_DB_ENDPOINT is refused" "$T/ep3.env" 00_preflight.sh 'placeholder'
base_env "$T/ep4.env" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
kit 00_preflight.sh "$T/ep4.env"
if printf '%s' "$OUT" | grep -q 'preflight passed'; then ok "a DRY preflight passes with the endpoint set (and a different DB_HOST)"; else bad "a DRY preflight with the endpoint set" "$OUT"; fi

printf 'the dump that is restored (step 01)\n'
mkdir -p "$T/dumps"
cat > "$T/dumps/good-body.sql" <<'SQL'
-- MySQL dump 10.13
DROP TABLE IF EXISTS `mdl_t`;
CREATE TABLE `mdl_t` (
  `use` int(11) NOT NULL,
  `id` int(11) NOT NULL
);
INSERT INTO `mdl_t` VALUES (1,2),(3,4);
INSERT INTO `mdl_x` VALUES ('use `a`; create database x;');
SQL
{ cat "$T/dumps/good-body.sql"; printf -- '-- Dump completed on 2026-04-06  7:54:07\n'; } > "$T/dumps/good.sql"
{ printf -- 'USE `airpayprod`;\n'; cat "$T/dumps/good.sql"; } > "$T/dumps/use.sql"
{ printf -- 'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `airpayprod` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;\n'; cat "$T/dumps/good.sql"; } > "$T/dumps/createdb.sql"
{ printf -- '/*!40000 DROP DATABASE IF EXISTS `airpayprod`*/;\n'; cat "$T/dumps/good.sql"; } > "$T/dumps/dropdb.sql"
{ head -c 200 "$T/dumps/good.sql"; } > "$T/dumps/cut.sql"
gzip -c "$T/dumps/good.sql" > "$T/dumps/good.sql.gz"
gzip -c "$T/dumps/use.sql" > "$T/dumps/use.sql.gz"
gzip -c "$T/dumps/good-body.sql" > "$T/dumps/notrailer.sql.gz"
res="$(in_kit dump_unsafe_statement "$T/dumps/good.sql")"
if [ -z "$(printf '%s' "$res" | head -n 1)" ]; then ok "a dump with a column called use and a row text 'create database' holds no unsafe statement"; else bad "a clean dump is not flagged" "$res"; fi
for f in use createdb dropdb; do
    res="$(in_kit dump_unsafe_statement "$T/dumps/${f}.sql")"
    if printf '%s' "$res" | head -n 1 | grep -q 'airpayprod'; then ok "a dump with a ${f} statement is flagged"; else bad "a dump with a ${f} statement is flagged" "$res"; fi
done
res="$(in_kit dump_unsafe_statement "$T/dumps/use.sql.gz")"
if printf '%s' "$res" | head -n 1 | grep -q 'airpayprod'; then ok "the scan reads a .gz dump too"; else bad "the scan reads a .gz dump" "$res"; fi
for pair in "good.sql:0" "cut.sql:1" "good.sql.gz:0" "notrailer.sql.gz:1"; do
    f="${pair%%:*}"; want="${pair##*:}"
    res="$(in_kit dump_has_trailer "$T/dumps/$f")"
    if [ "$(printf '%s' "$res" | tail -n 1)" = "rc=${want}" ]; then ok "the 'Dump completed' trailer check of ${f}: rc ${want}"; else bad "trailer check of ${f}" "$res"; fi
done

printf 'step 06: role 9 re-run, and step 04: the pre-repair invariant\n'
line='DRY RUN: 0 capabilities would be prohibited (7 already are) and 0 allow row(s) removed for role administrator. Nothing changed.'
res="$(printf 'noise\n%s\nWARNING x\n' "$line" | in_kit adr031_role9_dry_counts)"
if [ "$(printf '%s' "$res" | head -n 1)" = "0 0" ]; then ok "role 9 dry run: nothing left to do reads as 0 0"; else bad "role 9 dry counts 0 0" "$res"; fi
res="$(printf 'DRY RUN: 3 capabilities would be prohibited (4 already are) and 2 allow row(s) removed for role x. Nothing changed.\n' | in_kit adr031_role9_dry_counts)"
if [ "$(printf '%s' "$res" | head -n 1)" = "3 2" ]; then ok "role 9 dry run: changes wanted read as 3 2"; else bad "role 9 dry counts 3 2" "$res"; fi
res="$(printf 'DRY RUN: 3 capabilities would be prohibited (0 already are) and an unknown number of allow row(s) removed for role x.\n' | in_kit adr031_role9_dry_counts)"
if [ "$(printf '%s' "$res" | tail -n 1)" = "rc=1" ]; then ok "role 9 dry run with PART 2 unknown prints no counts (never read as nothing to do)"; else bad "role 9 dry counts unknown" "$res"; fi
printf 'RESULT: 1 invariant(s) FAILED (message_provider_defaults).\n        message_provider_defaults: run x\n' > "$T/pr1.txt"
printf 'RESULT: 1 invariant(s) FAILED (message_provider_defaults).\nRESULT: 2 metric(s) DRIFTED - investigate before proceeding.\n' > "$T/pr2.txt"
printf 'RESULT: 2 invariant(s) FAILED (message_provider_defaults, bizlms_import).\n' > "$T/pr3.txt"
printf 'RESULT: 100%% PARITY\n' > "$T/pr4.txt"
for pair in "pr1:0" "pr2:1" "pr3:1" "pr4:1"; do
    f="${pair%%:*}"; want="${pair##*:}"
    res="$(in_kit parity_only_pre_repair_invariant "$T/${f}.txt")"
    if [ "$(printf '%s' "$res" | tail -n 1)" = "rc=${want}" ]; then ok "only-the-pre-repair-invariant check of ${f}: rc ${want}"; else bad "pre-repair invariant check ${f}" "$res"; fi
done

printf 'step 09: where a re-run stands\n'
for case_ in ':::fresh' '1::apply:recorded' ':complete:apply:recover' ':failed:resume:resume' ':running:resume:resume' ':aborted:apply:refuse' ':failed:apply:refuse' '1:failed:resume:recorded'; do
    IFS=: read -r a r m want <<< "$case_"
    res="$(in_kit import_phase "$a" "$r" "$m" | head -n 1)"
    case "$res" in
        "$want"*) ok "import_phase applied='${a}' run='${r}' mode='${m}' -> ${want}" ;;
        *) bad "import_phase applied='${a}' run='${r}' mode='${m}' -> ${want}" "$res" ;;
    esac
done

printf 'cron scan, tree manifest, state rotation\n'
t_names() { CODE_45_DIR=/srv/r/m45 CODE_5X_DIR=/srv/r/m5/ names_our_tree "$1"; }
[ "$(in_kit t_names '* * * * * www-data php /srv/r/m5/public/cron.php' | tail -n 1)" = rc=0 ] && ok "a cron line naming CODE_5X_DIR is this rehearsal's" || bad "cron line naming CODE_5X_DIR"
[ "$(in_kit t_names '* * * * * php /var/www/uat/admin/cli/cron.php' | tail -n 1)" = rc=1 ] && ok "a cron line of another site is not" || bad "cron line of another site"
mkdir -p "$T/tree/a" "$T/tree/b/c"
printf '<?php $release = 1;\n' > "$T/tree/version.php"
printf '<?php $plugin = 1;\n' > "$T/tree/a/version.php"
printf '<?php $plugin = 2;\n' > "$T/tree/b/c/version.php"
m1="$(in_kit tree_manifest_sha "$T/tree" | head -n 1)"
touch -d '2001-01-01' "$T/tree/a/version.php"
m2="$(in_kit tree_manifest_sha "$T/tree" | head -n 1)"
printf '<?php $plugin = 3;\n' > "$T/tree/b/c/version.php"
m3="$(in_kit tree_manifest_sha "$T/tree" | head -n 1)"
if [[ "$m1" =~ ^[0-9a-f]{64}$ ]] && [ "$m1" = "$m2" ] && [ "$m1" != "$m3" ]; then ok "the tree manifest hash follows the content of every version.php, not file times"; else bad "tree manifest hash" "$m1 / $m2 / $m3"; fi
t_rotate() {
    EXECUTE=1 STEP_ID=t REHEARSAL_WORK="$T/rw" STATE_DIR="$T/rw/state" REPORT_DIR="$T/rw/reports" BASELINE_DIR="$T/rw/baseline" TIMINGS_FILE="$T/rw/logs/timings.tsv"
    mkdir -p "$STATE_DIR/kv" "$REPORT_DIR" "$BASELINE_DIR" "$T/rw/logs"
    printf 'old1234567890\n' > "$STATE_DIR/kv/restore.id"
    printf 'status=ok\n' > "$STATE_DIR/00.status"
    printf 'status=ok\n' > "$STATE_DIR/03.status"
    printf 'x\n' > "$REPORT_DIR/summary.md"
    printf '{}\n' > "$BASELINE_DIR/source-baseline.json"
    printf 't\n' > "$TIMINGS_FILE"
    mkdir -p "$T/rw/muc"
    printf 'x\n' > "$T/rw/muc/cacheconfig.php"
    work_state_has_history || exit 11
    rotate_work_state > /dev/null
    work_state_has_history && exit 12
    [ -f "$STATE_DIR/00.status" ] || exit 13
    [ ! -e "$STATE_DIR/kv/restore.id" ] || exit 14
    [ ! -e "$TIMINGS_FILE" ] || exit 15
    archived="$(find "$T/rw/archive" -name restore.id | wc -l | tr -d ' ')"
    [ "$archived" = 1 ] || exit 16
    [ -n "$(find "$T/rw/archive" -name source-baseline.json)" ] || exit 17
    [ -n "$(find "$T/rw/archive" -name summary.md)" ] || exit 18
    [ -n "$(find "$T/rw/archive" -name cacheconfig.php)" ] || exit 19
    [ ! -e "$T/rw/muc" ] || exit 20
}
res="$(in_kit t_rotate)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "a new restore moves the earlier rehearsal's state, reports, baseline and timings to archive/ (the preflight status stays)"; else bad "state rotation" "$res"; fi

printf 'cron scan: the tree as a whole path (Stage B tools fix round 2)\n'
t_names2() { CODE_45_DIR=/srv/r/m45 CODE_5X_DIR=/srv/r/m5 names_our_tree "$1"; }
for pair in 'cd /srv/r/m5 && php admin/cli/cron.php:0' '*/5 * * * * www-data cd "/srv/r/m45"; php admin/cli/cron.php:0' \
            '* * * * * php /srv/r/m5/admin/cli/cron.php:0' 'cd /srv/r/m5-uat && php admin/cli/cron.php:1' \
            'cd /srv/r/m55 && php cron.php:1' 'cd /mnt/srv/r/m5 && php cron.php:1' 'cd /srv/r/m5.bak && php cron.php:1' \
            '* * * * * php /var/www/uat/admin/cli/cron.php:1' 'cd /srv/r/m5-uat; cd /srv/r/m5 && php cron.php:0'; do
    text="${pair%:*}"; want="${pair##*:}"
    if [ "$(in_kit t_names2 "$text" | tail -n 1)" = "rc=${want}" ]; then ok "cron line '${text}': rc ${want}"; else bad "cron line '${text}': rc ${want}"; fi
done

printf 'the moodledata marker: which archive was unpacked, and whether it finished\n'
t_marker() {
    MOODLEDATA="$T/mdmark"
    mkdir -p "$MOODLEDATA"
    printf 'x' > "$T/arch.tar"
    id1="$(archive_identity "$T/arch.tar")"
    case "$id1" in */arch.tar\|1\|*) ;; *) exit 10 ;; esac
    moodledata_write_marker abc "$id1"
    [ "$(moodledata_marker_get)" = abc ] || exit 11
    [ "$(moodledata_unpack_state "$id1")" = incomplete ] || exit 12
    moodledata_write_marker abc "$id1" done
    [ "$(moodledata_unpack_state "$id1")" = match ] || exit 13
    printf 'xy' > "$T/arch.tar"
    id2="$(archive_identity "$T/arch.tar")"
    [ "$id1" != "$id2" ] || exit 14
    [ "$(moodledata_unpack_state "$id2")" = other ] || exit 15
    moodledata_write_marker abc
    [ "$(moodledata_unpack_state "$id2")" = none ] || exit 16
    moodledata_write_marker abc "$id1" done
    moodledata_restamp def
    [ "$(moodledata_marker_get)" = def ] || exit 17
    [ "$(moodledata_unpack_state "$id1")" = match ] || exit 18
    [ -z "$(moodledata_recent_writes)" ] || exit 19
    mkdir -p "$MOODLEDATA/sessions"
    : > "$MOODLEDATA/sessions/sess_x"
    [ -n "$(moodledata_recent_writes)" ] || exit 20
    touch -d '2 hours ago' "$MOODLEDATA/sessions/sess_x"
    [ -z "$(moodledata_recent_writes)" ] || exit 21
}
res="$(in_kit t_marker)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "the marker records the archive and the finish: none, incomplete, match, other; a restamp keeps them; recent writes in sessions/ are seen"; else bad "moodledata marker" "$res"; fi

printf 'the dump: GTID and the MySQL 8 collation (Stage B tools fix round 2)\n'
{ printf -- "SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '3E11FA47-71CA-11E1-9E33-C80AA9429562:1-5';\n"; cat "$T/dumps/good.sql"; } > "$T/dumps/gtid.sql"
gzip -c "$T/dumps/gtid.sql" > "$T/dumps/gtid.sql.gz"
for f in gtid.sql gtid.sql.gz; do
    res="$(in_kit dump_unsafe_statement "$T/dumps/$f")"
    if printf '%s' "$res" | head -n 1 | grep -qi 'GTID_PURGED'; then ok "a dump with SET @@GLOBAL.GTID_PURGED is flagged (${f})"; else bad "GTID dump flagged (${f})" "$res"; fi
done
printf 'CREATE TABLE `mdl_t` (`id` int) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;\n-- Dump completed on x\n' > "$T/dumps/mysql8.sql"
res="$(in_kit dump_mysql8_collation "$T/dumps/mysql8.sql" | head -n 1)"
if [ "$res" = "utf8mb4_0900_ai_ci" ]; then ok "a MySQL 8 collation in the dump is found"; else bad "MySQL 8 collation found" "$res"; fi
res="$(in_kit dump_mysql8_collation "$T/dumps/good.sql" | sed '/^rc=/d')"
if [ -z "$res" ]; then ok "a dump without it names none"; else bad "no MySQL 8 collation in a clean dump" "$res"; fi

printf 'step 01: what RESTORE_DONE_BY_HAND may adopt (Stage B tools fix round 3)\n'
# Step 01 in --execute mode against a stand-in mysql client (no database): it answers the probes step 01 makes before it decides what
# to do with the database, and the restore itself (no -e: the dump arrives on stdin) succeeds or dies as fake.restorefails says.
cat > "$T/fakemysql" <<'FAKE'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
tables="$(cat "$here/fake.tables")"
sql=""
while [ $# -gt 0 ]; do
    case "$1" in
        -e) sql="$2"; shift 2 ;;
        *) shift ;;
    esac
done
if [ -z "$sql" ]; then
    cat > /dev/null
    [ "$(cat "$here/fake.restorefails")" = 0 ]
    exit
fi
case "$sql" in
    'SELECT 1') echo 1 ;;
    *'COUNT(*) FROM information_schema.SCHEMATA'*) if [ "$tables" = none ]; then echo 0; else echo 1; fi ;;
    *'COUNT(*) FROM information_schema.TABLES'*) echo "$tables" ;;
    *'SCHEMA_NAME FROM information_schema.SCHEMATA') echo stageb_selftest ;;
    *"COUNT(*) FROM mdl_config WHERE name = 'rehearsal_kit_restore_id'"*) echo 0 ;;
esac
exit 0
FAKE
chmod +x "$T/fakemysql"
# fake_db TABLES [RESTORE_FAILS]: what the stand-in reports. TABLES: none = no such database, 0 = empty, N = N tables, no marker.
fake_db() { printf '%s\n' "$1" > "$T/fake.tables"; printf '%s\n' "${2:-0}" > "$T/fake.restorefails"; }
# rb_run NAME [ENV LINE ...]: step 01 --execute. Each NAME has its own work directory and moodledata, so a second run of the same NAME
# is a re-run of that rehearsal; the extra lines are the env settings of this run (they replace the earlier run's).
rb_run() {
    local n="$1"
    shift
    base_env "$T/rb-$n.env" "REHEARSAL_WORK=$T/rb-$n/work" "MOODLEDATA=$T/rb-$n/data" "MYSQL_BIN=$T/fakemysql" \
        "PRODUCTION_DB_ENDPOINT=live-db.example.internal" "$@"
    OUT="$(bash "$KIT/01_restore_check.sh" --env "$T/rb-$n.env" --execute 2>&1)"
    RC=$?
}
rb_kv() { cat "$T/rb-$1/work/state/kv/$2" 2> /dev/null || true; }
# rb_expect NAME WANT-RC PATTERN [FORBIDDEN-PATTERN]: the last rb_run exited as wanted, said PATTERN, and did not say FORBIDDEN.
rb_expect() {
    local rc_ok=0
    if [ "$2" = 0 ]; then
        [ "$RC" = 0 ] && rc_ok=1
    else
        [ "$RC" -ne 0 ] && rc_ok=1
    fi
    if [ "$rc_ok" = 1 ] && printf '%s' "$OUT" | grep -q "$3" && { [ -z "${4:-}" ] || ! printf '%s' "$OUT" | grep -q "$4"; }; then
        ok "$1"
    else
        bad "$1 (rc ${RC}, wanted '$3'${4:+ and not '$4'})" "$(printf '%s
' "$OUT" | grep -v '^$' | tail -n 8)"
    fi
}
RBDB=stageb_selftest
DUMP="RESTORE_DB_DUMP=$T/dumps/good.sql"

# Both variables set: refused outright, in DRY mode (the plan) and before the database is looked at in EXECUTE mode.
base_env "$T/rb-both.env" "RESTORE_DONE_BY_HAND=$RBDB" "$DUMP"
kit 01_restore_check.sh "$T/rb-both.env"
if [ "$RC" -ne 0 ] && printf '%s' "$OUT" | grep -q 'RESTORE_DONE_BY_HAND' && printf '%s' "$OUT" | grep -q 'RESTORE_DB_DUMP' && printf '%s' "$OUT" | grep -q 'both set'; then
    ok "RESTORE_DONE_BY_HAND and RESTORE_DB_DUMP set together are refused, naming both (DRY)"
else bad "both variables set are refused (DRY, rc ${RC})" "$OUT"; fi
fake_db 0
rb_run both "RESTORE_DONE_BY_HAND=$RBDB" "$DUMP"
rb_expect "both variables set are refused before the database is probed (--execute, an empty database)" 1 'both set' 'database stageb_selftest:'
[ -z "$(rb_kv both restore.started)" ] && ok "the refusal started no restore (no restore.started recorded)" || bad "the refusal started no restore"

# The leftover statement meets a new dump, the kit restore dies part way, the operator re-runs without dropping the database.
fake_db none 1
rb_run left "$DUMP"
rb_expect "a kit restore of the dump dies part way (the stand-in client fails the restore)" 1 'the database restore failed'
if [ -n "$(rb_kv left restore.started)" ] && [ -z "$(rb_kv left restore.complete)" ]; then ok "the failed restore is recorded: restore.started without restore.complete"; else bad "restore.started recorded, restore.complete absent"; fi
fake_db 400 1
rb_run left "RESTORE_DONE_BY_HAND=$RBDB" "$DUMP"
rb_expect "the re-run with the leftover statement AND the dump is refused (both set)" 1 'both set'
rb_run left "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "started-not-complete + RESTORE_DONE_BY_HAND (partial copy of 400 tables) is refused, not adopted" 1 'did not complete' 'restored by hand'
if [ -z "$(rb_kv left restore.id)" ] && [ -n "$(rb_kv left restore.started)" ] && [ -z "$(rb_kv left restore.complete)" ]; then
    ok "nothing was stamped or archived: no restore.id, the failed restore's record is still in place"
else bad "the refused partial copy was stamped or its record moved (restore.id '$(rb_kv left restore.id)')"; fi
rb_run left
rb_expect "started-not-complete without any statement is refused too" 1 'did not complete'

# A hand restore after the failure: drop and recreate (the kit sees it empty and archives the record), restore by hand, say so.
fake_db 0
rb_run left
rb_expect "the database dropped and recreated empty: stops ('restore the live backup first') and says the failed restore is archived" 1 'restore the live backup first' 'taking your word'
if [ -z "$(rb_kv left restore.started)" ] && [ -n "$(find "$T/rb-left/work/archive" -name restore.started 2> /dev/null)" ]; then ok "the failed restore's record moved to archive/ (not deleted)"; else bad "the failed restore's record moved to archive/"; fi
fake_db 400
rb_run left "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "then a database restored by hand into it is adopted and stamped" 1 'stamped as restore' 'did not complete'
if [[ "$(rb_kv left restore.id)" =~ ^[0-9a-f]{32}$ ]] && [ "$(rb_kv left restore.by_hand)" = 1 ]; then ok "restore.id and restore.by_hand=1 are recorded"; else bad "the adopted hand restore is recorded"; fi

# The plain hand restore: no dump, no failed kit restore, the database named: adopted as before.
fake_db 400
rb_run hand "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "a plain hand restore (RESTORE_DB_DUMP unset, RESTORE_DONE_BY_HAND=<that database>) is adopted" 1 'restored by hand' 'did not complete'
if [[ "$(rb_kv hand restore.id)" =~ ^[0-9a-f]{32}$ ]] && [ "$(rb_kv hand restore.by_hand)" = 1 ] && [ -n "$(rb_kv hand restore.complete)" ]; then ok "the adopted copy is stamped (restore.id, restore.by_hand=1, restore.complete)"; else bad "the adopted hand restore is stamped"; fi
rb_run nostmt
rb_expect "a populated database without the marker and without the statement is still refused" 1 'carries no rehearsal-kit marker'
rb_run other "RESTORE_DONE_BY_HAND=some_other_db"
rb_expect "a statement that names another database does not adopt this one" 1 'carries no rehearsal-kit marker'

printf 'the restore point before a hop or the import\n'
t_snap() {
    # t_snap HOOK TAKEN PRODUCTION LABEL
    EXECUTE=1 STEP_ID=t SNAPSHOT_HOOK="$1" SNAPSHOT_TAKEN="$2" BIZLMS_PRODUCTION_FLAG="$3" LOG_DIR="$T/logs" TIMINGS_FILE="$T/logs/timings.tsv"
    mkdir -p "$LOG_DIR"
    snapshot_hook "$4" > /dev/null 2>&1
}
[ "$(in_kit t_snap "" "" 1 before-hop-1 | rc_of)" = 1 ] && ok "cutover form, no hook and nothing acknowledged: refused" || bad "cutover form refuses a missing restore point"
[ "$(in_kit t_snap "" "before-hop-1, before-import" 1 before-import | rc_of)" = 0 ] && ok "cutover form: SNAPSHOT_TAKEN names the label" || bad "SNAPSHOT_TAKEN names the label"
[ "$(in_kit t_snap "" "before-hop-1" 1 before-hop-2 | rc_of)" = 1 ] && ok "cutover form: another label acknowledged is not enough" || bad "another label is not enough"
[ "$(in_kit t_snap "" "all" 1 before-hop-2 | rc_of)" = 0 ] && ok "cutover form: SNAPSHOT_TAKEN=all" || bad "SNAPSHOT_TAKEN=all"
[ "$(in_kit t_snap "" "" 0 before-hop-1 | rc_of)" = 0 ] && ok "rehearsal form: a missing hook is a reminder, not a stop" || bad "rehearsal form reminder"
[ "$(in_kit t_snap true "" 1 before-hop-1 | rc_of)" = 0 ] && ok "a hook that succeeds is the restore point" || bad "hook succeeds"
[ "$(in_kit t_snap false "" 0 before-hop-1 | rc_of)" = 1 ] && ok "a hook that fails stops the step" || bad "hook fails"

printf 'the activity check before hop 2\n'
t_lost() {
    db_scalar() { printf '3'; }
    rm -rf "$T/pkg"
    mkdir -p "$T/pkg/public/mod/chat"
    : > "$T/pkg/public/mod/chat/version.php"
    modules_lost_in_hop2 "$T/pkg"
}
res="$(in_kit t_lost)"
if [ "$(printf '%s\n' "$res" | sed '/^rc=/d')" = "survey 3" ]; then ok "only the module type the package lacks is reported (survey 3; chat is shipped)"; else bad "activity check names survey only" "$res"; fi
t_lost_none() {
    db_scalar() { printf '0'; }
    rm -rf "$T/pkg2"
    mkdir -p "$T/pkg2/public/mod"
    modules_lost_in_hop2 "$T/pkg2"
}
res="$(in_kit t_lost_none)"
if [ -z "$(printf '%s\n' "$res" | sed '/^rc=/d')" ] && [ "$(printf '%s' "$res" | tail -n 1)" = "rc=0" ]; then ok "no activity of those types: nothing reported"; else bad "no activities, nothing reported" "$res"; fi

printf 'the generated config and the parity columns\n'
write_cfg "$T/cfg_cache.php"
got="$(SOURCE_BASELINE_PHP="$KIT/../../moodle-enhancement/local/sentientia_platform/cli/source_baseline.php" php "$KIT/lib/config_probe.php" "$T/cfg_cache.php" --get=altcacheconfigpath 2> /dev/null)"
if [ "$got" = "$T/work/muc" ]; then ok "the generated config.php points the cache configuration at the kit's own directory (not live's muc/config.php)"; else bad "altcacheconfigpath is the kit's directory" "got '${got}'"; fi
if bash -n "$KIT/rehearsal.env.example" 2> /dev/null; then ok "bash -n rehearsal.env.example"; else bad "bash -n rehearsal.env.example"; fi
if grep -q '^tools/rehearsal/rehearsal.env$' "$KIT/../../.gitignore"; then ok "the operator's rehearsal.env is git-ignored"; else bad "rehearsal.env is git-ignored"; fi
hits="$(grep -n "'theme'" "$KIT/../../moodle-enhancement/local/sentientia_platform/cli/source_baseline.php" || true)"
if [ -z "$hits" ]; then ok "no column list of the parity baseline names 'theme' (step 07 clears theme overrides)"; else bad "a parity column list names theme" "$hits"; fi

printf 'portability\n'
hits="$(grep -nE '(^|[^A-Za-z0-9_])[A-Za-z]:[\\/]|xampp|/c/Users|/mnt/[a-z]/' "$KIT"/*.sh "$KIT"/lib/*.sh "$KIT"/lib/*.php "$KIT"/lib/bizlms_plugins.txt "$KIT"/rehearsal.env.example 2> /dev/null | grep -v 'selftest.sh' || true)"
if [ -z "$hits" ]; then ok "no Windows path, drive letter or XAMPP path is hard-coded in the kit"; else bad "hard-coded Windows paths" "$hits"; fi
hits="$(grep -nE 'password[[:space:]]*=[[:space:]]*["'"'"'][^"'"'"']{8,}' "$KIT"/*.sh "$KIT"/lib/*.sh "$KIT"/rehearsal.env.example 2> /dev/null | grep -v 'selftest.sh' || true)"
if [ -z "$hits" ]; then ok "no credential literal in the kit"; else bad "credential literal" "$hits"; fi

printf '\n%s passed, %s failed\n' "$PASS" "$FAILS"
[ "$FAILS" = 0 ]
