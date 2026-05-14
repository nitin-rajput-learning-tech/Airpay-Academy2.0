<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings link — Phase A0 / A0.5.
 *
 * Registers the two airpay_core admin pages in Site Admin → Plugins →
 * Local plugins so they appear in the standard Moodle admin nav:
 *
 *   - The Switchboard  (Phase A0)  /local/airpay_core/admin/switchboard.php
 *   - Style Guide      (Phase A0.5) /local/airpay_core/admin/styleguide.php
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

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_core_styleguide',
        get_string('styleguide_pagetitle', 'local_airpay_core'),
        new moodle_url('/local/airpay_core/admin/styleguide.php'),
        'moodle/site:config'
    ));
}
