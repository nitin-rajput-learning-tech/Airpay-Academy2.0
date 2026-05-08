<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_skills_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050800 — add per-skill level definitions table.
    // Closes Phase-A of the 2026-05-08 Tier-3 polish stretch.
    if ($oldversion < 2026050800) {
        $tbl = new xmldb_table('local_airpay_skill_levels');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('skillid',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('level',        XMLDB_TYPE_INTEGER, '2',   null, XMLDB_NOTNULL, null, '1');
            $tbl->add_field('label',        XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL);
            $tbl->add_field('description',  XMLDB_TYPE_TEXT);
            $tbl->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary',  XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_skill', XMLDB_KEY_FOREIGN, ['skillid'], 'local_airpay_skills', ['id']);
            $tbl->add_index('idx_skill_level', XMLDB_INDEX_UNIQUE, ['skillid', 'level']);
            $dbman->create_table($tbl);
        }
        upgrade_plugin_savepoint(true, 2026050800, 'local', 'airpay_skills');
    }

    return true;
}
