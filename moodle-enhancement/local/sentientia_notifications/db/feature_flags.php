<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_notifications.
 *
 * 2026-09-26 (decision delegated by Nitin): the "Course not started",
 * "Streak at risk" and "New course available" rules had a LIMIT precedence
 * bug that made them select nobody, so they never sent. db/install.php seeds
 * all three ENABLED and the smart_alert provider defaults popup and email ON,
 * so fixing the bug alone would have started messaging the imported
 * production users on UAT at the next hourly run. The fix therefore ships
 * with sending behind this flag, default OFF.
 *
 * Resolution is performed by \local_sentientia_platform\feature_flags, per
 * tenant root (see \local_sentientia_notifications\rule_engine::smart_rules_roots()).
 *
 * @package local_sentientia_notifications
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.notifications.smart_rules.enabled' => [
        'default'     => false,
        'description' => 'Lets three notification rules send: course_not_started
                          ("Course not started"), streak_broken ("Streak at risk")
                          and new_course ("New course available"). They are seeded
                          enabled but never sent anything before 2026-09-26 because
                          of a LIMIT bug. When OFF they do nothing and log nothing.
                          When ON for a tenant (or globally), each hourly run
                          messages that tenant\'s matching users only, by popup and
                          email under the smart_alert defaults, at most batch_limit
                          rows per rule per run (new_course: per new course);
                          default 500, ceiling 5000. The same user and course are
                          not messaged twice within 24 hours. Other rule types are
                          not affected. Default OFF.',
    ],

];
