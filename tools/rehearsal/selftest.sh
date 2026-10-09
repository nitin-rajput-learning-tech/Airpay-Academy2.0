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
#   * step 01 in --execute mode against a stand-in mysql client (fix rounds 3 to 5): RESTORE_DONE_BY_HAND with RESTORE_DB_DUMP is refused; a
#     restore the kit started and did not finish leaves the table zz_rehearsal_restore_inflight in the database, and a database that holds it
#     is refused whatever RESTORE_DONE_BY_HAND says, in any work directory (the fact is in the database, not in state/), a count of it the
#     client prints nothing for is "cannot tell", and only a dropped and re-created database is cleared; the CREATE TABLE is the claim on an
#     empty database (another restore's table, or a database that is no longer empty, stops the second restore and it writes nothing); the
#     same target string on another server is another database; a plain hand restore is adopted; one --execute run per REHEARSAL_WORK
#     (.run.lock, taken by a step run alone, held for the steps run_all.sh starts); a schema list that comes back empty is "unreachable";
#     a moodledata an earlier rehearsal's step after 01 used is still refused for a new restore after the restore that rotated its
#     state/ died and was retried;
#   * fix round 6, the same stand-in (and, for the signal, a copy of the kit with stub steps): a moodledata unpack cut short (a tar of the
#     moodledata that stops after filedir/) leaves .rehearsal_unpack_inflight and an archive line without an unpacked line, and is refused on the
#     retry with RESTORE_MOODLEDATA_ARCHIVE unset, in a new work directory and with both hand statements, and by require_kit_marker; an archive that
#     carries its own in-flight file is not a complete unpack; the in-flight table is read by the server's error (1146 = absent), so a count that
#     answers 0 cannot adopt it, a lost connection is "cannot tell", and a blank answer for an absent table does not refuse a finished copy;
#     require_kit_marker refuses an in-flight table or file behind a correct marker; a dump that names the table is refused; state/ stays in
#     place when a claim is not taken (a misread, a lost race); two real runs race for one empty database (the stand-in blocks the first at its
#     claim until a flag file appears); a TERM sent to run_all.sh alone does not release .run.lock under a running step;
#   * fix round 7: an UNCOMPRESSED tar is refused without RESTORE_MOODLEDATA_SHA256, however complete it is, and a zero-filled copy of the full size
#     (the last 1024 bytes of which pass the old test) is refused with it unless the checksum is the one taken where the archive was made; a
#     zero-filled .tar.gz is refused by its decompressor; a checksum that is set is checked on a re-run where filedir/ is there (wrong: refused,
#     right: the proof is recorded again), a checksum with no archive is refused; a whole step 01 against the stand-in (end-to-end mode) writes
#     its record that it finished LAST, after the neutralisation, and removes it when it starts again; require_kit_marker (steps 02 to 11) refuses a
#     stamped copy whose 01.status is fail, running or missing, whose state/kv/restore.verified or whose database record is missing, belongs to
#     another restore or cannot be read, and accepts the finished one; the real steps 02, 03 and 11 refuse the copy a failed file store gate left and the
#     copy a TERM left before the gate (nothing was neutralised: no SMTP wipe, no cron_enabled, no OAuth2 statement reached the database); the
#     preflight refuses a run that starts after step 01 on such a copy and warns for a run that includes it; USR1 and ALRM to a step alone are
#     recorded as failures, USR1 to run_all.sh alone keeps its lock, and a TERM to the whole process group ends the step (fail, signal=TERM) and
#     run_all.sh (exit 143, STOPPED in its log) with the lock released; a content hash that {files} records with two sizes is cut only when the file
#     on disk matches neither;
#   * fix round 8: step 01 reads the content of filedir/ and every file must hash to its own name (a clean file store passes; one file zero-filled at
#     its size, which the existence and size gates pass, is found; a whole step 01 against the stand-in refuses a copy whose SHA-256 the operator took
#     on the sandbox, on the unpack path and on the reuse path, and a file damaged since the unpack; a stray name is a warning and Moodle's warning.txt
#     is not counted; a file that cannot be read fails; RESTORE_FILEDIR_HASH_CHECK=0 skips with a warning that the summary prints); a tar must end where
#     a tar ends, with or without its checksum (a plain tar cut at a member header or inside a member, a .tar.gz around a cut tar, a full-size copy
#     with a zero-filled region and a tar padded with more than a record of zeros are each refused with a checksum that matches them, before anything is
#     touched, and a whole plain tar with its checksum is accepted); a step that does not reach its last line is never ok (ABRT, SYS and TRAP to a
#     step alone, ABRT to run_all.sh alone: fail, ended=unreached, the lock kept; a step that exits 0 without step_end; every step script ends with
#     step_end); a hop 1 that failed with the release already moved no longer locks step 01 out (and a database this work directory never saw at the
#     source release, or a release no hop leaves, is still refused); a refused and a passing gate leave no client.cnf in TMPDIR; the preflight refuses
#     an uncompressed moodledata archive without the checksum from the live server, in DRY mode too; the (premise) tests of GNU tar's behaviour are
#     assertions, not branches that can only say ok;
#   * run_all.sh --list and a DRY --only run work; no Windows path or drive letter is hard-coded in the kit.
# Exit 0 = every test passed.

set -uo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
T="$(mktemp -d "${TMPDIR:-/tmp}/rehearsal-selftest.XXXXXX")"
trap 'rm -rf "$T"' EXIT
PASS=0
FAILS=0
SKIPS=0

ok() { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
# skip LABEL: a case that cannot be run HERE (named, never counted as passed, and printed in the summary line).
skip() { SKIPS=$((SKIPS + 1)); printf '  skip  %s\n' "$1"; }
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

printf 'step 01: what RESTORE_DONE_BY_HAND may adopt, and the in-flight table (Stage B tools fix rounds 3 to 5)\n'
# Step 01 in --execute mode against a stand-in mysql client (no database). The stand-in is a "server": a directory ($FW) with a few state
# files. It answers the probes step 01 makes before it decides what to do with the database; it keeps the in-flight table the way a database
# does (fake.sentinel: CREATE TABLE fails while it exists, DROP TABLE removes it, the table count includes it); and the restore itself (no
# -e: the dump arrives on stdin) succeeds or dies as fake.restorefails says, leaving the tables fake.afterrestore / fake.partial name.
cat > "$T/fakemysql" <<'FAKE'
#!/usr/bin/env bash
here="$(cd "$(dirname "$0")" && pwd)"
tables="$(cat "$here/fake.tables")"
sent=0
[ -f "$here/fake.sentinel" ] && sent=1
total="$tables"
[ "$tables" = none ] || total=$((tables + sent))
sql=""
while [ $# -gt 0 ]; do
    case "$1" in
        -e) sql="$2"; shift 2 ;;
        *) shift ;;
    esac
done
# fake.e2e: every write the kit sends is kept in fake.sqllog (what a step 01 that failed before its neutralisation never sent)
if [ -f "$here/fake.e2e" ]; then
    case "$sql" in
        UPDATE*|INSERT*|DELETE*) printf '%s\n' "$sql" >> "$here/fake.sqllog" ;;
    esac
fi
if [ -z "$sql" ]; then
    cat > /dev/null
    echo load >> "$here/fake.loads"
    # fake.mutatedump=FILE: the dump is re-written while the load runs (a sync client, an editor, a copy that is still going)
    if [ -f "$here/fake.mutatedump" ]; then
        printf -- '-- appended while the load ran\n' >> "$(cat "$here/fake.mutatedump")"
    fi
    if [ "$(cat "$here/fake.restorefails")" != 0 ]; then
        # a restore that dies leaves the partial copy fake.partial names
        if [ -f "$here/fake.partial" ]; then
            cp "$here/fake.partial" "$here/fake.tables"
        fi
        exit 1
    fi
    # a restore that completes leaves the tables fake.afterrestore names (the database was created empty or absent before it)
    if [ -f "$here/fake.afterrestore" ]; then
        cp "$here/fake.afterrestore" "$here/fake.tables"
    fi
    exit 0
fi
case "$sql" in
    'SELECT 1') echo 1 ;;
    'CREATE DATABASE'*)
        # a database that exists is refused by a real server (error 1007)
        if [ "$tables" != none ]; then
            echo "ERROR 1007 (HY000): cannot create database, it exists" >&2
            exit 1
        fi
        echo 0 > "$here/fake.tables" ;;
    'CREATE TABLE `zz_rehearsal_restore_inflight`'*)
        # fake.blockclaim: the FIRST client to reach this statement waits here (it says so with the flag fake.blocked) until fake.release
        # exists, so a second run can go through a whole restore while the first sits at its claim: a deterministic race of two real processes
        if mv "$here/fake.blockclaim" "$here/fake.blockclaim.taken" 2> /dev/null; then
            : > "$here/fake.blocked"
            while [ ! -f "$here/fake.release" ]; do sleep 0.2; done
        fi
        # fake.claimrace=claimed: another restore claimed the database between this run's probe and its claim
        if [ "$(cat "$here/fake.claimrace" 2> /dev/null)" = claimed ]; then
            rm -f "$here/fake.claimrace"
            printf 'INSERT of another restore\n' > "$here/fake.sentinel"
            echo 120 > "$here/fake.tables"
        fi
        if [ -f "$here/fake.sentinel" ]; then
            echo "ERROR 1050 (42S01): the table zz_rehearsal_restore_inflight already exists" >&2
            exit 1
        fi
        printf '%s\n' "$sql" > "$here/fake.sentinel" ;;
    'INSERT INTO `zz_rehearsal_restore_inflight`'*) printf '%s\n' "$sql" >> "$here/fake.sentinel" ;;
    'DROP TABLE `zz_rehearsal_restore_inflight`') rm -f "$here/fake.sentinel" ;;
    *'COUNT(*) FROM information_schema.SCHEMATA'*)
        # fake.once, a fault of the client on the schema count: blank = prints nothing, once (the 2026-10-08 client fault); zero = answers
        # 0 once whatever is there (a read that is wrong); blankall = prints nothing every time.
        once="$(cat "$here/fake.once" 2> /dev/null)"
        case "$once" in
            blank) rm -f "$here/fake.once"; exit 0 ;;
            zero) rm -f "$here/fake.once"; echo 0; exit 0 ;;
            blankall) exit 0 ;;
        esac
        if [ "$tables" = none ]; then echo 0; else echo 1; fi ;;
    *'SELECT 1 FROM `zz_rehearsal_restore_inflight` LIMIT 0'*)
        # The error-based read of the in-flight table (inflight_count): the statement before it prints 'answered', and then the table either
        # exists (no error) or does not (error 1146). fake.once: blankinflight = prints nothing, status 0, once (the client fault);
        # blankinflightall = every time; inflighterr = a lost connection (error 2013), once; inflighterrall = every time.
        once="$(cat "$here/fake.once" 2> /dev/null)"
        case "$once" in
            blankinflight) rm -f "$here/fake.once"; exit 0 ;;
            blankinflightall) exit 0 ;;
            inflighterr) rm -f "$here/fake.once"; echo "ERROR 2013 (HY000): Lost connection to MySQL server during query" >&2; exit 1 ;;
            inflighterrall) echo "ERROR 2013 (HY000): Lost connection to MySQL server during query" >&2; exit 1 ;;
        esac
        if [ "$sent" = 1 ]; then
            echo answered
        else
            echo "ERROR 1146 (42S02) at line 1: Table 'stageb_selftest.zz_rehearsal_restore_inflight' doesn't exist" >&2
            exit 1
        fi ;;
    *"TABLE_NAME = 'zz_rehearsal_restore_inflight'"*)
        # The kit no longer reads the in-flight table through information_schema (inflight_count asks the table itself); this arm is here to
        # prove it. fake.asked records every ask; fake.once=zeroinflightall is a count that always answers 0 (a misread), which the old
        # single COUNT would have taken for "no in-flight table".
        printf 'asked\n' >> "$here/fake.asked"
        if [ "$(cat "$here/fake.once" 2> /dev/null)" = zeroinflightall ]; then
            echo 0
            exit 0
        fi
        echo "$sent" ;;
    *"TABLE_NAME = 'mdl_config'"*)
        if [ -f "$here/fake.noconfig" ] || [ "$tables" = none ] || [ "$tables" = 0 ]; then echo 0; else echo 1; fi ;;
    *'COUNT(*) FROM information_schema.TABLES'*)
        # fake.once=zerotables: a table count that is wrong once (0) whatever is there; zerotableslate: the same, but only once the restore
        # has been loaded (the count that follows the load, not the one at the claim)
        if [ "$(cat "$here/fake.once" 2> /dev/null)" = zerotables ]; then
            rm -f "$here/fake.once"
            echo 0
            exit 0
        fi
        if [ "$(cat "$here/fake.once" 2> /dev/null)" = zerotableslate ] && [ -f "$here/fake.loads" ]; then
            rm -f "$here/fake.once"
            echo 0
            exit 0
        fi
        echo "$total" ;;
    *'SCHEMA_NAME FROM information_schema.SCHEMATA')
        # fake.schemata: blank = prints nothing; prod = the server also holds airpayprod
        case "$(cat "$here/fake.schemata" 2> /dev/null)" in
            blank) exit 0 ;;
            prod) printf 'information_schema\nairpayprod\nstageb_selftest\n' ;;
            *) printf 'information_schema\nstageb_selftest\n' ;;
        esac ;;
    *'FROM `zz_rehearsal_restore_inflight`'*) echo 'restore 0123abcd..., dump (stand-in), started (stand-in)' ;;
    *"VALUES ('rehearsal_kit_restore_id'"*)
        # A PLAIN INSERT (the stamp of a hand restore) is refused with error 1062 when the row exists (fake.marker holds it) or when
        # fake.insertexists says it does (a row the reads did not show); the ON DUPLICATE KEY form (the kit's own restore) replaces it.
        case "$sql" in
            *'ON DUPLICATE KEY'*) ;;
            *)
                if [ -f "$here/fake.insertexists" ] || [ -s "$here/fake.marker" ]; then
                    echo "ERROR 1062 (23000) at line 1: Duplicate entry 'rehearsal_kit_restore_id' for key 'mdl_conf_nam_uix'" >&2
                    exit 1
                fi ;;
        esac
        # the marker row is kept only when fake.storemarker is there: the other tests rely on the marker NOT reading back
        if [ -f "$here/fake.storemarker" ]; then
            printf '%s\n' "$sql" | sed -n "s/.*VALUES ('rehearsal_kit_restore_id', '\([0-9a-f]*\)').*/\1/p" > "$here/fake.marker"
        fi ;;
    "SELECT value FROM mdl_config WHERE name = 'rehearsal_kit_restore_id'")
        # The read of the marker (marker_get). fake.once faults: markererr = a lost connection (error 2013), once; markererrall = every time;
        # markerblank = prints nothing, once; markerblankall = prints nothing every time while the COUNT still says the row is there;
        # allblank = prints nothing every time, the COUNT too; markerzero = prints nothing once AND the next COUNT answers 0 once (a blank read
        # confirmed by a wrong zero). fake.noconfig = there is no config table (error 1146).
        once="$(cat "$here/fake.once" 2> /dev/null)"
        case "$once" in
            markererr) rm -f "$here/fake.once"; echo "ERROR 2013 (HY000): Lost connection to MySQL server during query" >&2; exit 1 ;;
            markererrall) echo "ERROR 2013 (HY000): Lost connection to MySQL server during query" >&2; exit 1 ;;
            markerblank) rm -f "$here/fake.once"; exit 0 ;;
            markerblankall | allblank) exit 0 ;;
            markerzero) rm -f "$here/fake.once"; : > "$here/fake.countzero"; exit 0 ;;
        esac
        if [ -f "$here/fake.noconfig" ]; then
            echo "ERROR 1146 (42S02) at line 1: Table 'stageb_selftest.mdl_config' doesn't exist" >&2
            exit 1
        fi
        cat "$here/fake.marker" 2> /dev/null ;;
    *"COUNT(*) FROM mdl_config WHERE name = 'rehearsal_kit_restore_id'"*)
        if [ -f "$here/fake.countzero" ]; then
            rm -f "$here/fake.countzero"
            echo 0
            exit 0
        fi
        if [ "$(cat "$here/fake.once" 2> /dev/null)" = allblank ]; then
            exit 0
        fi
        if [ -s "$here/fake.marker" ]; then echo 1; else echo 0; fi ;;
    "SELECT value FROM mdl_config WHERE name = 'rehearsal_kit_step01_ok'")
        # The record that step 01 finished (fake.step01 holds the restore id). fake.once=step01errall: a read that always fails (error 2013).
        if [ "$(cat "$here/fake.once" 2> /dev/null)" = step01errall ]; then
            echo "ERROR 2013 (HY000): Lost connection to MySQL server during query" >&2
            exit 1
        fi
        cat "$here/fake.step01" 2> /dev/null ;;
    *"COUNT(*) FROM mdl_config WHERE name = 'rehearsal_kit_step01_ok'"*)
        if [ -s "$here/fake.step01" ]; then echo 1; else echo 0; fi ;;
    *"VALUES ('rehearsal_kit_step01_ok'"*)
        printf '%s\n' "$sql" | sed -n "s/.*VALUES ('rehearsal_kit_step01_ok', '\([0-9a-f]*\)').*/\1/p" > "$here/fake.step01" ;;
    "DELETE FROM mdl_config WHERE name = 'rehearsal_kit_step01_ok'") rm -f "$here/fake.step01" ;;
    *)
        # fake.e2e: the answers a whole step 01 asks after the restore, for a copy of the 4.1.2 live backup whose filedir is the one content
        # file of the archives below (11f6ad8e...: the SHA-1 of its 1 byte, 'x'). fake.twohashes: {files} also names a content hash that is not on disk (a failed gate).
        # fake.blockgate: the first client that asks for the content hashes waits (fake.atgate says so) until fake.releasegate exists.
        [ -f "$here/fake.e2e" ] || exit 0
        case "$sql" in
            "SELECT value FROM mdl_config WHERE name = 'release'")
                # fake.relval: the release the copy reports (a hop moved it: 4.5.4, 3.9.1...); the live backup's 4.1.2 otherwise
                if [ -f "$here/fake.relval" ]; then cat "$here/fake.relval"; else echo '4.1.2 (Build: 20230320)'; fi ;;
            "SELECT value FROM mdl_config WHERE name = 'version'") echo 2022112802 ;;
            "SELECT value FROM mdl_config WHERE name = 'cron_enabled'") echo 0 ;;
            "SELECT DISTINCT contenthash, filesize FROM mdl_files WHERE filesize > 0")
                if mv "$here/fake.blockgate" "$here/fake.blockgate.taken" 2> /dev/null; then
                    : > "$here/fake.atgate"
                    while [ ! -f "$here/fake.releasegate" ]; do sleep 0.2; done
                fi
                # the content file of the archives below: x (1 byte), named by the SHA-1 of its content as Moodle does; fake.hashrows replaces the rows
                if [ -f "$here/fake.hashrows" ]; then cat "$here/fake.hashrows"; else printf '11f6ad8ec52a2984abaafd7c3b516503785c2072\t1\n'; fi
                if [ -f "$here/fake.twohashes" ]; then printf 'ffeeddccbbaa99887766554433221100ffeeddcc\t5\n'; fi ;;
            "SELECT COALESCE(SUM(t.sz), 0)"*) echo 1 ;;
            "SELECT DISTINCT SUBSTRING_INDEX"*) echo /1 ;;
            *'COUNT(*) FROM information_schema.COLUMNS'*) echo 1 ;;
            *'COUNT(*) FROM mdl_files WHERE filesize > 0'*) echo 1 ;;
            *'COUNT(*) FROM mdl_user'*) echo 5 ;;
            *'SELECT COUNT(*)'*) echo 0 ;;
        esac ;;
