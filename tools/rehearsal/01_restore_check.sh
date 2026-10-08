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
#      The dump is refused when it holds a USE / CREATE DATABASE / DROP DATABASE statement (it would reach another schema
#      whatever the allow-list says; the client also runs with --one-database), a SET @@GLOBAL.GTID_PURGED (a server-wide
#      setting; dump with --set-gtid-purged=OFF), when it has no "-- Dump completed" trailer (an aborted mysqldump restores as a
#      silent partial copy; RESTORE_ALLOW_NO_TRAILER=1 for a dump made another way), or when it names a MySQL 8 collation
#      (utf8mb4_0900_*) and the server is MariaDB, and the restore stops at the first error (pipefail).
#      THE KIT MARKER: after a restore the kit made (or one the operator names with RESTORE_DONE_BY_HAND=<database name>)
#      the database and the moodledata are stamped with a random restore id (state/kv/restore.id). Every writing step later
#      refuses a database or a moodledata that does not carry it, so an allow-listed name on the wrong server, or UAT's
#      database, can never be written to. A new restore moves the earlier rehearsal's state, reports, baseline and cache
#      configuration to archive/ (nothing of it can be mistaken for this one's result). A restore that did not complete is
#      refused on re-run, WHATEVER RESTORE_DONE_BY_HAND says: a database a kit restore was writing to is never adopted. Drop it and
#      create it empty; the kit sees it empty and moves the record of the failed restore to archive/ (a hand restore then follows).
#      That record names the database it was writing to (state/kv/restore.started_db: host, port, name) and is cleared ONLY when that
#      same database is seen absent or empty, on two reads that agree (a COUNT the client printed nothing for is never read as absent
#      or empty); a run for any other database refuses and leaves the record alone.
#      RESTORE_DONE_BY_HAND together with RESTORE_DB_DUMP is refused outright (a leftover statement must not meet a new dump).
#      THE MOODLEDATA is per rehearsal. The marker file also records which archive the kit unpacked into it (path, size, mtime) and
#      that the unpack finished. A NEW restore (a new database) accepts a non-empty moodledata only when it is the same unpack of the
#      same RESTORE_MOODLEDATA_ARCHIVE that no later step has used (that fact outlives the rotation of state/: restore-ids.log);
#      anything else (a dataroot an earlier rehearsal ran in, with its
#      role-9 state file, caches and sessions) is refused: empty it or point MOODLEDATA at a new directory. A named
#      RESTORE_MOODLEDATA_ARCHIVE is never ignored: if the moodledata already holds a filedir it must be that archive's unpack, or the
#      step stops. A populated moodledata the kit did not stamp needs its own statement, RESTORE_MOODLEDATA_BY_HAND=<its path>, and
#      must show no recent writes in sessions/ or localcache/.
#   2. Check the source: the release matches SOURCE_RELEASE_REGEX (live is 4.1.x), active users (optionally equal to
#      EXPECT_ACTIVE_USERS), the BizLMS open_path substrate is there.
#   3. The file store gate: every files.contenthash with content must be on disk at filedir/ab/cd/<hash>. Missing = stop
#      (the local clone with a DB-only restore 404'd every SCORM package; this is why the gate exists). The missing list
#      goes to reports/filedir-missing.txt.
#   4. Restore neutralisation, before any code touches the database: wipe smtphosts/smtpuser/smtppass, set cron_enabled = 0,
#      and record the restored mail backlog (reports/restore-mail-backlog-audit.txt, input I-11). Other ways out are
#      audited and closed the same way: the restored cache configuration (muc/config.php: a Redis or memcached store named
#      there would be flushed by every purge) is moved aside, and the push-notification key (airnotifier) is wiped
#      (reports/restore-outbound-audit.txt holds the counts of what could phone out).
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
# 1. Restore into an empty database, unpack moodledata, stamp both
# ---------------------------------------------------------------------------------------------------------------------
restore_database() {
    [ -f "$RESTORE_DB_DUMP" ] || die "RESTORE_DB_DUMP not found: ${RESTORE_DB_DUMP}"
    local bad
    log "scanning the dump for statements that reach another database, and for its trailer (one read of the whole file)"
    bad="$(dump_unsafe_statement "$RESTORE_DB_DUMP")"
    if [ -n "$bad" ]; then
        die "the dump holds a statement that must not be restored here (${bad}): refused. A USE / CREATE DATABASE / DROP DATABASE reaches another database (take the dump without --databases / --all-databases: mysqldump ${DB_NAME} > dump.sql), and SET @@GLOBAL.GTID_PURGED is a server-wide setting (add --set-gtid-purged=OFF). Take the dump again, then restore"
    fi
    local collation server
    collation="$(dump_mysql8_collation "$RESTORE_DB_DUMP")"
    if [ -n "$collation" ]; then
        server="$(mysql_nodb -e 'SELECT VERSION()' 2> /dev/null || true)"
        case "$server" in
            *[Mm]aria[Dd][Bb]*) die "the dump names the MySQL 8 collation ${collation}, and the server is MariaDB (${server}), which does not know it: the restore would fail at the first table, after the whole dump was read. Restore it on MySQL 8.x, or take the dump with a collation MariaDB has" ;;
        esac
    fi
    if dump_has_trailer "$RESTORE_DB_DUMP"; then
        log "OK: the dump ends with mysqldump's '-- Dump completed' line, and holds no USE / CREATE DATABASE / DROP DATABASE statement"
    elif [ "${RESTORE_ALLOW_NO_TRAILER:-0}" = 1 ]; then
        warn "the dump has no '-- Dump completed' trailer; restoring it because RESTORE_ALLOW_NO_TRAILER=1 (make sure it is complete)"
    else
        die "the dump has no '-- Dump completed' trailer: the mysqldump that wrote it was aborted or the file is cut short, and it would restore as a silent partial copy. Take the dump again (or RESTORE_ALLOW_NO_TRAILER=1 for a dump made by another tool)"
    fi
    if [ "$DB_STATE" = absent ]; then
        timed "create database" mysql_nodb -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION}" \
            || die "cannot create database ${DB_NAME}: create it (empty, utf8mb4) and re-run"
    fi
    # pipefail: a gzip that fails half way must fail the restore, not leave a partial copy that looks restored.
    case "$RESTORE_DB_DUMP" in
        *.gz) timed "restore database" bash -c 'set -o pipefail; gzip -dc "$1" | "$2" --defaults-extra-file="$3" --one-database --max-allowed-packet=512M "$4"' _ \
                "$RESTORE_DB_DUMP" "$MYSQL_BIN" "$DB_CNF" "$DB_NAME" ;;
        *) timed "restore database" bash -c 'set -o pipefail; "$2" --defaults-extra-file="$3" --one-database --max-allowed-packet=512M "$4" < "$1"' _ \
                "$RESTORE_DB_DUMP" "$MYSQL_BIN" "$DB_CNF" "$DB_NAME" ;;
    esac || die "the database restore failed, and database ${DB_NAME} now holds a partial copy, which the kit never adopts (not even with RESTORE_DONE_BY_HAND). Drop it and create it empty (as a database administrator: DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION};), then either run step 01 again with RESTORE_DB_DUMP set (RESTORE_DONE_BY_HAND unset), or, to restore by hand: run step 01 once on the empty database with RESTORE_DB_DUMP unset, so that it archives the record of the failed restore (it stops with 'restore the live backup first'), restore the live backup into it, and run step 01 with RESTORE_DB_DUMP unset and RESTORE_DONE_BY_HAND=${DB_NAME}"
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

