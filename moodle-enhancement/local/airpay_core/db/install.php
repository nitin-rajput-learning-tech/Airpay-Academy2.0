<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Post-install hook for local_airpay_core.
 *
 * Runs once on fresh install (NOT on upgrade — upgrade.php handles
 * existing installs). Seeds the Airpay customer-zero row in
 * `local_airpay_customer_brand` so the per-customer branding resolver
 * has a row to read instead of always falling through to the hard-coded
 * default.
 *
 * The same seed lives in `db/upgrade.php` savepoint 2026052201 for
 * existing installs that predate the customer_brand table. The two
 * paths produce identical rows — keep them in sync if you change either.
 *
 * @package local_airpay_core
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_core_install(): bool {
    global $DB;

    // Idempotent — uk_customer blocks duplicates anyway, but skip the
    // insert when a row already exists (e.g. a re-install after a
    // partial drop where the table survived).
    if (!$DB->record_exists('local_airpay_customer_brand', ['customerid' => 1])) {
        $now = time();
        $DB->insert_record('local_airpay_customer_brand', (object) [
            'customerid'       => 1,
            'name'             => 'Airpay Academy',
            'short_name'       => 'Academy',
            'theme_color'      => '#0066A7',
            'bg_color'         => '#F2F4FB',
            'icon_192_url'     => '/local/airpay_core/pix/customer/1/icon-192.png',
            'icon_512_url'     => '/local/airpay_core/pix/customer/1/icon-512.png',
            'start_url'        => '/my/dashboard.php?utm_source=pwa_install',
            'lang'             => 'en',
            'status_bar_style' => 'default',
            'categories'       => 'education,productivity',
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    return true;
}
