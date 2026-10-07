<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_recompletion.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_recompletion_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026092500) {
        // ADR-031 (2026-09-25): db/install.php granted :reset to the
        // tenant-admin role ("administrator", a manager-archetype role held at
        // system context). Nothing checks :reset yet (bulk_reset.php was never
        // built), so the grant is inert today - but a future bulk-reset page
        // would inherit it before it is tenant-scoped, and a completion reset
        // is irreversible compliance-record deletion. install.php no longer
        // grants it; revoke every existing grant (archetype and install
        // changes never revoke). Site admins are unaffected. :view and
        // :manage stay: rule_access now scopes them to the holder's tenant.
        $syscontext = \context_system::instance();
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap', ['cap' => 'local/sentientia_recompletion:reset']);
        foreach ($roleids as $roleid) {
            unassign_capability('local/sentientia_recompletion:reset', (int) $roleid, $syscontext->id);
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_recompletion');
    }

    if ($oldversion < 2026093001) {
        // ADR-032 (2026-09-30): the BizLMS import. Every change is guarded, so a site that already
        // has a column or the table is left alone (a fresh install runs install.xml, not this step).
        $rules = new xmldb_table('local_sentientia_recompletion_rules');
        $field = new xmldb_field('legacy_config', XMLDB_TYPE_TEXT, 'big', null, null, null, null, 'last_run_resets');
        if (!$dbman->field_exists($rules, $field)) {
            $dbman->add_field($rules, $field);
        }

        $history = new xmldb_table('local_sentientia_recompletion_history');
        $field = new xmldb_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'engine', 'timecreated');
        if (!$dbman->field_exists($history, $field)) {
            $dbman->add_field($history, $field);
        }
        $field = new xmldb_field('time_inferred', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'source');
        if (!$dbman->field_exists($history, $field)) {
            $dbman->add_field($history, $field);
        }

        $archive = new xmldb_table('local_sentientia_recompletion_archive');
        if (!$dbman->table_exists($archive)) {
            $archive->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $archive->add_field('historyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $archive->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $archive->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $archive->add_field('itemtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
            $archive->add_field('cmid', XMLDB_TYPE_INTEGER, '10');
            $archive->add_field('instanceid', XMLDB_TYPE_INTEGER, '10');
            $archive->add_field('parentid', XMLDB_TYPE_INTEGER, '10');
            $archive->add_field('itemkey', XMLDB_TYPE_CHAR, '255');
            $archive->add_field('state', XMLDB_TYPE_CHAR, '30');
            $archive->add_field('grade', XMLDB_TYPE_NUMBER, '10, 5');
            $archive->add_field('timeevent', XMLDB_TYPE_INTEGER, '10');
            $archive->add_field('payload', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
            $archive->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $archive->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $archive->add_key('fk_history', XMLDB_KEY_FOREIGN, ['historyid'],
                'local_sentientia_recompletion_history', ['id']);
            $archive->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $archive->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $archive->add_index('idx_userid_course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $archive->add_index('idx_parentid', XMLDB_INDEX_NOTUNIQUE, ['parentid']);
            $dbman->create_table($archive);
        }

        upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_recompletion');
    }

    return true;
}