# by_hand_ack -> 0 when the operator named THIS database as restored by hand (RESTORE_DONE_BY_HAND=<database name>).
by_hand_ack() { [ -n "$RESTORE_DONE_BY_HAND" ] && [ "$RESTORE_DONE_BY_HAND" = "$DB_NAME" ]; }

# failed_kit_restore -> 0 when a restore this kit started in this work directory never completed (restore.started, no restore.complete).
failed_kit_restore() { [ -n "$(kv_get restore.started)" ] && [ -z "$(kv_get restore.complete)" ]; }

# restore_target -> the database this run is for, as restore.started_db records it for a restore the kit starts. The record of a failed
# restore describes ONE database (host, port and name): only that database seen absent or empty clears it.
restore_target() { printf 'host=%s port=%s name=%s' "$DB_HOST" "${DB_PORT:-default}" "$DB_NAME"; }

# confirm_database_gone: the record of a failed restore is cleared only on two reads that agree. probe_db (which asks a COUNT again
# when the client printed nothing, and never reads an empty answer as absent or empty) has just said absent or empty; it is run once
# more after a pause and has to say the same, or the record stays where it is.
confirm_database_gone() {
    local first_state="$DB_STATE" first_tables="$DB_TABLES"
    sleep 1
    probe_db
    if [ "$DB_STATE" != "$first_state" ] || [ "$DB_TABLES" != "$first_tables" ]; then
        die "two reads of database ${DB_NAME} disagree (${first_state}, ${first_tables} tables; then ${DB_STATE}, ${DB_TABLES} tables): the record of the failed restore ($(kv_get restore.started)) is NOT cleared, because the partial copy may still be there. Run step 01 again once the server answers the same twice"
    fi
}

