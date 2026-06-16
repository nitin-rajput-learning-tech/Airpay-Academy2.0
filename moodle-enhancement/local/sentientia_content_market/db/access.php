<?php
/**
 * Capability definitions for local_sentientia_content_market.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Search and browse the third-party catalog (learner-facing).
    'local/sentientia_content_market:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Trigger a manual sync for one or all providers (admin-facing).
    'local/sentientia_content_market:syncproviders' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Manage provider configuration (API keys, enable/disable providers).
    'local/sentientia_content_market:manageproviders' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Manually map items to skills taxonomy entries.
    'local/sentientia_content_market:mapskills' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
        ],
    ],

];
