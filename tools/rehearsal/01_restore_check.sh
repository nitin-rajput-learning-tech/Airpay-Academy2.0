#!/usr/bin/env bash
# 01 restore check -- the database and moodledata of the live backup are really there, and nothing in them can send mail.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 1 (restore; user count; THE FILEDIR GATE; the source tool's --compare on the
# restored copy isolates restore loss). Migration plan 4c (restore; contenthash gate: DB distinct hashes against the disk,
# exact) and 4f-a (wipe the restored SMTP credentials, audit the restored task backlog = input I-11).
#
# What it does, in order:
#   1. Restore the dump into the EMPTY rehearsal database and unpack the moodledata, only if RESTORE_DB_DUMP /
#      RESTORE_MOODLEDATA_ARCHIVE are set and the target is empty. It never drops, truncates or overwrites anything.
#   2. Check the source: the release matches SOURCE_RELEASE_REGEX (live is 4.1.x), active users (optionally equal to
#      EXPECT_ACTIVE_USERS), the BizLMS open_path substrate is there.
#   3. The file store gate: every files.contenthash with content must be on disk at filedir/ab/cd/<hash>. Missing = stop
#      (the local clone with a DB-only restore 404'd every SCORM package; this is why the gate exists). The missing list
#      goes to reports/filedir-missing.txt.
#   4. Restore neutralisation, before any code touches the database: wipe smtphosts/smtpuser/smtppass, set cron_enabled = 0,
#      and record the restored mail backlog (reports/restore-mail-backlog-audit.txt, input I-11).
#   5. With LIVE_BASELINE_FILE: compare the restored copy with the live baseline (source_baseline.php --compare); exit 0
#      required.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
# shellcheck source=lib/make_config.sh
. "$KIT_DIR/lib/make_config.sh"
step_init 01 restore_check "$@"

FILEDIR_MAX_MISSING="${FILEDIR_MAX_MISSING:-0}"
RESTORE_DB_DUMP="${RESTORE_DB_DUMP:-}"
RESTORE_MOODLEDATA_ARCHIVE="${RESTORE_MOODLEDATA_ARCHIVE:-}"
LIVE_BASELINE_FILE="${LIVE_BASELINE_FILE:-}"
EXPECT_ACTIVE_USERS="${EXPECT_ACTIVE_USERS:-}"

need_tool "$MYSQL_BIN"
need_tool "$PHP_BIN"

# ---------------------------------------------------------------------------------------------------------------------
# 1. Restore into an empty database, unpack moodledata
# ---------------------------------------------------------------------------------------------------------------------
restore_database() {
    [ -f "$RESTORE_DB_DUMP" ] || die "RESTORE_DB_DUMP not found: ${RESTORE_DB_DUMP}"
    if [ "$DB_STATE" = absent ]; then
        timed "create database" mysql_nodb -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION}" \
            || die "cannot create database ${DB_NAME}: create it (empty, utf8mb4) and re-run"
    fi
    case "$RESTORE_DB_DUMP" in
        *.gz) timed "restore database" bash -c 'gzip -dc "$1" | "$2" --defaults-extra-file="$3" --max-allowed-packet=512M "$4"' _ \
                "$RESTORE_DB_DUMP" "$MYSQL_BIN" "$DB_CNF" "$DB_NAME" ;;
        *) timed "restore database" bash -c '"$2" --defaults-extra-file="$3" --max-allowed-packet=512M "$4" < "$1"' _ \
                "$RESTORE_DB_DUMP" "$MYSQL_BIN" "$DB_CNF" "$DB_NAME" ;;
    esac || die "the database restore failed: drop the partial database by hand and restore again"
}

restore_moodledata() {
    [ -f "$RESTORE_MOODLEDATA_ARCHIVE" ] || die "RESTORE_MOODLEDATA_ARCHIVE not found: ${RESTORE_MOODLEDATA_ARCHIVE}"
    mkdir -p "$MOODLEDATA"
    if file_magic_pk "$RESTORE_MOODLEDATA_ARCHIVE"; then
        need_tool unzip
        timed "restore moodledata" unzip -q -o "$RESTORE_MOODLEDATA_ARCHIVE" -d "$MOODLEDATA" || die "unzip of the moodledata failed"
    else
        timed "restore moodledata" tar -C "$MOODLEDATA" -xf "$RESTORE_MOODLEDATA_ARCHIVE" || die "tar of the moodledata failed"
    fi
}