esac
exit 0
FAKE
chmod +x "$T/fakemysql"
# FW: the directory of the stand-in "server" the runs talk to (a second directory with its own copy is a second server).
FW="$T"
# fake_db TABLES [RESTORE_FAILS]: what the stand-in reports. TABLES: none = no such database, 0 = empty, N = N tables, no marker.
# It also clears every one-off hook below: this is a database dropped and created again.
fake_db() {
    printf '%s\n' "$1" > "$FW/fake.tables"
    printf '%s\n' "${2:-0}" > "$FW/fake.restorefails"
    rm -f "$FW"/fake.once "$FW"/fake.afterrestore "$FW"/fake.partial "$FW"/fake.sentinel "$FW"/fake.claimrace "$FW"/fake.schemata \
        "$FW"/fake.noconfig "$FW"/fake.loads "$FW"/fake.storemarker "$FW"/fake.marker "$FW"/fake.asked \
        "$FW"/fake.blockclaim "$FW"/fake.blockclaim.taken "$FW"/fake.blocked "$FW"/fake.release \
        "$FW"/fake.countzero "$FW"/fake.insertexists "$FW"/fake.mutatedump         "$FW"/fake.e2e "$FW"/fake.twohashes "$FW"/fake.blockgate "$FW"/fake.blockgate.taken "$FW"/fake.atgate "$FW"/fake.releasegate         "$FW"/fake.step01 "$FW"/fake.sqllog \
        "$FW"/fake.relval "$FW"/fake.hashrows
}
# fake_once FAULT: a one-off fault of the client (see the stand-in). fake_after N / fake_partial N: a restore that completes / dies leaves
# N tables. fake_set NAME VALUE: fake.NAME (claimrace, schemata, noconfig, storemarker, sentinel, blockclaim, release, marker).
fake_once() { printf '%s\n' "$1" > "$FW/fake.once"; }
fake_after() { printf '%s\n' "$1" > "$FW/fake.afterrestore"; }
fake_partial() { printf '%s\n' "$1" > "$FW/fake.partial"; }
fake_set() { printf '%s\n' "$2" > "$FW/fake.$1"; }
# fake_inflight -> 0 when the stand-in's database holds the in-flight table; fake_loads -> how many restores the client was given.
fake_inflight() { [ -f "$FW/fake.sentinel" ]; }
fake_loads() { cat "$FW/fake.loads" 2> /dev/null | wc -l | tr -d ' '; }
# rb_run NAME [ENV LINE ...]: step 01 --execute. Each NAME has its own work directory and moodledata, so a second run of the same NAME
# is a re-run of that rehearsal (another NAME is another work directory); the extra lines are the env settings of this run (they replace
# the earlier run's).
rb_run() {
    local n="$1"
    shift
    base_env "$T/rb-$n.env" "REHEARSAL_WORK=$T/rb-$n/work" "MOODLEDATA=$T/rb-$n/data" "MYSQL_BIN=$FW/fakemysql" \
        "PRODUCTION_DB_ENDPOINT=live-db.example.internal" "$@"
    OUT="$(bash "$KIT/01_restore_check.sh" --env "$T/rb-$n.env" --execute 2>&1)"
    RC=$?
}
rb_kv() { cat "$T/rb-$1/work/state/kv/$2" 2> /dev/null || true; }
# rb_kv_empty NAME LABEL: nothing is recorded in the kv store of that run's work directory (no restore id, nothing about a restore).
rb_kv_empty() {
    if [ -z "$(ls -A "$T/rb-$1/work/state/kv" 2> /dev/null)" ]; then ok "$2"; else bad "$2 (kv holds: $(ls "$T/rb-$1/work/state/kv" | tr '\n' ' '))"; fi
}
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
        bad "$1 (rc ${RC}, wanted '$3'${4:+ and not '$4'})" "$(printf '%s\n' "$OUT" | grep -v '^$' | tail -n 8)"
    fi
}
RBDB=stageb_selftest
DUMP="RESTORE_DB_DUMP=$T/dumps/good.sql"
res="$(in_kit sql_squote "it's a \\ path")"
if [ "$(printf '%s' "$res" | head -n 1)" = "it''s a \\\\ path" ]; then ok "sql_squote doubles a quote and a backslash"; else bad "sql_squote" "$res"; fi

# Both variables set: refused outright, in DRY mode (the plan) and before the database is looked at in EXECUTE mode.
base_env "$T/rb-both.env" "RESTORE_DONE_BY_HAND=$RBDB" "$DUMP"
kit 01_restore_check.sh "$T/rb-both.env"
if [ "$RC" -ne 0 ] && printf '%s' "$OUT" | grep -q 'RESTORE_DONE_BY_HAND' && printf '%s' "$OUT" | grep -q 'RESTORE_DB_DUMP' && printf '%s' "$OUT" | grep -q 'both set'; then
    ok "RESTORE_DONE_BY_HAND and RESTORE_DB_DUMP set together are refused, naming both (DRY)"
else bad "both variables set are refused (DRY, rc ${RC})" "$OUT"; fi
fake_db 0
rb_run both "RESTORE_DONE_BY_HAND=$RBDB" "$DUMP"
rb_expect "both variables set are refused before the database is probed (--execute, an empty database)" 1 'both set' 'database stageb_selftest:'
if ! fake_inflight && [ "$(fake_loads)" = 0 ]; then ok "the refusal touched nothing (no in-flight table, nothing loaded)"; else bad "the refusal touched the database"; fi

# The leftover statement meets a new dump, the kit restore dies part way, the operator re-runs without dropping the database.
fake_db none 1
fake_partial 400
rb_run left "$DUMP"
rb_expect "a kit restore of the dump dies part way (the stand-in client fails the restore)" 1 'the database restore failed' 'stamped as restore'
if fake_inflight && grep -q "$T/dumps/good.sql" "$FW/fake.sentinel" && grep -Eq "'[0-9a-f]{32}'" "$FW/fake.sentinel" && [ "$(cat "$FW/fake.tables")" = 400 ]; then
    ok "the database holds the partial copy AND the in-flight table, which records the restore id and the dump path"
else bad "the failed restore left the in-flight table with its row ($(cat "$FW/fake.sentinel" 2> /dev/null | tr '\n' ' '))"; fi
rb_kv_empty left "nothing about the unfinished restore is kept in the work directory (the fact is in the database)"
if [ ! -d "$T/rb-left/work/.run.lock" ]; then ok "a step run alone released its run lock when it died"; else bad "the run lock was left behind"; fi
rb_run left "RESTORE_DONE_BY_HAND=$RBDB" "$DUMP"
rb_expect "the re-run with the leftover statement AND the dump is refused (both set)" 1 'both set'
rb_run left "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "a database that holds the in-flight table (400-table partial copy) + RESTORE_DONE_BY_HAND is refused, not adopted" 1 'holds the table zz_rehearsal_restore_inflight' 'restored by hand\|stamped as restore'
if [ -z "$(rb_kv left restore.id)" ] && fake_inflight; then ok "nothing was stamped, and the in-flight table is still there"; else bad "the refused partial copy was stamped or its in-flight table dropped (restore.id '$(rb_kv left restore.id)')"; fi
rb_run left
rb_expect "with no statement at all it is refused too" 1 'holds the table zz_rehearsal_restore_inflight'
rb_run left "$DUMP"
rb_expect "a retry with the dump (and no statement) does not write over it either" 1 'holds the table zz_rehearsal_restore_inflight' 'RUN: restore database'
if [ "$(fake_loads)" = 1 ]; then ok "the client was given the one failed restore and no second one"; else bad "restores loaded: $(fake_loads)"; fi

# A hand restore after the failure: drop and recreate (no in-flight table any more), restore by hand, say so.
fake_db 0
rb_run left
rb_expect "the database dropped and recreated empty: stops ('restore the live backup first')" 1 'restore the live backup first' 'taking your word'
fake_db 400
rb_run left "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "then a database restored by hand into it is adopted and stamped" 1 'stamped as restore' 'holds the table'
if [[ "$(rb_kv left restore.id)" =~ ^[0-9a-f]{32}$ ]] && [ "$(rb_kv left restore.by_hand)" = 1 ]; then ok "restore.id and restore.by_hand=1 are recorded"; else bad "the adopted hand restore is recorded"; fi

# The plain hand restore: no dump, no in-flight table, the database named: adopted as before.
fake_db 400
rb_run hand "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "a plain hand restore (RESTORE_DB_DUMP unset, RESTORE_DONE_BY_HAND=<that database>) is adopted" 1 'restored by hand' 'holds the table'
if [[ "$(rb_kv hand restore.id)" =~ ^[0-9a-f]{32}$ ]] && [ "$(rb_kv hand restore.by_hand)" = 1 ] && [ -n "$(rb_kv hand restore.complete)" ]; then ok "the adopted copy is stamped (restore.id, restore.by_hand=1, restore.complete)"; else bad "the adopted hand restore is stamped"; fi
rb_run nostmt
rb_expect "a populated database without the marker and without the statement is still refused (and the message does not claim to know what it is)" 1 'carries no rehearsal-kit marker' 'did not restore it'
rb_run other "RESTORE_DONE_BY_HAND=some_other_db"
rb_expect "a statement that names another database does not adopt this one" 1 'carries no rehearsal-kit marker'

printf 'step 01: the in-flight table is in the database, so it holds for any work directory and any server (Stage B tools fix round 5)\n'
# 1. A NEW REHEARSAL_WORK after a failed kit restore: there is no record in it, and none is needed.
fake_db none 1
fake_partial 120
rb_run nwa "$DUMP"
rb_expect "(setup) a kit restore into ${RBDB} dies with 120 tables written" 1 'the database restore failed'
rb_run nwb "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "a NEW work directory and the statement: the 120-table partial copy is refused all the same" 1 'holds the table zz_rehearsal_restore_inflight' 'restored by hand\|stamped as restore'
if fake_inflight && [ -z "$(ls -A "$T/rb-nwb/work/state/kv" 2> /dev/null)" ]; then ok "nothing was stamped or adopted by the new work directory"; else bad "the new work directory stamped or touched the partial copy"; fi

# 2. A client that prints nothing for the count of the in-flight table is "cannot tell", never "no in-flight table".
fake_once blankinflight
rb_run nwb "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "the in-flight count printed nothing once: asked again, the partial copy is still found and refused" 1 'holds the table zz_rehearsal_restore_inflight' 'stamped as restore'
if [ ! -e "$FW/fake.once" ]; then ok "(the stand-in did print nothing for that count)"; else bad "the stand-in client printed nothing for the in-flight count"; fi
fake_once blankinflightall
rb_run nwb "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "a client that never answers that count is 'could not be read' (a partial restore until it can be), never adopted" 1 'could not be read' 'stamped as restore'
rm -f "$FW/fake.once"

# 3. A schema or table count that is wrong once cannot start a restore over the partial copy, nor adopt it.
fake_once blank
rb_run nwb "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "the schema count printed nothing once: asked again, the partial copy is found and refused" 1 'holds the table zz_rehearsal_restore_inflight' 'restore the live backup first'
fake_once blankall
rb_run nwb "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "a client that never answers the schema count is 'cannot be reached', never 'absent'" 1 'could not be read.*not as absent' 'restore the live backup first'
fake_once zero
rb_run nwb "$DUMP"
rb_expect "a schema count that is wrong once (0, 'absent') cannot start a restore over the partial copy: the database is there" 1 'cannot create database' 'RUN: restore database'
fake_once zerotables
rb_run nwb "$DUMP"
rb_expect "a table count that is wrong once (0, 'empty') cannot either: the claim finds the in-flight table" 1 'cannot claim database' 'RUN: restore database'
if fake_inflight && [ "$(fake_loads)" = 1 ]; then ok "nothing more was written: the in-flight table is still there, no second restore was loaded"; else bad "a misread let a second restore write ($(fake_loads) loads)"; fi

# 4. A populated database read as empty once (or finished by another restore in the meantime): the claim releases itself.
fake_db 120
fake_once zerotables
rb_run pop "$DUMP"
rb_expect "a populated database with no in-flight table, read as empty once: the claim finds it is not empty and releases itself" 1 'right after it was claimed' 'RUN: restore database'
if ! fake_inflight && [ "$(fake_loads)" = 0 ] && [ "$(cat "$FW/fake.tables")" = 120 ]; then ok "no in-flight table was left behind, nothing was loaded, the 120 tables are untouched"; else bad "the released claim left traces"; fi

# 5. Another restore claims the empty database between this run's probe (and its dump scan) and its claim.
fake_db 0
fake_set claimrace claimed
rb_run race "$DUMP"
rb_expect "another restore claimed the database meanwhile: this one stops at its own claim, writes nothing and cannot archive or adopt it" 1 'cannot claim database' 'RUN: restore database\|stamped as restore'
if fake_inflight && [ "$(fake_loads)" = 0 ] && [ -z "$(ls -A "$T/rb-race/work/state/kv" 2> /dev/null)" ]; then ok "the other restore's in-flight table is untouched (this run did not drop it), nothing was loaded"; else bad "the lost claim disturbed the other restore"; fi

# 6. The same name, host and port on ANOTHER server: that server's failed restore is none of this one's business.
FW="$T/w2"
mkdir -p "$FW"
cp "$T/fakemysql" "$FW/fakemysql"
fake_db none
fake_after 120
fake_set storemarker 1
rb_run nwa "$DUMP"
rb_expect "the same target string on another server (empty): it restores there, the failed restore on the first server does not matter" 1 'stamped as restore' 'holds the table'
if ! fake_inflight && [ "$(fake_loads)" = 1 ] && [[ "$(rb_kv nwa restore.id)" =~ ^[0-9a-f]{32}$ ]]; then ok "the restore was verified complete, its in-flight table dropped, and the database stamped"; else bad "the in-flight table of the finished restore (loads $(fake_loads))"; fi
rb_run nwa "$DUMP"
rb_expect "a re-run on the finished kit restore does not restore over it, and is not refused for the in-flight table (it is gone)" 1 'carries this rehearsal.s marker' 'holds the table\|RUN: restore database'
if [ "$(fake_loads)" = 1 ]; then ok "the client was not given a second restore"; else bad "the re-run restored again ($(fake_loads) loads)"; fi
fake_db none
fake_after 120
fake_set noconfig 1
rb_run nocfg "$DUMP"
rb_expect "a restore that brought no mdl_config table is not stamped, and keeps its in-flight table" 1 'has no mdl_config table' 'stamped as restore'
if fake_inflight; then ok "the in-flight table of the unfinished restore stays"; else bad "the in-flight table was dropped for a restore with no config table"; fi
FW="$T"
if fake_inflight; then ok "the first server still holds its own in-flight table"; else bad "the first server's in-flight table"; fi

# 7. One --execute run per work directory: a step run alone takes REHEARSAL_WORK/.run.lock, run_all.sh's own steps use the one it holds.
mkdir -p "$T/rb-lock/work/.run.lock"
printf '4242\n' > "$T/rb-lock/work/.run.lock/pid"
fake_db 0
rb_run lock "$DUMP"
if [ "$RC" = 3 ] && printf '%s' "$OUT" | grep -q 'Another rehearsal run holds'; then ok "a step run alone while another run holds .run.lock stops with exit 3"; else bad "the held lock stops the step (rc ${RC})" "$OUT"; fi
if [ -d "$T/rb-lock/work/.run.lock" ] && [ ! -e "$T/rb-lock/work/state" ] && [ ! -e "$T/rb-lock/work/logs" ] && ! fake_inflight && [ "$(fake_loads)" = 0 ]; then
    ok "the refused run left the other run's lock, its state and logs and the database alone"
else bad "the refused run touched something"; fi
fake_db 400
export REHEARSAL_RUN_LOCK="$T/rb-lock/work/.run.lock" REHEARSAL_RUN_LOCK_PID=4242
rb_run lock "RESTORE_DONE_BY_HAND=some_other_db"
unset REHEARSAL_RUN_LOCK REHEARSAL_RUN_LOCK_PID
rb_expect "a step started by run_all.sh (REHEARSAL_RUN_LOCK and the pid its lock file holds) goes on" 1 'carries no rehearsal-kit marker' 'Another rehearsal run holds'
if [ -d "$T/rb-lock/work/.run.lock" ]; then ok "and leaves the lock to run_all.sh (it did not take it, so it does not release it)"; else bad "a step released the lock run_all.sh holds"; fi
export REHEARSAL_RUN_LOCK="$T/rb-lock/work/.run.lock" REHEARSAL_RUN_LOCK_PID=999
rb_run lock "RESTORE_DONE_BY_HAND=some_other_db"
unset REHEARSAL_RUN_LOCK REHEARSAL_RUN_LOCK_PID
if [ "$RC" = 3 ] && printf '%s' "$OUT" | grep -q 'Another rehearsal run holds'; then ok "a REHEARSAL_RUN_LOCK whose pid is not the lock's is not believed"; else bad "a forged run lock is believed (rc ${RC})" "$OUT"; fi
rm -rf "$T/rb-lock/work/.run.lock"

# 8. The scan for production and UAT schemas needs a schema list: an empty answer is "cannot be reached", not "none forbidden here".
fake_db 400
fake_set schemata blank
rb_run schemata
rb_expect "a schema list that comes back empty (it always names information_schema) leaves the server 'unreachable'" 1 'schema list of .* could not be read' 'carries no rehearsal-kit marker'
fake_set schemata prod
rb_run schemata
rb_expect "a server whose list names airpayprod is still refused" 1 "holds the schema 'airpayprod'"

# 9. One rehearsal, one moodledata: the fact that a step after 01 used the dataroot survives the rotation a new restore makes.
mkdir -p "$T/mdsrc/filedir/11/f6"
printf 'x' > "$T/mdsrc/filedir/11/f6/11f6ad8ec52a2984abaafd7c3b516503785c2072"
tar -C "$T/mdsrc" -cf "$T/md-arch.tar" filedir
ARCH="RESTORE_MOODLEDATA_ARCHIVE=$T/md-arch.tar"
# An uncompressed tar needs its checksum (round 7): the operator vouches for each archive below with the one taken "where it was made".
ARCHSHA="RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/md-arch.tar" | cut -d ' ' -f 1)"
for variant in used idle; do
    # Rehearsal A: a database restored by hand and the moodledata unpacked from the archive (the stand-in then cannot show the marker
    # back, so step 01 stops at the marker check, after the unpack has been recorded). In the 'used' variant a step after 01 ran too.
    fake_db 400
    rb_run "m${variant}" "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
    rb_expect "(rehearsal A, ${variant}) hand restore stamped, moodledata unpacked from the archive and recorded" 1 'unpacked from RESTORE_MOODLEDATA_ARCHIVE'
    if [ "$variant" = used ]; then
        printf 'status=ok\n' > "$T/rb-m${variant}/work/state/05.status"
    fi
    # Rehearsal B: a kit restore into a new, empty database that dies part way (the rotation of A's state/ happens here)...
    fake_db none 1
    rb_run "m${variant}" "$DUMP" "$ARCH" "$ARCHSHA"
    rb_expect "(rehearsal B, ${variant}) a kit restore of the next rehearsal dies part way" 1 'the database restore failed'
    # ...and is retried on the dropped and re-created database.
    fake_db 0
    fake_after 120
    rb_run "m${variant}" "$DUMP" "$ARCH" "$ARCHSHA"
    if [ "$variant" = used ]; then
        rb_expect "B's retry refuses A's dataroot: a step after 01 ran against it, though A's status files are in archive/ by now" 1 'cannot be reused for a new restore' 'reusing it for the new restore'
    else
        rb_expect "B's retry reuses A's dataroot when no step after 01 ran against it (the control)" 1 'reusing it for the new restore' 'cannot be reused'
    fi
done

# 10. The retried read itself.
t_count_once() {
    rm -f "$T/cr.n"
    f() { local c; c="$(cat "$T/cr.n" 2> /dev/null || printf 0)"; printf '%s' $((c + 1)) > "$T/cr.n"; [ "$c" = 0 ] || printf '7\r\n'; }
    count_retry f
}
t_count_never() { f() { :; }; count_retry f; }
t_count_fails() { f() { printf '3\n'; return 4; }; count_retry f; }
res="$(in_kit t_count_once)"
if [ "$(printf '%s' "$res" | head -n 1)" = 7 ] && [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "count_retry asks again when the client printed nothing (and drops the carriage return)"; else bad "count_retry asks again after an empty answer" "$res"; fi
[ "$(in_kit t_count_never | tail -n 1)" = rc=1 ] && ok "count_retry: an answer that stays empty is an error, never a value" || bad "count_retry: three empty answers"
[ "$(in_kit t_count_fails | tail -n 1)" = rc=1 ] && ok "count_retry: a failing command is an error, whatever it printed" || bad "count_retry: a failing command"

printf 'step 01: the moodledata half of the in-flight record, the error-based read, the claim before the archive, the dump that names the table (Stage B tools fix round 6)\n'
# A stand-in "server" of its own, so the state of the sections above is not in the way.
FW="$T/w3"
mkdir -p "$FW"
cp "$T/fakemysql" "$FW/fakemysql"
# wait_file FILE [HALF-SECONDS]: poll until FILE exists (the stand-in's flags, the status of a background run).
wait_file() {
    local f="$1" n="${2:-360}" i=0
    while [ ! -e "$f" ] && [ "$i" -lt "$n" ]; do
        sleep 0.5
        i=$((i + 1))
    done
    [ -e "$f" ]
}
# The archives. filedir/ comes first (one content file), then a 2,000,000-byte language pack. md-cut.tar is cut inside that file: tar stops
# with 'Unexpected EOF' AFTER the whole filedir is there, which is the cut the filedir gate cannot see.
mkdir -p "$T/mdcut/filedir/11/f6" "$T/mdcut/lang/hi"
printf 'x' > "$T/mdcut/filedir/11/f6/11f6ad8ec52a2984abaafd7c3b516503785c2072"
head -c 2000000 /dev/zero > "$T/mdcut/lang/hi/langconfig.bin"
tar -C "$T/mdcut" -cf "$T/md-full.tar" filedir lang
head -c 600000 "$T/md-full.tar" > "$T/md-cut.tar"
# The kit refuses an archive that is cut (fix round 6b) before it unpacks anything, and since round 8 also when the operator vouches for the
# cut archive with a checksum of it (a tar must end where a tar ends, whatever its checksum says). So the cases below that need an unpack that
# dies part way (the in-flight file, the marker without an unpacked line) give the kit a COMPLETE archive and a tar that runs out of disk: the
# stand-in tar (shim-cut) is the real tar, and then cuts the language pack it has just unpacked and exits 2, as tar does on a full disk.
CUTSHA="$(sha256sum "$T/md-cut.tar" | cut -d ' ' -f 1)"
FULLSHA="$(sha256sum "$T/md-full.tar" | cut -d ' ' -f 1)"
REALTAR="$(command -v tar)"
mkdir -p "$T/shim-cut"
cat > "$T/shim-cut/tar" <<'SHIM'
#!/usr/bin/env bash
# The real tar ($SHIM_REALTAR); when $SHIM_CUT names a file (below the -C directory) an unpack then runs out of disk: that file is cut, tar exits 2.
"$SHIM_REALTAR" "$@"
rc=$?
if [ -n "${SHIM_CUT:-}" ]; then
    dir=""
    prev=""
    for a in "$@"; do
        [ "$prev" = -C ] && dir="$a"
        prev="$a"
    done
    case " $* " in
        *" -xf "*)
            if [ -f "$dir/$SHIM_CUT" ]; then
                head -c 596480 "$dir/$SHIM_CUT" > "$dir/$SHIM_CUT.cut" && mv "$dir/$SHIM_CUT.cut" "$dir/$SHIM_CUT"
                echo "tar: $SHIM_CUT: Wrote only 596480 of 2000000 bytes (stand-in: the disk is full)" >&2
                exit 2
            fi
            ;;
    esac
