<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS AI Content Translation (Phase T.0 MVP).
 *
 *   translate     — paste source + call Claude to produce a translation,
 *                   review the diff, and save. Manager only — cost-sensitive.
 *   manage_brands — add / edit / remove per-customer brand-name overrides.
 *                   Manager only.
 *   manage_all    — see + manage translation history across all owners.
 *                   Manager only.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_translate:translate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_translate:manage_brands' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_translate:manage_all' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
