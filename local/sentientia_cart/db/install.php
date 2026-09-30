<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook. Grants capabilities to Airpay custom roles
 * (`employee`, `administrator`) that aren't Moodle archetypes, then makes sure
 * the authenticated-user role holds :purchase (see the end of this function).
 */
function xmldb_local_sentientia_cart_install(): void {
    global $DB;

    // db/access.php caps aren't registered yet when install hook runs.
    // Force them in via update_capabilities() before assigning. Fixed
    // 2026-05-11 — original install silently failed and we had to
    // post-hoc patch caps via a CLI script. Now self-healing.
    require_once($GLOBALS['CFG']->libdir . '/upgradelib.php');
    // update_capabilities() needs underscore form, not slash.
    // Slash silently returns 0 caps (caught by Phase 7 UAT).
    update_capabilities('local_sentientia_cart');

    $context = \context_system::instance();

    $rolemap = [
        // shortname => list of [cap, permission]
        'employee' => [
            ['local/sentientia_cart:view',     CAP_ALLOW],
            ['local/sentientia_cart:purchase', CAP_ALLOW],
        ],
        'administrator' => [
            ['local/sentientia_cart:view',          CAP_ALLOW],
            ['local/sentientia_cart:purchase',      CAP_ALLOW],
            ['local/sentientia_cart:viewallorders', CAP_ALLOW],
            ['local/sentientia_cart:refund',        CAP_ALLOW],
            ['local/sentientia_cart:manageprices',  CAP_ALLOW],
        ],
    ];

    foreach ($rolemap as $shortname => $caps) {
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$role) {
            continue;  // role doesn't exist on this install (e.g. fresh moodle)
        }
        foreach ($caps as [$cap, $permission]) {
            if (!$DB->record_exists('capabilities', ['name' => $cap])) {
                continue;
            }
            assign_capability($cap, $permission, $role->id, $context->id, true);
        }
    }

    // D1 (persona pass 2026-09-30): a public-storefront or ZEEA learner holds no
    // role but Authenticated user, so that role must hold :purchase for them to
    // buy. db/access.php lists the `user` archetype, but archetype defaults are
    // applied only when a capability is first registered, and the upgrade path
    // has its own back-fill (step 2026093001). Fresh installs go through the
    // same helper so both paths end identically; it never overrides an
    // administrator's PREVENT / PROHIBIT and only fills a missing grant.
    require_once(__DIR__ . '/upgradelib.php');
    local_sentientia_cart_backfill_user_purchase();
}
