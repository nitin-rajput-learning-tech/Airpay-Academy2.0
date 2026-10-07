#!/usr/bin/env bash
# selftest.sh -- tests of the rehearsal kit's own safety rules and helpers. No database and no Moodle needed (bash, PHP and
# the usual coreutils); every kit script is run in DRY mode or its functions are called directly.
#
#   bash tools/rehearsal/selftest.sh
#
# What it proves (each line below is a test):
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
        REHEARSAL_DB_ALLOWLIST="stageb_selftest" PRODUCTION_HOSTNAMES="airpay.academy"
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

printf 'portability\n'
hits="$(grep -nE '(^|[^A-Za-z0-9_])[A-Za-z]:[\\/]|xampp|/c/Users|/mnt/[a-z]/' "$KIT"/*.sh "$KIT"/lib/*.sh "$KIT"/lib/*.php "$KIT"/lib/bizlms_plugins.txt "$KIT"/rehearsal.env.example 2> /dev/null | grep -v 'selftest.sh' || true)"
if [ -z "$hits" ]; then ok "no Windows path, drive letter or XAMPP path is hard-coded in the kit"; else bad "hard-coded Windows paths" "$hits"; fi
hits="$(grep -nE 'password[[:space:]]*=[[:space:]]*["'"'"'][^"'"'"']{8,}' "$KIT"/*.sh "$KIT"/lib/*.sh "$KIT"/rehearsal.env.example 2> /dev/null | grep -v 'selftest.sh' || true)"
if [ -z "$hits" ]; then ok "no credential literal in the kit"; else bad "credential literal" "$hits"; fi

printf '\n%s passed, %s failed\n' "$PASS" "$FAILS"
[ "$FAILS" = 0 ]
