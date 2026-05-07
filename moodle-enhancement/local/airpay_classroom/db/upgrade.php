<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_airpay_classroom.
 *
 * @package   local_airpay_classroom
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_airpay_classroom_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ─── 2026050800: G-02 — sessions get title/trainerid/notes; new
    //                classroom_users roster table; attendance gets markedby/notes
    if ($oldversion < 2026050800) {

        // 1. Add new columns to local_airpay_classroom_sessions.
        $sessions = new \xmldb_table('local_airpay_classroom_sessions');

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
        $users = new \xmldb_table('local_airpay_classroom_users');
        if (!$dbman->table_exists($users)) {
            $users->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $users->add_field('classroomid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $users->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $users->add_field('enrolledby',   XMLDB_TYPE_INTEGER, '10', null, null);
            $users->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $users->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $users->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $users->add_key('fk_classroom', XMLDB_KEY_FOREIGN, ['classroomid'], 'local_airpay_classroom', ['id']);
            $users->add_key('fk_user',      XMLDB_KEY_FOREIGN, ['userid'],      'user',                   ['id']);
            // NB: no separate idx_user — the fk_user foreign key already
            // provides the per-userid index. Adding both collides per Moodle XMLDB rules.
            $users->add_index('idx_classroom_user', XMLDB_INDEX_UNIQUE, ['classroomid', 'userid']);
            $dbman->create_table($users);
        }

        // 3. Add markedby + notes columns to attendance.
        $att = new \xmldb_table('local_airpay_classroom_attendance');

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

        upgrade_plugin_savepoint(true, 2026050800, 'local', 'airpay_classroom');
    }

    return true;
}
