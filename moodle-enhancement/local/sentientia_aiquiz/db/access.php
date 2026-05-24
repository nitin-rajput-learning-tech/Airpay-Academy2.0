<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS AI Quiz Generation (Phase G.0 MVP).
 *
 *   generate   — paste source text + call Claude to produce a draft.
 *                Editingteacher+, manager. Cost-sensitive — never user.
 *   review     — open the review UI, approve/edit/reject questions,
 *                push approved questions to mod_quiz. Same archetypes
 *                as :generate; in practice the generator IS the reviewer
 *                for MVP. Two-person review can be enforced later via
 *                a per-customer flag.
 *   manage_all — see + review every draft across all owners. Manager only.
 *
 * @package local_sentientia_aiquiz
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_aiquiz:generate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_aiquiz:review' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_aiquiz:manage_all' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
