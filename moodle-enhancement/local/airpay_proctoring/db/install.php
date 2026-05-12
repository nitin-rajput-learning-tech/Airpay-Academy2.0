<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_proctoring_install(): void {
    global $DB, $CFG;
    require_once($CFG->libdir . '/upgradelib.php');
    update_capabilities('local/airpay_proctoring');

    $context = \context_system::instance();
    $rolemap = [
        'employee' => [
            ['local/airpay_proctoring:attempt', CAP_ALLOW],
        ],
        'administrator' => [
            ['local/airpay_proctoring:attempt',       CAP_ALLOW],
            ['local/airpay_proctoring:viewattempts',  CAP_ALLOW],
            ['local/airpay_proctoring:review',        CAP_ALLOW],
            ['local/airpay_proctoring:manage',        CAP_ALLOW],
            ['local/airpay_proctoring:bypass',        CAP_ALLOW],
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
