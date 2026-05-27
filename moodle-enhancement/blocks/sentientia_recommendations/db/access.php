<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for the Sentientia LMS AI Recommendations block.
 *
 * Standard block add-instance capabilities. Viewing the recommendations
 * themselves is gated by local/sentientia_recommendations:view (checked
 * inside the block), so these only govern who may PLACE the block.
 *
 * @package block_sentientia_recommendations
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'block/sentientia_recommendations:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],

    'block/sentientia_recommendations:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
];
