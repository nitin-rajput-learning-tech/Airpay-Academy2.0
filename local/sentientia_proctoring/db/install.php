<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_proctoring_install(): void {
    global $DB, $CFG;
    require_once($CFG->libdir . '/upgradelib.php');
    // update_capabilities() needs underscore form (Moodle quirk —
    // slash form silently returns 0 caps).
    update_capabilities('local_sentientia_proctoring');

    $context = \context_system::instance();
    $rolemap = [
        'employee' => [
            ['local/sentientia_proctoring:attempt', CAP_ALLOW],
        ],
        'administrator' => [
            ['local/sentientia_proctoring:attempt',       CAP_ALLOW],
            ['local/sentientia_proctoring:viewattempts',  CAP_ALLOW],
            ['local/sentientia_proctoring:review',        CAP_ALLOW],
            ['local/sentientia_proctoring:manage',        CAP_ALLOW],
            ['local/sentientia_proctoring:bypass',        CAP_ALLOW],
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
