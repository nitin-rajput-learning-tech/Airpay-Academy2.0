<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// NOTE: 'employee' and 'administrator' are CUSTOM roles, not Moodle
// archetypes. They cannot appear in the `archetypes` array. Instead we
// grant capabilities to them via local_airpay_cart_after_install() in
// db/upgradelib.php (called once after table install).
$capabilities = [
    // View one's own cart and order history.
    'local/airpay_cart:view' => [
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
    'local/airpay_cart:purchase' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'manager' => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],

    // View ALL orders across all users (admin reports).
    'local/airpay_cart:viewallorders' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Process refunds. Siteadmin only by default — explicit assignment
    // to custom 'administrator' role done in upgradelib.php.
    'local/airpay_cart:refund' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Manage course pricing.
    'local/airpay_cart:manageprices' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