if [ "$EXECUTE" = 1 ]; then
    probe_db
    log "database ${DB_NAME}: ${DB_STATE} (${DB_TABLES} tables)"
    case "$DB_STATE" in
        unreachable) die "the database server at ${DB_HOST} cannot be reached" ;;
        absent | empty)
            if [ -n "$RESTORE_DB_DUMP" ]; then
                restore_database
                probe_db
                [ "$DB_STATE" = present ] || die "the restore ran but database ${DB_NAME} is still ${DB_STATE}"
            else
                die "database ${DB_NAME} is ${DB_STATE} and RESTORE_DB_DUMP is not set: restore the live backup first"
            fi
            ;;
        present)
            if [ -n "$RESTORE_DB_DUMP" ]; then
                note "database ${DB_NAME} already holds ${DB_TABLES} tables: not restoring the dump over it"
            fi
            ;;
    esac
    if [ ! -d "$MOODLEDATA/filedir" ] || [ -z "$(ls -A "$MOODLEDATA/filedir" 2> /dev/null)" ]; then
        if [ -n "$RESTORE_MOODLEDATA_ARCHIVE" ]; then
            restore_moodledata
        else
            die "${MOODLEDATA}/filedir is missing or empty and RESTORE_MOODLEDATA_ARCHIVE is not set: unpack the live moodledata first"
        fi
    else
        log "moodledata already holds a filedir ($(find "$MOODLEDATA/filedir" -type f | wc -l | tr -d ' ') files): not restoring over it"
    fi
else
    dry "would restore RESTORE_DB_DUMP (${RESTORE_DB_DUMP:-not set}) into the EMPTY database ${DB_NAME} (never over a populated one)"
    dry "would unpack RESTORE_MOODLEDATA_ARCHIVE (${RESTORE_MOODLEDATA_ARCHIVE:-not set}) into ${MOODLEDATA} when its filedir/ is missing"
fi

# ---------------------------------------------------------------------------------------------------------------------
# 2. The source is what it should be
# ---------------------------------------------------------------------------------------------------------------------
if [ "$EXECUTE" = 1 ]; then
    release="$(db_config_value release || true)"
    version="$(db_config_value version || true)"
    log "restored source: release '${release}', version ${version}"
    SOURCE_PHASE=1
    if [[ ! "$release" =~ $SOURCE_RELEASE_REGEX ]]; then
        if step_done_ok 03; then
            SOURCE_PHASE=0
            note "the release is past the source (hop 1 is done); the release gate was passed before the hop"
        else
            die "release '${release}' does not match SOURCE_RELEASE_REGEX '${SOURCE_RELEASE_REGEX}': this is not the live 4.1.x copy"
        fi
    fi
    if [ "$SOURCE_PHASE" = 1 ]; then
        kv_set release.source "$release"
    fi
    users="$(db_scalar "SELECT COUNT(*) FROM {p}user WHERE deleted = 0")"
    log "active users: ${users}"
    kv_set restore.active_users "$users"
    if [ -n "$EXPECT_ACTIVE_USERS" ] && [ "$users" != "$EXPECT_ACTIVE_USERS" ]; then
        die "active users ${users} differ from EXPECT_ACTIVE_USERS ${EXPECT_ACTIVE_USERS}: the restore lost or gained users"
    fi
    if [ "$(db_scalar "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}user' AND COLUMN_NAME = 'open_path'")" != 1 ]; then
        die "{user}.open_path is missing: the BizLMS tenant substrate did not arrive (truncated restore?)"
    fi
    withpath="$(db_scalar "SELECT COUNT(*) FROM {p}user WHERE deleted = 0 AND open_path IS NOT NULL AND open_path <> ''")"
    [ "$withpath" -gt 0 ] || die "no active user has an open_path: the tenant substrate is empty"
    log "tenant roots in open_path: $(db_q "SELECT DISTINCT SUBSTRING_INDEX(open_path, '/', 2) FROM {p}user WHERE deleted = 0 AND open_path <> '' ORDER BY 1" | tr '\n' ' ')"
