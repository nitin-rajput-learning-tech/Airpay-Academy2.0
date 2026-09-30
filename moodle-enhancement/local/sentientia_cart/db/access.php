<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// NOTE: 'employee' and 'administrator' are CUSTOM roles, not Moodle
// archetypes. They cannot appear in the `archetypes` array. Instead
// xmldb_local_sentientia_cart_install() in db/install.php grants them their
// capabilities once, after table install. That hook also calls
// local_sentientia_cart_backfill_user_purchase() (db/upgradelib.php), which
// upgrade step 2026093001 runs on existing sites; see :purchase below.
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
    // The `user` archetype (Authenticated user) is the only role a real public
    // (/77) or ZEEA (/177) learner holds. It has been listed here since the
    // plugin was written, so the list did not change; the persona pass still
    // found the role without the row. Most likely the capability was first
    // registered outside update_capabilities() (an earlier CLI patch or the
    // rename --migrate-caps path), and only update_capabilities() applies
    // archetype defaults, and only when it inserts the capability itself. The
    // grant is therefore filled in by
    // local_sentientia_cart_backfill_user_purchase() (db/upgradelib.php), from
    // db/install.php and upgrade step 2026093001. Buying is still gated by the
    // enabled_tenants setting and the ADR-031 catalogue purchase gate
    // (cart_manager::can_buy_course()), not by this capability.
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
