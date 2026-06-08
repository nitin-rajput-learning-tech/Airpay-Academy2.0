<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Version metadata for enrol_sentientiasub.
 *
 * Recurring-subscription enrolment (ADR-023). INCREMENT 2 — the no-sandbox
 * skeleton: data model + lifecycle state machine + feature flag (default OFF) +
 * capabilities + tests. The Airpay mandate / sb_* checkout + subscription-callback
 * (increments 3-4) are NOT in this build and require an Airpay sandbox.
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component   = 'enrol_sentientiasub';
$plugin->version     = 2026060300;       // YYYYMMDDNN
$plugin->requires    = 2024100700;       // Moodle 4.5+
$plugin->maturity    = MATURITY_ALPHA;   // increment 5 (cohort grant for allaccess/category) added; payment (3-4) sandbox-pending
$plugin->release     = '0.2.0-alpha';
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,  // feature_flags registry
];
