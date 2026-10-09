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
#      configuration to archive/ (nothing of it can be mistaken for this one's result).
#      THE IN-FLIGHT TABLE: a restore that did not finish is recorded IN THE DATABASE it was writing to, not in the work directory. After
#      the dump has been checked, and immediately before it is loaded, the kit creates the table zz_rehearsal_restore_inflight (restore id,
#      dump path, start time) in the target database; it drops it only when the restore is verified complete, just before it stamps the
#      database. A database that holds the table is a PARTIAL copy and is refused whatever RESTORE_DONE_BY_HAND says and whatever
#      REHEARSAL_WORK the run uses; only DROP DATABASE clears it (then create it empty). The CREATE TABLE is also the claim on an
#      empty database: a second restore into it (a second run, another work directory) finds the table, or finds the database no longer
#      empty, and stops instead of writing over the first. Nothing about an unfinished restore is kept in state/. The table is looked for by
#      its own error (1146 = absent; no error plus its answer = present; anything else = cannot tell = refused), never by one count; and
#      the earlier rehearsal's state/ moves to archive/ only AFTER the claim is taken, so a misread cannot strand it.
#      RESTORE_DONE_BY_HAND together with RESTORE_DB_DUMP is refused outright (a leftover statement must not meet a new dump).
#      A dump that names the in-flight table (taken from a partial copy) is refused with the other unsafe statements.
#      THE IN-FLIGHT FILE: the moodledata has the same record. Before the unpack of RESTORE_MOODLEDATA_ARCHIVE writes anything the kit creates
#      the file .rehearsal_unpack_inflight in MOODLEDATA (restore id, archive, start time) and removes it only when the unpack has returned
#      success. A MOODLEDATA that holds it, or whose marker file records an archive and no 'unpacked' line, is a PARTIAL copy: step 01 (before it
#      looks at the database), step 00 and steps 02 to 11 refuse it whatever RESTORE_MOODLEDATA_ARCHIVE (even unset), RESTORE_MOODLEDATA_BY_HAND
#      or REHEARSAL_WORK say; empty the directory and run step 01 again, or unpack by hand into an EMPTY directory and use
#      RESTORE_MOODLEDATA_BY_HAND.
#      THE MOODLEDATA is per rehearsal. The marker file also records which archive the kit unpacked into it (path, size, mtime) and
#      that the unpack finished. A NEW restore (a new database) accepts a non-empty moodledata only when it is the same unpack of the
#      same RESTORE_MOODLEDATA_ARCHIVE that no later step has used (that fact outlives the rotation of state/: restore-ids.log);
#      anything else (a dataroot an earlier rehearsal ran in, with its
#      role-9 state file, caches and sessions) is refused: empty it or point MOODLEDATA at a new directory. A named
#      RESTORE_MOODLEDATA_ARCHIVE is never ignored: if the moodledata already holds a filedir it must be that archive's unpack, or the
#      step stops. A populated moodledata the kit did not stamp needs its own statement, RESTORE_MOODLEDATA_BY_HAND=<its path>, and
#      must show no recent writes in sessions/ or localcache/.
#      THE ARCHIVE IS PROVEN WHOLE before the database is touched (archive_proof): RESTORE_MOODLEDATA_SHA256 (computed ON THE LIVE SERVER where the
#      archive was made, delivered with the backup: a sha256sum of the copy on this box matches a cut copy and proves nothing) is REQUIRED for an
#      uncompressed tar, whose last bytes cannot show that a zero-filled copy is whole, and is checked whenever it is set (also when filedir/ is
#      already there, and a checksum without RESTORE_MOODLEDATA_ARCHIVE is refused); a tar must END where a tar ends with or without the checksum
#      (the two zero blocks; for a plain tar also GNU tar's end of the archive within one record of the end of the file); a compressed tar is read
#      to its end; a zip is checked by unzip itself.
#      THE CONTENT OF filedir/ IS READ (section 3, filedir_hash_check): Moodle names every file by the SHA-1 of its content, so every file of the
#      file store must hash to its own name, whatever the archive, its checksum or the route the moodledata took (RESTORE_FILEDIR_HASH_CHECK=0 skips
#      it with a warning; the cost is one full read of filedir/). The proof is recorded in state/kv (restore.filedir_hash_proof) and the summary.
#   2. Check the source: the release matches SOURCE_RELEASE_REGEX (live is 4.1.x), active users (optionally equal to
#      EXPECT_ACTIVE_USERS), the BizLMS open_path substrate is there. A release past the source (4.5 or 5.x) is accepted on a re-run when hop 1 is
#      done, or when this restore passed the gate before a hop moved the release (state/kv/release.source): a hop 1 that failed after the release
#      moved must leave step 01 able to pass again, so that step 03 can be run again.
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
#   6. The very last act, only when everything above passed: record that step 01 FINISHED for this restore ({config} row
#      rehearsal_kit_step01_ok and state/kv/restore.verified). The copy is stamped early (right after the restore) but cleared only here: while
#      step 01 runs, and after a step 01 that failed or was killed, steps 02 to 11 refuse the copy (require_kit_marker), and the preflight
#      refuses a run that starts after step 01.

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
RESTORE_FILEDIR_HASH_CHECK="${RESTORE_FILEDIR_HASH_CHECK:-1}"
case "$RESTORE_FILEDIR_HASH_CHECK" in
    0 | 1) ;;
    *) die "RESTORE_FILEDIR_HASH_CHECK is '${RESTORE_FILEDIR_HASH_CHECK}': it is 1 (read every file of filedir/ and compare its SHA-1 with its name: the default) or 0 (skip it, with a warning)" ;;
esac

need_tool "$MYSQL_BIN"
need_tool "$PHP_BIN"

