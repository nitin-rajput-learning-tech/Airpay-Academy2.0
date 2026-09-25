<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_ai.
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: take :viewledger and :manage back from every role that holds
 * them at system context.
 *
 * db/access.php no longer grants either to the manager archetype, but
 * changing archetypes never revokes grants Moodle already applied (at
 * install, or via reset_role_capabilities()). Used by upgrade step
 * 2026092500; a function so tests/tenant_scope_test.php can prove the revoke
 * without replaying the upgrade.
 *
 * @return int number of (role, capability) grants revoked
 */
function local_sentientia_ai_revoke_operator_caps(): int {
    global $DB;
    $syscontext = \context_system::instance();
    $revoked = 0;
    foreach (['local/sentientia_ai:viewledger', 'local/sentientia_ai:manage'] as $cap) {
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap AND contextid = :ctx', ['cap' => $cap, 'ctx' => $syscontext->id]);
        foreach ($roleids as $roleid) {
            unassign_capability($cap, (int) $roleid, $syscontext->id);
            $revoked++;
        }
    }
    $syscontext->mark_dirty();
    return $revoked;
}
