<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_translate.
 *
 * The schema is still the db/install.xml baseline; the only step so far
 * revokes a capability grant.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_translate_upgrade(int $oldversion): bool {

    // ── ADR-031 (2026-09-25): take :manage_all back from every role ──
    // db/access.php no longer grants it to the manager archetype, but changing
    // archetypes never revokes what was already applied (install, or
    // reset_role_capabilities() - which is how UAT's tenant-admin role 9 got
    // it). Every existing grant let a tenant admin open, save or discard other
    // tenants' translations by rowid, so revoke them all. Site admins are
    // unaffected; even a deliberate grant now reaches other tenants only for
    // a cross-tenant caller (translate_engine::is_unscoped()).
    if ($oldversion < 2026092500) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_translate_revoke_manage_all();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_translate');
    }

    return true;
}