# ---------------------------------------------------------------------------------------------------------------------
# 1. Restore into an empty database, unpack moodledata, stamp both
# ---------------------------------------------------------------------------------------------------------------------
DUMP_ID=""
restore_database() {
    [ -f "$RESTORE_DB_DUMP" ] || die "RESTORE_DB_DUMP not found: ${RESTORE_DB_DUMP}"
    # The identity of the dump (path, size, mtime) BEFORE it is read. The scan, the trailer check and the load each open the file by its path,
    # 49 minutes apart on a big dump, and nothing tied the three reads together: a dump that was re-written, re-synced (OneDrive) or cut in
    # between could load with the client's exit status 0 when the cut falls on a statement boundary, and be stamped. It is read again after
    # the load; a change keeps the in-flight table. It is also what the stamp records (restore.dump), so a later run that names ANOTHER
    # dump on this database is refused instead of silently ignored.
    DUMP_ID="$(archive_identity "$RESTORE_DB_DUMP")"
    local bad
    log "scanning the dump for statements that reach another database, and for its trailer (one read of the whole file)"
    bad="$(dump_unsafe_statement "$RESTORE_DB_DUMP")"
    if [ -n "$bad" ]; then
        die "the dump holds a statement that must not be restored here (${bad}): refused. A USE / CREATE DATABASE / DROP DATABASE reaches another database (take the dump without --databases / --all-databases: mysqldump ${DB_NAME} > dump.sql), SET @@GLOBAL.GTID_PURGED is a server-wide setting (add --set-gtid-purged=OFF), and a line that names ${KIT_INFLIGHT_TABLE} means the dump was taken from a database the kit was still restoring into (a partial copy): it would drop and re-create the kit's own claim in the middle of the load. Take the dump again (from a finished copy), then restore"
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
    # The dump is clean and the database exists: claim it (the in-flight table) BEFORE the restore writes anything. From here until the
    # restore is verified complete (see the flow below), the database holds the table, and a database that holds it is never adopted.
    inflight_begin "$RESTORE_ID" "$RESTORE_DB_DUMP"
    # The claim is taken, so this run really owns an empty database: only now does the earlier rehearsal's state move to archive/. Before
    # the claim, a run that misread a finished database as absent or empty would have stranded that rehearsal's state/ in archive/ and
    # then stopped at the claim.
    archive_earlier_rehearsal
    # pipefail: a gzip that fails half way must fail the restore, not leave a partial copy that looks restored.
    case "$RESTORE_DB_DUMP" in
        *.gz) timed "restore database" bash -c 'set -o pipefail; gzip -dc "$1" | "$2" --defaults-file="$3" --one-database --max-allowed-packet=512M "$4"' _ \
                "$RESTORE_DB_DUMP" "$MYSQL_BIN" "$DB_CNF" "$DB_NAME" ;;
        *) timed "restore database" bash -c 'set -o pipefail; "$2" --defaults-file="$3" --one-database --max-allowed-packet=512M "$4" < "$1"' _ \
                "$RESTORE_DB_DUMP" "$MYSQL_BIN" "$DB_CNF" "$DB_NAME" ;;
    esac || die "the database restore failed, and database ${DB_NAME} now holds a PARTIAL copy and the in-flight table ${KIT_INFLIGHT_TABLE}: the kit never adopts it, not even with RESTORE_DONE_BY_HAND, whatever REHEARSAL_WORK a later run uses. Only DROP DATABASE clears it. Drop it and create it empty (as a database administrator: DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION};), then either run step 01 again with RESTORE_DB_DUMP set (RESTORE_DONE_BY_HAND unset), or restore by hand into the empty database and run step 01 with RESTORE_DB_DUMP unset and RESTORE_DONE_BY_HAND=${DB_NAME}"
    # The load returned success. The file it read must still be the file that was scanned.
    if [ "$(archive_identity "$RESTORE_DB_DUMP")" != "$DUMP_ID" ]; then
        die "the dump ${RESTORE_DB_DUMP} changed while it was scanned and loaded (it was ${DUMP_ID}, it is now $(archive_identity "$RESTORE_DB_DUMP")): what was loaded may not be the file that was checked, and a dump that is cut on a statement boundary loads with exit status 0. The load is NOT verified: database ${DB_NAME} keeps the in-flight table ${KIT_INFLIGHT_TABLE} and is refused, whatever RESTORE_DONE_BY_HAND says, until it is dropped and created empty (as a database administrator: DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION};). Then make the dump stable (copy it somewhere nothing syncs or rewrites) and run step 01 again"
    fi
}

# archive_sha_check: RESTORE_MOODLEDATA_ARCHIVE must have the SHA-256 in RESTORE_MOODLEDATA_SHA256 (sets ARCHIVE_PROVEN=sha256). Asked whenever the
# checksum is set: before an unpack, and also when filedir/ is already there (a re-run, or a new restore that reuses the unpack): a checksum that is
# set and silently not looked at proves nothing (round 7 review).
# WHERE THE CHECKSUM COMES FROM is what it is worth (round 7 review, must-fix): a sha256sum of the copy ON THIS BOX matches a cut copy just as
# well as a whole one (a pre-allocated or segmented download that stopped part way hashes to itself) and proves nothing. It must have been computed
# ON THE LIVE SERVER, right after the archive was written and before it was copied, and delivered with the backup. The kit cannot tell where a
# checksum was taken; what it can do, whatever the checksum's source, is step 01's filedir check below (every file of filedir/ must hash to its
# own name).
archive_sha_check() {
    local got want="${RESTORE_MOODLEDATA_SHA256,,}"
    got="$(sha256_of "$RESTORE_MOODLEDATA_ARCHIVE")"
    [ "$got" = "$want" ] || die "RESTORE_MOODLEDATA_ARCHIVE ${RESTORE_MOODLEDATA_ARCHIVE} has SHA-256 ${got}, not the RESTORE_MOODLEDATA_SHA256 ${want}: it is not the archive that checksum was taken from (cut, changed or another file). Refused; nothing was restored or unpacked. Copy the archive again, or correct RESTORE_MOODLEDATA_SHA256 (the SHA-256 computed on the live server, where the archive was made)"
    log "OK: RESTORE_MOODLEDATA_ARCHIVE has the SHA-256 in RESTORE_MOODLEDATA_SHA256 (${got:0:12}...)"
    ARCHIVE_PROVEN="sha256"
}

