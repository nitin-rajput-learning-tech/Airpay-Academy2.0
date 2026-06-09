<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// NOTE: 'employee' and 'administrator' are CUSTOM roles, not Moodle
// archetypes. They cannot appear in the `archetypes` array. Instead we
// grant capabilities to them via local_sentientia_cart_after_install() in
// db/upgradelib.php (called once after table install).
$capabilities = [
    // View one's own cart and order history.
    'local/sentientia_cart:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'student'        => CAP_ALLOW,
        ],
    ],

    // Add to cart, place orders.
    'local/sentientia_cart:purchase' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],

    // View ALL orders across all users (admin reports).
    'local/sentientia_cart:viewallorders' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Process refunds. Siteadmin only by default — explicit assignment
    // to custom 'administrator' role done in upgradelib.php.
    'local/sentientia_cart:refund' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Manage course pricing.
    // Phase 8.1 B9 fix: was CONTEXT_SYSTEM, moved to CONTEXT_COURSE.
    // The cap now must be checked at the COURSE context, not system —
    // managers in tenant X only get the cap on courses inside their
    // tenant's category hierarchy. This prevents a Public-tenant
    // manager from re-pricing an Airpay-tenant course.
    'local/sentientia_cart:manageprices' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],
];
