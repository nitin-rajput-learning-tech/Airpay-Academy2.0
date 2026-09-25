<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_recommendations.
 *
 * The schema is still the db/install.xml baseline; the only step so far
 * revokes a capability grant.
 *
 * @package local_sentientia_recommendations
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_recommendations_upgrade(int $oldversion): bool {

    // ── ADR-031 (2026-09-25): take :manage_all back from every role ──
    // db/access.php no longer grants it to the manager archetype, but changing
    // archetypes never revokes what was already applied (install, or
    // reset_role_capabilities() - which is how UAT's tenant-admin role 9 got
    // it). Nothing checks it yet, but its name promises every learner's
    // history, so no tenant admin may carry it into the day something does.
    if ($oldversion < 2026092500) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_recommendations_revoke_manage_all();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_recommendations');
    }

    return true;
}