# archive_end_check KIND: the tar in RESTORE_MOODLEDATA_ARCHIVE ENDS where a tar ends (round 8, S3). KIND is archive_kind's answer (not zip).
#   * every kind: the tar, as it is once uncompressed, finishes with the two zero blocks that end every tar (tar_ends_complete; a compressed
#     stream is read to its end for it, which is also the decompressor's own CRC and length check);
#   * a plain tar: and the end of the archive that GNU tar finds is within one record of the end of the file (tar_end_near_file_end), which a
#     zero-filled region in the middle of a full-size copy is not.
# Asked whether or not RESTORE_MOODLEDATA_SHA256 is set: the checksum shows this copy is the file that was made, and cannot show that the file
# that was made is a whole tar (a live backup job whose tar died while it wrote into gzip, with the checksum taken afterwards, is a valid .tar.gz
# around a cut tar with a matching checksum).
archive_end_check() {
    local kind="$1"
    need_tool "$kind"
    tar_ends_complete "$kind" "$RESTORE_MOODLEDATA_ARCHIVE" \
        || die "RESTORE_MOODLEDATA_ARCHIVE ${RESTORE_MOODLEDATA_ARCHIVE} is not proven complete: its ${kind} stream is damaged, or the tar inside it does not end with the two zero blocks that end every tar. It was cut (a tar writer that died, a full disk, a copy still running). GNU tar extracts such a file with exit status 0 when the cut falls on a member header, and a compressor closes the stream of a tar that stopped early without complaint, so the unpack would look complete and be partial. Refused, whatever RESTORE_MOODLEDATA_SHA256 says (a checksum taken after the tar died matches the cut archive); nothing was restored or unpacked. Make or copy the archive again"
    if [ "$kind" = tar ]; then
        tar_end_near_file_end "$RESTORE_MOODLEDATA_ARCHIVE" \
            || die "RESTORE_MOODLEDATA_ARCHIVE ${RESTORE_MOODLEDATA_ARCHIVE} is not proven complete: GNU tar cannot list it to its end, or finds the end of the archive (block ${TAR_END_BLOCK:-?}) ${TAR_END_GAP:-?} bytes before the end of the file, more than one 10240-byte record: the file holds a zero-filled or padded region where members were (a pre-allocated or segmented copy that stopped part way keeps its size and ends in the zero blocks), so every member after it would be silently left out of the unpack. Refused, whatever RESTORE_MOODLEDATA_SHA256 says; nothing was restored or unpacked. Copy the archive again (an archive written with another blocking factor than tar's default 20 pads further: write it with the default)"
        log "OK: the tar ends with the two zero blocks, and GNU tar's end of the archive (block ${TAR_END_BLOCK}) is ${TAR_END_GAP} bytes from the end of the file"
    else
        log "OK: the ${kind} archive reads to its end and its tar ends with the two zero blocks"
    fi
}

# archive_proof [reuse]: an archive that is not proven COMPLETE is refused BEFORE the database is restored and before anything is unpacked (nothing is
# claimed, nothing is written). It sets ARCHIVE_PROVEN to the proof used (sha256, tar-end or format; step 01 records it as restore.moodledata_proof).
# GNU tar 1.35 extracts an uncompressed tar that was cut exactly at a member header with exit status 0 and no message, so a cut archive
# unpacked "successfully", the in-flight file went, the marker said 'unpacked', the filedir gate (which looks for the files {files} names, and
# only for those) passed when the cut fell after filedir/, and steps 02 to 11 ran on a moodledata with no lang packs or repository files
# (reproduced, round 6 review). A tar that is cut while it is written into a compressor is no better: the compressor closes its stream
# normally, so 'gzip -t' passes a valid .tar.gz that holds a cut tar. So:
#   * RESTORE_MOODLEDATA_SHA256 set (computed ON THE LIVE SERVER where the archive was made, see archive_sha_check; any format): the archive's
#     SHA-256 must equal it, AND a tar (any kind but a zip) must still end where a tar ends (archive_end_check): the checksum does not replace
#     the format check any more (round 8). The strong proof of the CONTENT of filedir/ is step 01's filedir check, which depends neither on the
#     archive nor on where its checksum came from;
#   * not set, an UNCOMPRESSED tar: REFUSED. Its last bytes cannot prove it is whole: a zero-filled region (a pre-allocated or segmented copy
#     that stopped, a file system that kept the size and lost the data) ends in zero blocks at any cut point, and GNU tar takes two zero blocks where a
#     header is due for the end of the archive and exits 0, so such a copy unpacks without a message and lacks every member after the region (round 7
#     review, reproduced). Only the checksum computed on the live server, where the archive was made, can show that every byte arrived;
#   * not set, a compressed tar (gzip, bzip2, xz or zstd): the compressed stream is read to its end (a cut or zero-filled stream fails the
#     decompressor's own CRC or length check) and the tar must END with its two zero blocks (archive_end_check);
#   * not set, a zip: unzip's own checks while it unpacks (the central directory at the end of the file, and a CRC-32 per member): a cut or
#     zero-filled zip fails there, loudly, with an exit status.
# 'reuse' (filedir/ is already there and the archive is not unpacked again): the checksum, when set, is still checked; a plain tar still needs it
# (it is the one format whose own content cannot vouch for it); the end of the tar is not read again (the unpack that is here was proven before it
# was made, and the archive's path, size and mtime are the record of which archive that was).
ARCHIVE_PROVEN=""
archive_proof() {
    local kind
    kind="$(archive_kind "$RESTORE_MOODLEDATA_ARCHIVE")"
    if [ -n "$RESTORE_MOODLEDATA_SHA256" ]; then
        archive_sha_check
        if [ "${1:-}" != reuse ] && [ "$kind" != zip ]; then
            archive_end_check "$kind"
        fi
        return 0
    fi
    case "$kind" in
        tar)
            die "RESTORE_MOODLEDATA_ARCHIVE ${RESTORE_MOODLEDATA_ARCHIVE} is an uncompressed tar and RESTORE_MOODLEDATA_SHA256 is not set, so it is not proven complete: nothing inside an uncompressed tar can show that every byte of it was written. GNU tar takes two zero blocks where a header is due for the end of the archive and exits 0, so a copy that stopped part way (a pre-allocated or segmented download, a file system that kept the size and lost the data) is full size, ends in zeros, and unpacks with exit status 0 and no message, without every member after the zero-filled region. Refused; nothing was restored or unpacked. Set RESTORE_MOODLEDATA_SHA256 to the SHA-256 COMPUTED ON THE LIVE SERVER, right after the archive was written there and before it was copied, and delivered with the backup (a sha256sum of the copy on this box matches a cut copy and proves nothing), or use a compressed archive (.tar.gz, .tar.xz, .tar.zst) or a .zip, whose own checks catch a zero-filled copy"
            ;;
    esac
    [ "${1:-}" != reuse ] || return 0
    case "$kind" in
        zip) ARCHIVE_PROVEN="format" ;;
        *)
            archive_end_check "$kind"
            ARCHIVE_PROVEN="tar-end"
            ;;
    esac
    if [ "$kind" = zip ]; then
        note "RESTORE_MOODLEDATA_SHA256 is not set: the zip is checked by unzip itself while it unpacks (its central directory and a CRC-32 per member), which fails on a cut or zero-filled zip. The SHA-256 computed on the live server, where the archive was made, is the strong proof that it is whole; set it for the real rehearsal"
    else
        note "RESTORE_MOODLEDATA_SHA256 is not set: the archive is checked only by its format (${kind}). The SHA-256 computed on the live server, where the archive was made, is the strong proof that it is whole; set it for the real rehearsal"
    fi
}