fi
exit $rc
SHIM
chmod +x "$T/shim-cut/tar"
# An archive made from a moodledata whose own unpack had not finished: it carries the in-flight file.
mkdir -p "$T/mdinf/filedir/11/f6"
printf 'x' > "$T/mdinf/filedir/11/f6/11f6ad8ec52a2984abaafd7c3b516503785c2072"
printf 'restore_id=0123456789abcdef0123456789abcdef\narchive=/elsewhere/old.tar|1|2\nstarted=2026-10-01T00:00:00Z\n' > "$T/mdinf/.rehearsal_unpack_inflight"
tar -C "$T/mdinf" -cf "$T/md-inf.tar" filedir .rehearsal_unpack_inflight
INFL=".rehearsal_unpack_inflight"

# M1. A kit unpack of the moodledata that is cut short: the cut filedir must never be adopted by a re-run, whatever the archive variable says.
MD="$T/rb-cut/data"
fake_db none
fake_after 120
fake_set storemarker 1
OLDPATH="$PATH"
PATH="$T/shim-cut:$PATH"
export SHIM_REALTAR="$REALTAR" SHIM_CUT=lang/hi/langconfig.bin
rb_run cut "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
PATH="$OLDPATH"
unset SHIM_CUT
rb_expect "(M1) a moodledata unpack that dies part way (a full disk: tar cuts the language pack and exits 2): the step says the unpack is UNFINISHED and never adopted" 1 'tar of the moodledata failed: the unpack is UNFINISHED' 'restore check done'
id="$(rb_kv cut restore.id)"
if [ -f "$MD/$INFL" ] && grep -qx "restore_id=${id}" "$MD/$INFL" && grep -q '^archive=.*md-full.tar|' "$MD/$INFL" && grep -q '^started=' "$MD/$INFL" \
        && [[ "$(sed -n 2p "$MD/.rehearsal-kit-restore-id")" == archive=* ]] && [ -z "$(sed -n 3p "$MD/.rehearsal-kit-restore-id")" ] \
        && [ -f "$MD/filedir/11/f6/11f6ad8ec52a2984abaafd7c3b516503785c2072" ] && [ "$(wc -c < "$MD/lang/hi/langconfig.bin")" -lt 2000000 ]; then
    ok "(M1) the cut unpack left the in-flight file (restore id, archive, start), a marker with an archive line and no unpacked line, a whole filedir and a cut language pack"
else bad "(M1) what the cut unpack left in the moodledata ($(ls -A "$MD" | tr '\n' ' '))"; fi
rb_run cut "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-cut.tar"
rb_expect "(M1) the same run again, the archive still named: refused, and nothing is unpacked again" 1 'did not finish' 'RUN: restore moodledata'
rb_run cut "$DUMP"
rb_expect "(M1) the retry with RESTORE_MOODLEDATA_ARCHIVE UNSET (the reproduced defect): refused, the cut filedir is not adopted" 1 'holds an unpack of the moodledata that did not finish' 'not restoring over it'
if ! grep -qx 'status=ok' "$T/rb-cut/work/state/01.status"; then ok "(M1) step 01 did not finish ok on the cut copy (01.status is not ok)"; else bad "(M1) step 01 finished ok on a cut moodledata"; fi
rb_run cut "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_BY_HAND=$MD"
rb_expect "(M1) the two hand statements (the database, and this very path) do not override it" 1 'did not finish' 'stamping it as this rehearsal'
rb_run cut2 "MOODLEDATA=$MD"
rb_expect "(M1) a NEW REHEARSAL_WORK on the same moodledata, archive unset: refused all the same" 1 'did not finish' 'adopting it'
rb_kv_empty cut2 "(M1) the new work directory adopted and recorded nothing"
rm -f "$MD/$INFL"
rb_run cut "$DUMP"
rb_expect "(M1) the in-flight file gone (a kit before it, or removed by hand) but the marker records an archive and no unpacked line: refused as well" 1 'records an unpack of an archive' 'not restoring over it'
rm -rf "$MD"
rb_run cut "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
rb_expect "(M1, control) an emptied moodledata and a complete archive: the kit unpacks it" 1 'unpacked from RESTORE_MOODLEDATA_ARCHIVE' 'did not finish'
if [ ! -e "$MD/$INFL" ] && [[ "$(sed -n 3p "$MD/.rehearsal-kit-restore-id")" == unpacked=* ]] && [ "$(wc -c < "$MD/lang/hi/langconfig.bin")" = 2000000 ]; then
    ok "(M1, control) the in-flight file went only after the unpack was verified, and the marker records the unpack as finished"
else bad "(M1, control) the finished unpack's state ($(ls -A "$MD" | tr '\n' ' '))"; fi
fake_db none
fake_after 120
fake_set storemarker 1
rb_run inf "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-inf.tar" "RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/md-inf.tar" | cut -d ' ' -f 1)"
rb_expect "(M1) an archive that carries its own in-flight file (it was made from an unfinished unpack) is not a complete unpack, though tar succeeds" 1 'carries its own .rehearsal_unpack_inflight' 'unpacked from RESTORE_MOODLEDATA_ARCHIVE'
if [ -f "$T/rb-inf/data/$INFL" ]; then ok "(M1) the file stays, so every kit step refuses that directory"; else bad "(M1) the archive's in-flight file was removed"; fi

# S1. The in-flight table is read from the table, by the server's error: a count that comes back wrong cannot read it as absent.
fake_db 120
fake_set sentinel 'in-flight (stand-in)'
fake_once zeroinflightall
rb_run s1 "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(S1) a count of the in-flight table that always answers 0 (a misread that would have said 'no table') cannot adopt the partial copy" 1 'holds the table zz_rehearsal_restore_inflight' 'restored by hand\|stamped as restore'
if [ ! -e "$FW/fake.asked" ] && fake_inflight && [ -z "$(rb_kv s1 restore.id)" ]; then ok "(S1) the kit reads the table itself: the information_schema count was never asked, nothing was stamped"; else bad "(S1) the kit asked the count of the in-flight table, or stamped"; fi
fake_once inflighterr
rb_run s1 "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(S1) a lost connection on that read (once): asked again, the partial copy is found and refused" 1 'holds the table zz_rehearsal_restore_inflight' 'stamped as restore'
if [ ! -e "$FW/fake.once" ]; then ok "(S1) (the stand-in did lose the connection once)"; else bad "(S1) the stand-in's fault was not used"; fi
fake_once inflighterrall
rb_run s1 "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(S1) a read that always fails with an error other than 1146 is 'could not be read', never 'absent'" 1 'could not be read' 'stamped as restore'
fake_db 400
fake_once blankinflight
rb_run s1b "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(S1) the other direction: the client prints nothing once for an ABSENT in-flight table, error 1146 is the answer on the second read, and the plain hand restore is adopted" 1 'restored by hand' 'holds the table'
if [ ! -e "$FW/fake.once" ]; then ok "(S1) (the stand-in did print nothing once)"; else bad "(S1) the stand-in's blank answer was not used"; fi

# S2. require_kit_marker, the gate of steps 02 to 11, refuses a partial copy that carries the right marker.
RKM_ID=0123456789abcdef0123456789abcdef
rkm_setup() {
    fake_db 120
    printf '%s\n' "$RKM_ID" > "$FW/fake.marker"
    rm -rf "$T/rkm"
    mkdir -p "$T/rkm/state/kv" "$T/rkm/data"
    printf '%s\n' "$RKM_ID" > "$T/rkm/state/kv/restore.id"
    printf '%s\n' "$RKM_ID" > "$T/rkm/data/.rehearsal-kit-restore-id"
    # and step 01 has finished for this restore (round 7: the gate asks for it as well): its status, state/kv/restore.verified and the database's record
    printf 'status=ok\n' > "$T/rkm/state/01.status"
    printf '%s\n' "$RKM_ID" > "$T/rkm/state/kv/restore.verified"
    printf '%s\n' "$RKM_ID" > "$FW/fake.step01"
}
t_rkm() {
    EXECUTE=1 STEP_ID=t MYSQL_BIN="$FW/fakemysql" DB_NAME=stageb_selftest REHEARSAL_DB_ALLOWLIST=stageb_selftest DB_PREFIX=mdl_ \
        DB_HOST=127.0.0.1 DB_PORT="" DB_USER=rehearsal DB_PASS_FILE="$T/db.pass" STATE_DIR="$T/rkm/state" MOODLEDATA="$T/rkm/data"
    require_kit_marker
}
rkm_setup
res="$(in_kit t_rkm)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(S2, control) a database and a moodledata that carry this rehearsal's marker pass require_kit_marker"; else bad "(S2, control) require_kit_marker" "$res"; fi
rkm_setup
fake_set sentinel 'in-flight (stand-in)'
res="$(in_kit t_rkm)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ] && printf '%s' "$res" | grep -q 'holds the table zz_rehearsal_restore_inflight' && printf '%s' "$res" | grep -q 'PARTIAL copy whatever marker'; then
    ok "(S2) a database that holds the in-flight table is refused although it carries the right marker"
else bad "(S2) the in-flight table behind a marker" "$res"; fi
rkm_setup
fake_once inflighterrall
res="$(in_kit t_rkm)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ] && printf '%s' "$res" | grep -q 'could not be read'; then ok "(S2) an in-flight table that cannot be told is refused (not taken for absent)"; else bad "(S2) the unreadable in-flight table" "$res"; fi
rkm_setup
printf 'restore_id=%s\narchive=/a/b.tar|1|2\nstarted=2026-10-08T00:00:00Z\n' "$RKM_ID" > "$T/rkm/data/$INFL"
res="$(in_kit t_rkm)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ] && printf '%s' "$res" | grep -q 'holds an unpack that did not finish'; then ok "(S2) a moodledata that holds the in-flight unpack file is refused although it carries the right marker"; else bad "(S2) the in-flight file behind a marker" "$res"; fi
rkm_setup
printf '%s\narchive=/a/b.tar|1|2\n' "$RKM_ID" > "$T/rkm/data/.rehearsal-kit-restore-id"
res="$(in_kit t_rkm)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ] && printf '%s' "$res" | grep -q 'records an unpack of an archive'; then ok "(S2) a marker with an archive line and no unpacked line is refused"; else bad "(S2) the unfinished marker" "$res"; fi
printf '%s\narchive=/a/b.tar|1|2\nunpacked=2026-10-08T00:00:00Z\n' "$RKM_ID" > "$T/rkm/data/.rehearsal-kit-restore-id"
res="$(in_kit t_rkm)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(S2, control) a marker that records the finished unpack passes"; else bad "(S2, control) the finished marker" "$res"; fi

# S3. A dump that names the in-flight table is refused with the other unsafe statements.
{ printf 'DROP TABLE IF EXISTS `zz_rehearsal_restore_inflight`;\nCREATE TABLE `zz_rehearsal_restore_inflight` (`restore_id` char(32) NOT NULL);\n'; cat "$T/dumps/good.sql"; } > "$T/dumps/inflight.sql"
gzip -c "$T/dumps/inflight.sql" > "$T/dumps/inflight.sql.gz"
for f in inflight.sql inflight.sql.gz; do
    res="$(in_kit dump_unsafe_statement "$T/dumps/$f")"
    if printf '%s' "$res" | head -n 1 | grep -q 'zz_rehearsal_restore_inflight'; then ok "(S3) a dump that names the in-flight table is flagged (${f})"; else bad "(S3) the in-flight dump flagged (${f})" "$res"; fi
done
fake_db none
rb_run s3 "RESTORE_DB_DUMP=$T/dumps/inflight.sql"
rb_expect "(S3) step 01 refuses that dump before it creates or claims anything" 1 'must not be restored here' 'cannot create database\|RUN: restore database'
if [ "$(cat "$FW/fake.tables")" = none ] && ! fake_inflight && [ "$(fake_loads)" = 0 ]; then ok "(S3) nothing was created, claimed or loaded"; else bad "(S3) the refused dump touched the database"; fi

# S4. The earlier rehearsal's state/ moves to archive/ only after the claim: a run that misreads a finished database as absent stops with
# state/ in place, and the next plain re-run adopts its own database.
fake_db none
fake_after 120
fake_set storemarker 1
rb_run s4 "$DUMP"
rb_expect "(S4, setup) a kit restore finishes the database (the step stops later, at the moodledata)" 1 'stamped as restore'
printf 'status=ok\n' > "$T/rb-s4/work/state/05.status"
fake_once zero
rb_run s4 "$DUMP"
rb_expect "(S4) an idempotent re-run reads the finished database as absent once: it stops at 'cannot create database'" 1 'cannot create database' 'RUN: restore database'
if [ -f "$T/rb-s4/work/state/05.status" ] && [[ "$(rb_kv s4 restore.id)" =~ ^[0-9a-f]{32}$ ]] && [ ! -e "$T/rb-s4/work/archive" ]; then
    ok "(S4) the finished rehearsal's state/ is where it was (05.status, restore.id), and nothing was moved to archive/"
else bad "(S4) the misread run moved the finished rehearsal's state ($(ls "$T/rb-s4/work" | tr '\n' ' '))"; fi
rb_run s4 "$DUMP"
rb_expect "(S4) the next plain re-run adopts its own database, not 'another rehearsal's'" 1 'carries this rehearsal.s marker' 'another rehearsal'
# A claim that is lost (another restore got there first) leaves the state alone as well.
fake_db 0
fake_set claimrace claimed
printf 'status=ok\n' > "$T/rb-s4/work/state/06.status"
rb_run s4 "$DUMP"
rb_expect "(S4) a claim that loses the race to another restore stops at the claim" 1 'cannot claim database'
if [ -f "$T/rb-s4/work/state/06.status" ] && [ ! -e "$T/rb-s4/work/archive" ]; then ok "(S4) and archives nothing"; else bad "(S4) the lost claim moved the state to archive/"; fi

# Two real runs, deterministically. Run A is held at its claim by the stand-in client until a flag is raised; run B (another work
# directory) restores the whole empty database meanwhile; A is then released, finds a database that is no longer empty right after its
# claim, releases itself and writes nothing.
rb_bg() {
    local n="$1"
    shift
    base_env "$T/rb-$n.env" "REHEARSAL_WORK=$T/rb-$n/work" "MOODLEDATA=$T/rb-$n/data" "MYSQL_BIN=$FW/fakemysql" \
        "PRODUCTION_DB_ENDPOINT=live-db.example.internal" "$@"
    rm -f "$T/rb-$n.rc"
    ( bash "$KIT/01_restore_check.sh" --env "$T/rb-$n.env" --execute > "$T/rb-$n.out" 2>&1; printf '%s\n' "$?" > "$T/rb-$n.rc" ) &
    BGPID=$!
}
fake_db 0
fake_after 120
fake_set storemarker 1
fake_set blockclaim 1
rb_bg conca "$DUMP"
if wait_file "$FW/fake.blocked" 360; then ok "(concurrency) run A reached its claim and the stand-in client holds it there"; else bad "(concurrency) run A never reached its claim"; fi
rb_run concb "$DUMP"
rb_expect "(concurrency) run B, another work directory, restores the empty database meanwhile and stamps it" 1 'stamped as restore' 'cannot claim'
touch "$FW/fake.release"
if wait_file "$T/rb-conca.rc" 360; then
    sleep 2
    a_out="$(cat "$T/rb-conca.out" 2> /dev/null)"
    if [ "$(cat "$T/rb-conca.rc")" != 0 ] && printf '%s' "$a_out" | grep -q 'right after it was claimed' && ! printf '%s' "$a_out" | grep -q 'RUN: restore database\|stamped as restore'; then
        ok "(concurrency) run A, released, found the database no longer empty right after its claim and stopped without loading"
    else bad "(concurrency) run A's end (rc $(cat "$T/rb-conca.rc"))" "$(printf '%s\n' "$a_out" | grep -v '^$' | tail -n 6)"; fi
else
    bad "(concurrency) run A never finished"
    kill "$BGPID" 2> /dev/null || true
fi
if [ "$(fake_loads)" = 1 ] && ! fake_inflight && [ "$(cat "$FW/fake.tables")" = 120 ] && [ "$(cat "$FW/fake.marker")" = "$(rb_kv concb restore.id)" ] && [ -z "$(ls -A "$T/rb-conca/work/state/kv" 2> /dev/null)" ]; then
    ok "(concurrency) one load only (B's), no in-flight table left behind, B's 120 tables and marker intact, A recorded nothing"
else bad "(concurrency) the database after both runs (loads $(fake_loads), tables $(cat "$FW/fake.tables"))"; fi