else
    dry "would check: release matches '${SOURCE_RELEASE_REGEX}', active users (EXPECT_ACTIVE_USERS='${EXPECT_ACTIVE_USERS}'), {user}.open_path present and populated"
fi

# ---------------------------------------------------------------------------------------------------------------------
# 3. The file store gate
# ---------------------------------------------------------------------------------------------------------------------
if [ "$EXECUTE" = 1 ]; then
    work="$(mktemp -d "$REPORT_DIR/.filedir.XXXXXX")"
    t0="$(epoch)"
    db_q "SELECT DISTINCT contenthash FROM {p}files WHERE filesize > 0" | expected_paths | LC_ALL=C sort -u > "$work/db.txt"
    disk_paths "$MOODLEDATA/filedir" | LC_ALL=C sort -u > "$work/disk.txt"
    comm_only_first "$work/db.txt" "$work/disk.txt" > "$REPORT_DIR/filedir-missing.txt"
    comm_only_second "$work/db.txt" "$work/disk.txt" > "$REPORT_DIR/filedir-extra.txt"
    dbrows="$(db_scalar "SELECT COUNT(*) FROM {p}files WHERE filesize > 0")"
    dbhashes="$(wc -l < "$work/db.txt" | tr -d ' ')"
    dbbytes="$(db_scalar "SELECT COALESCE(SUM(t.sz), 0) FROM (SELECT DISTINCT contenthash, filesize AS sz FROM {p}files WHERE filesize > 0) t")"
    diskfiles="$(wc -l < "$work/disk.txt" | tr -d ' ')"
    diskbytes="$(du -sb "$MOODLEDATA/filedir" | cut -f 1)"
    missing="$(wc -l < "$REPORT_DIR/filedir-missing.txt" | tr -d ' ')"
    extra="$(wc -l < "$REPORT_DIR/filedir-extra.txt" | tr -d ' ')"
    rm -rf "$work"
    log "FILEDIR: {files} rows with content ${dbrows}; distinct content hashes ${dbhashes} (${dbbytes} bytes); on disk ${diskfiles} files (${diskbytes} bytes incl. directories)"
    log "FILEDIR: missing from disk ${missing}; on disk but not in {files} ${extra} (extras are normal: content waiting for the trash cleanup)"
    log "FILEDIR: checked in $(( $(epoch) - t0 ))s; lists in ${REPORT_DIR}/filedir-missing.txt and filedir-extra.txt"
    kv_set filedir.db_hashes "$dbhashes"
    kv_set filedir.disk_files "$diskfiles"
    kv_set filedir.missing "$missing"
    kv_set filedir.extra "$extra"
    if [ "$missing" -gt "$FILEDIR_MAX_MISSING" ]; then
        head -n 20 "$REPORT_DIR/filedir-missing.txt" | sed 's/^/    missing: /'
        die "${missing} content hash(es) of {files} are not on disk (allowed ${FILEDIR_MAX_MISSING}): the moodledata restore is incomplete. SCORM packages and certificate images would 404. Do not go on"
    fi
    [ "$dbhashes" -gt 0 ] || die "the database has no file content at all: a restore without files rows"
    log "OK: every content hash of {files} is on disk"
else
    dry "would check the file store gate: every distinct files.contenthash (filesize > 0) exists at ${MOODLEDATA}/filedir/ab/cd/<hash>; missing > ${FILEDIR_MAX_MISSING:-0} stops the step; lists go to reports/"
fi

# ---------------------------------------------------------------------------------------------------------------------
# 4. Restore neutralisation (before any code touches the database)
# ---------------------------------------------------------------------------------------------------------------------
if [ "$EXECUTE" = 1 ] && [ -f "$REPORT_DIR/restore-mail-backlog-audit.txt" ]; then
    log "the mail backlog audit already exists (reports/restore-mail-backlog-audit.txt): keeping the first one, it is the state before the wipe"
