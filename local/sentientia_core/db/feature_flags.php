<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_core.
 *
 * Read by local_sentientia_platform\feature_flags::load_registry(): a key that is
 * not registered here (or in another plugin's db/feature_flags.php) makes ::set() throw.
 *
 * @package local_sentientia_core
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── ADR-032 legacy_logs: the imported admin log report ───────────────────
    'sentientia.legacy_logs.report.enabled' => [
        'default'     => false,
        'description' => 'Imported admin log report (ADR-032, legacy_logs). When ON,
                          Site administration > Plugins > Local plugins lists "Imported
                          admin log" and the page /local/sentientia_core/admin_log.php
                          works: a read-only list of the BizLMS admin log (course
                          insert, update and delete, from local_logs) and of the bulk
                          course upload errors (local_courseerrors) that the importer
                          copied into local_sentientia_admin_log. BizLMS had no screen
                          for either table, so the report is new: it needs the
                          local/sentientia_core:viewadminlog capability, which no role
                          holds by default, and a caller who is not cross-tenant sees
                          only rows whose actor sat inside their own tenant (a row with
                          no resolvable tenant is cross-tenant only). The descriptions
                          name the actor by first name. The importer itself does not
                          depend on this flag (it is CLI-gated). Do not turn it ON for a
                          customer before the page has been reviewed with screenshots.',
    ],

];