printf 'run_all.sh: a TERM sent to it alone does not release the lock while a step runs (Stage B tools fix round 6)\n'
# A copy of the kit whose steps 01, 02 and 12 are stubs: 01 runs until a flag is raised.
K2="$T/kitcopy"
SD="$T/sig"
mkdir -p "$K2/lib" "$SD"
cp "$KIT"/*.sh "$K2/"
rm -f "$K2/selftest.sh"
cp "$KIT"/lib/* "$K2/lib/"
cat > "$K2/01_restore_check.sh" <<'STUB'
#!/usr/bin/env bash
: > "$STUBDIR/01.started"
while [ ! -f "$STUBDIR/release" ]; do sleep 0.2; done
: > "$STUBDIR/01.finished"
exit 0
STUB
for n in 02_source_baseline 12_summary; do
    printf '#!/usr/bin/env bash\n: > "$STUBDIR/%s.started"\nexit 0\n' "${n%%_*}" > "$K2/$n.sh"
done
base_env "$T/rb-sig.env" "REHEARSAL_WORK=$T/rb-sig/work" "MOODLEDATA=$T/rb-sig/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
STUBDIR="$SD" bash "$K2/run_all.sh" --env "$T/rb-sig.env" --execute --only 01,02,12 > "$T/rb-sig.out" 2>&1 &
RP=$!
if wait_file "$SD/01.started" 240; then
    kill -TERM "$RP"
    sleep 3
    if kill -0 "$RP" 2> /dev/null && [ -d "$T/rb-sig/work/.run.lock" ] && [ ! -e "$SD/01.finished" ]; then
        ok "TERM to run_all.sh alone: it is still alive and still holds .run.lock while step 01 runs (it used to remove the lock and leave the step running)"
    else bad "TERM to run_all.sh released the lock while step 01 still ran (alive: $(kill -0 "$RP" 2> /dev/null && echo yes || echo no), lock: $([ -d "$T/rb-sig/work/.run.lock" ] && echo held || echo gone))"; fi
    touch "$SD/release"
    rc=0
    wait "$RP" || rc=$?
    if [ "$rc" = 143 ] && [ -e "$SD/01.finished" ] && [ ! -e "$SD/02.started" ] && [ ! -e "$SD/12.started" ] && [ ! -d "$T/rb-sig/work/.run.lock" ]; then
        ok "once step 01 has finished run_all.sh starts no further step (the summary included), releases the lock and exits 143"
    else bad "run_all.sh after the TERM (rc ${rc}; 02 $([ -e "$SD/02.started" ] && echo started || echo not started); lock $([ -d "$T/rb-sig/work/.run.lock" ] && echo held || echo gone))" "$(tail -n 6 "$T/rb-sig.out")"; fi
    if grep -q 'SIGTERM received by run_all.sh' "$T/rb-sig.out" && grep -q 'STOPPED by SIGTERM' "$T/rb-sig.out"; then ok "run_all.sh says what it does with the signal and that it stopped"; else bad "run_all.sh does not log the signal" "$(tail -n 6 "$T/rb-sig.out")"; fi
else
    bad "the stub step 01 never started"
    touch "$SD/release"
    kill "$RP" 2> /dev/null || true
fi
FW="$T"

printf 'step 01: an archive cut at a member header, a stand-in for the checksum, and the archive that changes under the unpack (Stage B tools fix round 6b, must-fix 1)\n'
FW="$T/w4"
mkdir -p "$FW"
cp "$T/fakemysql" "$FW/fakemysql"
# md-cuthdr.tar is md-full.tar cut exactly where the header of lang/ begins (after filedir/ and its one file). GNU tar lists and unpacks
# it with exit status 0 and no message: the cut the round 6 review reproduced, which tar's own status cannot see.
head -c 2560 "$T/md-full.tar" > "$T/md-cuthdr.tar"
tar -tf "$T/md-cuthdr.tar" > /dev/null 2>&1 && TARCUTRC=0 || TARCUTRC=1
if [ "$TARCUTRC" = 0 ]; then ok "(premise) GNU tar reads the archive cut at a member header with exit status 0, so tar's status cannot tell it from a whole one"; else bad "(premise) GNU tar reads the archive cut at a member header with exit status 0 (this tar does not: the premise of the tar-end checks does not hold on this box)"; fi
# The same cut tar, written into a compressor that was closed normally (a tar that died in 'tar c | gzip'): a valid .gz around a cut tar.
gzip -c "$T/md-cuthdr.tar" > "$T/md-cuthdr.tar.gz"
gzip -c "$T/md-full.tar" > "$T/md-full.tar.gz"
head -c 1000 "$T/md-full.tar.gz" > "$T/md-trunc.tar.gz"
if gzip -t "$T/md-cuthdr.tar.gz" 2> /dev/null; then ok "(premise) gzip -t passes the .gz that wraps the cut tar: the compression is whole, the tar inside is not"; else bad "(premise) the .gz around the cut tar is a valid gzip file"; fi
# nothing_touched NAME: the refused run restored, claimed, stamped and unpacked nothing.
nothing_touched() {
    if [ "$(fake_loads)" = 0 ] && ! fake_inflight && [ "$(cat "$FW/fake.tables")" = none ] && [ ! -e "$T/rb-$1/data/.rehearsal-kit-restore-id" ] \
            && [ ! -e "$T/rb-$1/data/$INFL" ] && [ ! -e "$T/rb-$1/data/filedir" ] && [ -z "$(rb_kv "$1" restore.id)" ]; then
        ok "$2"
    else bad "$2 (loads $(fake_loads), tables $(cat "$FW/fake.tables"), data: $(ls -A "$T/rb-$1/data" 2> /dev/null | tr '\n' ' '))"; fi
}
for variant in "md-cuthdr.tar:a plain tar cut at a member header" "md-cuthdr.tar.gz:a valid .tar.gz that holds a cut tar" "md-trunc.tar.gz:a .tar.gz whose compressed stream is cut"; do
    f="${variant%%:*}"
    fake_db none
    fake_after 120
    fake_set storemarker 1
    rb_run "pa-${f%%.*}-${f##*.}" "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/$f"
    rb_expect "(6b-1) ${variant#*:} is refused as not proven complete" 1 'is not proven complete' 'RUN: restore\|stamped as restore\|unpacked from'
    nothing_touched "pa-${f%%.*}-${f##*.}" "(6b-1) the refusal came before the database restore: nothing was loaded, claimed, stamped or unpacked (${f})"
done
# The good .tar.gz: read to its end, its tar ends with the two zero blocks.
fake_db none
fake_after 120
fake_set storemarker 1
rb_run pgz "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar.gz"
rb_expect "(6b-1, control) a complete .tar.gz is accepted and unpacked" 1 'unpacked from RESTORE_MOODLEDATA_ARCHIVE' 'is not proven complete'
if [ "$(wc -c < "$T/rb-pgz/data/lang/hi/langconfig.bin")" = 2000000 ] && [ "$(rb_kv pgz restore.moodledata_proof)" = tar-end ] && [ -z "$(rb_kv pgz restore.moodledata_sha256)" ]; then
    ok "(6b-1, control) the whole language pack is there, and the proof used (tar-end, no checksum) is recorded in state/kv"
else bad "(6b-1, control) the unpack of the good .tar.gz, or its recorded proof ($(rb_kv pgz restore.moodledata_proof))"; fi
# RESTORE_MOODLEDATA_SHA256: must match before anything happens, and is recorded.
fake_db none
fake_after 120
fake_set storemarker 1
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$CUTSHA"
rb_expect "(6b-1) a RESTORE_MOODLEDATA_SHA256 that is not the archive's checksum is refused" 1 'has SHA-256 .*not the RESTORE_MOODLEDATA_SHA256' 'RUN: restore\|unpacked from'
nothing_touched psh "(6b-1) ... before the database restore and before anything is unpacked"
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=not-a-checksum"
rb_expect "(6b-1) a RESTORE_MOODLEDATA_SHA256 that is not 64 hexadecimal digits is refused" 1 'is not a SHA-256' 'RUN: restore'
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=${FULLSHA^^}"
rb_expect "(6b-1) the right checksum (in capitals: compared as lower case) is accepted and the archive unpacked" 1 'unpacked from RESTORE_MOODLEDATA_ARCHIVE' 'has SHA-256'
if [ "$(rb_kv psh restore.moodledata_sha256)" = "$FULLSHA" ] && [ "$(rb_kv psh restore.moodledata_proof)" = sha256 ]; then
    ok "(6b-1) the checksum and the proof 'sha256' are recorded in state/kv (the summary prints them)"
else bad "(6b-1) the recorded checksum ($(rb_kv psh restore.moodledata_sha256))"; fi

# (Stage B tools fix round 7, M1) A checksum that is set is ALWAYS checked, also when filedir/ is already there (a re-run: the 'psh' rehearsal above is a finished
# unpack of md-full.tar), and one that has no archive to check is refused.
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$CUTSHA"
rb_expect "(7-M1) a re-run on a finished unpack, with a RESTORE_MOODLEDATA_SHA256 that is not the archive's: refused (it used to be ignored: filedir/ was there)" 1 'has SHA-256 .*not the RESTORE_MOODLEDATA_SHA256' 'not restoring over it'
rm -f "$T/rb-psh/work/state/kv/restore.moodledata_proof" "$T/rb-psh/work/state/kv/restore.moodledata_sha256"
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
rb_expect "(7-M1) the same re-run with the right checksum: it is checked, and the finished unpack is kept" 1 'has the SHA-256 in RESTORE_MOODLEDATA_SHA256' 'RUN: restore moodledata'
if [ "$(rb_kv psh restore.moodledata_sha256)" = "$FULLSHA" ] && [ "$(rb_kv psh restore.moodledata_proof)" = sha256 ]; then
    ok "(7-M1) ... and the proof is recorded again (the summary prints it, also for an unpack that a new restore reuses)"
else bad "(7-M1) the proof after the re-run ('$(rb_kv psh restore.moodledata_proof)', '$(rb_kv psh restore.moodledata_sha256)')"; fi
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
rb_expect "(7-M1) a checksum with no RESTORE_MOODLEDATA_ARCHIVE is refused, not silently ignored" 1 'RESTORE_MOODLEDATA_SHA256 is set and RESTORE_MOODLEDATA_ARCHIVE is not' 'not restoring over it'
rb_run psh "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar"
rb_expect "(7-M1) an uncompressed tar needs its checksum on a re-run too (filedir/ is there; nothing in the tar can show it is whole)" 1 'is an uncompressed tar and RESTORE_MOODLEDATA_SHA256 is not set' 'not restoring over it'

# The zero-filled copy of the round 6b review: full size, every byte after the header of lang/ zero. Its last 1024 bytes are the two zero blocks, GNU tar
# takes them for the end of the archive and exits 0, and lang/ is simply not there.
head -c 2560 "$T/md-full.tar" > "$T/md-zero.tar"
head -c "$(($(stat -c %s "$T/md-full.tar") - 2560))" /dev/zero >> "$T/md-zero.tar"
if [ "$(stat -c %s "$T/md-zero.tar")" = "$(stat -c %s "$T/md-full.tar")" ]; then ok "(premise) the zero-filled copy has the full size of the archive"; else bad "(premise) the zero-filled copy's size"; fi
res="$(in_kit tar_ends_complete tar "$T/md-zero.tar")"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(premise) the old test, the last 1024 bytes, passes the zero-filled tar (rc 0): it cannot tell it from a whole one"; else bad "(premise) tar_ends_complete on the zero-filled tar" "$res"; fi
mkdir -p "$T/md-zero-x"
if tar -C "$T/md-zero-x" -xf "$T/md-zero.tar" 2> /dev/null && [ ! -e "$T/md-zero-x/lang" ] && [ -f "$T/md-zero-x/filedir/11/f6/11f6ad8ec52a2984abaafd7c3b516503785c2072" ]; then
    ok "(premise) GNU tar unpacks it with exit status 0 and no lang/: a cut that nothing but a checksum shows"
else bad "(premise) GNU tar unpacks the zero-filled copy with exit status 0 and no lang/ (this tar does not: the premise of the zero-filled-copy checks does not hold on this box)"; fi
# A zero-filled .tar.gz (the compressed file itself padded with zeros after its first bytes): the decompressor fails on it.
gzsize="$(stat -c %s "$T/md-full.tar.gz")"
head -c 1000 "$T/md-full.tar.gz" > "$T/md-zerogz.tar.gz"
head -c "$((gzsize - 1000))" /dev/zero >> "$T/md-zerogz.tar.gz"
for variant in "md-full.tar:a COMPLETE uncompressed tar" "md-zero.tar:a zero-filled uncompressed tar of the full size" "md-zerogz.tar.gz:a zero-filled .tar.gz"; do
    f="${variant%%:*}"
    fake_db none
    fake_after 120
    fake_set storemarker 1
    rb_run "pz-${f%%.*}" "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/$f"
    case "$f" in
        *.gz) rb_expect "(7-M1) ${variant#*:} without a checksum is refused (the decompressor fails on the zeros)" 1 'is not proven complete' 'RUN: restore\|stamped as restore\|unpacked from' ;;
        *) rb_expect "(7-M1) ${variant#*:} without RESTORE_MOODLEDATA_SHA256 is refused, however complete it is" 1 'is an uncompressed tar and RESTORE_MOODLEDATA_SHA256 is not set' 'RUN: restore\|stamped as restore\|unpacked from' ;;
    esac
    nothing_touched "pz-${f%%.*}" "(7-M1) the refusal came before anything was loaded, claimed, stamped or unpacked (${f})"
done
# With the checksum the operator vouches for exactly this file: the zero-filled copy is then the file that was made, and is unpacked (the checksum is the proof, and it
# is the one taken where the archive was made: a copy that stopped part way does not have it).
fake_db none
fake_after 120
fake_set storemarker 1
rb_run pzr "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-zero.tar" "RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/md-full.tar" | cut -d ' ' -f 1)"
rb_expect "(7-M1) the zero-filled copy with the checksum of the COMPLETE archive (the one taken where it was made) is refused: the copy is not that file" 1 'has SHA-256 .*not the RESTORE_MOODLEDATA_SHA256' 'RUN: restore\|unpacked from'
nothing_touched pzr "(7-M1) ... before anything was loaded, claimed, stamped or unpacked"
# An archive that is a plain tar but not complete in another way: not a tar at all.
printf 'this is not an archive\n' > "$T/md-text.tar"
fake_db none
rb_run ptx "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-text.tar"
rb_expect "(6b-1) a file that is no tar at all is refused too" 1 'is not proven complete'
# The archive that changes while it is unpacked: tar is replaced by a wrapper that appends to the archive after it has returned success.
mkdir -p "$T/shim"
REALTAR="$(command -v tar)"
printf '#!/usr/bin/env bash\n"%s" "$@"\nrc=$?\nif [ -f "%s/shim.append" ]; then printf "more" >> "$(cat "%s/shim.append")"; fi\nexit $rc\n' "$REALTAR" "$T" "$T" > "$T/shim/tar"
chmod +x "$T/shim/tar"
cp "$T/md-full.tar" "$T/md-grow.tar"
printf '%s\n' "$T/md-grow.tar" > "$T/shim.append"
fake_db none
fake_after 120
fake_set storemarker 1
OLDPATH="$PATH"
PATH="$T/shim:$PATH"
rb_run pgr "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-grow.tar" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
PATH="$OLDPATH"
rm -f "$T/shim.append"
rb_expect "(6b-1) an archive that grew while it was unpacked: tar succeeded, the unpack is NOT verified" 1 'changed while it was unpacked' 'unpacked from RESTORE_MOODLEDATA_ARCHIVE'
if [ -f "$T/rb-pgr/data/$INFL" ]; then ok "(6b-1) the in-flight file stays, so every kit step refuses that directory"; else bad "(6b-1) the in-flight file of the unverified unpack is gone"; fi
FW="$T"

printf 'step 01 and the steps: a signal never records success, and a step killed without a trap is never read as ok (Stage B tools fix round 6b, must-fix 2)\n'
FW="$T/w4"
K2="$T/kitcopy"
SD="$T/sig2"
mkdir -p "$SD" "$K2/lib"
cp "$KIT"/*.sh "$K2/"
rm -f "$K2/selftest.sh"
cp "$KIT"/lib/* "$K2/lib/"
# A real step (step_init, the status file, the lock, the exit trap) whose one command runs until a flag is raised, with a background job.
cat > "$K2/sigstep.sh" <<'STUB'
#!/usr/bin/env bash
# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 01 sigstep "$@"
: > "$STUBDIR/01.started"
sleep 1000 &
printf '%s\n' "$!" > "$STUBDIR/bg.pid"
bash -c 'while [ ! -f "$1/release" ]; do sleep 0.2; done; : > "$1/child.finished"' _ "$STUBDIR"
: > "$STUBDIR/after.child"
: > "$STUBDIR/01.finished"
step_end
exit 0
STUB
cp "$K2/sigstep.sh" "$K2/01_restore_check.sh"
# status_field NAME FIELD -> a field of state/01.status of that work directory
status_field() { sed -n "s/^$2=//p" "$T/rb-$1/work/state/01.status" 2> /dev/null | head -n 1; }
# alive PID -> 0 when that process exists
alive() { kill -0 "$1" 2> /dev/null; }

# Control: a step that finishes records ok, and does not leave its background job behind.
rm -rf "$SD"
mkdir -p "$SD"
touch "$SD/release"
base_env "$T/rb-sg0.env" "REHEARSAL_WORK=$T/rb-sg0/work" "MOODLEDATA=$T/rb-sg0/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
STUBDIR="$SD" bash "$K2/sigstep.sh" --env "$T/rb-sg0.env" --execute > "$T/rb-sg0.out" 2>&1
rc=$?
if [ "$rc" = 0 ] && [ "$(status_field sg0 status)" = ok ] && [ "$(status_field sg0 rc)" = 0 ] && [ -z "$(status_field sg0 signal)" ] && [ -e "$SD/after.child" ] && [ ! -d "$T/rb-sg0/work/.run.lock" ]; then
    ok "(6b-2, control) a step that finishes records status=ok rc=0, and releases its lock"
else bad "(6b-2, control) the finished step (rc ${rc}, status '$(status_field sg0 status)')" "$(tail -n 5 "$T/rb-sg0.out")"; fi
if ! alive "$(cat "$SD/bg.pid")"; then ok "(6b-2, control) the step's background job did not outlive it"; else bad "(6b-2, control) a background job of the step is still running"; kill "$(cat "$SD/bg.pid")" 2> /dev/null; fi

# A signal that was ignored when THIS script started (nohup, a launcher that sets SIGHUP to ignore) is inherited by every bash it starts,
# and bash cannot trap a signal that was ignored on entry: the HUP cases cannot be delivered there, which says nothing about the kit (a
# real run under sudo, ssh, tmux or screen does not have HUP ignored). Probed once, the way a background step is started below.
HUP_TRAPPABLE=0
bash -c 'trap "exit 7" HUP; kill -HUP $$; sleep 1; exit 0' > /dev/null 2>&1 &
wait $! 2> /dev/null
[ "$?" = 7 ] && HUP_TRAPPABLE=1

# A signal to a step run alone, while its command runs: TERM and HUP (INT takes the same handler; a background job of this script has INT
# ignored from the start, which a trap cannot undo, so it cannot be delivered here). The status is running while it runs; the signal is
# acted on when the command has returned; the step then records failure (128+n, signal=NAME), releases the lock and does not go on.
for name in TERM HUP USR1 ALRM; do
    num="$(kill -l "$name")"
    lname="sg-${name,,}"
    if [ "$name" = HUP ] && [ "$HUP_TRAPPABLE" != 1 ]; then
        skip "(6b-2) SIGHUP to a step alone: SIGHUP is ignored on entry in this environment (started under nohup?), so no bash here can trap it; run the suite without nohup"
        continue
    fi
    rm -rf "$SD"
    mkdir -p "$SD"
    base_env "$T/rb-${lname}.env" "REHEARSAL_WORK=$T/rb-${lname}/work" "MOODLEDATA=$T/rb-${lname}/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
    STUBDIR="$SD" bash "$K2/sigstep.sh" --env "$T/rb-${lname}.env" --execute > "$T/rb-${lname}.out" 2>&1 &
    SP=$!
    if wait_file "$SD/01.started" 240; then
        if [ "$(status_field "$lname" status)" = running ]; then ok "(6b-2) while the step runs its status file says status=running (SIG${name})"; else bad "(6b-2) the status file of a running step ($(status_field "$lname" status))"; fi
        kill -"$name" "$SP"
        sleep 3
        if alive "$SP" && [ -d "$T/rb-${lname}/work/.run.lock" ] && [ ! -e "$SD/child.finished" ] && [ "$(status_field "$lname" status)" = running ]; then
            ok "(6b-2) SIG${name} to a step alone: it is still alive, still holds .run.lock and has not recorded anything while its command runs"
        else bad "(6b-2) SIG${name} acted on before the command returned (alive $(alive "$SP" && echo yes || echo no), status '$(status_field "$lname" status)')"; fi
        touch "$SD/release"
        rc=0
        wait "$SP" || rc=$?
        if [ "$rc" = $((128 + num)) ] && [ ! -e "$SD/after.child" ] && [ ! -e "$SD/01.finished" ]; then
            ok "(6b-2) once the command has returned the step stops: exit $((128 + num)), the code after it never runs (SIG${name})"
        else bad "(6b-2) the step after SIG${name} (rc ${rc}, after.child $([ -e "$SD/after.child" ] && echo ran || echo not run))" "$(tail -n 6 "$T/rb-${lname}.out")"; fi
        if [ "$(status_field "$lname" status)" = fail ] && [ "$(status_field "$lname" rc)" = $((128 + num)) ] && [ "$(status_field "$lname" signal)" = "$name" ]; then
            ok "(6b-2) the status file says status=fail rc=$((128 + num)) signal=${name}, never ok"
        else bad "(6b-2) the status file after SIG${name} ($(tr '\n' ' ' < "$T/rb-${lname}/work/state/01.status"))"; fi
        if [ ! -d "$T/rb-${lname}/work/.run.lock" ] && grep -q "SIG${name} received by step 01" "$T/rb-${lname}.out" && ! grep -q 'step 01 sigstep ok' "$T/rb-${lname}.out"; then
            ok "(6b-2) the lock is released only after the command ended, the log names the signal and never says ok (SIG${name})"
        else bad "(6b-2) the lock or the log after SIG${name}" "$(tail -n 6 "$T/rb-${lname}.out")"; fi
        if ! alive "$(cat "$SD/bg.pid")"; then ok "(6b-2) the step's background job is gone too (SIG${name})"; else bad "(6b-2) a background job outlived the signalled step (SIG${name})"; kill "$(cat "$SD/bg.pid")" 2> /dev/null; fi
    else
        bad "(6b-2) the stub step never started (SIG${name})"
        touch "$SD/release"
        kill "$SP" 2> /dev/null || true
    fi
done

# A step that dies without any trap (KILL): its status file is 'running', which nothing reads as ok.
rm -rf "$SD"
mkdir -p "$SD"
base_env "$T/rb-sgk.env" "REHEARSAL_WORK=$T/rb-sgk/work" "MOODLEDATA=$T/rb-sgk/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
STUBDIR="$SD" bash "$K2/sigstep.sh" --env "$T/rb-sgk.env" --execute > "$T/rb-sgk.out" 2>&1 &
SP=$!
if wait_file "$SD/01.started" 240; then
    kill -KILL "$SP"
    wait "$SP" 2> /dev/null
    kill "$(cat "$SD/bg.pid")" 2> /dev/null
    touch "$SD/release"
    t_sdo() {
        EXECUTE=1 STATE_DIR="$T/rb-sgk/work/state" STEP_ID=02
        step_done_ok 01
    }
    if [ "$(status_field sgk status)" = running ] && [ "$(in_kit t_sdo | tail -n 1)" = rc=1 ]; then
        ok "(6b-2) a step killed with SIGKILL leaves status=running, and step_done_ok does not take that for ok"
    else bad "(6b-2) the status file of a killed step ($(status_field sgk status))"; fi
    if grep -q 'DID NOT FINISH' "$KIT/12_summary.sh"; then ok "(6b-2) the summary labels a status that is still running as DID NOT FINISH (not ok)"; else bad "(6b-2) the summary has no label for a step that did not finish"; fi
else
    bad "(6b-2) the stub step never started (KILL)"
    touch "$SD/release"
    kill "$SP" 2> /dev/null || true
fi

# step_done_ok with a status that is 'running' (the step asking about itself reads previous=).
t_sdo2() {
    EXECUTE=1 STATE_DIR="$T/sdo" STEP_ID=02
    mkdir -p "$STATE_DIR"
    printf 'status=ok\n' > "$STATE_DIR/03.status"
    step_done_ok 03 || exit 10
    printf 'status=running\nprevious=ok\n' > "$STATE_DIR/03.status"
    ! step_done_ok 03 || exit 11
    STEP_ID=03
    step_done_ok 03 || exit 12
    printf 'status=running\nprevious=fail\n' > "$STATE_DIR/03.status"
    ! step_done_ok 03 || exit 13
    printf 'status=running\nprevious=\n' > "$STATE_DIR/03.status"
    ! step_done_ok 03 || exit 14
    ! step_done_ok 07 || exit 15
}
res="$(in_kit t_sdo2)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(6b-2) step_done_ok: a step that is running is not done, except for the step asking about its own earlier run (previous=)"; else bad "(6b-2) step_done_ok and a 'running' status" "$res"; fi

# A TERM sent to a STEP that run_all.sh started (not to run_all.sh): the step records failure, run_all.sh starts nothing more, its lock
# stays until the step has ended, and the summary (a report) still runs.
rm -rf "$SD"
mkdir -p "$SD"
for n in 02_source_baseline 12_summary; do
    printf '#!/usr/bin/env bash\n: > "$STUBDIR/%s.started"\nexit 0\n' "${n%%_*}" > "$K2/$n.sh"
done
base_env "$T/rb-sgr.env" "REHEARSAL_WORK=$T/rb-sgr/work" "MOODLEDATA=$T/rb-sgr/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
STUBDIR="$SD" bash "$K2/run_all.sh" --env "$T/rb-sgr.env" --execute --only 01,02,12 > "$T/rb-sgr.out" 2>&1 &
RP=$!
if wait_file "$SD/01.started" 240 && wait_file "$T/rb-sgr/work/.run.lock/step.pid" 20; then
    STEPPID="$(cat "$T/rb-sgr/work/.run.lock/step.pid")"
    if [ -n "$STEPPID" ] && [ "$STEPPID" != "$RP" ]; then ok "(6b-2) a step started by run_all.sh writes its own pid into the lock directory (step.pid)"; else bad "(6b-2) step.pid of the lock ('${STEPPID}')"; fi
    kill -TERM "$STEPPID"
    sleep 3
    if alive "$RP" && alive "$STEPPID" && [ -d "$T/rb-sgr/work/.run.lock" ] && [ ! -e "$SD/child.finished" ]; then
        ok "(6b-2) TERM to the STEP under run_all.sh: both are alive and the lock is held while the step's command runs"
    else bad "(6b-2) TERM to a step under run_all.sh acted at once"; fi
    touch "$SD/release"
    rc=0
    wait "$RP" || rc=$?
    if [ "$rc" = 143 ] && [ ! -e "$SD/02.started" ] && [ -e "$SD/12.started" ] && [ ! -d "$T/rb-sgr/work/.run.lock" ] && [ "$(status_field sgr status)" = fail ] && [ "$(status_field sgr signal)" = TERM ]; then
        ok "(6b-2) run_all.sh exits 143, starts no further step but the summary, releases the lock, and state/01.status says fail with signal=TERM (it said ok)"
    else bad "(6b-2) run_all.sh after a TERM to its step (rc ${rc}; 02 $([ -e "$SD/02.started" ] && echo started || echo not started); status '$(status_field sgr status)')" "$(tail -n 8 "$T/rb-sgr.out")"; fi
    if grep -q 'exited 143' "$T/rb-sgr.out"; then ok "(6b-2) run_all.sh logs that the step exited 143"; else bad "(6b-2) run_all.sh does not log the step's exit" "$(tail -n 6 "$T/rb-sgr.out")"; fi
else
    bad "(6b-2) the stub step 01 of run_all.sh never started"
    touch "$SD/release"
    kill "$RP" 2> /dev/null || true
fi

# A held lock names the pid of the run and the pid of its step, each with whether it is alive.
mkdir -p "$T/rb-lk/work/.run.lock"
printf '999991\n' > "$T/rb-lk/work/.run.lock/pid"
printf '999992\n' > "$T/rb-lk/work/.run.lock/step.pid"
fake_db 0
rb_run lk "$DUMP"
if [ "$RC" = 3 ] && printf '%s' "$OUT" | grep -q 'pid 999991 (not running), step pid 999992 (not running)' && printf '%s' "$OUT" | grep -q 'NONE of the pids'; then
    ok "(6b-2) a held lock names the run's pid and its step's pid, each 'not running', and says to remove it only when none is alive"
else bad "(6b-2) the held-lock message (rc ${RC})" "$OUT"; fi
printf '%s\n' "$$" > "$T/rb-lk/work/.run.lock/step.pid"
rb_run lk "$DUMP"
if [ "$RC" = 3 ] && printf '%s' "$OUT" | grep -q "step pid $$ (alive)"; then ok "(6b-2) a step that outlived its run_all.sh is named as alive"; else bad "(6b-2) the held-lock message with a live step" "$OUT"; fi
rm -rf "$T/rb-lk/work/.run.lock"

# HUP to run_all.sh alone, as the TERM test above: the lock stays under the running step, nothing more starts, exit 129.
SD="$T/sig3"
rm -rf "$SD"
mkdir -p "$SD"
cp "$K2/sigstep.sh" "$K2/01_restore_check.sh"
base_env "$T/rb-sgh.env" "REHEARSAL_WORK=$T/rb-sgh/work" "MOODLEDATA=$T/rb-sgh/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
if [ "$HUP_TRAPPABLE" != 1 ]; then
    skip "HUP to run_all.sh alone: SIGHUP is ignored on entry in this environment (started under nohup?), so no bash here can trap it; run the suite without nohup"
else
STUBDIR="$SD" bash "$K2/run_all.sh" --env "$T/rb-sgh.env" --execute --only 01,02,12 > "$T/rb-sgh.out" 2>&1 &
RP=$!
if wait_file "$SD/01.started" 240; then
    kill -HUP "$RP"
    sleep 3
    if alive "$RP" && [ -d "$T/rb-sgh/work/.run.lock" ] && [ ! -e "$SD/child.finished" ]; then
        ok "HUP to run_all.sh alone: it is still alive and holds .run.lock while step 01 runs (sudo relays HUP when the ssh session drops)"
    else bad "HUP to run_all.sh released the lock while step 01 still ran (alive: $(alive "$RP" && echo yes || echo no), lock: $([ -d "$T/rb-sgh/work/.run.lock" ] && echo held || echo gone))"; fi
    touch "$SD/release"
    rc=0
    wait "$RP" || rc=$?
    if [ "$rc" = 129 ] && [ -e "$SD/child.finished" ] && [ ! -e "$SD/02.started" ] && [ ! -e "$SD/12.started" ] && [ ! -d "$T/rb-sgh/work/.run.lock" ]; then
        ok "after the step has finished run_all.sh starts no further step (the summary included), releases the lock and exits 129"
    else bad "run_all.sh after the HUP (rc ${rc}; 02 $([ -e "$SD/02.started" ] && echo started || echo not started))" "$(tail -n 6 "$T/rb-sgh.out")"; fi
    if [ "$(status_field sgh status)" = ok ]; then ok "(the step itself, which was not signalled, finished ok)"; else bad "(the unsignalled step's status '$(status_field sgh status)')"; fi
else
    bad "the step 01 of the HUP test never started"
    touch "$SD/release"
    kill "$RP" 2> /dev/null || true
fi
fi

# A signal that arrives between two steps (while run_all.sh is writing the line that announces the next one) starts nothing.
SD="$T/sig4"
rm -rf "$SD"
mkdir -p "$SD/bin"
printf '#!/usr/bin/env bash\n: > "$STUBDIR/01.started"\n: > "$STUBDIR/slowdate"\nexit 0\n' > "$K2/01_restore_check.sh"
printf '#!/usr/bin/env bash\nif [ -f "$STUBDIR/slowdate" ] && [ ! -f "$STUBDIR/in-date" ]; then : > "$STUBDIR/in-date"; sleep 4; fi\nexec "%s" "$@"\n' "$(command -v date)" > "$SD/bin/date"
chmod +x "$SD/bin/date"
base_env "$T/rb-sgw.env" "REHEARSAL_WORK=$T/rb-sgw/work" "MOODLEDATA=$T/rb-sgw/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
PATH="$SD/bin:$PATH" STUBDIR="$SD" bash "$K2/run_all.sh" --env "$T/rb-sgw.env" --execute --only 01,02,12 > "$T/rb-sgw.out" 2>&1 &
RP=$!
if wait_file "$SD/in-date" 240; then
    kill -TERM "$RP"
    rc=0
    wait "$RP" || rc=$?
    if [ "$rc" = 143 ] && [ -e "$SD/01.started" ] && [ ! -e "$SD/02.started" ] && [ ! -e "$SD/12.started" ] && grep -q 'NOT started: SIGTERM was received' "$T/rb-sgw.out"; then
        ok "a TERM that arrives while run_all.sh announces the next step starts nothing (it used to start the step and wait for it)"
    else bad "the signal between two steps (rc ${rc}; 02 $([ -e "$SD/02.started" ] && echo started || echo not started))" "$(tail -n 6 "$T/rb-sgw.out")"; fi
else
    bad "the slow date of the between-steps test was never called"
    kill "$RP" 2> /dev/null || true
fi
FW="$T"

printf 'a signal to the whole process group, USR1 to run_all.sh alone (Stage B tools fix round 7)\n'
# USR1 to run_all.sh alone is handled like TERM: it used to end run_all.sh with its EXIT trap (status 0) and the lock released under the running step.
USR1NUM="$(kill -l USR1)"
SD="$T/sig6"
rm -rf "$SD"
mkdir -p "$SD"
cp "$K2/sigstep.sh" "$K2/01_restore_check.sh"
base_env "$T/rb-sgu.env" "REHEARSAL_WORK=$T/rb-sgu/work" "MOODLEDATA=$T/rb-sgu/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
STUBDIR="$SD" bash "$K2/run_all.sh" --env "$T/rb-sgu.env" --execute --only 01,02,12 > "$T/rb-sgu.out" 2>&1 &
RP=$!
if wait_file "$SD/01.started" 240; then
    kill -USR1 "$RP"
    sleep 3
    if alive "$RP" && [ -d "$T/rb-sgu/work/.run.lock" ] && [ ! -e "$SD/child.finished" ]; then
        ok "(7) USR1 to run_all.sh alone: it is still alive and still holds .run.lock while step 01 runs"
    else bad "(7) USR1 to run_all.sh released the lock while step 01 still ran (alive: $(alive "$RP" && echo yes || echo no), lock: $([ -d "$T/rb-sgu/work/.run.lock" ] && echo held || echo gone))"; fi
    touch "$SD/release"
    rc=0
    wait "$RP" || rc=$?
    if [ "$rc" = $((128 + USR1NUM)) ] && [ -e "$SD/child.finished" ] && [ ! -e "$SD/02.started" ] && [ ! -e "$SD/12.started" ] && [ ! -d "$T/rb-sgu/work/.run.lock" ]; then
        ok "(7) once step 01 has finished run_all.sh starts no further step, releases the lock and exits $((128 + USR1NUM)) (128 + USR1)"
    else bad "(7) run_all.sh after USR1 (rc ${rc}, wanted $((128 + USR1NUM)); 02 $([ -e "$SD/02.started" ] && echo started || echo not started))" "$(tail -n 6 "$T/rb-sgu.out")"; fi
else
    bad "(7) the stub step 01 of the USR1 test never started"
    touch "$SD/release"
    kill "$RP" 2> /dev/null || true
fi

# A step whose log pipe dies (its output goes to a process that has gone) and which then finishes "normally": SIGPIPE is only noted, and the step is
# recorded failed (rc 141, signal=PIPE), never ok, and releases its lock.
cat > "$K2/pipestep.sh" <<'STUB'
#!/usr/bin/env bash
# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 01 pipestep "$@"
exec > >(exit 0) 2>&1
sleep 1
printf 'to a dead pipe\n' || true
printf 'and again\n' || true
step_end
exit 0
STUB
base_env "$T/rb-sgp.env" "REHEARSAL_WORK=$T/rb-sgp/work" "MOODLEDATA=$T/rb-sgp/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
bash "$K2/pipestep.sh" --env "$T/rb-sgp.env" --execute > "$T/rb-sgp.out" 2>&1
rc=$?
if [ "$rc" = 141 ] && [ "$(status_field sgp status)" = fail ] && [ "$(status_field sgp rc)" = 141 ] && [ "$(status_field sgp signal)" = PIPE ] && [ ! -d "$T/rb-sgp/work/.run.lock" ]; then
    ok "(7) a step that saw SIGPIPE (its log pipe died) and finished 'normally' is recorded failed, rc 141, signal=PIPE, never ok, and releases its lock"
else bad "(7) the step with a dead log pipe (rc ${rc}, status '$(status_field sgp status)', rc '$(status_field sgp rc)', signal '$(status_field sgp signal)')" "$(tail -n 4 "$T/rb-sgp.out")"; fi

# A signal to the whole PROCESS GROUP (Ctrl-C, an ssh hangup, kill -TERM -- -PGID, which the documentation recommends to stop a step and what it runs)
# also kills the tee that run_all.sh and the step each write their output through. The handlers' first log line then met a dead pipe: SIGPIPE ended the
# step with status=running and .run.lock behind it, and run_all.sh with exit 141 and no STOPPED line (round 6b review). The shell has to be able to
# signal a process group ('set -m': each background job is the leader of its own group).
GROUP_KILL=0
bash -c 'set -m; sleep 100 & p=$!; kill -TERM -- -"$p"; wait "$p"; [ "$?" = 143 ]' > /dev/null 2>&1 && GROUP_KILL=1
if [ "$GROUP_KILL" != 1 ]; then
    skip "(7) a signal to the whole process group: this shell cannot signal a process group ('set -m', then kill -- -PGID)"
else
    cat > "$T/grp.sh" <<'GRP'
#!/usr/bin/env bash
# grp.sh STUBDIR KITDIR ENVFILE OUTFILE PIDFILE RCFILE: run_all.sh as the leader of a process group of its own, as a terminal or a tmux window runs it
set -m
STUBDIR="$1" bash "$2/run_all.sh" --env "$3" --execute --only 01,02,12 > "$4" 2>&1 &
printf '%s\n' "$!" > "$5"
wait "$!"
printf '%s\n' "$?" > "$6"
GRP
    SD="$T/sig5"
    rm -rf "$SD"
    mkdir -p "$SD"
    cp "$K2/sigstep.sh" "$K2/01_restore_check.sh"
    base_env "$T/rb-sgg.env" "REHEARSAL_WORK=$T/rb-sgg/work" "MOODLEDATA=$T/rb-sgg/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
    bash "$T/grp.sh" "$SD" "$K2" "$T/rb-sgg.env" "$T/rb-sgg.out" "$T/rb-sgg.pid" "$T/rb-sgg.rc" &
    GP=$!
    if wait_file "$SD/01.started" 240 && wait_file "$T/rb-sgg.pid" 20; then
        RPG="$(cat "$T/rb-sgg.pid")"
        kill -TERM -- "-$RPG"
        if wait_file "$T/rb-sgg.rc" 240; then
            sleep 1
            rc="$(cat "$T/rb-sgg.rc")"
            if [ "$rc" = 143 ] && [ ! -d "$T/rb-sgg/work/.run.lock" ] && [ ! -e "$SD/02.started" ] && [ ! -e "$SD/12.started" ]; then
                ok "(7) kill -TERM to the whole process group: run_all.sh exits 143 (it used to die of SIGPIPE: 141), releases its lock, starts no further step"
            else bad "(7) run_all.sh after a group TERM (rc ${rc}; lock $([ -d "$T/rb-sgg/work/.run.lock" ] && echo held || echo gone); 02 $([ -e "$SD/02.started" ] && echo started || echo not started))" "$(tail -n 6 "$T/rb-sgg.out")"; fi
            if [ "$(status_field sgg status)" = fail ] && [ "$(status_field sgg rc)" = 143 ] && [ "$(status_field sgg signal)" = TERM ]; then
                ok "(7) ... the step recorded itself failed (status=fail rc=143 signal=TERM), not 'running' and not ok"
            else bad "(7) the status file of the step after a group TERM ($(tr '\n' ' ' < "$T/rb-sgg/work/state/01.status" 2> /dev/null))"; fi
            if grep -q 'SIGTERM received by step 01' "$T/rb-sgg/work/logs/01-sigstep.log" 2> /dev/null && grep -q 'STOPPED by SIGTERM' "$T/rb-sgg/work/logs/run_all.log" 2> /dev/null; then
                ok "(7) ... and both say so in their log files (the step's line and run_all.sh's STOPPED line are written to the files when the tee is gone)"
            else bad "(7) the log files after a group TERM" "$(tail -n 3 "$T/rb-sgg/work/logs/01-sigstep.log" 2> /dev/null; tail -n 3 "$T/rb-sgg/work/logs/run_all.log" 2> /dev/null)"; fi
            if ! alive "$(cat "$SD/bg.pid")"; then ok "(7) ... and the step's background job is gone"; else bad "(7) the step's background job outlived a group TERM"; kill "$(cat "$SD/bg.pid")" 2> /dev/null; fi
        else
            bad "(7) run_all.sh never ended after a group TERM"
            touch "$SD/release"
            kill -KILL -- "-$RPG" 2> /dev/null || true
        fi
    else
        bad "(7) the stub step 01 of the group-signal test never started"
        touch "$SD/release"
        kill "$GP" 2> /dev/null || true
    fi
fi
FW="$T"

printf 'step 01: the marker read fails closed, and the hand-restore path moves nothing on a misread (Stage B tools fix round 6b, must-fix 3)\n'
FW="$T/w4"
fake_db 120
printf '%s\n' "$RKM_ID" > "$FW/fake.marker"
t_mg() {
    TMPDIR="$T" EXECUTE=1 STEP_ID=t MYSQL_BIN="$FW/fakemysql" DB_NAME=stageb_selftest REHEARSAL_DB_ALLOWLIST=stageb_selftest DB_PREFIX=mdl_ \
        DB_HOST=127.0.0.1 DB_PORT="" DB_USER=rehearsal DB_PASS_FILE="$T/db.pass"
    marker_get
}
t_mgabs() {
    TMPDIR="$T" EXECUTE=1 STEP_ID=t MYSQL_BIN="$FW/fakemysql" DB_NAME=stageb_selftest REHEARSAL_DB_ALLOWLIST=stageb_selftest DB_PREFIX=mdl_ \
        DB_HOST=127.0.0.1 DB_PORT="" DB_USER=rehearsal DB_PASS_FILE="$T/db.pass"
    marker_definitely_absent
}
# mg_expect LABEL FAULT WANT-RC WANT-VALUE: marker_get with the stand-in's fault (empty = none).
mg_expect() {
    rm -f "$FW/fake.once"
    [ -z "$2" ] || fake_once "$2"
    res="$(in_kit t_mg)"
    if [ "$(printf '%s' "$res" | tail -n 1)" = "rc=$3" ] && [ "$(printf '%s' "$res" | head -n 1 | grep -v '^rc=')" = "$4" ]; then ok "$1"; else bad "$1" "$res"; fi
    rm -f "$FW/fake.once"
}
mg_expect "(6b-3, control) marker_get: a marker that reads back is returned, rc 0" "" 0 "$RKM_ID"
mg_expect "(6b-3) a lost connection on the read, once: asked again, the marker is returned" markererr 0 "$RKM_ID"
mg_expect "(6b-3) a client that printed nothing once for a row that exists: asked again, the marker is returned" markerblank 0 "$RKM_ID"
mg_expect "(6b-3) a read that always fails with an error other than 1146 is 'cannot tell' (rc 1), never 'no marker'" markererrall 1 ""
mg_expect "(6b-3) a value that stays blank while the COUNT says the row exists is 'cannot tell' (rc 1)" markerblankall 1 ""
mg_expect "(6b-3) a client that prints nothing for the value AND the COUNT, every time, is 'cannot tell' (rc 1)" allblank 1 ""
rm -f "$FW/fake.marker"
mg_expect "(6b-3) a database whose COUNT says 0 and answers no error: no marker, rc 0" "" 0 ""
fake_set noconfig 1
mg_expect "(6b-3) the server's own error 1146 (no config table) is 'no marker', rc 0" "" 0 ""
rm -f "$FW/fake.noconfig"
res="$(in_kit t_mgabs)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(6b-3) marker_definitely_absent: two agreeing error-free counts of 0 on an existing config table say 'absent'"; else bad "(6b-3) marker_definitely_absent (control)" "$res"; fi
printf '%s\n' "$RKM_ID" > "$FW/fake.marker"
res="$(in_kit t_mgabs)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ]; then ok "(6b-3) ... and a count of 1 is not absent"; else bad "(6b-3) marker_definitely_absent with a marker" "$res"; fi
rm -f "$FW/fake.marker"
fake_set noconfig 1
res="$(in_kit t_mgabs)"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ]; then ok "(6b-3) ... and no config table at all is not absent either (a mistyped DB_PREFIX)"; else bad "(6b-3) marker_definitely_absent without a config table" "$res"; fi
rm -f "$FW/fake.noconfig"

# The hand path itself. Setup: a hand restore stamped (its marker is kept), a step after 01 ran, RESTORE_DONE_BY_HAND stays in the env file.
fake_db 400
fake_set storemarker 1
rb_run hp "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(6b-3, setup) a hand restore is stamped by a plain INSERT" 1 'stamped as restore'
HPID="$(rb_kv hp restore.id)"
printf 'status=ok\n' > "$T/rb-hp/work/state/05.status"
# hp_intact LABEL: the finished rehearsal is where it was: state/ not archived, restore id and the database's marker unchanged.
hp_intact() {
    if [ -f "$T/rb-hp/work/state/05.status" ] && [ ! -e "$T/rb-hp/work/archive" ] && [ "$(rb_kv hp restore.id)" = "$HPID" ] && [ "$(cat "$FW/fake.marker" 2> /dev/null)" = "$HPID" ]; then
        ok "$1"
    else bad "$1 (05.status $([ -f "$T/rb-hp/work/state/05.status" ] && echo there || echo gone), archive $([ -e "$T/rb-hp/work/archive" ] && echo made || echo none), restore.id '$(rb_kv hp restore.id)', marker '$(cat "$FW/fake.marker" 2> /dev/null)')"; fi
}
fake_once markererr
rb_run hp "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(6b-3, control) one lost connection on the marker read: asked again, the rehearsal's own marker is found" 1 'carries this rehearsal.s marker' 'stamping it\|stamped as restore'
hp_intact "(6b-3, control) ... and nothing was moved, archived or stamped"
for fault in markererrall allblank markerblankall; do
    fake_once "$fault"
    rb_run hp "RESTORE_DONE_BY_HAND=$RBDB"
    rb_expect "(6b-3) ${fault}: a marker read that cannot be believed is refused with the statement still in the env file" 1 'marker could not be read' 'stamping it\|stamped as restore'
    hp_intact "(6b-3) ${fault}: the finished rehearsal's state/ and the database's marker are untouched"
done
rm -f "$FW/fake.once"
fake_once markerzero
rb_run hp "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(6b-3) a blank read confirmed by a wrong COUNT of 0 (the reproduced misread) is not believed: two more counts must agree" 1 'could not confirm that it carries NO' 'stamping it\|stamped as restore'
hp_intact "(6b-3) markerzero: the finished rehearsal's state/ and the database's marker are untouched"
rm -f "$FW/fake.once"
# A marker row the reads did not show: the plain INSERT meets it (error 1062) and nothing is overwritten or moved.
rm -f "$FW/fake.marker"
fake_set insertexists 1
rb_run hp "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(6b-3) the stamp is a plain INSERT: a marker row that exists (error 1062) is refused, not overwritten" 1 'already holds a marker row' 'stamped as restore'
if [ -f "$T/rb-hp/work/state/05.status" ] && [ ! -e "$T/rb-hp/work/archive" ] && [ "$(rb_kv hp restore.id)" = "$HPID" ]; then ok "(6b-3) ... and state/ was not moved to archive/ (the rotation comes after the INSERT succeeded)"; else bad "(6b-3) state/ after the refused INSERT"; fi
rm -f "$FW/fake.insertexists"
# The control: a database that really carries no marker is stamped, and only then does the earlier rehearsal's state move.
rb_run hp "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(6b-3, control) a database that DEFINITELY carries no marker is stamped as a new hand restore" 1 'stamped as restore'
if [ -d "$T/rb-hp/work/archive" ] && [ ! -f "$T/rb-hp/work/state/05.status" ] && [ "$(rb_kv hp restore.id)" != "$HPID" ] && [ "$(cat "$FW/fake.marker" 2> /dev/null)" = "$(rb_kv hp restore.id)" ]; then
    ok "(6b-3, control) the earlier rehearsal's state moved to archive/ after the INSERT, and the new id is in the database and in state/kv"
else bad "(6b-3, control) the state after a real new hand restore"; fi
FW="$T"

printf 'step 01: the dump that is restored (identity before and after the load, a changed RESTORE_DB_DUMP), a re-probe after the load, the record of an older kit (Stage B tools fix round 6b, should-fix)\n'
FW="$T/w4"
fake_db none
fake_after 120
fake_set storemarker 1
rb_run dmp "$DUMP"
rb_expect "(6b) a kit restore finishes the database and records the identity of its dump" 1 'stamped as restore'
if [[ "$(rb_kv dmp restore.dump)" == *"/dumps/good.sql|"* ]]; then ok "(6b) state/kv/restore.dump holds the dump's path, size and mtime"; else bad "(6b) restore.dump ($(rb_kv dmp restore.dump))"; fi
rb_run dmp "$DUMP"
rb_expect "(6b, control) the same dump on the re-run: the finished copy is kept, nothing is said about the dump" 1 'carries this rehearsal.s marker' 'silently ignored'
{ cat "$T/dumps/good.sql"; printf -- '-- another dump\n'; } > "$T/dumps/other.sql"
rb_run dmp "RESTORE_DB_DUMP=$T/dumps/other.sql"
rb_expect "(6b) a DIFFERENT RESTORE_DB_DUMP on a stamped database is refused, not silently ignored" 1 'was restored from another dump' 'not restoring over it'
if [ "$(fake_loads)" = 1 ]; then ok "(6b) ... and the client was given no second restore"; else bad "(6b) restores loaded: $(fake_loads)"; fi
rb_run dmp
rb_expect "(6b, control) with no dump named the copy is used as it is" 1 'carries this rehearsal.s marker' 'silently ignored'
# A dump that is re-written while it is loaded: the load returns 0, the identity has changed.
fake_db none
fake_after 120
fake_set storemarker 1
cp "$T/dumps/good.sql" "$T/dumps/mut.sql"
printf '%s\n' "$T/dumps/mut.sql" > "$FW/fake.mutatedump"
rb_run mut "RESTORE_DB_DUMP=$T/dumps/mut.sql"
rb_expect "(6b) a dump that changed between its scan and the end of its load: the load is NOT verified, the step stops" 1 'changed while it was scanned and loaded' 'stamped as restore'
if fake_inflight && [ -z "$(rb_kv mut restore.id)" ]; then ok "(6b) ... the in-flight table stays (the copy is refused) and nothing is stamped"; else bad "(6b) the unverified load was stamped or its in-flight table dropped"; fi
# The table count that follows the load is wrong once: asked again before the copy is declared empty.
fake_db none
fake_after 120
fake_set storemarker 1
fake_once zerotableslate
rb_run rep "$DUMP"
rb_expect "(6b) a table count after the load that is wrong once (0) does not refuse a complete copy: it is read again" 1 'stamped as restore' 'read twice'
# The record of an older kit: restore.started without restore.complete, on a populated database.
fake_db 400
mkdir -p "$T/rb-leg/work/state/kv"
printf 'old kit\n' > "$T/rb-leg/work/state/kv/restore.started"
rb_run leg "RESTORE_DONE_BY_HAND=$RBDB"
rb_expect "(6b) restore.started without restore.complete (an older kit's unfinished restore) refuses the populated database, whatever RESTORE_DONE_BY_HAND says" 1 'restore.started is set and restore.complete is not' 'stamped as restore'
# The client commands a dump must not carry.
for cmd in '\u airpayprod' '\. /tmp/other.sql' '\! rm -rf /' 'source /tmp/other.sql' 'system rm -rf /' 'connect otherdb' 'quit' '  \r otherhost'; do
    { printf '%s\n' "$cmd"; cat "$T/dumps/good.sql"; } > "$T/dumps/cmd.sql"
    res="$(in_kit dump_unsafe_statement "$T/dumps/cmd.sql")"
    if [ -n "$(printf '%s' "$res" | head -n 1 | grep -v '^rc=')" ]; then ok "(6b) a dump line that is a mysql client command (${cmd}) is flagged"; else bad "(6b) the client command ${cmd} is flagged" "$res"; fi
done
FW="$T"

printf 'step 01: the file store gate compares sizes (Stage B tools fix round 6b)\n'
(
    # shellcheck disable=SC1091
    . "$KIT/lib/common.sh"
    mkdir -p "$T/fs/aa/bb" "$T/fs/11/22"
    printf 'abc' > "$T/fs/aa/bb/aabbccddeeff00112233445566778899aabbccdd"
    printf 'abcdef' > "$T/fs/11/22/1122334455667788990011223344556677889900"
    # (round 7) two hashes that {files} records with TWO sizes each (legacy rows: one of the two is wrong); the files on disk are 4 bytes
    mkdir -p "$T/fs/33/44" "$T/fs/55/66"
    printf 'abcd' > "$T/fs/33/44/3344556677889900112233445566778899001122"
    printf 'abcd' > "$T/fs/55/66/5566778899001122334455667788990011223344"
    tab=$'\t'
    # {files} says: aabb... 3 bytes (right), 1122... 10 bytes (the file on disk is 6: cut), ffee... 5 bytes (not on disk: the missing list's business),
    # 3344... 7 and 4 bytes (the disk has 4: it matches one of the two rows, so it is NOT cut), 5566... 7 and 8 bytes (the disk has 4: it matches neither)
    printf 'aabbccddeeff00112233445566778899aabbccdd%s3\n1122334455667788990011223344556677889900%s10\nffeeddccbbaa99887766554433221100ffeeddcc%s5\n' "$tab" "$tab" "$tab" \
        | expected_paths_sizes | LC_ALL=C sort -u > "$T/fs-db.txt"
    printf '3344556677889900112233445566778899001122%s7\n3344556677889900112233445566778899001122%s4\n5566778899001122334455667788990011223344%s8\n5566778899001122334455667788990011223344%s7\n' "$tab" "$tab" "$tab" "$tab" \
        | expected_paths_sizes >> "$T/fs-db.txt"
    LC_ALL=C sort -u -o "$T/fs-db.txt" "$T/fs-db.txt"
    disk_paths_sizes "$T/fs" | LC_ALL=C sort -u > "$T/fs-disk.txt"
    out="$(filedir_wrong_sizes "$T/fs-db.txt" "$T/fs-disk.txt")"
    want="$(printf '11/22/1122334455667788990011223344556677889900\t10\t6\n55/66/5566778899001122334455667788990011223344\t7,8\t4')"
    [ "$out" = "$want" ] || { printf 'got [%s]\n' "$out"; exit 11; }
    [ "$(wc -l < "$T/fs-disk.txt" | tr -d ' ')" = 4 ] || exit 12
) && ok "a content file on disk with a size other than {files}.filesize is listed (path, size(s) in {files}, size on disk); a missing one is not (that is the missing list's); a hash that two {files} rows record with two sizes is cut only when the disk matches neither" || bad "the size comparison of the file store gate (exit $?)"
if grep -q 'FILEDIR_MAX_WRONGSIZE' "$KIT/01_restore_check.sh" && grep -q 'FILEDIR_MAX_WRONGSIZE' "$KIT/rehearsal.env.example"; then ok "step 01 stops on a wrong-size content file (FILEDIR_MAX_WRONGSIZE, default 0), documented in rehearsal.env.example"; else bad "FILEDIR_MAX_WRONGSIZE is wired in step 01 and documented"; fi

printf 'step 01 finished: steps 02 to 11 refuse a copy that step 01 stamped and did not clear (Stage B tools fix round 7, M2)\n'
# A stand-in server of its own, in end-to-end mode (fake.e2e): it also answers what a WHOLE step 01 asks after the restore, for a copy whose filedir is the one
# content file of md-arch.tar, and keeps every write the kit sends (fake.sqllog).
FW="$T/w5"
mkdir -p "$FW"
cp "$T/fakemysql" "$FW/fakemysql"
# A copy of the kit for the real steps 00 and 02: where the file system does not keep mode 600 for the password file (Git Bash on NTFS reports 644), the real
# check_pass_file, which both steps run before anything else, cannot pass; the copy's one change is that this check always passes. Nothing else differs.
K3="$T/kit3"
mkdir -p "$K3/lib"
cp "$KIT"/*.sh "$K3/"
rm -f "$K3/selftest.sh"
cp "$KIT"/lib/* "$K3/lib/"
if [ "$(stat -c %a "$T/db.pass")" != 600 ]; then
    sed -i 's/^check_pass_file() {$/check_pass_file() { return 0/' "$K3/lib/common.sh"
fi
REPO="$(cd "$KIT/../.." && pwd)"
# the files the preflight looks for (the copy of the kit does not sit in the repository)
PFENV=("SOURCE_BASELINE_PHP=\"$REPO/moodle-enhancement/local/sentientia_platform/cli/source_baseline.php\"" "CAP_ALLOWLIST=\"$REPO/moodle-enhancement/docs/cutover/bizlms-capability-allowlist.json\""
       "IMPORT_DECISIONS=\"$REPO/moodle-enhancement/docs/cutover/bizlms-import-decisions.json\"" "ADR031_SCRIPTS_DIR=\"$REPO/tools/uat\"")
# pf_env NAME -> $T/pf-NAME.env: the env file of rehearsal NAME for the copy of the kit (with the files the real steps and the preflight look for)
pf_env() {
    base_env "$T/pf-$1.env" "REHEARSAL_WORK=$T/rb-$1/work" "MOODLEDATA=$T/rb-$1/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal" "${PFENV[@]}"
}
# pf_run NAME STEP01 -> the real preflight --execute on the copy of rehearsal NAME; STEP01 = 0 or 1 as run_all.sh says whether the run includes step 01, empty = run alone
pf_run() {
    pf_env "$1"
    if [ -n "$2" ]; then
        OUT="$(REHEARSAL_RUN_STEP01="$2" bash "$K3/00_preflight.sh" --env "$T/pf-$1.env" --execute 2>&1)"
    else
        OUT="$(env -u REHEARSAL_RUN_STEP01 bash "$K3/00_preflight.sh" --env "$T/pf-$1.env" --execute 2>&1)"
    fi
    RC=$?
}
t_rkm2() {
    # t_rkm2 NAME [during-step-01]: what step 02 asks first, on the work directory and moodledata of rehearsal NAME
    EXECUTE=1 STEP_ID=02 MYSQL_BIN="$FW/fakemysql" DB_NAME=stageb_selftest REHEARSAL_DB_ALLOWLIST=stageb_selftest DB_PREFIX=mdl_ \
        DB_HOST=127.0.0.1 DB_PORT="" DB_USER=rehearsal DB_PASS_FILE="$T/db.pass" STATE_DIR="$T/rb-$1/work/state" MOODLEDATA="$T/rb-$1/data"
    require_kit_marker ${2:+"$2"}
}
fake_db 400
fake_set storemarker 1
fake_set e2e 1
rb_run f1 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(7-M2, control) a whole step 01 against the stand-in (a hand restore, the unpack, the file store gate, the neutralisation) finishes" 0 'restore check done'
F1ID="$(rb_kv f1 restore.id)"
if [ -n "$F1ID" ] && [ "$(rb_kv f1 restore.verified)" = "$F1ID" ] && [ "$(cat "$FW/fake.step01" 2> /dev/null)" = "$F1ID" ] && [ "$(sed -n 's/^status=//p' "$T/rb-f1/work/state/01.status")" = ok ]; then
    ok "(7-M2) it records that it finished: state/kv/restore.verified and the {config} row both hold the restore id, and 01.status is ok"
else bad "(7-M2) the record of a finished step 01 (id '${F1ID}', kv '$(rb_kv f1 restore.verified)', row '$(cat "$FW/fake.step01" 2> /dev/null)')"; fi
smtpline="$(grep -n 'smtphosts' "$FW/fake.sqllog" | head -n 1 | cut -d: -f1)"
unverline="$(grep -n "DELETE FROM mdl_config WHERE name = 'rehearsal_kit_step01_ok'" "$FW/fake.sqllog" | head -n 1 | cut -d: -f1)"
lastline="$(wc -l < "$FW/fake.sqllog" | tr -d ' ')"
if [ -n "$smtpline" ] && [ -n "$unverline" ] && [ "$unverline" -lt "$smtpline" ] && sed -n "${lastline}p" "$FW/fake.sqllog" | grep -q "rehearsal_kit_step01_ok"; then
    ok "(7-M2) the record is removed when step 01 starts on the stamped copy, and written LAST: after the neutralisation (SMTP wipe, cron_enabled, tokens), as the final statement"
else bad "(7-M2) the order of the record's statements (unverify line '${unverline}', smtp wipe '${smtpline}', last '$(sed -n "${lastline}p" "$FW/fake.sqllog" | cut -c1-80)')" "$(cut -c1-110 "$FW/fake.sqllog")"; fi

# The gate every writing step starts with, on the finished copy and with each part of the record spoiled.
m2_good() {
    printf 'status=ok\nrc=0\n' > "$T/rb-f1/work/state/01.status"
    printf '%s\n' "$F1ID" > "$T/rb-f1/work/state/kv/restore.verified"
    printf '%s\n' "$F1ID" > "$FW/fake.step01"
    rm -f "$FW/fake.once"
}
# m2_expect LABEL WANT-RC PATTERN [during-step-01]
m2_expect() {
    res="$(in_kit t_rkm2 f1 "${4:-}")"
    if [ "$(printf '%s' "$res" | tail -n 1)" = "rc=$2" ] && { [ -z "$3" ] || printf '%s' "$res" | grep -q "$3"; }; then ok "$1"; else bad "$1" "$res"; fi
}
m2_good
m2_expect "(7-M2, control) the finished copy passes the gate of steps 02 to 11 (require_kit_marker)" 0 ''
printf 'status=fail\nrc=1\n' > "$T/rb-f1/work/state/01.status"
m2_expect "(7-M2) 01.status says fail (the copy is stamped, the record is there): refused, and the message names step 01" 1 'step 01 (restore check) has not finished ok.*state/01.status says fail'
printf 'status=running\nprevious=ok\n' > "$T/rb-f1/work/state/01.status"
m2_expect "(7-M2) 01.status says running (step 01 is running, or died without a trap): refused" 1 'state/01.status says running'
m2_good
rm -f "$T/rb-f1/work/state/01.status"
m2_expect "(7-M2) there is no 01.status at all: refused" 1 'there is no state/01.status'
m2_good
rm -f "$T/rb-f1/work/state/kv/restore.verified"
m2_expect "(7-M2) state/kv/restore.verified is not set: refused" 1 'restore.verified is not set'
m2_good
printf 'ffffffffffffffffffffffffffffffff\n' > "$T/rb-f1/work/state/kv/restore.verified"
m2_expect "(7-M2) state/kv/restore.verified holds another restore's id: refused" 1 'restore.verified holds ffffffff'
m2_good
rm -f "$FW/fake.step01"
m2_expect "(7-M2) the database holds no step-01 record (a snapshot or a dump taken before step 01 finished): refused" 1 'holds no {config} row rehearsal_kit_step01_ok'
m2_good
printf 'ffffffffffffffffffffffffffffffff\n' > "$FW/fake.step01"
m2_expect "(7-M2) the database's record belongs to another restore: refused" 1 'holds no {config} row rehearsal_kit_step01_ok for this restore (it holds ffffffff'
m2_good
fake_once step01errall
m2_expect "(7-M2) the database's record cannot be read (a lost connection, every time): refused as 'cannot tell', never as finished" 1 'step 01 finished for restore .*could not be read'
m2_good
rm -f "$FW/fake.step01"
printf 'status=running\nprevious=ok\n' > "$T/rb-f1/work/state/01.status"
m2_expect "(7-M2) step 01's own call (during-step-01) does not ask for the record: it is the step that makes it" 0 '' during-step-01
m2_good

# A step 01 that stops at the file store gate AFTER it stamped the copy: {files} names a content hash that is not on disk (the archive carries one).
fake_db 400
fake_set storemarker 1
fake_set e2e 1
fake_set twohashes 1
rb_run f2 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(7-M2) the file store gate fails (one content hash of {files} is not on disk): step 01 stops, after it stamped the copy" 1 'content hash(es) of {files} are not on disk' 'restore check done'
if [[ "$(rb_kv f2 restore.id)" =~ ^[0-9a-f]{32}$ ]] && [ "$(sed -n 's/^status=//p' "$T/rb-f2/work/state/01.status")" = fail ] && [ -z "$(rb_kv f2 restore.verified)" ] && [ ! -e "$FW/fake.step01" ]; then
    ok "(7-M2) the copy is stamped (restore.id, the marker in the database and in the moodledata), 01.status is fail and nothing says it finished"
else bad "(7-M2) the state after the failed gate (01.status '$(sed -n 's/^status=//p' "$T/rb-f2/work/state/01.status")', verified '$(rb_kv f2 restore.verified)')"; fi
if ! grep -q 'smtphosts\|cron_enabled\|oauth2' "$FW/fake.sqllog"; then ok "(7-M2) ... and nothing was neutralised: no SMTP wipe, no cron_enabled, no OAuth2 statement ever reached the database"; else bad "(7-M2) the failed step 01 neutralised something" "$(cut -c1-120 "$FW/fake.sqllog")"; fi
pf_env f2
for n in 02_source_baseline 03_hop1_to_45 11_cron_cycle; do
    OUT="$(bash "$K3/$n.sh" --env "$T/pf-f2.env" --execute 2>&1)"
    RC=$?
    rb_expect "(7-M2) step ${n%%_*} --execute on that stamped copy is refused, and says step 01 has not finished ok (it used to go on)" 1 'step 01 (restore check) has not finished ok for this restore (state/01.status says fail' 'database release\|4.5 tree'
done
# The preflight. A run that starts after step 01 is refused on that copy, before any step; a run that goes through step 01 (or a preflight run alone) is warned.
pf_run f2 0
rb_expect "(7-M2) the preflight of a run that starts after step 01 (REHEARSAL_RUN_STEP01=0, as run_all.sh --from 02 says) refuses the stamped copy whose step 01 failed" 1 'step 01 has not finished ok for this copy.*does not include it'
pf_run f2 1
rb_expect "(7-M2) the preflight of a run that includes step 01 (--from 01, or a full run) only warns: step 01 is what finishes the copy" 0 'WARN: step 01 has not finished ok for this copy' 'FAIL'
pf_run f2 ""
rb_expect "(7-M2) a preflight run alone does not know, and warns" 0 'WARN: step 01 has not finished ok for this copy' 'FAIL'

# A step 01 that is KILLED (TERM) before its gate: the stand-in holds it at the content-hash query until a flag file appears.
fake_db 400
fake_set storemarker 1
fake_set e2e 1
fake_set blockgate 1
base_env "$T/rb-f3.env" "REHEARSAL_WORK=$T/rb-f3/work" "MOODLEDATA=$T/rb-f3/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal" "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
bash "$KIT/01_restore_check.sh" --env "$T/rb-f3.env" --execute > "$T/rb-f3.out" 2>&1 &
KP=$!
if wait_file "$FW/fake.atgate" 360; then
    kill -TERM "$KP"
    sleep 2
    touch "$FW/fake.releasegate"
    rc=0
    wait "$KP" || rc=$?
    if [ "$rc" = 143 ] && [ "$(sed -n 's/^status=//p' "$T/rb-f3/work/state/01.status")" = fail ] && [ "$(sed -n 's/^signal=//p' "$T/rb-f3/work/state/01.status")" = TERM ]; then
        ok "(7-M2) step 01 killed by TERM before its file store gate: exit 143, 01.status fail with signal=TERM"
    else bad "(7-M2) the killed step 01 (rc ${rc}, status '$(sed -n 's/^status=//p' "$T/rb-f3/work/state/01.status")')" "$(tail -n 4 "$T/rb-f3.out")"; fi
    if [[ "$(rb_kv f3 restore.id)" =~ ^[0-9a-f]{32}$ ]] && ! grep -q 'smtphosts\|cron_enabled\|oauth2' "$FW/fake.sqllog" && [ ! -e "$FW/fake.step01" ]; then
        ok "(7-M2) the copy is stamped and NOT neutralised (no SMTP wipe, no cron_enabled, no OAuth2 statement), and carries no record that step 01 finished"
    else bad "(7-M2) the database after the killed step 01" "$(cut -c1-120 "$FW/fake.sqllog")"; fi
    pf_env f3
    OUT="$(bash "$K3/02_source_baseline.sh" --env "$T/pf-f3.env" --execute 2>&1)"
    RC=$?
    rb_expect "(7-M2) step 02 on the copy the TERM left (it used to go on, with live's OAuth2 refresh tokens usable) is refused: step 01 has not finished ok" 1 'step 01 (restore check) has not finished ok for this restore (state/01.status says fail' 'database release'
else
    bad "(7-M2) step 01 never reached its file store gate"
    touch "$FW/fake.releasegate"
    kill "$KP" 2> /dev/null || true
fi

# A step 01 that is started again on a finished copy takes the record away first: a re-run that fails leaves the copy refused, though it was finished before.
fake_db 400
fake_set storemarker 1
fake_set e2e 1
rb_run f4 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(7-M2, setup) a second copy: step 01 finishes" 0 'restore check done'
F4ID="$(rb_kv f4 restore.id)"
if [ -n "$F4ID" ] && [ "$(cat "$FW/fake.step01" 2> /dev/null)" = "$F4ID" ]; then ok "(7-M2, setup) its record is in place"; else bad "(7-M2, setup) the record of the second copy"; fi
fake_set twohashes 1
rb_run f4 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(7-M2) step 01 again on the finished copy, and now its gate fails" 1 'content hash(es) of {files} are not on disk' 'restore check done'
if [ ! -e "$FW/fake.step01" ] && [ -z "$(rb_kv f4 restore.verified)" ] && [ "$(sed -n 's/^status=//p' "$T/rb-f4/work/state/01.status")" = fail ]; then
    ok "(7-M2) the earlier record was removed when step 01 started again: the copy is no longer finished (row gone, restore.verified gone, 01.status fail)"
else bad "(7-M2) the record after a failed re-run (row '$(cat "$FW/fake.step01" 2> /dev/null)', kv '$(rb_kv f4 restore.verified)')"; fi
OUT="$(bash "$KIT/03_hop1_to_45.sh" --env "$T/rb-f4.env" --execute 2>&1)"
RC=$?
rb_expect "(7-M2) step 03 on it is refused" 1 'step 01 (restore check) has not finished ok for this restore' '4.5 tree'
rm -f "$FW/fake.twohashes"
rb_run f4 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(7-M2) and the step 01 that then finishes makes it accepted again" 0 'restore check done'
if [ "$(cat "$FW/fake.step01" 2> /dev/null)" = "$F4ID" ] && [ "$(rb_kv f4 restore.verified)" = "$F4ID" ]; then ok "(7-M2) ... the record is written again, for the same restore id"; else bad "(7-M2) the record after the successful re-run"; fi
pf_run f4 0
rb_expect "(7-M2) the preflight of a run that starts after step 01 accepts the copy whose step 01 finished (and says so)" 0 'OK: step 01 finished ok for restore' 'FAIL\|WARN: step 01'
FW="$T"

printf 'step 01: the content of filedir/ is read, and every file must hash to its own name (Stage B tools fix round 8, must-fix A)\n'
# Moodle names each file of its file store by the SHA-1 of its content. mkfd DIR CONTENT...: each content goes to DIR/ab/cd/<sha1 of the content>.
mkfd() {
    local d="$1" c h
    shift
    for c in "$@"; do
        h="$(printf '%s' "$c" | sha1sum | cut -d ' ' -f 1)"
        mkdir -p "$d/${h:0:2}/${h:2:2}"
        printf '%s' "$c" > "$d/${h:0:2}/${h:2:2}/$h"
    done
}
# t_hash NAME: filedir_hash_check on $T/hc-NAME/filedir, the counters it sets, and the lists it writes
t_hash() {
    local rc=0
    mkdir -p "$T/hc-$1/rep"
    filedir_hash_check "$T/hc-$1/filedir" "$T/hc-$1/rep/h" || rc=$?
    printf 'files=%s bytes=%s bad=%s unread=%s odd=%s hashrc=%s\n' "$FILEDIR_HASH_FILES" "$FILEDIR_HASH_BYTES" "$FILEDIR_HASH_BAD" "$FILEDIR_HASH_UNREAD" "$FILEDIR_HASH_ODD" "$rc"
}
BIGC="$(head -c 30000 /dev/urandom | od -An -tx1 | tr -d ' \n')"
mkdir -p "$T/hc-clean/filedir"
mkfd "$T/hc-clean/filedir" x "hello world" "$BIGC"
printf 'Moodle sentinel' > "$T/hc-clean/filedir/warning.txt"
res="$(in_kit t_hash clean)"
if printf '%s' "$res" | grep -q "^files=3 bytes=$((1 + 11 + ${#BIGC})) bad=0 unread=0 odd=0 hashrc=0$" && [ ! -s "$T/hc-clean/rep/h-mismatch.txt" ] && [ ! -s "$T/hc-clean/rep/h-odd.txt" ]; then
    ok "(8-A) a clean file store (3 files named by the SHA-1 of their content, and Moodle's warning.txt in the root) passes: 3 files read, nothing odd, nothing listed"
else bad "(8-A) the clean file store" "$res"; fi
# the same file store with ONE file zero-filled at its size: its name, its size and its place are all right, and only its content is gone
cp -r "$T/hc-clean" "$T/hc-zero"
zf="$T/hc-zero/filedir/$(printf '%s' "$BIGC" | sha1sum | cut -c 1-2)/$(printf '%s' "$BIGC" | sha1sum | cut -c 3-4)/$(printf '%s' "$BIGC" | sha1sum | cut -d ' ' -f 1)"
zsize="$(stat -c %s "$zf")"
head -c "$zsize" /dev/zero > "$zf"
if [ "$(stat -c %s "$zf")" = "$zsize" ]; then ok "(premise) the file is zero-filled and has its size and its name: the existence and size gates of step 01 pass it"; else bad "(premise) the zero-filled file's size"; fi
res="$(in_kit t_hash zero)"
if printf '%s' "$res" | grep -q '^files=3 .* bad=1 unread=0 odd=0 hashrc=1$' && grep -q "$(printf '%s' "$BIGC" | sha1sum | cut -d ' ' -f 1)" "$T/hc-zero/rep/h-mismatch.txt" && [ "$(wc -l < "$T/hc-zero/rep/h-mismatch.txt" | tr -d ' ')" = 1 ]; then
    ok "(8-A) one file zero-filled at the same size is found: 3 read, 1 mismatch, listed with the SHA-1 of what it holds; the other two pass"
else bad "(8-A) the zero-filled file" "$res"; fi
# names that are not a content hash are counted and listed, not failed; warning.txt in the root is Moodle's own and is not counted
cp -r "$T/hc-clean" "$T/hc-odd"
hd="$(printf 'x' | sha1sum | cut -c 1-2)/$(printf 'x' | sha1sum | cut -c 3-4)"
printf 'stray' > "$T/hc-odd/filedir/$hd/not-a-hash.tmp"
printf 'stray' > "$T/hc-odd/filedir/stray.dat"
mkdir -p "$T/hc-odd/filedir/AB/CD"
printf 'upper' > "$T/hc-odd/filedir/AB/CD/ABCDEFABCDEFABCDEFABCDEFABCDEFABCDEFABCD"
res="$(in_kit t_hash odd)"
if printf '%s' "$res" | grep -q '^files=3 .* bad=0 unread=0 odd=3 hashrc=0$' && grep -qx 'stray.dat' "$T/hc-odd/rep/h-odd.txt" && ! grep -q 'warning.txt' "$T/hc-odd/rep/h-odd.txt"; then
    ok "(8-A) names that are not a content hash (a stray file, a file in the root, an upper-case name) are counted and listed (3), not failed; warning.txt is not counted"
else bad "(8-A) the odd names" "$res"; fi
# a file that cannot be read (sha1sum fails for it and prints no line): not ok either, however many others pass
mkdir -p "$T/shim-sha"
cat > "$T/shim-sha/sha1sum" <<'SHIM'
#!/usr/bin/env bash
# the real sha1sum; the file named in $SHIM_DROP is "unreadable": no line for it, a message, exit 1
out="$("$SHIM_REALSHA" "$@")"
rc=$?
if [ -n "${SHIM_DROP:-}" ]; then
    printf '%s\n' "$out" | grep -v "$SHIM_DROP"
    echo "sha1sum: ${SHIM_DROP}: Permission denied (stand-in)" >&2
    exit 1
fi
printf '%s\n' "$out"
exit $rc
SHIM
chmod +x "$T/shim-sha/sha1sum"
OLDPATH="$PATH"
SHIM_REALSHA="$(command -v sha1sum)"
SHIM_DROPHASH="$(printf 'hello world' | sha1sum | cut -d ' ' -f 1)"
PATH="$T/shim-sha:$PATH"
export SHIM_REALSHA SHIM_DROP="$SHIM_DROPHASH"
res="$(in_kit t_hash clean)"
PATH="$OLDPATH"
unset SHIM_DROP
if printf '%s' "$res" | grep -q '^files=2 .* bad=0 unread=1 odd=0 hashrc=1$'; then ok "(8-A) a file that could not be read is counted (unread 1 of 3) and fails the check although every file that was read is right"; else bad "(8-A) the unreadable file" "$res"; fi

# The same, through a whole step 01 (end-to-end mode of the stand-in). The archive holds a file store of three files named by the SHA-1 of their content
# and a language pack. md-hz.tar is that archive with the CONTENT of one file zero-filled in place: every header, name, size and the end of the archive are
# intact, so nothing in the tar shows it, and the operator who takes its SHA-256 ON THE SANDBOX (the round 7 review's repro) gets a checksum it matches.
FW="$T/w6"
mkdir -p "$FW" "$T/mdh/lang/hi"
cp "$T/fakemysql" "$FW/fakemysql"
printf 'x' > "$T/mdh.c1"
printf 'hello world' > "$T/mdh.c2"
printf '%s' "$BIGC" > "$T/mdh.c3"
: > "$T/mdh.rows"
for c in "$T/mdh.c1" "$T/mdh.c2" "$T/mdh.c3"; do
    h="$(sha1sum "$c" | cut -d ' ' -f 1)"
    mkdir -p "$T/mdh/filedir/${h:0:2}/${h:2:2}"
    cp "$c" "$T/mdh/filedir/${h:0:2}/${h:2:2}/$h"
    printf '%s\t%s\n' "$h" "$(stat -c %s "$c")" >> "$T/mdh.rows"
done
head -c 100000 /dev/urandom > "$T/mdh/lang/hi/langconfig.bin"
tar -C "$T/mdh" -cf "$T/md-h.tar" filedir lang
php -r '$f = file_get_contents($argv[1]); $c = file_get_contents($argv[3]); $o = strpos($f, substr($c, 0, 4096)); if ($o === false || substr($f, $o, strlen($c)) !== $c) { exit(3); } file_put_contents($argv[2], substr($f, 0, $o) . str_repeat("\0", strlen($c)) . substr($f, $o + strlen($c)));' "$T/md-h.tar" "$T/md-hz.tar" "$T/mdh.c3"
if [ "$(stat -c %s "$T/md-hz.tar")" = "$(stat -c %s "$T/md-h.tar")" ] && ! cmp -s "$T/md-h.tar" "$T/md-hz.tar"; then ok "(premise) md-hz.tar is md-h.tar with one content file zero-filled in place: the same size, the same headers and names"; else bad "(premise) md-hz.tar"; fi
tar -tf "$T/md-hz.tar" > /dev/null 2>&1 && ok "(premise) GNU tar lists and unpacks md-hz.tar with exit status 0: the tar itself shows nothing" || bad "(premise) tar on md-hz.tar"
HSHA="RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/md-h.tar" | cut -d ' ' -f 1)"
HZSHA="RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/md-hz.tar" | cut -d ' ' -f 1)"
fake_db 400
fake_set storemarker 1
fake_set e2e 1
cp "$T/mdh.rows" "$FW/fake.hashrows"
rb_run ha1 "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-h.tar" "$HSHA"
rb_expect "(8-A, control) a whole archive: step 01 unpacks it, reads all three content files, and finishes" 0 'restore check done' 'FAIL'
if [ "$(rb_kv ha1 restore.filedir_hash_proof)" = sha1 ] && [ "$(rb_kv ha1 restore.filedir_hash_files)" = 3 ] && [ "$(rb_kv ha1 restore.filedir_hash_odd)" = 0 ] && printf '%s' "$OUT" | grep -q 'hashes to its own name'; then
    ok "(8-A, control) the proof is recorded in state/kv (restore.filedir_hash_proof=sha1, 3 files) and said in the log"
else bad "(8-A, control) the recorded proof ('$(rb_kv ha1 restore.filedir_hash_proof)', files '$(rb_kv ha1 restore.filedir_hash_files)')"; fi
HA1ID="$(rb_kv ha1 restore.id)"
fake_db 400
fake_set storemarker 1
fake_set e2e 1
cp "$T/mdh.rows" "$FW/fake.hashrows"
rb_run ha2 "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-hz.tar" "$HZSHA"
rb_expect "(8-A) the zero-filled copy with the SHA-256 the operator took on the sandbox (it matches the copy): refused by the content check (it used to finish)" 1 'content of filedir/ does not match its names' 'restore check done'
if printf '%s' "$OUT" | grep -q 'OK: RESTORE_MOODLEDATA_ARCHIVE has the SHA-256 in RESTORE_MOODLEDATA_SHA256' && printf '%s' "$OUT" | grep -q 'OK: every content hash of {files} is on disk'; then
    ok "(8-A) ... although the checksum matched the copy and the file store gate (names and sizes) passed it: only the content shows it"
else bad "(8-A) the checksum or the names-and-sizes gate did not pass the zero-filled copy" "$OUT"; fi
if [ "$(rb_kv ha2 restore.filedir_hash_proof)" = failed ] && grep -q "$(sha1sum "$T/mdh.c3" | cut -d ' ' -f 1)" "$T/rb-ha2/work/reports/filedir-hash-mismatch.txt" \
        && [ "$(sed -n 's/^status=//p' "$T/rb-ha2/work/state/01.status")" = fail ] && [ -z "$(rb_kv ha2 restore.verified)" ] && [ ! -e "$FW/fake.step01" ] && ! grep -q 'smtphosts\|cron_enabled' "$FW/fake.sqllog"; then
    ok "(8-A) ... step 01 is fail, the file is listed in reports/filedir-hash-mismatch.txt, nothing says step 01 finished, and nothing was neutralised yet"
else bad "(8-A) the state after the refused copy (proof '$(rb_kv ha2 restore.filedir_hash_proof)')"; fi
pf_env ha2
OUT="$(bash "$K3/02_source_baseline.sh" --env "$T/pf-ha2.env" --execute 2>&1)"
RC=$?
rb_expect "(8-A) step 02 on that copy is refused: step 01 has not finished ok" 1 'step 01 (restore check) has not finished ok' 'database release'
# RESTORE_FILEDIR_HASH_CHECK=0 skips the read, says so, and the summary prints it
fake_db 400
fake_set storemarker 1
fake_set e2e 1
cp "$T/mdh.rows" "$FW/fake.hashrows"
rb_run ha3 "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-hz.tar" "$HZSHA" "RESTORE_FILEDIR_HASH_CHECK=0"
rb_expect "(8-A) RESTORE_FILEDIR_HASH_CHECK=0 skips the content check with a WARN (and the step goes on)" 0 'WARN: RESTORE_FILEDIR_HASH_CHECK=0: the CONTENT of filedir/ was NOT read' 'FILEDIR HASH: reading'
if [ "$(rb_kv ha3 restore.filedir_hash_proof)" = skipped ]; then ok "(8-A) the skip is recorded (restore.filedir_hash_proof=skipped)"; else bad "(8-A) the recorded skip ('$(rb_kv ha3 restore.filedir_hash_proof)')"; fi
OUT="$(bash "$KIT/12_summary.sh" --env "$T/rb-ha3.env" --execute 2>&1)"
if grep -q 'NOT CHECKED.*RESTORE_FILEDIR_HASH_CHECK=0' "$T/rb-ha3/work/reports/summary.md"; then ok "(8-A) the summary says the content of filedir/ was NOT CHECKED"; else bad "(8-A) the summary's line on the skipped check" "$(grep -n 'File store content' "$T/rb-ha3/work/reports/summary.md")"; fi
rb_run ha3 "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-hz.tar" "$HZSHA" "RESTORE_FILEDIR_HASH_CHECK=yes"
rb_expect "(8-A) a value that is not 0 or 1 is refused" 1 'RESTORE_FILEDIR_HASH_CHECK is .yes.' 'restore check done'
# The re-run on the finished copy (the reuse path) reads the content again: a stray name is a warning, a file damaged since is a failure.
printf 'stray' > "$T/rb-ha1/data/filedir/$hd/not-a-hash.tmp"
printf 'Moodle sentinel' > "$T/rb-ha1/data/filedir/warning.txt"
fake_db 400
fake_set storemarker 1
fake_set e2e 1
cp "$T/mdh.rows" "$FW/fake.hashrows"
printf '%s\n' "$HA1ID" > "$FW/fake.marker"
rb_run ha1 "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-h.tar" "$HSHA"
rb_expect "(8-A) a re-run on the finished unpack (the reuse path) reads the content again; a stray name is one WARN and warning.txt is not counted" 0 'WARN: 1 name(s) under filedir/ are not a content hash' 'FAIL'
if printf '%s' "$OUT" | grep -q 'not restoring over it' && printf '%s' "$OUT" | grep -q 'hashes to its own name'; then ok "(8-A) ... on the reuse path, with the unpack kept"; else bad "(8-A) the reuse path did not read the content" "$OUT"; fi
c3p="$T/rb-ha1/data/filedir/$(sha1sum "$T/mdh.c3" | cut -c 1-2)/$(sha1sum "$T/mdh.c3" | cut -c 3-4)/$(sha1sum "$T/mdh.c3" | cut -d ' ' -f 1)"
head -c "$(stat -c %s "$c3p")" /dev/zero > "$c3p"
rb_run ha1 "RESTORE_DONE_BY_HAND=$RBDB" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-h.tar" "$HSHA"
rb_expect "(8-A) a file damaged on disk since the unpack (zero-filled, same size) is found by the next run of step 01" 1 'content of filedir/ does not match its names' 'restore check done'
FW="$T"

printf 'step 01: an archive must END where a tar ends, with or without its checksum (Stage B tools fix round 8, S3)\n'
# tar_end_near_file_end: GNU tar's own end of the archive, within one record of the end of the file
res="$(in_kit tar_end_near_file_end "$T/md-full.tar")"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(8-S3) a whole tar: GNU tar's end of the archive is within one record of the end of the file"; else bad "(8-S3) tar_end_near_file_end on a whole tar" "$res"; fi
tar -b 1 -C "$T/mdcut" -cf "$T/md-b1.tar" filedir lang
res="$(in_kit tar_end_near_file_end "$T/md-b1.tar")"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ]; then ok "(8-S3) a tar written with a blocking factor of 1 (a 512-byte record) is whole too"; else bad "(8-S3) a tar with a blocking factor of 1" "$res"; fi
{ cat "$T/md-full.tar"; head -c 20480 /dev/zero; } > "$T/md-pad.tar"
res="$(in_kit tar_end_near_file_end "$T/md-pad.tar")"
if [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ]; then ok "(8-S3) a tar followed by 20480 bytes of zeros (more than one record after GNU tar's end of the archive) is refused"; else bad "(8-S3) a padded tar" "$res"; fi
for f in md-zero.tar md-cut.tar md-cuthdr.tar md-text.tar; do
    res="$(in_kit tar_end_near_file_end "$T/$f")"
    if [ "$(printf '%s' "$res" | tail -n 1)" = rc=0 ] && [ "$f" = md-cuthdr.tar ]; then
        ok "(8-S3) ${f}: the end of the archive GNU tar finds is the end of the file (nothing but the 1024-byte test shows a cut at a member header)"
    elif [ "$(printf '%s' "$res" | tail -n 1)" = rc=1 ] && [ "$f" != md-cuthdr.tar ]; then
        ok "(8-S3) ${f} is refused by tar_end_near_file_end (a zero-filled region, a cut inside a member, or no tar at all)"
    else bad "(8-S3) tar_end_near_file_end on ${f}" "$res"; fi
done
# Each of these archives, vouched for by the SHA-256 of ITSELF (the checksum the operator takes on the sandbox, or after a tar died on the live server), is refused
# before anything is restored, claimed, stamped or unpacked. Before round 8 the checksum replaced the format check, and every one of them was unpacked.
FW="$T/w4"
for variant in "md-cuthdr.tar:a plain tar cut at a member header" "md-cut.tar:a plain tar cut inside a member" "md-cuthdr.tar.gz:a valid .tar.gz that holds a cut tar (a tar that died while it wrote into gzip)" "md-zero.tar:a full-size copy with a zero-filled region" "md-pad.tar:a tar followed by more than one record of zeros"; do
    f="${variant%%:*}"
    fake_db none
    fake_after 120
    fake_set storemarker 1
    rb_run "pv-${f%%.*}-${f##*.}" "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/$f" "RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/$f" | cut -d ' ' -f 1)"
    rb_expect "(8-S3) ${variant#*:}, with a checksum that matches it, is refused as not proven complete" 1 'is not proven complete' 'RUN: restore\|stamped as restore\|unpacked from'
    nothing_touched "pv-${f%%.*}-${f##*.}" "(8-S3) ... before anything was loaded, claimed, stamped or unpacked (${f})"
done
rb_run pv-md-zero-tar "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-zero.tar" "RESTORE_MOODLEDATA_SHA256=$(sha256sum "$T/md-zero.tar" | cut -d ' ' -f 1)"
rb_expect "(8-S3) the zero-filled copy says why: GNU tar's end of the archive is more than one record before the end of the file" 1 'more than one 10240-byte record' 'unpacked from'
fake_db none
fake_after 120
fake_set storemarker 1
rb_run pv-ok "$DUMP" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
rb_expect "(8-S3, control) a whole plain tar with its checksum is accepted, and its end is checked too" 1 'GNU tar.s end of the archive (block [0-9]*) is [0-9]* bytes from the end of the file' 'is not proven complete'
FW="$T"

printf 'a step that does not reach its last line is never ok, and keeps its lock (Stage B tools fix round 8, S2)\n'
FW="$T/w4"
for name in ABRT SYS TRAP; do
    num="$(kill -l "$name")"
    lname="sg-${name,,}-u"
    SD="$T/sigu-$name"
    rm -rf "$SD"
    mkdir -p "$SD"
    base_env "$T/rb-${lname}.env" "REHEARSAL_WORK=$T/rb-${lname}/work" "MOODLEDATA=$T/rb-${lname}/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
    STUBDIR="$SD" bash "$K2/sigstep.sh" --env "$T/rb-${lname}.env" --execute > "$T/rb-${lname}.out" 2>&1 &
    SP=$!
    if wait_file "$SD/01.started" 240; then
        kill -"$name" "$SP"
        rc=0
        { wait "$SP" || rc=$?; } 2> /dev/null
        sleep 1
        # bash re-raises the signal after its EXIT trap (status 128+n, as bash 5.2 does); another bash may end with the trap's own exit 1: never 0
        if { [ "$rc" = $((128 + num)) ] || [ "$rc" = 1 ]; } && [ ! -e "$SD/after.child" ] && [ ! -e "$SD/01.finished" ]; then
            ok "(8-S2) SIG${name} (not one of STEP_SIGNALS) ends the step at once with a non-zero status (${rc}; the signal's own status is $((128 + num))); the code after the command never ran"
        else bad "(8-S2) the step after SIG${name} (rc ${rc}, wanted $((128 + num)))" "$(tail -n 4 "$T/rb-${lname}.out")"; fi
        if [ "$(status_field "$lname" status)" = fail ] && [ "$(status_field "$lname" rc)" = 1 ] && [ "$(status_field "$lname" ended)" = unreached ] && ! grep -qx 'status=ok' "$T/rb-${lname}/work/state/01.status"; then
            ok "(8-S2) state/01.status says status=fail, ended=unreached, never ok (it said ok, rc=0, and released the lock)"
        else bad "(8-S2) the status file after SIG${name} ($(tr '\n' ' ' < "$T/rb-${lname}/work/state/01.status"))"; fi
        if [ -d "$T/rb-${lname}/work/.run.lock" ] && [ ! -e "$SD/child.finished" ] && grep -q 'ended without reaching its last line' "$T/rb-${lname}.out"; then
            ok "(8-S2) the lock is KEPT while the step's command still runs, and the log says the step ended without reaching its last line (SIG${name})"
        else bad "(8-S2) the lock or the log after SIG${name} (lock $([ -d "$T/rb-${lname}/work/.run.lock" ] && echo held || echo gone))" "$(tail -n 4 "$T/rb-${lname}.out")"; fi
        touch "$SD/release"
        wait_file "$SD/child.finished" 60 || true
        kill "$(cat "$SD/bg.pid" 2> /dev/null)" 2> /dev/null || true
        rm -rf "$T/rb-${lname}/work/.run.lock"
        if [ "$name" = ABRT ]; then
            OUT="$(bash "$KIT/12_summary.sh" --env "$T/rb-${lname}.env" --execute 2>&1)"
            if grep -q 'fail (ended without reaching its last line' "$T/rb-${lname}/work/reports/summary.md" 2> /dev/null; then
                ok "(8-S2) the summary labels that step: fail (ended without reaching its last line: a signal that ends bash)"
            else bad "(8-S2) the summary's label of the unreached step" "$(grep -n '| 01 |' "$T/rb-${lname}/work/reports/summary.md" 2> /dev/null)"; fi
        fi
    else
        bad "(8-S2) the stub step never started (SIG${name})"
        touch "$SD/release"
        kill "$SP" 2> /dev/null || true
    fi
done
# An 'exit 0' that skips the last line, with no signal at all, is not ok either.
cat > "$K2/noend.sh" <<'STUB'
#!/usr/bin/env bash
# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 01 noendstep "$@"
exit 0
STUB
base_env "$T/rb-sgn.env" "REHEARSAL_WORK=$T/rb-sgn/work" "MOODLEDATA=$T/rb-sgn/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
bash "$K2/noend.sh" --env "$T/rb-sgn.env" --execute > "$T/rb-sgn.out" 2>&1
rc=$?
if [ "$rc" = 1 ] && [ "$(status_field sgn status)" = fail ] && [ "$(status_field sgn ended)" = unreached ]; then ok "(8-S2) a step that exits 0 without step_end is recorded fail (ended=unreached) and exits 1, never ok"; else bad "(8-S2) the step without step_end (rc ${rc}, status '$(status_field sgn status)')" "$(tail -n 3 "$T/rb-sgn.out")"; fi
rm -rf "$T/rb-sgn/work/.run.lock"
# run_all.sh: the same signal to run_all.sh alone while a step runs must not release its lock under the step.
SD="$T/sigu-run"
rm -rf "$SD"
mkdir -p "$SD"
cp "$K2/sigstep.sh" "$K2/01_restore_check.sh"
for n in 02_source_baseline 12_summary; do
    printf '#!/usr/bin/env bash\n: > "$STUBDIR/%s.started"\nexit 0\n' "${n%%_*}" > "$K2/$n.sh"
done
base_env "$T/rb-sgy.env" "REHEARSAL_WORK=$T/rb-sgy/work" "MOODLEDATA=$T/rb-sgy/data" "MYSQL_BIN=$FW/fakemysql" "PRODUCTION_DB_ENDPOINT=live-db.example.internal"
STUBDIR="$SD" bash "$K2/run_all.sh" --env "$T/rb-sgy.env" --execute --only 01,02,12 > "$T/rb-sgy.out" 2>&1 &
RP=$!
if wait_file "$SD/01.started" 240; then
    kill -ABRT "$RP"
    rc=0
    { wait "$RP" || rc=$?; } 2> /dev/null
    sleep 1
    if { [ "$rc" = $((128 + $(kill -l ABRT))) ] || [ "$rc" = 1 ]; } && [ -d "$T/rb-sgy/work/.run.lock" ] && [ ! -e "$SD/child.finished" ] && grep -q 'run_all.sh ended without reaching its end' "$T/rb-sgy/work/logs/run_all.log"; then
        ok "(8-S2) ABRT to run_all.sh alone while step 01 runs: it dies with a non-zero status, its lock is KEPT under the running step, and its log says so (it removed the lock)"
    else bad "(8-S2) run_all.sh after ABRT (rc ${rc}, lock $([ -d "$T/rb-sgy/work/.run.lock" ] && echo held || echo gone))" "$(tail -n 4 "$T/rb-sgy.out")"; fi
    touch "$SD/release"
    wait_file "$SD/child.finished" 60 || true
    sleep 3
    if [ "$(status_field sgy status)" = ok ] && [ ! -e "$SD/02.started" ] && [ ! -e "$SD/12.started" ]; then ok "(8-S2) the step it had started ran to its end (ok) and nothing further was started"; else bad "(8-S2) the step under the killed run_all.sh (status '$(status_field sgy status)')"; fi
    kill "$(cat "$SD/bg.pid" 2> /dev/null)" 2> /dev/null || true
    rm -rf "$T/rb-sgy/work/.run.lock"
else
    bad "(8-S2) the stub step 01 of the run_all.sh ABRT test never started"
    touch "$SD/release"
    kill "$RP" 2> /dev/null || true
fi
# Every step script ends with step_end (a step without it would be recorded fail); the two early exits say it too.
missing=""
for f in "$KIT"/[0-1][0-9]_*.sh; do
    [ "$(grep -v '^[[:space:]]*$' "$f" | tail -n 1)" = step_end ] || missing="$missing $(basename "$f")"
done
if [ -z "$missing" ]; then ok "(8-S2) every step script (00 to 12) ends with step_end"; else bad "(8-S2) steps whose last line is not step_end:${missing}"; fi
if [ "$(grep -c -B1 '^ *exit 0$' "$KIT/02_source_baseline.sh" "$KIT/12_summary.sh" | grep -c .)" -ge 2 ] && ! grep -B1 '^ *exit 0$' "$KIT/02_source_baseline.sh" "$KIT/12_summary.sh" | grep -v 'step_end\|exit 0\|^--' | grep -q .; then ok "(8-S2) the two early 'exit 0' of steps 02 and 12 follow a step_end"; else bad "(8-S2) an early exit 0 without step_end"; fi
FW="$T"

printf 'no copy of the database password is left in TMPDIR (Stage B tools fix round 8, S4)\n'
# The stand-in server w5 holds the finished copy f4 (the section above). Steps are run with their own TMPDIR; each leaves nothing in it.
FW="$T/w5"
mkdir -p "$T/tmpx"
printf 'status=fail\nrc=1\n' > "$T/rb-f4/work/state/01.status"
pf_env f4
TMPDIR="$T/tmpx" bash "$K3/10_parity_compare.sh" --env "$T/pf-f4.env" --execute > "$T/tmpx.refused.out" 2>&1
rc=$?
if [ "$rc" = 1 ] && grep -q 'step 01 (restore check) has not finished ok' "$T/tmpx.refused.out"; then ok "(8-S4, setup) step 10 on a copy whose step 01 did not finish is refused at the gate"; else bad "(8-S4, setup) the refused gate (rc ${rc})" "$(tail -n 3 "$T/tmpx.refused.out")"; fi
if [ -z "$(ls -A "$T/tmpx")" ]; then ok "(8-S4) a refused gate leaves nothing in TMPDIR (it left one directory with a client.cnf, a copy of the password, per read)"; else bad "(8-S4) what the refused gate left in TMPDIR" "$(find "$T/tmpx" -type f | head -n 5)"; fi
printf 'status=ok\nrc=0\n' > "$T/rb-f4/work/state/01.status"
TMPDIR="$T/tmpx" bash "$K3/10_parity_compare.sh" --env "$T/pf-f4.env" --execute > "$T/tmpx.passed.out" 2>&1
rc=$?
if ! grep -q 'has not finished ok\|could not be read\|PARTIAL copy' "$T/tmpx.passed.out"; then ok "(8-S4, setup) step 10 on the finished copy passes the gate (it stops later, on what the stand-in does not have)"; else bad "(8-S4, setup) the gate on the finished copy" "$(tail -n 3 "$T/tmpx.passed.out")"; fi
if [ -z "$(ls -A "$T/tmpx")" ]; then ok "(8-S4) a gate that passes leaves nothing in TMPDIR either (three directories per step run before)"; else bad "(8-S4) what the passing gate left in TMPDIR" "$(find "$T/tmpx" -type f | head -n 5)"; fi
FW="$T"

printf 'the preflight and an uncompressed moodledata archive without the checksum from the live server (Stage B tools fix round 8, S5)\n'
base_env "$T/pe1.env" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar"
expect_refused "(8-S5) DRY preflight: an uncompressed tar (it is here, so read by its first bytes) with no RESTORE_MOODLEDATA_SHA256 is refused, in the live-server wording" "$T/pe1.env" 00_preflight.sh 'is an uncompressed tar and RESTORE_MOODLEDATA_SHA256 is not set.*COMPUTED ON THE LIVE SERVER'
base_env "$T/pe2.env" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar.gz"
kit 00_preflight.sh "$T/pe2.env"
if [ "$RC" = 0 ] && printf '%s' "$OUT" | grep -q 'preflight passed'; then ok "(8-S5) a .tar.gz with no checksum passes the preflight (its decompressor is its proof)"; else bad "(8-S5) the preflight on a .tar.gz (rc ${RC})" "$OUT"; fi
base_env "$T/pe3.env" "RESTORE_MOODLEDATA_ARCHIVE=$T/md-full.tar" "RESTORE_MOODLEDATA_SHA256=$FULLSHA"
kit 00_preflight.sh "$T/pe3.env"
if [ "$RC" = 0 ] && printf '%s' "$OUT" | grep -q 'preflight passed' && printf '%s' "$OUT" | grep -q 'computed ON THE LIVE SERVER'; then ok "(8-S5) an uncompressed tar with its checksum passes, and the note says where the checksum must come from"; else bad "(8-S5) the preflight on a tar with its checksum (rc ${RC})" "$OUT"; fi
base_env "$T/pe4.env" "RESTORE_MOODLEDATA_ARCHIVE=$T/not-here/live-moodledata.tar"
expect_refused "(8-S5) DRY preflight: an archive that is not on this box and is named .tar is refused as well (it is read by its name)" "$T/pe4.env" 00_preflight.sh 'is an uncompressed tar and RESTORE_MOODLEDATA_SHA256 is not set'
base_env "$T/pe5.env" "RESTORE_MOODLEDATA_ARCHIVE=$T/not-here/live-moodledata.tar.gz"
kit 00_preflight.sh "$T/pe5.env"
if [ "$RC" = 0 ] && printf '%s' "$OUT" | grep -q 'preflight passed'; then ok "(8-S5) an archive that is not on this box and is named .tar.gz is not refused (DRY: nothing to read)"; else bad "(8-S5) the preflight on an absent .tar.gz (rc ${RC})" "$OUT"; fi
cp "$T/pf-f4.env" "$T/pe6.env"
printf 'RESTORE_MOODLEDATA_ARCHIVE=%s\n' "$T/md-full.tar" >> "$T/pe6.env"
OUT="$(bash "$K3/00_preflight.sh" --env "$T/pe6.env" --execute 2>&1)"
RC=$?
if [ "$RC" != 0 ] && printf '%s' "$OUT" | grep -q 'FAIL: RESTORE_MOODLEDATA_ARCHIVE .* is an uncompressed tar and RESTORE_MOODLEDATA_SHA256 is not set'; then ok "(8-S5) the --execute preflight refuses it too"; else bad "(8-S5) the --execute preflight (rc ${RC})" "$(printf '%s\n' "$OUT" | grep 'FAIL' | head -n 3)"; fi
# the words of the refusal and of the documents: where the checksum comes from
bad_words=""
for f in "$KIT/01_restore_check.sh" "$KIT/rehearsal.env.example" "$KIT/README.md" "$KIT/../../moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md"; do
    grep -qi 'on the live server' "$f" || bad_words="$bad_words missing:$(basename "$f")"
done
if grep -q "sha256sum, or the live backup's manifest" "$KIT/01_restore_check.sh" "$KIT/rehearsal.env.example"; then bad_words="$bad_words old-wording"; fi
if [ -z "$bad_words" ]; then ok "(8-S5) the refusal, rehearsal.env.example, the README and the runbook all say the checksum is computed ON THE LIVE SERVER (a sha256sum on the sandbox proves nothing)"; else bad "(8-S5) the wording where the checksum comes from:${bad_words}"; fi
for f in "$KIT/rehearsal.env.example" "$KIT/README.md" "$KIT/../../moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md"; do
    if grep -q 'RESTORE_FILEDIR_HASH_CHECK' "$f"; then ok "(8-A) $(basename "$f") documents RESTORE_FILEDIR_HASH_CHECK"; else bad "(8-A) $(basename "$f") does not document RESTORE_FILEDIR_HASH_CHECK"; fi
done

printf 'step 01 after a hop 1 that failed with the release already moved (Stage B tools fix round 8, S1)\n'
FW="$T/w7"
mkdir -p "$FW"
cp "$T/fakemysql" "$FW/fakemysql"
fake_db 400
fake_set storemarker 1
fake_set e2e 1
rb_run rr1 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(8-S1, setup) step 01 finishes on the live backup's copy (release 4.1.2)" 0 'restore check done'
RR1ID="$(rb_kv rr1 restore.id)"
printf 'status=ok\nrc=0\n' > "$T/rb-rr1/work/state/02.status"
printf 'status=fail\nrc=1\n' > "$T/rb-rr1/work/state/03.status"
printf '4.5.4 (Build: 20250414)\n' > "$FW/fake.relval"
if [ -n "$(rb_kv rr1 release.source)" ]; then ok "(8-S1, setup) step 01 recorded the source release it saw (state/kv/release.source), hop 1 failed with the release moved to 4.5.4 (03.status fail)"; else bad "(8-S1, setup) release.source was not recorded"; fi
rb_run rr1 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(8-S1) a full re-run: step 01 passes again on the copy whose release moved (it died at the release gate and locked the rehearsal out)" 0 'restore check done' 'does not match'
if printf '%s' "$OUT" | grep -q 'passed the release gate before a hop moved it' && [ "$(sed -n 's/^status=//p' "$T/rb-rr1/work/state/01.status")" = ok ] && [ "$(rb_kv rr1 restore.verified)" = "$RR1ID" ] && [ "$(cat "$FW/fake.step01" 2> /dev/null)" = "$RR1ID" ]; then
    ok "(8-S1) ... it says why, 01.status is ok, and both records that step 01 finished are written again for the same restore"
else bad "(8-S1) the re-run's records (01.status '$(sed -n 's/^status=//p' "$T/rb-rr1/work/state/01.status")', verified '$(rb_kv rr1 restore.verified)')" "$OUT"; fi
pf_env rr1
OUT="$(bash "$K3/03_hop1_to_45.sh" --env "$T/pf-rr1.env" --execute 2>&1)"
RC=$?
rb_expect "(8-S1) step 03 (the retry of the failed hop) now gets past the gate of steps 02 to 11 (it stops later, for want of the 4.5 code in this stand-in)" 1 'holds no code and no archive is configured' 'has not finished ok'
rm -f "$T/rb-rr1/work/state/kv/release.source"
rb_run rr1 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(8-S1, control) without that record (a copy this work directory never saw at the source release) a release past the source is still refused" 1 'does not match SOURCE_RELEASE_REGEX' 'restore check done'
printf '4.1.2 (Build: 20230320)\n' > "$T/rb-rr1/work/state/kv/release.source"
printf '3.9.1 (Build: 20210101)\n' > "$FW/fake.relval"
rb_run rr1 "RESTORE_DONE_BY_HAND=$RBDB" "$ARCH" "$ARCHSHA"
rb_expect "(8-S1, control) a release that no hop of this kit leaves (3.9.1) is refused even with the record" 1 'does not match SOURCE_RELEASE_REGEX' 'restore check done'
FW="$T"

printf 'the mysql client of the kit reads the kit'"'"'s option file only (Stage B tools fix round 6b)\n'
if ! grep -n -e '--defaults-extra-file' "$KIT"/*.sh "$KIT"/lib/*.sh 2> /dev/null | grep -v '/selftest.sh:' | grep -v '^[^:]*:[0-9]*:[[:space:]]*#' | grep -q .; then
    ok "no kit client call uses --defaults-extra-file (the user's ~/.my.cnf is read after it and would override the kit's host, port and password)"
else bad "a kit script still uses --defaults-extra-file" "$(grep -n -e '--defaults-extra-file' "$KIT"/*.sh "$KIT"/lib/*.sh | grep -v '/selftest.sh:' | grep -v ':[[:space:]]*#' | head -n 4)"; fi

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

printf '\n%s passed, %s failed%s\n' "$PASS" "$FAILS" "$([ "$SKIPS" = 0 ] || printf ', %s skipped (see the skip lines above)' "$SKIPS")"
[ "$FAILS" = 0 ]