# start_new_restore: a new rehearsal begins. The earlier one's state moves to archive/, and the restore id is chosen.
RESTORE_ID=""
OLD_RESTORE_ID=""
BY_HAND=0
NEW_RESTORE=0
PAST_RESTORE=0
start_new_restore() {
    NEW_RESTORE=1
    OLD_RESTORE_ID="$(kv_get restore.id)"
    # How far did the earlier rehearsal get? A step after 01 ran code against the moodledata (hop 1 and 2, the repairs, the role-9
    # state file, caches, sessions), so such a dataroot is not the clean unpack of the archive any more. Read BEFORE the rotation. The
    # rotation then writes the same fact to restore-ids.log (restore_dataroot_used), which is what a retry of a restore that dies after
    # the rotation reads: its state/ no longer holds the status files.
    local f
    for f in "$STATE_DIR"/0[2-9].status "$STATE_DIR"/1[0-2].status; do
        if [ -f "$f" ]; then
            PAST_RESTORE=1
        fi
    done
    if work_state_has_history; then
        rotate_work_state
    fi
    RESTORE_ID="$(new_restore_id)"
    [[ "$RESTORE_ID" =~ ^[0-9a-f]{32}$ ]] || die "could not make a restore id"
}

# stamp_database: the restore of the database is complete (or the operator vouched for it): mark it, record the id.
stamp_database() {
    marker_set "$RESTORE_ID"
    # Every id this kit stamped in this work directory, in a file a new restore does not move to archive/. A moodledata that carries an
    # earlier id of this lineage is only the first condition for reusing it in a new restore (see the moodledata block below): it must also
    # be the finished unpack of the same archive, and no step after 01 may have run against it.
    mkdir -p "$REHEARSAL_WORK"
    printf '%s\n' "$RESTORE_ID" >> "$REHEARSAL_WORK/restore-ids.log"
    kv_set restore.id "$RESTORE_ID"
    kv_set restore.by_hand "$BY_HAND"
    kv_set restore.complete "$(ts)"
    log "OK: database ${DB_NAME} stamped as restore ${RESTORE_ID:0:8}... (state/kv/restore.id)"
}

# restored_cache_stores -> the cache store plugins the restored muc/config.php names, other than file, session and static.
restored_cache_stores() {
    local f="$MOODLEDATA/muc/config.php"
    if [ -f "$f" ]; then
        grep -o "'plugin' *=> *'[a-z0-9_]*'" "$f" | sed "s/.*=> *'\\(.*\\)'/\\1/" | LC_ALL=C sort -u | grep -Ev '^(file|session|static)$' || true
    fi
}

