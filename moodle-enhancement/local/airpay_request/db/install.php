<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_request_install(): void {
    global $DB;

    // NOTE: capabilities from db/access.php are NOT yet registered when
    // this install hook runs. They get loaded later by
    // upgrade_component_updated() in core upgradelib. Guard each
    // assign_capability() with a check, OR defer via upgrade hook.
    //
    // Cleanest pattern: pre-register the caps in mdl_capabilities table
    // ourselves via update_capabilities() before assigning.
    require_once($GLOBALS['CFG']->libdir . '/upgradelib.php');
    update_capabilities('local/airpay_request');

    $context = \context_system::instance();
    $rolemap = [
        'employee' => [
            ['local/airpay_request:request', CAP_ALLOW],
        ],
        'administrator' => [
            ['local/airpay_request:request',        CAP_ALLOW],
            ['local/airpay_request:approve',        CAP_ALLOW],
            ['local/airpay_request:viewall',        CAP_ALLOW],
            ['local/airpay_request:overrideroute',  CAP_ALLOW],
        ],
    ];
    foreach ($rolemap as $shortname => $caps) {
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$role) continue;
        foreach ($caps as [$cap, $perm]) {
            // Guard: only assign if the cap is actually registered.
            if (!$DB->record_exists('capabilities', ['name' => $cap])) {
                continue;
            }
            assign_capability($cap, $perm, $role->id, $context->id, true);
        }
    }
}
