<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for enrol_sentientiasub (ADR-023).
 *
 * Per CLAUDE.md §13: every new feature ships behind a default-OFF flag.
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.subscriptions.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS recurring subscriptions (ADR-023).
                          Master switch. When OFF (default), no subscription
                          enrol instances can be added (can_add_instance returns
                          false) and existing instances are inert — the platform
                          behaves exactly as today (one-time enrol_fee only).
                          When ON (per-tenant override; Public/77 first), the
                          enrol_sentientiasub instance UI + the lifecycle activate.
                          The Airpay mandate checkout + subscription-callback
                          (increments 3-4) additionally require the sandbox-
                          validated payment path; this flag alone does not charge
                          anyone.',
    ],

];