restore_moodledata() {
    [ -f "$RESTORE_MOODLEDATA_ARCHIVE" ] || die "RESTORE_MOODLEDATA_ARCHIVE not found: ${RESTORE_MOODLEDATA_ARCHIVE}"
    mkdir -p "$MOODLEDATA"
    # The unpack is UNFINISHED when it fails: ${KIT_UNPACK_INFLIGHT_FILE} (created before it wrote anything) stays in MOODLEDATA, which every kit
    # step then refuses, and the marker file has an 'archive=' line and no 'unpacked=' line.
    local unfinished="the unpack is UNFINISHED and ${MOODLEDATA} now holds a partial copy of the moodledata (${KIT_UNPACK_INFLIGHT_FILE} stays in it, so the kit never adopts it, whatever any setting says). Empty ${MOODLEDATA} (or point MOODLEDATA at a new, empty directory) and run step 01 again"
    if file_magic_pk "$RESTORE_MOODLEDATA_ARCHIVE"; then
        need_tool unzip
        timed "restore moodledata" unzip -q -o "$RESTORE_MOODLEDATA_ARCHIVE" -d "$MOODLEDATA" || die "unzip of the moodledata failed: ${unfinished}"
    else
        timed "restore moodledata" tar -C "$MOODLEDATA" -xf "$RESTORE_MOODLEDATA_ARCHIVE" || die "tar of the moodledata failed: ${unfinished}"
    fi
    # The tool returned success. The archive it read must still be the archive that was proven complete before it started.
    if [ "$(archive_identity "$RESTORE_MOODLEDATA_ARCHIVE")" != "$ARCHIVE_ID" ]; then
        die "RESTORE_MOODLEDATA_ARCHIVE changed while it was unpacked (it was ${ARCHIVE_ID}, it is now $(archive_identity "$RESTORE_MOODLEDATA_ARCHIVE")): what was unpacked may not be the archive that was checked (a file that is still being copied or re-synced, or one that grew). The unpack is NOT verified: ${unfinished}"
    fi
}

# by_hand_ack -> 0 when the operator named THIS database as restored by hand (RESTORE_DONE_BY_HAND=<database name>).
by_hand_ack() { [ -n "$RESTORE_DONE_BY_HAND" ] && [ "$RESTORE_DONE_BY_HAND" = "$DB_NAME" ]; }

# check_dump_record: this rehearsal's database is never restored over, so a RESTORE_DB_DUMP that is set is either the dump the database was
# restored from (stamp_database recorded its identity: state/kv/restore.dump) or a dump that would be silently IGNORED, the moodledata's
# rule for RESTORE_MOODLEDATA_ARCHIVE applied to the database. A run that names a new dump (new-live-backup.sql) on a stamped database must
# not log "not restoring over it" and carry on with the old copy.
check_dump_record() {
    [ -n "$RESTORE_DB_DUMP" ] || return 0
    [ -f "$RESTORE_DB_DUMP" ] || die "RESTORE_DB_DUMP not found: ${RESTORE_DB_DUMP}"
    local now rec how
    now="$(archive_identity "$RESTORE_DB_DUMP")"
    rec="$(kv_get restore.dump)"
    if [ "$now" != "$rec" ]; then
        if [ -n "$rec" ]; then
            how="was restored from another dump (${rec})"
        elif [ -n "$(kv_get restore.adopted)" ]; then
            how="was adopted by this work directory ($(kv_get restore.adopted): it already carried another run's marker, so this work directory never restored it from a dump and records none)"
        else
            how="was not restored from a dump by this kit (a hand restore, or an older kit)"
        fi
        die "RESTORE_DB_DUMP names ${RESTORE_DB_DUMP} (${now}), but database ${DB_NAME} is this rehearsal's copy and ${how}: the dump would be silently ignored. To restore it instead, drop database ${DB_NAME} and create it empty (as a database administrator): step 01 then restores into it and moves this rehearsal's state to archive/; to keep using this copy, unset RESTORE_DB_DUMP"
    fi
}

# start_new_restore: a new rehearsal begins. The restore id is chosen and how far the earlier rehearsal got is read; NOTHING is moved here.
# The earlier rehearsal's state moves to archive/ in archive_earlier_rehearsal, which is called only once this run owns the database: after
# the claim (restore_database, right after inflight_begin) or, for a hand restore, once the operator's statement is taken. A run that misreads a
# finished database as absent or empty (the client was seen to) must stop at the claim with the earlier rehearsal's state/ still in place:
# archiving it first stranded a finished rehearsal (the next plain re-run then refused its own database as "another rehearsal's").
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
    RESTORE_ID="$(new_restore_id)"
    [[ "$RESTORE_ID" =~ ^[0-9a-f]{32}$ ]] || die "could not make a restore id"
}

# archive_earlier_rehearsal: the earlier rehearsal's state, reports, baseline, cache configuration and timings move to archive/. Only after
# start_new_restore, and only once the database is this run's (claimed, or vouched for by hand).
archive_earlier_rehearsal() {
    if work_state_has_history; then
        rotate_work_state
    fi
}

