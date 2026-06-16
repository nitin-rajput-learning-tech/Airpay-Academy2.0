<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_talent.
 *
 * Discovered + merged by \local_sentientia_platform\feature_flags. The
 * master flag defaults OFF per CLAUDE.md §13 — no talent-mobility UI,
 * navigation, or web service responds until an admin flips it ON in the
 * Switchboard (per-customer + per-tenant overridable).
 *
 * @package local_sentientia_talent
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Talent category ────────────────────────────────────────────
    'sentientia.talent.enabled' => [
        'default'     => false,
        'description' => 'Master switch for the Talent Mobility suite (career paths,
                          succession planning, internal opportunity board). When OFF,
                          all talent pages return "feature disabled", the navigation
                          entries are hidden, and the web services short-circuit.
                          Default OFF until P2.1 rolls out per tenant.',
    ],

    // Sub-flag: surface the internal opportunity board to learners. Lets
    // HR pilot succession planning internally BEFORE opening the public
    // opportunity board to employees. Requires the master flag ON too.
    'sentientia.talent.opportunities' => [
        'default'     => false,
        'description' => 'Internal opportunity board visible to employees. When OFF
                          (but the master talent flag ON), HR can still manage career
                          paths + succession plans, but the learner-facing
                          "Internal opportunities" surface is hidden. Requires
                          sentientia.talent.enabled = ON.',
    ],
];