# RESTORE_DB_DUMP says "restore it for me", RESTORE_DONE_BY_HAND says "I restored it myself": both at once is a contradiction. The second
# one lives in rehearsal.env until somebody clears it, so left over from an earlier hand-restored rehearsal it would meet the next
# rehearsal's dump, and a kit restore that died part way (packet size, disk, a lost connection) could then be taken for a whole copy.
# Refused before anything else of the restore is decided (DRY too: the plan must not look fine).
if [ -n "$RESTORE_DONE_BY_HAND" ] && [ -n "$RESTORE_DB_DUMP" ]; then
    die "RESTORE_DONE_BY_HAND (=${RESTORE_DONE_BY_HAND}) and RESTORE_DB_DUMP (=${RESTORE_DB_DUMP}) are both set: the kit restores the dump, or you restored the database by hand, never both. Unset RESTORE_DONE_BY_HAND when the kit is to restore the dump (it is a statement about ONE database, and it stays in rehearsal.env until you clear it); unset RESTORE_DB_DUMP when you restored by hand"
fi

if [ "$EXECUTE" = 1 ]; then
    probe_db
    log "database ${DB_NAME}: ${DB_STATE} (${DB_TABLES} tables)"
    if failed_kit_restore && [ "$(kv_get restore.started_db)" != "$(restore_target)" ]; then
        # The record of a failed restore describes ONE database. A run for another one (a different name, host or port) must neither clear
        # it (the partial copy is still there) nor start a restore of its own, which would move it to archive/ with the rest of the state.
        die "the restore this kit started ($(kv_get restore.started)) did not complete, and it was into [$(kv_get restore.started_db)], not into this run's database [$(restore_target)]. The record is cleared only when THAT database is seen absent or empty, so this run leaves it alone (and a restore into another database would archive it). Use a work directory (REHEARSAL_WORK) of its own for this database, or set DB_HOST, DB_PORT and DB_NAME back to the recorded ones, drop that database, create it empty and run step 01 once (a record that names no database was made by an older kit: move state/kv/restore.started to archive/ by hand once you have checked that database)"
    fi
    case "$DB_STATE" in
        unreachable) die "the database server at ${DB_HOST} cannot be reached" ;;
        absent | empty)
            if failed_kit_restore; then
                # The kit itself sees THIS database (the one the record names, checked above) absent or empty, on two reads that agree: the
                # partial copy that the failed restore wrote is gone, so the record describes no database any more. It moves to archive/
                # now (not deleted), which is what lets a database restored by hand into this empty one be told from the partial copy.
                # Nothing else clears a failed restore.
                confirm_database_gone
                note "database ${DB_NAME} is ${DB_STATE} again (two reads agree) after the restore this kit started ($(kv_get restore.started)) did not complete: the partial copy is gone, and the record of the failed restore moves to archive/"
                rotate_work_state
            fi
            [ -n "$RESTORE_DB_DUMP" ] || die "database ${DB_NAME} is ${DB_STATE} and RESTORE_DB_DUMP is not set: restore the live backup first"
            start_new_restore
            # The database is recorded first: a record of a failed restore (restore.started) always names the database it was writing to.
            kv_set restore.started_db "$(restore_target)"
            kv_set restore.started "$(ts) dump=${RESTORE_DB_DUMP}"
            kv_unset restore.complete
            restore_database
            probe_db
            [ "$DB_STATE" = present ] || die "the restore ran but database ${DB_NAME} is still ${DB_STATE}"
            stamp_database
            ;;
        present)
            have="$(marker_get)"
            want="$(kv_get restore.id)"
            if [ -n "$have" ]; then
                if [ "$have" = "$want" ]; then
                    RESTORE_ID="$have"
                    log "database ${DB_NAME} already holds ${DB_TABLES} tables and carries this rehearsal's marker (restore ${RESTORE_ID:0:8}...): not restoring over it"
                elif [ -z "$want" ] && ! work_state_has_history; then
                    RESTORE_ID="$have"
                    kv_set restore.id "$RESTORE_ID"
                    log "database ${DB_NAME} carries restore ${RESTORE_ID:0:8}... and this work directory is new: adopting it"
                else
                    die "database ${DB_NAME} carries restore id ${have:0:8}..., but this work directory records ${want:-none}: it is another rehearsal's database. Use a work directory (REHEARSAL_WORK) of its own, or restore again into an empty database"
                fi
            elif failed_kit_restore; then
                # Checked BEFORE RESTORE_DONE_BY_HAND and never overridden by it: that variable lives in rehearsal.env until somebody clears
                # it, so it cannot tell a copy restored by hand after the failure from the partial copy the kit was writing. Only the kit
                # seeing the database absent or empty (above) clears the failed restore; a hand restore then follows.
                die "the restore this kit started ($(kv_get restore.started)) did not complete, and database ${DB_NAME} holds a partial copy of ${DB_TABLES} tables: it is refused whatever RESTORE_DONE_BY_HAND says (a database a kit restore was writing to is never adopted). Drop it and create it empty (as a database administrator: DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION};), then run step 01 again: with RESTORE_DB_DUMP set (and RESTORE_DONE_BY_HAND unset) the kit restores into the empty database. To restore by hand instead: run step 01 once on the empty database (it stops with 'restore the live backup first' and moves the record of the failed restore to archive/), restore the live backup into it, then run step 01 with RESTORE_DB_DUMP unset and RESTORE_DONE_BY_HAND=${DB_NAME}"
            elif by_hand_ack; then
                start_new_restore
                BY_HAND=1
                warn "database ${DB_NAME} was restored by hand (RESTORE_DONE_BY_HAND=${RESTORE_DONE_BY_HAND}): stamping it as this rehearsal's copy on that statement"
                stamp_database
            else
                die "database ${DB_NAME} holds ${DB_TABLES} tables and carries no rehearsal-kit marker: this kit did not restore it. If it is a copy of the live backup that you restored by hand for this rehearsal, set RESTORE_DONE_BY_HAND=${DB_NAME} for this run (and leave RESTORE_DB_DUMP unset); if you are not sure what it is, it may be a real site: stop"
            fi
            ;;
    esac

    # The moodledata: empty (or absent), this restore's own (marked), the unpack of an earlier restore that nothing has used since
    # (a NEW restore of the database over the same archive), or foreign (anything else: never written without an explicit
    # statement). A kit restore into an empty directory is the normal case.
    ARCHIVE_ID=""
    if [ -n "$RESTORE_MOODLEDATA_ARCHIVE" ]; then
        [ -f "$RESTORE_MOODLEDATA_ARCHIVE" ] || die "RESTORE_MOODLEDATA_ARCHIVE not found: ${RESTORE_MOODLEDATA_ARCHIVE}"
        ARCHIVE_ID="$(archive_identity "$RESTORE_MOODLEDATA_ARCHIVE")"
    fi
    md_state=empty
    if [ -d "$MOODLEDATA" ] && [ -n "$(ls -A "$MOODLEDATA" 2> /dev/null)" ]; then
        md_have="$(moodledata_marker_get)"
        if [ -z "$md_have" ]; then
            md_state=foreign
        elif [ "$md_have" = "${RESTORE_ID}" ]; then
            md_state=marked
        elif [ "$NEW_RESTORE" = 1 ]; then
            # A new restore found a dataroot stamped by an earlier one. It is reusable only if it is the very unpack the new restore
            # would make (same archive, finished) and nothing after step 01 has run against it: everything else an earlier
            # rehearsal left there (the role-9 state file, caches, sessions, the cron's files) would carry into this one.
            lineage=0
            if { [ -n "$OLD_RESTORE_ID" ] && [ "$md_have" = "$OLD_RESTORE_ID" ]; } \
                    || { [ -f "$REHEARSAL_WORK/restore-ids.log" ] && grep -qx "$md_have" "$REHEARSAL_WORK/restore-ids.log"; }; then
                lineage=1
            fi
            # Used by a step after 01: seen in state/ just now (PAST_RESTORE), or recorded when an earlier new restore rotated it away.
            if restore_dataroot_used "$md_have"; then
                PAST_RESTORE=1
            fi
            if [ "$lineage" = 1 ] && [ "$PAST_RESTORE" = 0 ] && [ -n "$ARCHIVE_ID" ] \
                    && [ "$(moodledata_unpack_state "$ARCHIVE_ID")" = match ]; then
                md_state=marked
                log "${MOODLEDATA} is the finished unpack of RESTORE_MOODLEDATA_ARCHIVE by an earlier restore of this lineage (${md_have:0:8}...), and no step after 01 has run against it: reusing it for the new restore"
            else
                die "${MOODLEDATA} carries restore id ${md_have:0:8}... and cannot be reused for a new restore: a new rehearsal needs an EMPTY moodledata (or a new directory), because the earlier rehearsal ran in this one (its state file of the role-9 script, caches, sessions and cron files would carry over). It is reusable only as the unfinished-restore retry of the same archive: the same unpack of RESTORE_MOODLEDATA_ARCHIVE, finished, with no step after 01 run (here: lineage ${lineage}, a later step ran ${PAST_RESTORE}, archive $([ -n "$ARCHIVE_ID" ] && moodledata_unpack_state "$ARCHIVE_ID" || printf 'not named')). Move it away and point MOODLEDATA at an empty directory"
            fi
        else
            die "${MOODLEDATA} carries restore id ${md_have:0:8}..., not this rehearsal's ${RESTORE_ID:0:8}...: it belongs to another rehearsal or site. Use a moodledata directory of its own"
        fi
    fi
    if [ "$md_state" = foreign ]; then
        # Its own statement, naming the directory: vouching for the DATABASE (RESTORE_DONE_BY_HAND) is not vouching for a populated
        # directory that a mistyped MOODLEDATA may have pointed at another site's dataroot (UAT's, on a shared box).
        if [ "$RESTORE_MOODLEDATA_BY_HAND" != "$MOODLEDATA" ]; then
            die "${MOODLEDATA} is not empty and carries no rehearsal-kit marker: it may be another site's dataroot (UAT's, for one). Use an empty directory, or, if it holds the live moodledata you restored for this rehearsal, set RESTORE_MOODLEDATA_BY_HAND=${MOODLEDATA} (the path itself) for this run"
        fi
        recent="$(moodledata_recent_writes)"
        if [ -n "$recent" ]; then
            die "${MOODLEDATA} has files written in the last 30 minutes in sessions/ or localcache/ (${recent}): a running site is using this dataroot, so it is not a restored copy. RESTORE_MOODLEDATA_BY_HAND does not override this: stop that site or use another directory"
        fi
        warn "${MOODLEDATA} was restored by hand (RESTORE_MOODLEDATA_BY_HAND names it, and sessions/ and localcache/ show no write in the last 30 minutes): stamping it as this rehearsal's"
    fi
    # Stamp it BEFORE anything is unpacked into it: a partial unpack then still reads as this rehearsal's, not as a foreign directory.
    # What an earlier unpack of this lineage recorded (the archive and the finished line) is kept only for a reused unpack.
    if [ "$(moodledata_marker_get)" != "$RESTORE_ID" ]; then
        mkdir -p "$MOODLEDATA"
        if [ "$md_state" = marked ]; then
            moodledata_restamp "$RESTORE_ID"
        else
            moodledata_write_marker "$RESTORE_ID"
        fi
        log "OK: ${MOODLEDATA} stamped with restore ${RESTORE_ID:0:8}... (${KIT_MARKER_FILE})"
    fi
    if [ ! -d "$MOODLEDATA/filedir" ] || [ -z "$(ls -A "$MOODLEDATA/filedir" 2> /dev/null)" ]; then
        if [ -n "$RESTORE_MOODLEDATA_ARCHIVE" ]; then
            # The archive is recorded BEFORE the unpack, so an unpack that stops half way is recognised as this archive's unfinished
            # unpack on the next run; the finished line is written after it, and the whole marker is written again then, because an
            # archive made from an earlier rehearsal's dataroot would have overwritten the marker file.
            moodledata_write_marker "$RESTORE_ID" "$ARCHIVE_ID"
            restore_moodledata
            moodledata_write_marker "$RESTORE_ID" "$ARCHIVE_ID" done
            log "OK: ${MOODLEDATA} unpacked from RESTORE_MOODLEDATA_ARCHIVE and recorded in ${KIT_MARKER_FILE}"
        else
            die "${MOODLEDATA}/filedir is missing or empty and RESTORE_MOODLEDATA_ARCHIVE is not set: unpack the live moodledata first"
        fi
    elif [ -n "$RESTORE_MOODLEDATA_ARCHIVE" ]; then
        # A filedir is already here AND an archive is named: never ignore it. It must be what this directory was unpacked from.
        case "$(moodledata_unpack_state "$ARCHIVE_ID")" in
            match)
                log "moodledata already holds the finished unpack of RESTORE_MOODLEDATA_ARCHIVE (same path, size and mtime): not restoring over it"
                ;;
            incomplete)
                die "the unpack of RESTORE_MOODLEDATA_ARCHIVE into ${MOODLEDATA} did not finish (${KIT_MARKER_FILE} has no 'unpacked' line), so ${MOODLEDATA}/filedir is partial: empty ${MOODLEDATA} (or use a new directory) and run step 01 again"
                ;;
            other)
                die "RESTORE_MOODLEDATA_ARCHIVE is $(basename "$RESTORE_MOODLEDATA_ARCHIVE") (${ARCHIVE_ID}), but ${MOODLEDATA}/filedir was unpacked from another archive ($(moodledata_marker_line 2)): the new archive would be ignored. Use an empty moodledata for it"
                ;;
            *)
                die "RESTORE_MOODLEDATA_ARCHIVE is set, but ${MOODLEDATA}/filedir already exists and this kit did not unpack it (it was restored by hand, or by an older kit): the archive would be silently ignored. Unset RESTORE_MOODLEDATA_ARCHIVE to use the directory as it is, or empty it to have the kit unpack the archive"
                ;;
        esac
    else
        log "moodledata already holds a filedir ($(find "$MOODLEDATA/filedir" -type f | wc -l | tr -d ' ') files): not restoring over it"
    fi
    require_kit_marker
