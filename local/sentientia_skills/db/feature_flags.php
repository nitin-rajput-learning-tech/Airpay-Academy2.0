<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_skills.
 *
 * First flag added 2026-08-05 (the plugin itself is STABLE and pre-dates
 * the registry convention; its own pages are capability-gated, not
 * flag-gated — grandfathered).
 *
 * @package local_sentientia_skills
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Skills ─────────────────────────────────
    'sentientia.dashboard.skillsrecs.enabled' => [
        'default'     => false,
        'description' => 'Skills-first dashboard recommendations (ADR-028
                          Phase 2.2). When ON, the learner dashboard\'s
                          "Recommended for you" rail is driven by the
                          skills gap engine — skills_manager::
                          get_gap_courses(): unmet role skills mapped to
                          courses that teach them at the required level,
                          completed courses excluded. Deterministic data
                          (role-required vs held), ZERO AI spend. Learners
                          with no gap data (new joiner, unmapped
                          designation) and OFF deployments fall back to
                          the legacy same-category-newest heuristic, so
                          the rail never goes empty because of this flag.
                          Registered here (the data owner); consumed by
                          theme_sentientia layout/dashboard.php.',
    ],

];
