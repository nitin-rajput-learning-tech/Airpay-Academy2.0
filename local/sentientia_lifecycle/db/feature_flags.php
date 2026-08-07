<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_lifecycle.
 *
 * 2026-08-07 — one flag, default OFF:
 *
 *  1. sentientia.lifecycle.autoenrol.enabled — gates the joiner
 *     auto-enrolment observer. The pre-flag behaviour enrolled every
 *     new user into EVERY visible course with a future enddate,
 *     platform-wide and tenant-blind (KeKa JML investigation
 *     2026-08-05, work item 9). That heuristic is retired; the
 *     observer now targets courses carrying the configured mandatory
 *     tag, tenant-scoped — and does nothing at all until this flag
 *     is flipped. See ADR-029 for the mandatory-course definition.
 *
 * @package local_sentientia_lifecycle
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — employee lifecycle automation ──────────
    'sentientia.lifecycle.autoenrol.enabled' => [
        'default'     => false,
        'description' => 'Joiner auto-enrolment into mandatory courses.
                          Mandatory = visible course carrying the tag
                          configured in local_sentientia_lifecycle/
                          mandatory_tag (default "mandatory"), scoped to
                          the joiner\'s tenant via course open_path.
                          When OFF (default) new users are not
                          auto-enrolled in anything. Replaces the retired
                          enddate heuristic that enrolled joiners into
                          every dated course platform-wide (ADR-029).',
    ],

];
