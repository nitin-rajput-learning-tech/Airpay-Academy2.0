<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_translate.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: take local/sentientia_translate:manage_all back from every role
 * that holds it at system context.
 *
 * db/access.php no longer grants it to the manager archetype, but changing
 * archetypes never revokes grants Moodle already applied (at install, or via
 * reset_role_capabilities()). Used by upgrade step 2026092500; a function so
 * tests/tenant_scope_test.php can prove the revoke without replaying the
 * upgrade.
 *
 * @return int number of roles revoked
 */
function local_sentientia_translate_revoke_manage_all(): int {
    global $DB;
    $syscontext = \context_system::instance();
    $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
        'capability = :cap AND contextid = :ctx',
        ['cap' => 'local/sentientia_translate:manage_all', 'ctx' => $syscontext->id]);
    foreach ($roleids as $roleid) {
        unassign_capability('local/sentientia_translate:manage_all', (int) $roleid, $syscontext->id);
    }
    $syscontext->mark_dirty();
    return count($roleids);
}
