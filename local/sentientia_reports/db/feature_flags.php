<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_reports.
 *
 * Read by local_sentientia_platform\feature_flags::load_registry(): a key that is not registered here (or in
 * another plugin's db/feature_flags.php) makes ::set() throw. Both flags are default OFF (CLAUDE.md section 5):
 * they gate the two report surfaces that read the history the BizLMS import (ADR-032, users) copied into
 * local_sentientia_users_*. The importer never depends on them and never flips one.
 *
 * @package local_sentientia_reports
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // --- ADR-032 users: the earlier training records report ------------------------------------------------
    'sentientia.reports.training_transcript' => [
        'default'     => false,
        'description' => 'Earlier training records report (ADR-032, users). When ON, "Training Transcript" is
                          a report type: one row per record of the 2015-2016 transcript the BizLMS import copied
                          into local_sentientia_users_transcript (learner, employee id, training, type,
                          completion date, status as normalised and as loaded, score, hours). History only:
                          nothing here is added to any completion total. A scoped caller sees the rows of
                          learners inside their own organisation; the rows with no matched learner appear only on
                          an "All organisations" report, which only a cross-tenant caller can run. BizLMS never
                          showed this table, so the report is new: do not turn it ON for a customer before the
                          page has been reviewed with screenshots.',
    ],

    // --- ADR-032 users: imported login days in the User Activity report -------------------------------------
    'sentientia.reports.login_days' => [
        'default'     => false,
        'description' => 'Imported login days column (ADR-032, users). When ON, the User Activity report gains a
                          "Login days (imported)" column: how many days BizLMS recorded a web login for the user
                          (local_sentientia_users_logindays). It is a frozen count of history: Sentientia does
                          not write that table after cutover, so it never grows. The column is new: do not turn
                          it ON for a customer before the report has been reviewed with screenshots.',
    ],

];
