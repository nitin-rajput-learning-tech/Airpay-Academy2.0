<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_privacy.
 *
 * Two flags for the supplementary erasure provider (1.1.0, 2026-11-13, "Option A": no core edit). Both
 * default OFF. Both are read at GLOBAL scope only, by \local_sentientia_privacy\privacy\provider through
 * feature_flags::is_enabled_for(KEY, 0, 0): a tool_dataprivacy DELETE task runs as the admin and an EXPORT
 * task as the subject, so a per-user read could let one subject's erasure and export disagree. Customer and
 * tenant overrides are therefore ignored by design (an erasure duty is not tenant-optional): set them at
 * customer 0 / tenant 0 only.
 *
 * Turning either ON is Nitin's call and needs a recorded DPO retention decision first: it erases data
 * (security and audit log rows, learning records) that today survives only because of the core defects.
 *
 * @package local_sentientia_privacy
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — privacy / right to erasure ────────────
    'sentientia.privacy.erasure_completeness.enabled' => [
        'default'     => false,
        'description' => 'Privacy: recover contexts the stock data-request approval drops (contextlist truncation).
                          Moodle core stops reading a component\'s context list at the first gap left by duplicate
                          context ids, so tool_dataprivacy erasure (and export) silently skips the rest: log rows,
                          monthly statistics, quiz and SCORM activity completion. When ON, the Sentientia privacy
                          provider adds those contexts to its own list, so they pass the same DPO approval and data
                          registry checks as any other, and erases or exports them through the component that owns
                          them. Becomes a no-op by itself once Moodle core is fixed. Site-wide only (customer 0,
                          tenant 0). Default OFF: turning it ON deletes data that survives today only because of the
                          core bug, so it needs a recorded DPO retention decision first.',
    ],

    'sentientia.privacy.completion_gap.enabled' => [
        'default'     => false,
        'description' => 'Privacy: erase course completion records Moodle core never finds.
                          Core discovers a user\'s course completions only through completion criteria the user has
                          touched, so course_completions rows in courses without criteria (or with untouched
                          criteria) are never erased or exported. When ON, the Sentientia privacy provider adds
                          those course contexts to its list and erases or exports them through core_course, after
                          the same DPO approval and data registry checks. Site-wide only (customer 0, tenant 0).
                          Default OFF: turning it ON erases learning records stock Moodle keeps today, so it needs a
                          recorded DPO retention decision first.',
    ],

];
