<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook. Grants capabilities to Airpay custom roles
 * (`employee`, `administrator`) that aren't Moodle archetypes.
 */
function xmldb_local_airpay_cart_install(): void {
    global $DB;

    $context = \context_system::instance();

    $rolemap = [
        // shortname => list of [cap, permission]
        'employee' => [
            ['local/airpay_cart:view',     CAP_ALLOW],
            ['local/airpay_cart:purchase', CAP_ALLOW],
        ],
        'administrator' => [
            ['local/airpay_cart:view',          CAP_ALLOW],
            ['local/airpay_cart:purchase',      CAP_ALLOW],
            ['local/airpay_cart:viewallorders', CAP_ALLOW],
            ['local/airpay_cart:refund',        CAP_ALLOW],
            ['local/airpay_cart:manageprices',  CAP_ALLOW],
        ],
    ];

    foreach ($rolemap as $shortname => $caps) {
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$role) {
            continue;  // role doesn't exist on this install (e.g. fresh moodle)
        }
        foreach ($caps as [$cap, $permission]) {
            assign_capability($cap, $permission, $role->id, $context->id, true);
        }
    }
}
