<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_request.
 *
 * @package   local_sentientia_request
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add local_sentientia_request.legacy_source, the column that marks a request imported from BizLMS
 * (ADR-032, request feature), exactly as db/install.xml declares it since 2026093001.
 *
 * NULL on every native row, 'bizlms' on an imported one. The cron jobs select only NULL rows and the request
 * lists show imported rows only with the sentientia.request.imported_history flag. No index: the column is
 * only ever tested alongside status, which is indexed.
 *
 * Safe to run on any site: a site installed from the new install.xml already has the column, and so does one
 * that ran the step before. No row is touched.
 *
 * Used by upgrade step 2026093001; a function so tests/imported_history_test.php can prove it without
 * replaying the upgrade (upgrade_plugin_savepoint() refuses a version the site already has).
 *
 * @param database_manager $dbman
 * @return bool True when the column was added, false when it was there already.
 */
function local_sentientia_request_ensure_legacy_source(database_manager $dbman): bool {
    $table = new xmldb_table('local_sentientia_request');
    $field = new xmldb_field('legacy_source', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'timemodified');
    if ($dbman->field_exists($table, $field)) {
        return false;
    }
    $dbman->add_field($table, $field);
    return true;
}

/**
 * Visual-pass finding 7 back-fill: let the authenticated-user role hold local/sentientia_request:approve.
 *
 * db/access.php now lists the `user` archetype for :approve, as it already did for :request. Moodle applies
 * archetype defaults only when a capability is first registered, so a site that already has the capability
 * never gives it to an existing role; this grants it explicitly, as a fresh install now would. The reason
 * is in db/access.php: the line manager that approver_routing picks is an ordinary employee on BizLMS data,
 * who held neither the capability nor, because message_send() checks the message provider's capability, the
 * request_pending notification.
 *
 * Idempotent, and it only fills a gap: a role that already has ANY setting for the capability at system
 * context (ALLOW already, or an administrator's PREVENT or PROHIBIT) is left as it is, so nothing is ever
 * downgraded or overridden. A capability that is not registered yet is skipped. Only roles of the `user`
 * archetype (the authenticated-user role) are touched, and only :approve: :viewall and :overrideroute stay
 * with the manager archetype and the tenant-admin role.
 *
 * Used by upgrade step 2026101501; a function so tests/approver_capability_test.php can prove it without
 * replaying the upgrade.
 *
 * @return int number of grants made (0 when every such role already had a setting)
 */
function local_sentientia_request_backfill_approve_cap(): int {
    global $DB;

    $cap = 'local/sentientia_request:approve';
    if (!$DB->record_exists('capabilities', ['name' => $cap])) {
        return 0;
    }

    $syscontext = \context_system::instance();
    $granted = 0;
    foreach ($DB->get_records('role', ['archetype' => 'user'], 'id ASC', 'id') as $role) {
        if ($DB->record_exists('role_capabilities',
                ['roleid' => $role->id, 'capability' => $cap, 'contextid' => $syscontext->id])) {
            continue;   // An explicit setting stands, whatever it is.
        }
        assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, false);
        $granted++;
    }
    $syscontext->mark_dirty();

    return $granted;
}