else
    dry "would restore RESTORE_DB_DUMP (${RESTORE_DB_DUMP:-not set}) into the EMPTY database ${DB_NAME} (never over a populated one); the dump is refused when it holds USE / CREATE DATABASE / DROP DATABASE or has no '-- Dump completed' trailer"
    dry "would unpack RESTORE_MOODLEDATA_ARCHIVE (${RESTORE_MOODLEDATA_ARCHIVE:-not set}) into ${MOODLEDATA} when its filedir/ is missing"
    dry "would stamp the database (a {config} row) and ${MOODLEDATA} (${KIT_MARKER_FILE}) with a random restore id; a populated database without that marker is refused unless RESTORE_DONE_BY_HAND=${DB_NAME} (with RESTORE_DB_DUMP unset, and never for a restore the kit started and did not complete)"
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
    # Known before any hop: the activities the Moodle 5.0 upgrade deletes unless the package carries mod_survey and mod_chat. Step 04
    # refuses to start hop 2 while that is so (it checks the package then); this is the early sight of the count.
    if [ "$SOURCE_PHASE" = 1 ]; then
        for m in $HOP2_UNINSTALLS_MISSING_MODULES; do
            n="$(db_scalar "SELECT COUNT(*) FROM {p}course_modules cm JOIN {p}modules m ON m.id = cm.module WHERE m.name = '${m}'" || printf '?')"
            kv_set "restore.activities_${m}" "$n"
            if [ "${n:-0}" != 0 ] && [ "$n" != '?' ]; then
                note "this copy holds ${n} mod_${m} activit(ies): the Moodle 5.0 upgrade DELETES them (and their answers) unless the 5.x package carries a 5.x mod_${m}. Step 04 stops before hop 2 when it does not. Decide before the real run (ADR-032 FINDING)"
            fi
        done
    fi
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

