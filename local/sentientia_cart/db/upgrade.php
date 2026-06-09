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
    // **However**: any CUSTOM role that was granted `:manageprices` at
    // CONTEXT_SYSTEM by hand will now silently no-op — the cap is
    // checked at CONTEXT_COURSE going forward. Per re-audit finding N4,
    // ops should verify custom-role assignments post-upgrade and re-grant
    // at the relevant CONTEXT_COURSECAT (typically the tenant root
    // category) or CONTEXT_COURSE.
    //
    // The "Set capability cleanup checklist" entry in
    // PHASE-8-DEPLOYMENT-RUNBOOK.md §0 enforces the manual step.
    if ($oldversion < 2026051201) {
        upgrade_plugin_savepoint(true, 2026051201, 'local', 'sentientia_cart');
    }

    return true;
}
