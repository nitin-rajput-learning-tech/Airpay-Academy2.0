<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capability definitions for Sentientia LMS AI Quiz (Phase G.1 scaffold).
 *
 * Single capability gates the future generate UI + live-wiring chip:
 *
 *   local/sentientia_ai_quiz:generate
 *     Default DENY for every archetype. An administrator must explicitly
 *     grant the capability to a role (typically `editingteacher` or
 *     `manager`) once the live-wiring chip ships and per-customer cost
 *     budgets are agreed. Default-deny enforces the rule that scaffold
 *     code shipping with no enforcement is the safest possible default.
 *
 * @package local_sentientia_ai_quiz
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_ai_quiz:generate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        // Phase G.1 ships scaffold only. Every role defaults to deny.
        // Administrators grant per-role once live wiring is reviewed.
        'archetypes'   => [],
    ],

];
