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
$plugin->version     = 2026060200;       // YYYYMMDDNN
$plugin->requires    = 2024100700;       // Moodle 4.5+
$plugin->maturity    = MATURITY_ALPHA;   // skeleton — payment integration pending
$plugin->release     = '0.1.0-alpha';
$plugin->dependencies = [
    'local_airpay_core' => ANY_VERSION,  // feature_flags registry
];