elif [ "$EXECUTE" = 1 ]; then
    audit="$REPORT_DIR/restore-mail-backlog-audit.txt"
    {
        printf 'Restored mail and task backlog, taken before neutralisation (migration plan input I-11)\n'
        printf 'taken: %s\n\n' "$(ts)"
        printf 'smtp settings holding a value: %s of smtphosts/smtpuser/smtppass\n' \
            "$(db_scalar "SELECT COUNT(*) FROM {p}config WHERE name IN ('smtphosts','smtpuser','smtppass') AND value <> ''")"
        printf 'task_adhoc rows: %s\n' "$(db_scalar "SELECT COUNT(*) FROM {p}task_adhoc")"
        printf 'task_scheduled rows past due and enabled: %s\n' \
            "$(db_scalar "SELECT COUNT(*) FROM {p}task_scheduled WHERE disabled = 0 AND nextruntime <> 0 AND nextruntime < UNIX_TIMESTAMP()")"
        printf 'task rows marked running (timestarted set): adhoc %s, scheduled %s\n' \
            "$(db_scalar "SELECT COUNT(*) FROM {p}task_adhoc WHERE timestarted IS NOT NULL")" \
            "$(db_scalar "SELECT COUNT(*) FROM {p}task_scheduled WHERE timestarted IS NOT NULL")"
        printf '\ntask_adhoc by class (top 40):\n'
        db_q "SELECT classname, COUNT(*) FROM {p}task_adhoc GROUP BY classname ORDER BY 2 DESC LIMIT 40"
    } > "$audit"
    log "mail backlog audit written to ${audit}: $(sed -n 's/^task_adhoc rows: /task_adhoc rows /p' "$audit"); $(sed -n 's/^smtp settings holding a value: /smtp values set: /p' "$audit")"
    kv_set restore.adhoc_rows "$(sed -n 's/^task_adhoc rows: //p' "$audit")"
else
    dry "would write reports/restore-mail-backlog-audit.txt (smtp values set, task_adhoc rows and classes, past-due scheduled tasks)"
fi
db_write "UPDATE {p}config SET value = '' WHERE name IN ('smtphosts', 'smtpuser', 'smtppass')"
db_write "INSERT INTO {p}config (name, value) VALUES ('cron_enabled', '0') ON DUPLICATE KEY UPDATE value = '0'"
if [ "$EXECUTE" = 1 ]; then
    [ "$(db_config_value cron_enabled)" = "0" ] || die "cron_enabled is not 0 after the update"
    [ "$(db_scalar "SELECT COUNT(*) FROM {p}config WHERE name IN ('smtphosts','smtpuser','smtppass') AND value <> ''")" = 0 ] \
        || die "an smtp setting still holds a value after the wipe"
    log "OK: smtp credentials wiped, cron_enabled = 0 (no restored scheduler can fire; \$CFG->noemailever stays in config.php)"
fi

# ---------------------------------------------------------------------------------------------------------------------
# 5. Restore loss against the live baseline
# ---------------------------------------------------------------------------------------------------------------------
if [ -n "$LIVE_BASELINE_FILE" ] && [ "$EXECUTE" = 1 ] && [ "${SOURCE_PHASE:-1}" = 0 ]; then
    note "the database is past the source release: the restore-loss comparison with the live baseline was made before hop 1"
elif [ -n "$LIVE_BASELINE_FILE" ]; then
    if [ "$EXECUTE" = 1 ]; then
        [ -f "$LIVE_BASELINE_FILE" ] || die "LIVE_BASELINE_FILE not found: ${LIVE_BASELINE_FILE}"
        make_config db
    fi
    rc=0
    timed_to "$REPORT_DIR/restore-vs-live-baseline.txt" "restored copy vs live baseline" baseline_tool --compare="$LIVE_BASELINE_FILE" || rc=$?
    show_tail "$REPORT_DIR/restore-vs-live-baseline.txt" 14
    if [ "$EXECUTE" = 1 ]; then
        judge "restore vs live baseline" "$rc"
        kv_set parity.restore_vs_live "$rc"
    fi
else
    note "LIVE_BASELINE_FILE is not set: restore loss is not isolated. Step 02 takes the baseline from this copy (acceptable for a rehearsal from a dump; live's own baseline at the freeze is the strong form)"
fi

log "restore check done"
