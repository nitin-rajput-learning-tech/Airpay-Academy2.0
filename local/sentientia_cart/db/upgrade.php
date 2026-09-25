<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Schema upgrades for local_sentientia_cart.
 *
 * @package local_sentientia_cart
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_cart_upgrade(int $oldversion): bool {
    global $DB;

    // ── 2026051201 — Phase 8.1 security remediation ──────────────────
    //
    // Phase 8.1 / B9 changed `local/sentientia_cart:manageprices` from
    // contextlevel CONTEXT_SYSTEM to CONTEXT_COURSE in db/access.php.
    //
    // Moodle re-registers capability metadata automatically when the
    // plugin version bumps (`update_capabilities()` called on upgrade).
    // The archetype defaults `manager => CAP_ALLOW` re-apply at the
    // new context level. So out-of-the-box archetype roles continue
    // to work after upgrade.
    //
    // **Correction (ADR-031, 2026-09-25):** this used to say that a
    // `:manageprices` grant at CONTEXT_SYSTEM "silently no-ops" once the cap
    // is checked at CONTEXT_COURSE. It does not: a system-level grant is
    // inherited by EVERY course context on the site, so a tenant admin (a
    // manager-archetype role assigned at system context) could price any
    // tenant's course. Moving the check to course context scoped nothing.
    // The tenant bound is now enforced in code instead
    // (cart_manager::require_course_in_tenant() in set_course_price, and
    // tenant::path_filter() on set_price.php). Per re-audit finding N4, ops
    // may still prefer to grant custom roles at the tenant root
    // CONTEXT_COURSECAT rather than at system context.
    //
    // The "Set capability cleanup checklist" entry in
    // PHASE-8-DEPLOYMENT-RUNBOOK.md §0 enforces the manual step.
    if ($oldversion < 2026051201) {
        upgrade_plugin_savepoint(true, 2026051201, 'local', 'sentientia_cart');
    }

    return true;
}