# Other ways out of the box (counts only, never a value). Mail is not the only channel a restored backup can use: the push
# service key, a registration with moodle.net, OAuth2 system accounts and the cache stores all carry live's settings.
table_rows() {
    # table_rows TABLE -> its row count, or '-' when this release has no such table.
    if [ "$(db_scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}$1'")" = 1 ]; then
        db_scalar "SELECT COUNT(*) FROM {p}$1"
    else
        printf -- '-'
    fi
}
if [ "$EXECUTE" = 1 ]; then
    outbound="$REPORT_DIR/restore-outbound-audit.txt"
    if [ -f "$outbound" ]; then
        log "the outbound audit already exists (reports/restore-outbound-audit.txt): keeping the first one, it is the state before the wipe"
    else
        {
            printf 'Restored settings that could reach outside this box, taken before neutralisation (counts only)\n'
            printf 'taken: %s\n\n' "$(ts)"
            printf 'push service (airnotifier) access key set: %s\n' \
                "$(db_scalar "SELECT COUNT(*) FROM {p}config WHERE name = 'airnotifieraccesskey' AND value <> ''")"
            printf 'push devices registered (user_devices): %s\n' "$(table_rows user_devices)"
            printf 'site registrations with moodle.net (registration_hubs): %s\n' "$(table_rows registration_hubs)"
            printf 'OAuth2 system accounts (oauth2_system_account): %s\n' "$(table_rows oauth2_system_account)"
            printf 'external cache stores named in the restored muc/config.php: %s\n' "$(restored_cache_stores | tr '\n' ' ')"
        } > "$outbound"
        sed 's/^/    /' "$outbound" | sed -n '3,$p'
    fi
    db_write "UPDATE {p}config SET value = '' WHERE name = 'airnotifieraccesskey'"
    log "OK: the push service key is wiped (airnotifier is then not configured and sends nothing); step 11 disables the scheduled tasks that phone home (registration, update check, OAuth2 token refresh) for the cron cycle"
    # OAuth2 system accounts carry live's refresh tokens (a Microsoft or Google service account). Step 11 switches off the one task that
    # refreshes them, but other code that uses the system account (the OneDrive repository's clean-up, for one) would still send them to
    # the provider, so the stored tokens are blanked here, in the rehearsal database only, whenever the audit counted any.
    oauth_n="$(table_rows oauth2_system_account)"
    if [ "$oauth_n" != '-' ] && [ "${oauth_n:-0}" -gt 0 ]; then
        db_write "UPDATE {p}oauth2_system_account SET refreshtoken = ''"
        if [ "$(table_rows oauth2_access_token)" != '-' ]; then
            db_write "UPDATE {p}oauth2_access_token SET token = ''"
        fi
        log "OK: ${oauth_n} OAuth2 system account(s) found: their refresh tokens (and any stored access tokens) are blanked in this database, so nothing here can authenticate as live's service account"
        kv_set restore.oauth2_tokens_blanked "$oauth_n"
    fi
