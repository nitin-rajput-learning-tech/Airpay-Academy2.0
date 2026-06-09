<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Live engagement — entry router.
 *
 * Historical note: this file shipped in Phase E.0 as a "coming soon"
 * placeholder, with a comment promising "Phase E.1 will replace this with
 * the full trainer dashboard". E.1/E.2 shipped the full trainer + audience
 * UIs (trainer/index.php, audience/join.php, run.php, stream.php, …) but the
 * root landing was never repointed — so /local/sentientia_live/ kept showing
 * "being built incrementally" over a fully-built feature. This routes the
 * user to the correct surface for their role instead (fix 2026-06-09).
 *
 *   - trainers (local/sentientia_live:create)  -> the trainer dashboard;
 *   - everyone else                            -> the audience join page.
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = \context_system::instance();

// Master flag gate. Default OFF in Phase E.0; ON enables the feature.
// NB this 5.2-line branch predates ADR-025, so the flag class is still
// local_airpay_core (production uses the renamed local_sentientia_platform).
if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

// Route to the surface that matches the user's role. Each target enforces
// its own access (the trainer dashboard requires :create; the audience join
// page is open to any logged-in user and self-gates anonymous joins).
if (has_capability('local/sentientia_live:create', $context)) {
    redirect(new \moodle_url('/local/sentientia_live/trainer/index.php'));
}

redirect(new \moodle_url('/local/sentientia_live/audience/join.php'));
