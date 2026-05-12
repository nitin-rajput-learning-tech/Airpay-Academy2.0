<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_recompletion_install(): void {
    global $DB, $CFG;
    require_once($CFG->libdir . '/upgradelib.php');
    // update_capabilities() needs underscore form (Moodle quirk).
    update_capabilities('local_airpay_recompletion');

    $context = \context_system::instance();
    $rolemap = [
        'administrator' => [
            ['local/airpay_recompletion:view',   CAP_ALLOW],
            ['local/airpay_recompletion:manage', CAP_ALLOW],
            ['local/airpay_recompletion:reset',  CAP_ALLOW],
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