else
    dry "would write reports/restore-outbound-audit.txt (counts of what could reach outside) and wipe the airnotifier access key"
fi

# The restored cache configuration. Moodle reads dataroot/muc/config.php; a store named there (Redis, memcached) points at live's
# cache servers. The generated config.php sets altcacheconfigpath to the kit's own directory; the restored file is also moved aside.
if [ "$EXECUTE" = 1 ]; then
    cachecfg="$MOODLEDATA/muc/config.php"
    if [ -f "$cachecfg" ]; then
        stores="$(restored_cache_stores | tr '\n' ' ')"
        if [ -n "$stores" ]; then
            warn "the restored cache configuration names store(s) other than file/session/static: ${stores}. Moved aside; the rehearsal uses its own cache directory"
        fi
        prodhost="$(prod_host_in_text "$(cat "$cachecfg")")"
        if [ -n "$prodhost" ]; then
            warn "the restored cache configuration names a production host (${prodhost}). Moved aside"
        fi
        mv "$cachecfg" "$cachecfg.restored-from-live"
        log "OK: ${cachecfg} moved aside (muc/config.php.restored-from-live); the generated config.php sets altcacheconfigpath to $(cache_config_dir)"
    else
        log "no restored muc/config.php in ${MOODLEDATA}: nothing to move aside"
    fi
else
    dry "would move a restored ${MOODLEDATA}/muc/config.php aside (cache stores of live); the generated config.php sets altcacheconfigpath to $(cache_config_dir)"
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