# stamp_database: the restore of the database is complete (or the operator vouched for it): mark it, record the id.
# A kit restore replaces a marker row the dump may carry (marker_set). A HAND restore is stamped with a PLAIN INSERT, and only after it
# succeeded does the earlier rehearsal's state/ move to archive/: a marker row that exists (which the read that led here missed) is the
# server's error 1062, and then nothing is overwritten and nothing has been moved (round 6 review: one misread on this path used to archive
# a finished rehearsal's state and overwrite the database's marker with a new id).
stamp_database() {
    local rc=0
    if [ "$BY_HAND" = 1 ]; then
        marker_insert_new "$RESTORE_ID" || rc=$?
        case "$rc" in
            0) ;;
            1) die "database ${DB_NAME} already holds a marker row (the INSERT of the new restore id was refused with error 1062), although the reads that led here said it did not: another run stamped it, or a read was wrong. Nothing was overwritten, and state/ was not moved. Run step 01 again" ;;
            *) die "the rehearsal-kit marker could not be written to database ${DB_NAME}: it is not stamped, and state/ was not moved. Check that the database user may INSERT into ${DB_PREFIX}config, then run step 01 again" ;;
        esac
        archive_earlier_rehearsal
    else
        marker_set "$RESTORE_ID" \
            || die "the rehearsal-kit marker could not be written to database ${DB_NAME}: it is not stamped (nothing is recorded in state/). Check that the database user may INSERT into ${DB_PREFIX}config, then run step 01 again"
    fi
    # Every id this kit stamped in this work directory, in a file a new restore does not move to archive/. A moodledata that carries an
    # earlier id of this lineage is only the first condition for reusing it in a new restore (see the moodledata block below): it must also
    # be the finished unpack of the same archive, and no step after 01 may have run against it.
    mkdir -p "$REHEARSAL_WORK"
    printf '%s\n' "$RESTORE_ID" >> "$REHEARSAL_WORK/restore-ids.log"
    kv_set restore.id "$RESTORE_ID"
    kv_set restore.by_hand "$BY_HAND"
    if [ -n "$DUMP_ID" ]; then
        kv_set restore.dump "$DUMP_ID"
    fi
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
    # FIRST, before the database is even looked at: a moodledata that holds an unpack the kit started and did not finish is a PARTIAL copy of
    # the filedir. The fact is in the directory (the in-flight file; a marker with an 'archive=' line and no 'unpacked=' line), so it holds
    # whatever RESTORE_MOODLEDATA_ARCHIVE (even unset: the retry that adopted the cut filedir), RESTORE_MOODLEDATA_BY_HAND or REHEARSAL_WORK says.
    unfinished="$(moodledata_unfinished_unpack)"
    if [ -n "$unfinished" ]; then
        die "${MOODLEDATA} holds an unpack of the moodledata that did not finish (${unfinished}), so its filedir is a PARTIAL copy. $(unpack_unfinished_advice)"
    fi
    if [ -n "$RESTORE_MOODLEDATA_SHA256" ] && ! [[ "${RESTORE_MOODLEDATA_SHA256,,}" =~ ^[0-9a-f]{64}$ ]]; then
        die "RESTORE_MOODLEDATA_SHA256 (${RESTORE_MOODLEDATA_SHA256}) is not a SHA-256 (64 hexadecimal digits): the SHA-256 computed on the live server, where the archive was made"
    fi
    # The archive named for the moodledata is identified now (path, size, mtime: the unpack checks that it is still that file when it has
    # finished) and, when it is going to be unpacked, PROVEN WHOLE before the database is touched: a cut archive found after a 49-minute
    # database restore costs that restore (a tar cut at a member header unpacks with exit status 0, see archive_proof).
    # The checksum RESTORE_MOODLEDATA_SHA256 is never ignored: it is checked here whether the archive is about to be unpacked or filedir/ is
    # already there (a re-run, or a new restore that reuses the finished unpack), and a checksum with no archive to check is refused like any
    # other setting that would be silently ignored (round 7 review).
    ARCHIVE_ID=""
    if [ -n "$RESTORE_MOODLEDATA_ARCHIVE" ]; then
        [ -f "$RESTORE_MOODLEDATA_ARCHIVE" ] || die "RESTORE_MOODLEDATA_ARCHIVE not found: ${RESTORE_MOODLEDATA_ARCHIVE}"
        ARCHIVE_ID="$(archive_identity "$RESTORE_MOODLEDATA_ARCHIVE")"
        if [ ! -d "$MOODLEDATA/filedir" ] || [ -z "$(ls -A "$MOODLEDATA/filedir" 2> /dev/null)" ]; then
            archive_proof
        else
            archive_proof reuse
        fi
    elif [ -n "$RESTORE_MOODLEDATA_SHA256" ]; then
        die "RESTORE_MOODLEDATA_SHA256 is set and RESTORE_MOODLEDATA_ARCHIVE is not: there is no archive for the checksum to prove, and a checksum that is silently ignored proves nothing. Set RESTORE_MOODLEDATA_ARCHIVE (the archive that checksum was taken from) or unset RESTORE_MOODLEDATA_SHA256"
    fi
    probe_db
    log "database ${DB_NAME}: ${DB_STATE} (${DB_TABLES} tables)"
    case "$DB_STATE" in
        unreachable) die "the database server at ${DB_HOST} cannot be reached" ;;
        absent | empty)
            [ -n "$RESTORE_DB_DUMP" ] || die "database ${DB_NAME} is ${DB_STATE} and RESTORE_DB_DUMP is not set: restore the live backup first (by hand, then run step 01 again with RESTORE_DONE_BY_HAND=${DB_NAME}, or set RESTORE_DB_DUMP for the kit to restore it)"
            start_new_restore
            restore_database
            probe_db
            # One wrong answer must not refuse a complete copy (the only way out is DROP DATABASE and a 49-minute restore): a state or a
            # table count that says 'nothing there' is looked at once more before it is believed.
            if [ "$DB_STATE" != present ] || [ "$DB_TABLES" -le 1 ]; then
                sleep 1
                probe_db
            fi
            # The in-flight table is in the count: the restore wrote something only when there is more than that one table, and it is
            # complete enough to stamp only when it brought the {config} table the stamp is written to.
            if [ "$DB_STATE" != present ] || [ "$DB_TABLES" -le 1 ]; then
                die "the restore ran but database ${DB_NAME} holds no table besides ${KIT_INFLIGHT_TABLE} (${DB_STATE}, ${DB_TABLES} tables, read twice): the database keeps the in-flight table and is refused until it is dropped and created empty"
            fi
            cfg_n="$(db_scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}config'" || true)"
            if [ "$cfg_n" != 1 ]; then
                sleep 1
                cfg_n="$(db_scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}config'" || true)"
            fi
            [ "$cfg_n" = 1 ] \
                || die "the restore ran but database ${DB_NAME} has no ${DB_PREFIX}config table (is DB_PREFIX the prefix of the dump?): it is not a Moodle copy the kit can stamp. The database keeps the in-flight table and is refused until it is dropped and created empty"
            # Verified complete: the dump was checked whole before the load, the load returned no error (pipefail), and the tables are there.
            inflight_end
            stamp_database
            ;;
        present)
            # FIRST, before the marker or any statement of the operator: a database that holds the in-flight table is the partial copy of a
            # restore the kit started and did not finish. The fact is in the database, so it holds for any REHEARSAL_WORK, any env file and
            # any RESTORE_DONE_BY_HAND; only DROP DATABASE clears it. It is read from the table itself (the server's error 1146 is the only
            # "absent"); an answer that cannot be told is "cannot tell", never "no table".
            inflight="$(inflight_count)" \
                || die "database ${DB_NAME} holds ${DB_TABLES} tables and whether it holds its in-flight table ${KIT_INFLIGHT_TABLE} could not be read (the client answered nothing, or with an error other than 'table does not exist'): it is treated as a partial restore until it can be read. Run step 01 again once the server answers"
            if [ "$inflight" = 1 ]; then
                die "database ${DB_NAME} holds the table ${KIT_INFLIGHT_TABLE}: a restore this kit started into it did not finish ($(inflight_describe)), so it holds a PARTIAL copy (${DB_TABLES} tables including that one). It is refused whatever RESTORE_DONE_BY_HAND says and whatever REHEARSAL_WORK this run uses, because the fact is in the database; only DROP DATABASE clears it. Drop it and create it empty (as a database administrator: DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION};), then run step 01 again: with RESTORE_DB_DUMP set (and RESTORE_DONE_BY_HAND unset) the kit restores into the empty database; to restore by hand instead, restore the live backup into it, then run step 01 with RESTORE_DB_DUMP unset and RESTORE_DONE_BY_HAND=${DB_NAME}"
            fi
            # The record of an older kit (rounds 3 and 4 wrote restore.started, and restore.complete only when the restore was verified): a work
            # directory that still holds the first without the second belongs to a restore that did not finish, and this database is its
            # partial copy (made before the in-flight table existed, so it carries none). Drop it and create it empty; the kit then starts a new
            # rehearsal (state/ moves to archive/ and the record goes with it).
            if [ -n "$(kv_get restore.started)" ] && [ -z "$(kv_get restore.complete)" ]; then
                die "state/kv/restore.started is set and restore.complete is not: an earlier kit recorded a restore into ${DB_NAME} that did not finish, so database ${DB_NAME} (${DB_TABLES} tables) is its PARTIAL copy, and it carries no in-flight table because that kit did not make one. Refused whatever RESTORE_DONE_BY_HAND says. Drop the database and create it empty (as a database administrator: DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE ${DB_COLLATION};), then run step 01 again (the kit starts a new rehearsal and moves this record to archive/)"
            fi
            # The marker is read with a verdict: a read that failed, or came back blank for a row that exists, is "cannot tell", never "no
            # marker". "No marker" moves a finished rehearsal's state/ to archive/ and writes a new id (the hand path below), so it needs more
            # than one read (marker_definitely_absent).
            have="$(marker_get)" \
                || die "database ${DB_NAME} holds ${DB_TABLES} tables and its rehearsal-kit marker could not be read (a failed read, or a blank answer for a row that exists): the kit cannot tell whether it is this rehearsal's copy. Nothing was moved, archived or stamped. Run step 01 again once the server answers"
            want="$(kv_get restore.id)"
            if [ -n "$have" ]; then
                if [ "$have" = "$want" ]; then
                    RESTORE_ID="$have"
                    check_dump_record
                    log "database ${DB_NAME} already holds ${DB_TABLES} tables and carries this rehearsal's marker (restore ${RESTORE_ID:0:8}...): not restoring over it"
                elif [ -z "$want" ] && ! work_state_has_history; then
                    RESTORE_ID="$have"
                    kv_set restore.id "$RESTORE_ID"
                    kv_set restore.adopted "$(ts)"
                    if [ -n "$RESTORE_DB_DUMP" ]; then
                        warn "RESTORE_DB_DUMP is set, but database ${DB_NAME} is already restored (it carries a marker): the dump is NOT used, and this work directory records no dump for the copy it adopts, so a later run that still names RESTORE_DB_DUMP is refused (a dump that would be silently ignored): unset RESTORE_DB_DUMP from now on"
                    fi
                    log "database ${DB_NAME} carries restore ${RESTORE_ID:0:8}... and this work directory is new: adopting it"
                else
                    die "database ${DB_NAME} carries restore id ${have:0:8}..., but this work directory records ${want:-none}: it is another rehearsal's database. Use a work directory (REHEARSAL_WORK) of its own, or restore again into an empty database"
                fi
            elif by_hand_ack; then
                # "No marker" is believed here only after two more error-free reads that agree (and a {config} table that exists): this
                # branch writes a new id and (in stamp_database, after the id is written) moves the state of the rehearsal that has run.
                marker_definitely_absent \
                    || die "database ${DB_NAME} is named as restored by hand (RESTORE_DONE_BY_HAND), but the kit could not confirm that it carries NO rehearsal-kit marker (two reads a second apart, each answering without an error that the marker row count is 0, on an existing ${DB_PREFIX}config table): a read failed, a count was not 0, the reads disagreed, or there is no ${DB_PREFIX}config table (is DB_PREFIX the prefix of the copy?). Nothing was moved, archived or stamped. Run step 01 again once the server answers; RESTORE_DONE_BY_HAND stays in rehearsal.env until you clear it, and a rehearsal that already carries a marker never reaches this branch"
                start_new_restore
                BY_HAND=1
                warn "database ${DB_NAME} was restored by hand (RESTORE_DONE_BY_HAND=${RESTORE_DONE_BY_HAND}): stamping it as this rehearsal's copy on that statement"
                stamp_database
            else
                die "database ${DB_NAME} holds ${DB_TABLES} tables and carries no rehearsal-kit marker, and the kit cannot tell what it is (a copy of the live backup restored by hand, another rehearsal's database whose marker was lost, or a real site). If it is a copy of the live backup that you restored by hand for this rehearsal, set RESTORE_DONE_BY_HAND=${DB_NAME} for this run (and leave RESTORE_DB_DUMP unset); if you are not sure what it is, it may be a real site: stop"
            fi
            ;;
    esac

    # The moodledata: empty (or absent), this restore's own (marked), the unpack of an earlier restore that nothing has used since
    # (a NEW restore of the database over the same archive), or foreign (anything else: never written without an explicit
    # statement). A kit restore into an empty directory is the normal case.
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
            # THE IN-FLIGHT FILE comes first, before the unpack writes anything, and goes only when the unpack has returned success: a
            # directory that holds it is a partial copy and is refused by step 01 and by every step after it, whatever any setting says.
            # The archive is recorded in the marker file BEFORE the unpack too (the second record of the same fact: an 'archive=' line
            # and no 'unpacked=' line is refused as well); the finished line is written after it, and the whole marker is written again
            # then, because an archive made from an earlier rehearsal's dataroot would have overwritten the marker file.
            # An archive that is not proven whole is refused BEFORE the directory is claimed (see archive_proof: tar exits 0 on a tar cut at a
            # member header). It was proven at the top of this step; this is the same rule for a path that did not go through it.
            [ -n "$ARCHIVE_PROVEN" ] || archive_proof
            unpack_inflight_begin "$RESTORE_ID" "$ARCHIVE_ID"
            moodledata_write_marker "$RESTORE_ID" "$ARCHIVE_ID"
            restore_moodledata
            unpack_inflight_end
            moodledata_write_marker "$RESTORE_ID" "$ARCHIVE_ID" done
            # How the archive was shown to be whole is part of the record of the rehearsal (the summary prints it).
            kv_set restore.moodledata_proof "$ARCHIVE_PROVEN"
            if [ -n "$RESTORE_MOODLEDATA_SHA256" ]; then
                kv_set restore.moodledata_sha256 "${RESTORE_MOODLEDATA_SHA256,,}"
            fi
            log "OK: ${MOODLEDATA} unpacked from RESTORE_MOODLEDATA_ARCHIVE and recorded in ${KIT_MARKER_FILE}"
        else
            die "${MOODLEDATA}/filedir is missing or empty and RESTORE_MOODLEDATA_ARCHIVE is not set: unpack the live moodledata first"
        fi
    elif [ -n "$RESTORE_MOODLEDATA_ARCHIVE" ]; then
        # A filedir is already here AND an archive is named: never ignore it. It must be what this directory was unpacked from.
        case "$(moodledata_unpack_state "$ARCHIVE_ID")" in
            match)
                log "moodledata already holds the finished unpack of RESTORE_MOODLEDATA_ARCHIVE (same path, size and mtime): not restoring over it"
                # The proof is recorded again: a new restore that reuses the unpack starts with an empty state/kv, and the summary prints it.
                if [ "$ARCHIVE_PROVEN" = sha256 ]; then
                    kv_set restore.moodledata_proof "$ARCHIVE_PROVEN"
                    kv_set restore.moodledata_sha256 "${RESTORE_MOODLEDATA_SHA256,,}"
                elif [ -z "$(kv_get restore.moodledata_proof)" ]; then
                    kv_set restore.moodledata_proof "earlier-unpack"
                    note "RESTORE_MOODLEDATA_SHA256 is not set: the archive is not proven again; the unpack that is here is the finished unpack of this very archive (path, size and mtime), made by a run that proved it first"
                fi
                ;;
            incomplete)
                # Refused by the check at the top of this step already; kept as the second line of the same rule.
                die "the unpack of RESTORE_MOODLEDATA_ARCHIVE into ${MOODLEDATA} did not finish (${KIT_MARKER_FILE} has no 'unpacked' line), so ${MOODLEDATA}/filedir is partial: $(unpack_unfinished_advice)"
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
    require_kit_marker during-step-01
    # The copy is stamped, but NOT cleared: its file store gate and its neutralisation are still to come. From here to the last line of this step
    # it does not count as finished (the record of an earlier, finished run of this step is removed now), so a step 01 that fails or is killed
    # before the end leaves a copy that steps 02 to 11 refuse (round 7 review; STEP 01 FINISHED in lib/common.sh).
    step01_unverify
