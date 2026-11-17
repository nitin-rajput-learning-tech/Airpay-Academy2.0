<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_authoring.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: take local/sentientia_authoring:manage_all back from every role
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
function local_sentientia_authoring_revoke_manage_all(): int {
    global $DB;
    $syscontext = \context_system::instance();
    $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
        'capability = :cap AND contextid = :ctx',
        ['cap' => 'local/sentientia_authoring:manage_all', 'ctx' => $syscontext->id]);
    foreach ($roleids as $roleid) {
        unassign_capability('local/sentientia_authoring:manage_all', (int) $roleid, $syscontext->id);
    }
    $syscontext->mark_dirty();
    return count($roleids);
}

/**
 * Grant this plugin's three author capabilities to the Sentientia Author role,
 * but only on a site that shows the fresh-install gap.
 *
 * Until 2026111600, db/install.php ran author_role::ensure() before Moodle had
 * registered this plugin's capabilities, so a fresh install created the role
 * without :generate, :review and :managetemplates. They reached the role only
 * when local_sentientia_skillsai installed later in the same run and re-ran
 * ensure(). A site without skillsai, or one where skillsai was installed
 * before this plugin, kept the role without them. Used by upgrade step
 * 2026111600; a function so tests/install_caps_test.php can prove it without
 * replaying the upgrade.
 *
 * The gap has one signature: it leaves ALL THREE caps without a system-context
 * {role_capabilities} row for the role at once. So the three caps are granted
 * (CAP_ALLOW, system context) only when ALL of these hold:
 *  - a role with shortname author_role::SHORTNAME ('sentientiaauthor') exists;
 *  - all three capabilities are registered in {capabilities};
 *  - {role_capabilities} has no system-context row for that role and ANY of
 *    the three capabilities.
 * If even one of them has a row, with any permission (ALLOW, PREVENT or
 * PROHIBIT), nothing is granted: that is an administrator's configuration,
 * never the gap. So a PREVENT or PROHIBIT survives, and so does a cap an
 * administrator cleared to "Not set" while leaving another one set. Only an
 * administrator who cleared all three looks the same as the gap; those three
 * get ALLOW back.
 *
 * It touches no other role and no other capability. It does not re-run
 * author_role::ensure(), which would reset every author capability to ALLOW.
 *
 * @return int number of capabilities granted (3 or 0)
 */
function local_sentientia_authoring_backfill_author_caps(): int {
    global $DB;

    // Literal, as in author_role::SHORTNAME: upgrade code does not lean on a
    // class that later versions may change.
    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'sentientiaauthor']);
    if (!$roleid) {
        return 0;
    }

    $caps = [
        'local/sentientia_authoring:generate',
        'local/sentientia_authoring:review',
        'local/sentientia_authoring:managetemplates',
    ];
    foreach ($caps as $cap) {
        if (!$DB->record_exists('capabilities', ['name' => $cap])) {
            return 0;
        }
    }

    $syscontext = \context_system::instance();
    [$insql, $params] = $DB->get_in_or_equal($caps, SQL_PARAMS_NAMED, 'cap');
    $params['roleid'] = $roleid;
    $params['contextid'] = $syscontext->id;
    if ($DB->record_exists_select('role_capabilities',
            "roleid = :roleid AND contextid = :contextid AND capability {$insql}", $params)) {
        // Any setting for any of the three is an administrator's, not the gap.
        return 0;
    }

    foreach ($caps as $cap) {
        // overwrite=false as a second guard: never replace an existing row.
        assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, false);
    }
    $syscontext->mark_dirty();
    return count($caps);
}
