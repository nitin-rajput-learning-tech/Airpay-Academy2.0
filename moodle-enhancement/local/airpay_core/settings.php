<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings link — Phase A0 (2026-05-14).
 *
 * Adds the Switchboard to Site Admin → Plugins → Local plugins so it
 * appears in the standard Moodle admin nav alongside other plugin
 * pages. The Switchboard itself is at
 * /local/airpay_core/admin/switchboard.php — this file just registers
 * the link.
 *
 * @package local_airpay_core
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_core_switchboard',
        get_string('switchboard_pagetitle', 'local_airpay_core'),
        new moodle_url('/local/airpay_core/admin/switchboard.php'),
        'moodle/site:config'
    ));
}
