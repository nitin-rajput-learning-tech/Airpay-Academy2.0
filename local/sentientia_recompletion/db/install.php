<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_recompletion_install(): void {
    global $DB, $CFG;
    require_once($CFG->libdir . '/upgradelib.php');
    // update_capabilities() needs underscore form (Moodle quirk).
    update_capabilities('local_sentientia_recompletion');

    $context = \context_system::instance();
    // ADR-031 (2026-09-25): :view and :manage are tenant-scoped in code
    // (classes/rule_access.php), so the tenant-admin role keeps them. :reset
    // is NOT granted: nothing checks it yet, and a future bulk-reset page
    // must not inherit a tenant-admin grant before it is tenant-scoped.
    $rolemap = [
        'administrator' => [
            ['local/sentientia_recompletion:view',   CAP_ALLOW],
            ['local/sentientia_recompletion:manage', CAP_ALLOW],
        ],
    ];
    foreach ($rolemap as $shortname => $caps) {
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$role) continue;
        foreach ($caps as [$cap, $perm]) {
            if (!$DB->record_exists('capabilities', ['name' => $cap])) continue;
            assign_capability($cap, $perm, $role->id, $context->id, true);
        }
    }
}
