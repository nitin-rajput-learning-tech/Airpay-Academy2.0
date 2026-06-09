<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_m365.
 *
 * Phase C.1 (2026-05-24) — one flag:
 *
 *  1. sentientia_m365_enabled — master switch for the entire Microsoft
 *                                365 integration. Default OFF on every
 *                                customer. Even when ON, every public
 *                                method on graph_client still throws
 *                                \moodle_exception('confirm_required')
 *                                in Phase C.1; the live API gate lands
 *                                in Phase C.2 behind a separate flag.
 *
 * Per CLAUDE.md §13: every new feature MUST ship behind a default-OFF
 * flag. This file satisfies that mandate for Phase C.1.
 *
 * @package local_sentientia_m365
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Microsoft 365 integration ─────────────
    'sentientia_m365_enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS Microsoft 365 Knowledge Automation
                          (Workstream C). Master switch. When OFF (default),
                          /local/sentientia_m365/ endpoints return 403, the
                          OAuth redirect endpoint refuses to start a flow,
                          and the privacy provider reports zero contexts.
                          When ON, the connection UI surfaces and the OAuth
                          Authorization Code flow becomes reachable — but
                          actual Graph API calls remain gated behind the
                          confirm_required guard in graph_client until
                          Phase C.2 lands the live API flag.',
    ],

];
