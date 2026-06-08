<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_challenge_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026050700) {
        // Phase 1 — first real schema. Create all 3 tables idempotently.

        // local_sentientia_challenge_challenges
        $tbl = new xmldb_table('local_sentientia_challenge_challenges');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('name',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
            $tbl->add_field('shortname',    XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL);
            $tbl->add_field('description',  XMLDB_TYPE_TEXT);
            $tbl->add_field('type',         XMLDB_TYPE_CHAR,    '40',  null, XMLDB_NOTNULL, null, 'course_completion');
            $tbl->add_field('targetcount',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '1');
            $tbl->add_field('courseids',    XMLDB_TYPE_TEXT);
            $tbl->add_field('pointsreward', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '100');
            $tbl->add_field('badge',        XMLDB_TYPE_CHAR,    '100');
            $tbl->add_field('cohortid',     XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('status',       XMLDB_TYPE_INTEGER, '2',   null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('startdate',    XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('enddate',      XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('open_path',    XMLDB_TYPE_CHAR,    '255');
            $tbl->add_field('createdby',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_creator', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            $tbl->add_index('idx_status',       XMLDB_INDEX_NOTUNIQUE, ['status']);
            $tbl->add_index('idx_costcenterid', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $tbl->add_index('idx_shortname',    XMLDB_INDEX_NOTUNIQUE, ['shortname']);
            $dbman->create_table($tbl);
        }

        // local_sentientia_challenge_attempts
        $tbl = new xmldb_table('local_sentientia_challenge_attempts');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',             XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('challengeid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('userid',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('status',         XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'enrolled');
            $tbl->add_field('progress',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('targetcount',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $tbl->add_field('pointsawarded',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('completiondate', XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('costcenterid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('timemodified',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_challenge', XMLDB_KEY_FOREIGN, ['challengeid'],
                'local_sentientia_challenge_challenges', ['id']);
            $tbl->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $tbl->add_index('idx_challenge_user', XMLDB_INDEX_UNIQUE,    ['challengeid', 'userid']);
            $tbl->add_index('idx_status',         XMLDB_INDEX_NOTUNIQUE, ['status']);
            $tbl->add_index('idx_costcenterid',   XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $dbman->create_table($tbl);
        }

        // local_sentientia_challenge_leaderboard
        $tbl = new xmldb_table('local_sentientia_challenge_leaderboard');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',                XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('challengeid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('userid',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('costcenterid',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('points',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('userrank',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('attemptscompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('lastrecomputed',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $tbl->add_index('idx_challenge_user',  XMLDB_INDEX_UNIQUE,    ['challengeid', 'userid']);
            $tbl->add_index('idx_challenge_pts',   XMLDB_INDEX_NOTUNIQUE, ['challengeid', 'points']);
            $tbl->add_index('idx_costcenter_pts',  XMLDB_INDEX_NOTUNIQUE, ['costcenterid', 'points']);
            $dbman->create_table($tbl);
        }

        upgrade_plugin_savepoint(true, 2026050700, 'local', 'sentientia_challenge');
    }

    return true;
}
