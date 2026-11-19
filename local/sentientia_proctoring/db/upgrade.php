<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_proctoring.
 *
 * The plugin had no upgrade.php until B2: every earlier version left the schema as install.xml created it.
 *
 * @package local_sentientia_proctoring
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin's tables.
 *
 * @param int $oldversion the version installed before this upgrade
 * @return bool
 */
function xmldb_local_sentientia_proctoring_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026112002) {
        // B2 "Secure assessment mode" (ADR-038 D3 and D6, post-demo plan B2, 2026-11-20). The session keeps the policy
        // the learner accepted (level and auto-submit limit, fixed at the pre-start check), the violations the server
        // counted, the server time of the last counted violation (events within 2 seconds count once), the heartbeat
        // (a gap shows where the page stopped reporting) and when the server submitted the attempt itself.
        // Additive: every B1 session keeps NULL / 0 here and is scored exactly as before.
        $table = new xmldb_table('local_sentientia_proctor_sessions');
        $fields = [
            new xmldb_field('securitylevel', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'human_decision'),
            new xmldb_field('violationlimit', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'securitylevel'),
            new xmldb_field('violations', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'violationlimit'),
            new xmldb_field('lastviolation', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'violations'),
            new xmldb_field('lastheartbeat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastviolation'),
            new xmldb_field('timeautosubmitted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastheartbeat'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026112002, 'local', 'sentientia_proctoring');
    }

    return true;
}
