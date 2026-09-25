<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_ai.
 *
 * The schema is still the db/install.xml baseline; the only step so far
 * revokes capability grants.
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_ai_upgrade(int $oldversion): bool {

    // ── ADR-031 (2026-09-25): take :viewledger and :manage back ──
    // db/access.php no longer grants them to the manager archetype, but
    // changing archetypes never revokes what was already applied (install, or
    // reset_role_capabilities() - which is how UAT's tenant-admin role 9 got
    // them). :viewledger let every tenant admin open index.php by URL and read
    // every tenant's AI calls (user ids, features, tokens, cost, errors) and
    // the platform-wide spend; :manage is reserved for global controls. Site
    // admins are unaffected.
    if ($oldversion < 2026092500) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_ai_revoke_operator_caps();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_ai');
    }

    return true;
}