else
    dry "would restore RESTORE_DB_DUMP (${RESTORE_DB_DUMP:-not set}) into the EMPTY database ${DB_NAME} (never over a populated one); the dump is refused when it holds USE / CREATE DATABASE / DROP DATABASE or has no '-- Dump completed' trailer; the database holds the table ${KIT_INFLIGHT_TABLE} from just before the load until the restore is verified complete, and a database that holds it is refused (only DROP DATABASE clears it)"
    dry "would unpack RESTORE_MOODLEDATA_ARCHIVE (${RESTORE_MOODLEDATA_ARCHIVE:-not set}) into ${MOODLEDATA} when its filedir/ is missing; the file ${KIT_UNPACK_INFLIGHT_FILE} is in ${MOODLEDATA} from just before the unpack until it is verified complete, and a moodledata that holds it (or a marker with an archive line and no unpacked line) is a partial copy that every step refuses, whatever any setting says"
    dry "would stamp the database (a {config} row) and ${MOODLEDATA} (${KIT_MARKER_FILE}) with a random restore id; a populated database without that marker is refused unless RESTORE_DONE_BY_HAND=${DB_NAME} (with RESTORE_DB_DUMP unset, and never for a database that holds ${KIT_INFLIGHT_TABLE})"
    dry "would remove the record that step 01 finished ({config} ${KIT_STEP01_KEY}, state/kv/restore.verified) while it runs, and write it again as the last act of a step 01 that reached its end: steps 02 to 11 refuse a copy without it"
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
        # A release past the source is accepted when THIS restore already passed the gate before a hop moved it: hop 1 is done (step 03 ok), or
        # state/kv/release.source is set (round 8: only a step 01 that saw the source release writes it, and it moves to archive/ with a new
        # restore) and the release is one a hop of this kit leaves (4.5 or 5.x). The second case is a hop 1 that FAILED with the release already
        # moved (a plugin upgrade that failed after core set the release, or a parity check that exited 1 or 2 after hop 1): without it the full
        # re-run that the README invites ('run_all.sh --execute', step 01 first) removed step 01's records, died here, and locked the rehearsal out
        # for good: step 03 was refused for want of step 01, and step 01 could never pass again (round 7 review).
        if step_done_ok 03; then
            SOURCE_PHASE=0
            note "the release is past the source (hop 1 is done); the release gate was passed before the hop"
        elif [ -n "$(kv_get release.source)" ] && { [[ "$release" =~ $HOP1_RELEASE_REGEX ]] || [[ "$release" =~ $HOP2_RELEASE_REGEX ]]; }; then
            SOURCE_PHASE=0
            note "the release '${release}' is past the source, and this restore passed the release gate before a hop moved it (state/kv/release.source = '$(kv_get release.source)'): a re-run after a hop that did not finish; step 03 can be run again"
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
    # {files} says each content file's hash AND its size: a file that is on disk but shorter than {files}.filesize is a cut file (a cut
    # archive, or a hand unpack that stopped inside a file), which "the file exists" cannot see.
    db_q "SELECT DISTINCT contenthash, filesize FROM {p}files WHERE filesize > 0" > "$work/db.raw"
    expected_paths_sizes < "$work/db.raw" | LC_ALL=C sort -u > "$work/dbsz.txt"
    cut -f 1 "$work/dbsz.txt" | LC_ALL=C sort -u > "$work/db.txt"
    disk_paths_sizes "$MOODLEDATA/filedir" | LC_ALL=C sort -u > "$work/disksz.txt"
    cut -f 1 "$work/disksz.txt" | LC_ALL=C sort -u > "$work/disk.txt"
    comm_only_first "$work/db.txt" "$work/disk.txt" > "$REPORT_DIR/filedir-missing.txt"
    comm_only_second "$work/db.txt" "$work/disk.txt" > "$REPORT_DIR/filedir-extra.txt"
    filedir_wrong_sizes "$work/dbsz.txt" "$work/disksz.txt" > "$REPORT_DIR/filedir-wrongsize.txt"
    wrongsize="$(wc -l < "$REPORT_DIR/filedir-wrongsize.txt" | tr -d ' ')"
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
    kv_set filedir.wrong_size "$wrongsize"
    log "FILEDIR: on disk with a size other than {files}.filesize ${wrongsize} (reports/filedir-wrongsize.txt: path, size(s) in {files}, size on disk; a content hash that two {files} rows record with two sizes counts as wrong only when the file on disk matches neither)"
    if [ "$missing" -gt "$FILEDIR_MAX_MISSING" ]; then
        head -n 20 "$REPORT_DIR/filedir-missing.txt" | sed 's/^/    missing: /'
        die "${missing} content hash(es) of {files} are not on disk (allowed ${FILEDIR_MAX_MISSING}): the moodledata restore is incomplete. SCORM packages and certificate images would 404. Do not go on"
    fi
    if [ "$wrongsize" -gt "$FILEDIR_MAX_WRONGSIZE" ]; then
        head -n 20 "$REPORT_DIR/filedir-wrongsize.txt" | sed 's/^/    wrong size: /'
        die "${wrongsize} content file(s) on disk have a size other than {files}.filesize (allowed ${FILEDIR_MAX_WRONGSIZE}, FILEDIR_MAX_WRONGSIZE): the moodledata restore is cut inside a file (a cut archive, or an unpack that stopped half way). The list is reports/filedir-wrongsize.txt. Do not go on"
    fi
    [ "$dbhashes" -gt 0 ] || die "the database has no file content at all: a restore without files rows"
    log "OK: every content hash of {files} is on disk"
    # THE CONTENT. The gates above compare names and sizes, which a copy that kept every name and size and lost the data (a pre-allocated or
    # segmented download that stopped part way: zero-filled files of the right size) passes, and an archive checksum taken on THIS box matches such
    # a copy. Moodle names each file by the SHA-1 of its content, so every file of filedir/ is read and must hash to its own name (round 8): the
    # proof does not depend on the archive, on its checksum or on how the moodledata got here (unpacked by the kit, by hand, or reused).
    if [ "$RESTORE_FILEDIR_HASH_CHECK" = 0 ]; then
        warn "RESTORE_FILEDIR_HASH_CHECK=0: the CONTENT of filedir/ was NOT read, so a file that kept its name and size and lost its data (a zero-filled or damaged copy) is not found here; the summary says so. Leave the setting at 1 (its default) for a rehearsal that counts: the cost is one full read of filedir/"
        kv_set restore.filedir_hash_proof skipped
    else
        hrc=0
        filedir_hash_check "$MOODLEDATA/filedir" "$REPORT_DIR/filedir-hash" || hrc=$?
        kv_set restore.filedir_hash_files "$FILEDIR_HASH_FILES"
        kv_set restore.filedir_hash_bytes "$FILEDIR_HASH_BYTES"
        kv_set restore.filedir_hash_odd "$FILEDIR_HASH_ODD"
        if [ "$hrc" != 0 ]; then
            kv_set restore.filedir_hash_proof failed
            head -n 20 "$REPORT_DIR/filedir-hash-mismatch.txt" | sed 's/^/    content does not hash to its name: /'
            die "the content of filedir/ does not match its names: ${FILEDIR_HASH_BAD} file(s) hash to something other than their own name (the first 20 are listed above, all in reports/filedir-hash-mismatch.txt: path, SHA-1 of its content), ${FILEDIR_HASH_UNREAD} could not be read. The moodledata copy is damaged (zero-filled, cut or corrupted: a copy that stopped part way, a segmented download), whatever the checksum of its archive says. Do not go on. Empty ${MOODLEDATA} (the kit deletes nothing), get the moodledata archive again from the live server together with the SHA-256 computed THERE, and run step 01 again"
        fi
        if [ "$FILEDIR_HASH_ODD" -gt 0 ]; then
            head -n 5 "$REPORT_DIR/filedir-hash-odd.txt" | sed 's/^/    not a content hash: /'
            warn "${FILEDIR_HASH_ODD} name(s) under filedir/ are not a content hash (40 lowercase hexadecimal digits in two two-digit directories) and were not hashed (reports/filedir-hash-odd.txt)"
        fi
        kv_set restore.filedir_hash_proof sha1
        log "OK: the content of every one of the ${FILEDIR_HASH_FILES} files of filedir/ (${FILEDIR_HASH_BYTES} bytes) hashes to its own name"
    fi
else
    dry "would check the file store gate: every distinct files.contenthash (filesize > 0) exists at ${MOODLEDATA}/filedir/ab/cd/<hash>; missing > ${FILEDIR_MAX_MISSING:-0} stops the step; lists go to reports/"
    dry "would read EVERY file of ${MOODLEDATA}/filedir/ (one full read, in parallel) and require that the SHA-1 of its content is its own name (Moodle's naming): a zero-filled, cut or damaged copy that kept its names and sizes is found whatever any archive checksum says; RESTORE_FILEDIR_HASH_CHECK=0 skips it with a warning (now ${RESTORE_FILEDIR_HASH_CHECK})"
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

# The last act of a step 01 that reached its end: the copy is cleared (restored, stamped, file store complete, neutralised). Steps 02 to 11 accept a
# copy only with this record (and state/01.status ok): see STEP 01 FINISHED in lib/common.sh.
if [ "$EXECUTE" = 1 ]; then
    step01_verify "$(kv_get restore.id)"
fi
log "restore check done"
step_end
