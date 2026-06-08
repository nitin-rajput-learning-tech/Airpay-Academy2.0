<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_sentientia_classroom.
 *
 * @package   local_sentientia_classroom
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_sentientia_classroom_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ─── 2026050800: G-02 — sessions get title/trainerid/notes; new
    //                classroom_users roster table; attendance gets markedby/notes
    if ($oldversion < 2026050800) {

        // 1. Add new columns to local_sentientia_classroom_sessions.
        $sessions = new \xmldb_table('local_sentientia_classroom_sessions');

        $title_field = new \xmldb_field('title', XMLDB_TYPE_CHAR, '254', null, null, null, null, 'classroomid');
        if ($dbman->table_exists($sessions) && !$dbman->field_exists($sessions, $title_field)) {
            $dbman->add_field($sessions, $title_field);
        }

        $trainer_field = new \xmldb_field('trainerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'location');
        if ($dbman->table_exists($sessions) && !$dbman->field_exists($sessions, $trainer_field)) {
            $dbman->add_field($sessions, $trainer_field);
        }

        $notes_field = new \xmldb_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'trainerid');
        if ($dbman->table_exists($sessions) && !$dbman->field_exists($sessions, $notes_field)) {
            $dbman->add_field($sessions, $notes_field);
        }

        // Add the (classroomid, sessiondate) compound index.
        $sessions_idx = new \xmldb_index('idx_classroom_date', XMLDB_INDEX_NOTUNIQUE, ['classroomid', 'sessiondate']);
        if ($dbman->table_exists($sessions) && !$dbman->index_exists($sessions, $sessions_idx)) {
            $dbman->add_index($sessions, $sessions_idx);
        }

        // 2. Create the classroom_users roster table.
        $users = new \xmldb_table('local_sentientia_classroom_users');
        if (!$dbman->table_exists($users)) {
            $users->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $users->add_field('classroomid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $users->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $users->add_field('enrolledby',   XMLDB_TYPE_INTEGER, '10', null, null);
            $users->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $users->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $users->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $users->add_key('fk_classroom', XMLDB_KEY_FOREIGN, ['classroomid'], 'local_sentientia_classroom', ['id']);
            $users->add_key('fk_user',      XMLDB_KEY_FOREIGN, ['userid'],      'user',                   ['id']);
            // NB: no separate idx_user — the fk_user foreign key already
            // provides the per-userid index. Adding both collides per Moodle XMLDB rules.
            $users->add_index('idx_classroom_user', XMLDB_INDEX_UNIQUE, ['classroomid', 'userid']);
            $dbman->create_table($users);
        }

        // 3. Add markedby + notes columns to attendance.
        $att = new \xmldb_table('local_sentientia_classroom_attendance');

        $markedby_field = new \xmldb_field('markedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
        if ($dbman->table_exists($att) && !$dbman->field_exists($att, $markedby_field)) {
            $dbman->add_field($att, $markedby_field);
        }

        $notes_field2 = new \xmldb_field('notes', XMLDB_TYPE_CHAR, '254', null, null, null, null, 'markedby');
        if ($dbman->table_exists($att) && !$dbman->field_exists($att, $notes_field2)) {
            $dbman->add_field($att, $notes_field2);
        }

        // Attendance also lacked timemodified — add it for parity.
        $tmod_field = new \xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if ($dbman->table_exists($att) && !$dbman->field_exists($att, $tmod_field)) {
            $dbman->add_field($att, $tmod_field);
        }

        upgrade_plugin_savepoint(true, 2026050800, 'local', 'sentientia_classroom');
    }

    // ─── 2026051130: Phase 3 B.4 — waiting list. ───────────────────────
    // When capacity is reached, additional users go to a queue. When an
    // active enrolment cancels, the head of the queue auto-promotes.
    if ($oldversion < 2026051130) {

        $table = new \xmldb_table('local_sentientia_classroom_waitlist');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('classroomid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('position',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'userid');
        $table->add_field('status',       XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'waiting',
            'position');
        $table->add_field('reason',       XMLDB_TYPE_TEXT,    null, null, null, null, null,
            'status');
        $table->add_field('promoted_at',  XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'reason');
        $table->add_field('removed_at',   XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'promoted_at');
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_classroom', XMLDB_KEY_FOREIGN,
            ['classroomid'], 'local_sentientia_classroom', ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('idx_classroom_status', XMLDB_INDEX_NOTUNIQUE,
            ['classroomid', 'status']);
        $table->add_index('idx_user_status', XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051130, 'local', 'sentientia_classroom');
    }

    // ─── 2026051160: Phase 5 A.5 — locations table (Airpay-owned). ─────
    // Replaces the dropped BizLMS local_location plugin. Locations are
    // a property of classroom sessions, not a top-level concept.
    if ($oldversion < 2026051160) {
        $table = new \xmldb_table('local_airpay_locations');
        $table->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name',        XMLDB_TYPE_CHAR,    '200', null, XMLDB_NOTNULL);
        $table->add_field('city',        XMLDB_TYPE_CHAR,    '100', null, null, null, '');
        $table->add_field('address',     XMLDB_TYPE_TEXT,    null,  null, null, null, null,  'city');
        $table->add_field('capacity',    XMLDB_TYPE_INTEGER, '10', null, null, null, '0',    'address');
        $table->add_field('equipment',   XMLDB_TYPE_TEXT,    null,  null, null, null, null,  'capacity');
        $table->add_field('latitude',    XMLDB_TYPE_NUMBER,  '10', null, null, null, null,
            'equipment', null, '6');
        $table->add_field('longitude',   XMLDB_TYPE_NUMBER,  '10', null, null, null, null,
            'latitude',  null, '6');
        $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'longitude');
        $table->add_field('active',      XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_active',       XMLDB_INDEX_NOTUNIQUE, ['active']);
        $table->add_index('idx_costcenter',   XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
        $table->add_index('idx_city',         XMLDB_INDEX_NOTUNIQUE, ['city']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add locationid FK to existing classroom + session tables.
        foreach (['local_sentientia_classroom', 'local_sentientia_classroom_sessions'] as $tn) {
            $t = new \xmldb_table($tn);
            $f = new \xmldb_field('locationid', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
                'location');  // sits next to existing free-text 'location'
            if ($dbman->table_exists($t) && !$dbman->field_exists($t, $f)) {
                $dbman->add_field($t, $f);
            }
        }

        upgrade_plugin_savepoint(true, 2026051160, 'local', 'sentientia_classroom');
    }

    // 2026051500 — W1-7: virtual meeting + recording URL fields on sessions.
    //
    // For a remote-first workforce, every classroom session needs a join link
    // (Zoom/Teams/Webex/Meet) and, after the session, a recording link for
    // late joiners and replay. BizLMS shipped these as `messagelink` +
    // `recordinglink` — we use the clearer `meeting_url` + `recording_url`.
    if ($oldversion < 2026051500) {
        $table = new xmldb_table('local_sentientia_classroom_sessions');

        $field = new xmldb_field('meeting_url', XMLDB_TYPE_CHAR, '1024',
            null, null, null, null, 'notes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('recording_url', XMLDB_TYPE_CHAR, '1024',
            null, null, null, null, 'meeting_url');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026051500, 'local', 'sentientia_classroom');
    }

    // 2026051600 — P1 batch: time-bounded enrolment window on the classroom
    // itself (mirrors the start/end-date fields added to sentientia_learningpath
    // in commit 8df39b36f).
    //
    //   startdate INT NULLABLE — UNIX ts; classroom becomes enrollable
    //   enddate   INT NULLABLE — UNIX ts; classroom closes for new enrolments
    //
    // Empty form input (0) is stored as NULL so "WHERE enddate IS NULL"
    // cleanly means "no window".
    if ($oldversion < 2026051600) {
        $table = new xmldb_table('local_sentientia_classroom');

        $field = new xmldb_field('startdate', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'visible');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('enddate', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'startdate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'sentientia_classroom');
    }

    return true;
}
