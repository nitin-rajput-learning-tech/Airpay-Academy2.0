<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_sentientia_talent.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_talent_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061600) {
        // Initial install path is handled by db/install.xml; this guard
        // keeps the upgrade function idempotent and ready for future
        // schema migrations. Each table is created only when missing so a
        // partial install can be reconciled by re-running the upgrade.

        // ── Career-path table ──────────────────────────────────────────
        $path = new xmldb_table('local_sentientia_talent_path');
        if (!$dbman->table_exists($path)) {
            $path->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $path->add_field('costcenterid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $path->add_field('name',            XMLDB_TYPE_CHAR,    '200', null, XMLDB_NOTNULL);
            $path->add_field('description',     XMLDB_TYPE_TEXT);
            $path->add_field('from_designation', XMLDB_TYPE_CHAR,   '200', null, XMLDB_NOTNULL);
            $path->add_field('to_designation',  XMLDB_TYPE_CHAR,    '200', null, XMLDB_NOTNULL);
            $path->add_field('sort_order',      XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $path->add_field('active',          XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $path->add_field('usermodified',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $path->add_field('timecreated',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $path->add_field('timemodified',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $path->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $path->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $path->add_index('idx_from_desig', XMLDB_INDEX_NOTUNIQUE, ['from_designation']);
            $path->add_index('idx_to_desig',   XMLDB_INDEX_NOTUNIQUE, ['to_designation']);
            $dbman->create_table($path);
        }

        // ── Succession table ───────────────────────────────────────────
        $succ = new xmldb_table('local_sentientia_talent_succ');
        if (!$dbman->table_exists($succ)) {
            $succ->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $succ->add_field('costcenterid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $succ->add_field('designation',   XMLDB_TYPE_CHAR,    '200', null, XMLDB_NOTNULL);
            $succ->add_field('incumbentid',   XMLDB_TYPE_INTEGER, '10');
            $succ->add_field('candidateid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $succ->add_field('readiness',     XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'developing');
            $succ->add_field('notes',         XMLDB_TYPE_TEXT);
            $succ->add_field('usermodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $succ->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $succ->add_field('timemodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $succ->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $succ->add_key('fk_candidate', XMLDB_KEY_FOREIGN, ['candidateid'], 'user', ['id']);
            $succ->add_index('idx_costcenter',      XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $succ->add_index('idx_designation',     XMLDB_INDEX_NOTUNIQUE, ['designation']);
            $succ->add_index('idx_desig_candidate', XMLDB_INDEX_UNIQUE, ['costcenterid', 'designation', 'candidateid']);
            $succ->add_index('idx_incumbent',       XMLDB_INDEX_NOTUNIQUE, ['incumbentid']);
            $dbman->create_table($succ);
        }

        // ── Opportunity table ──────────────────────────────────────────
        $opp = new xmldb_table('local_sentientia_talent_opp');
        if (!$dbman->table_exists($opp)) {
            $opp->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $opp->add_field('costcenterid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $opp->add_field('title',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
            $opp->add_field('description',   XMLDB_TYPE_TEXT);
            $opp->add_field('designation',   XMLDB_TYPE_CHAR,    '200');
            $opp->add_field('department',    XMLDB_TYPE_CHAR,    '200');
            $opp->add_field('postedby',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $opp->add_field('status',        XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'open');
            $opp->add_field('closes',        XMLDB_TYPE_INTEGER, '10');
            $opp->add_field('usermodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $opp->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $opp->add_field('timemodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $opp->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $opp->add_index('idx_costcenter',  XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $opp->add_index('idx_status',      XMLDB_INDEX_NOTUNIQUE, ['status']);
            $opp->add_index('idx_designation', XMLDB_INDEX_NOTUNIQUE, ['designation']);
            $dbman->create_table($opp);
        }

        // ── Expression-of-interest table ───────────────────────────────
        $int = new xmldb_table('local_sentientia_talent_int');
        if (!$dbman->table_exists($int)) {
            $int->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $int->add_field('costcenterid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $int->add_field('opportunityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $int->add_field('userid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $int->add_field('message',       XMLDB_TYPE_TEXT);
            $int->add_field('matchpct',      XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $int->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $int->add_field('timemodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $int->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $int->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $int->add_key('fk_opp', XMLDB_KEY_FOREIGN, ['opportunityid'], 'local_sentientia_talent_opp', ['id']);
            $int->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $int->add_index('idx_opp_user',   XMLDB_INDEX_UNIQUE, ['opportunityid', 'userid']);
            $dbman->create_table($int);
        }

        // ── Audit table ────────────────────────────────────────────────
        $audit = new xmldb_table('local_sentientia_talent_audit');
        if (!$dbman->table_exists($audit)) {
            $audit->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $audit->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $audit->add_field('action',       XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL);
            $audit->add_field('objecttable',  XMLDB_TYPE_CHAR,    '80', null, XMLDB_NOTNULL);
            $audit->add_field('objectid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $audit->add_field('targetuserid', XMLDB_TYPE_INTEGER, '10');
            $audit->add_field('detail',       XMLDB_TYPE_TEXT);
            $audit->add_field('changedby',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $audit->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $audit->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $audit->add_key('fk_changedby', XMLDB_KEY_FOREIGN, ['changedby'], 'user', ['id']);
            $audit->add_index('idx_costcenter',  XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $audit->add_index('idx_action',      XMLDB_INDEX_NOTUNIQUE, ['action']);
            $audit->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($audit);
        }

        upgrade_plugin_savepoint(true, 2026061600, 'local', 'sentientia_talent');
    }

    return true;
}
