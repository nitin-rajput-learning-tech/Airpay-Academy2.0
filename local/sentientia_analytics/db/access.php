<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capability definitions for local_sentientia_analytics.
 *
 * WHY THIS FILE EXISTS (added 2026-09-22)
 * ---------------------------------------
 * Until today this plugin had no db/access.php at all. All three entry points
 * (index.php, drilldown.php, export.php) gated on:
 *
 *     is_siteadmin() || has_capability('local/courses:manage', $context)
 *
 * `local/courses:manage` was renamed to `local/sentientia_courses:manage` by
 * ADR-025 and is no longer declared by any shipped plugin. Moodle's
 * has_capability() answers an unknown capability with a debugging() notice and
 * `false`, so the second half of that gate had been dead code: effective access
 * was site admins plus whoever happened to hold hardcoded role id 9 at a
 * course-category context. Line managers and the `manager` role saw
 * "nopermission" on a dashboard built for them.
 *
 * A hardcoded role id is also wrong for a white-label product — role 9 is
 * `administrator` on the Airpay deployment and something else everywhere else.
 * See classes/permission.php for the dynamic resolution that replaces it.
 *
 * Capability split mirrors local_sentientia_compliance_report: viewing the
 * aggregate dashboard and releasing the per-learner matrix are different
 * decisions, so they are different capabilities.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View the analytics dashboard, scoped to the user's own org subtree.
    // Drill-down reveals named learners and their completion state, so this
    // is RISK_PERSONAL even though the landing page is aggregate.
    'local/sentientia_analytics:view' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // See every org's numbers, not just the caller's own subtree. Without
    // this, permission::visible_org_path() clamps the view to the caller's
    // tenant, which is what a line manager or an org admin should get.
    'local/sentientia_analytics:viewallorgs' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Download the CSV. This releases a named per-learner dataset in one
    // file, which is a materially larger disclosure than reading the
    // dashboard, so viewers are deliberately NOT granted it by default.
    'local/sentientia_analytics:export' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
