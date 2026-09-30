<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_request.
 *
 * Consumed by \local_sentientia_platform\feature_flags::load_registry().
 * Every flag defaults OFF - no change to what Airpay Academy users see today.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.request.imported_history' => [
        'default'     => false,
        'description' => 'Show the requests imported from BizLMS (ADR-032) in My requests, Pending
                          approvals and All requests, and count them in the approver nav badge.
                          When OFF (the default) those lists leave imported rows out and look exactly
                          as they did before the import; the rows stay in the database. Legacy
                          pending course and learning-path requests become actionable in the approvals
                          inbox only when this is ON. The import never flips it: turning it on for
                          Airpay is the owner\'s decision, taken after the visual evidence is reviewed.',
    ],

];
