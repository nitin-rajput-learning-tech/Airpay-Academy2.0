<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_ai_quiz (Phase G.1 scaffold).
 *
 * One flag:
 *
 *   sentientia_ai_quiz_enabled
 *     Master switch for the G.1 Hindi-quiz / per-customer-prompt
 *     surface. Default OFF. Per CLAUDE.md §13 every new feature ships
 *     behind a default-OFF flag. Per-customer + per-tenant overrides
 *     resolved through local_airpay_core\feature_flags::is_enabled_for()
 *     (5-level precedence; see local_airpay_core\classes\feature_flags.php).
 *
 *     Even when ON, the Phase G.1 scaffold's anthropic_client throws
 *     confirm_required for every call — the live wiring is a separate
 *     chip with its own per-call [CONFIRM] gate.
 *
 * Note on naming: dots vs underscores. Sibling G.0 plugin uses
 * `sentientia.aiquiz.enabled` (dotted). The G.1 scaffold deliberately
 * registers `sentientia_ai_quiz_enabled` (underscored) to mirror the
 * plugin component name. Both naming styles are valid feature-flag keys;
 * local_airpay_core's resolver treats them as opaque strings.
 *
 * @package local_sentientia_ai_quiz
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia_ai_quiz_enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS AI Quiz — Phase G.1 scaffold.
                          Master switch for Hindi quiz generation with
                          per-customer Anthropic prompt templates. Default
                          OFF. Per-customer and per-tenant overrides are
                          supported via the local_airpay_core resolver
                          (5-level precedence). Even with the flag ON,
                          anthropic_client::generate_quiz() throws
                          confirm_required until the live-wiring chip
                          lands with its own per-call [CONFIRM] gate.',
    ],

];
