<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_skillsai.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Grant this plugin's two author capabilities to the Sentientia Author role,
 * but only on a site that shows the fresh-install gap.
 *
 * Until 2026111600, db/install.php ran author_role::ensure() before Moodle had
 * registered this plugin's capabilities, so every fresh install (CI, PHPUnit
 * sites, a new customer, the Stage B cutover) created the role without
 * :extract and :review. Used by upgrade step 2026111600; a function so
 * tests/install_caps_test.php can prove it without replaying the upgrade.
 *
 * The gap has one signature: it leaves BOTH caps without a system-context
 * {role_capabilities} row for the role at once. So both caps are granted
 * (CAP_ALLOW, system context) only when ALL of these hold:
 *  - a role with shortname 'sentientiaauthor' exists (the same lookup as
 *    \local_sentientia_authoring\author_role::ensure(); kept literal so this
 *    step does not depend on another plugin's class);
 *  - both capabilities are registered in {capabilities};
 *  - {role_capabilities} has no system-context row for that role and EITHER
 *    capability.
 * If even one of the two has a row, with any permission (ALLOW, PREVENT or
 * PROHIBIT), nothing is granted: that is an administrator's configuration,
 * never the gap. So a PREVENT or PROHIBIT survives, and so does a cap an
 * administrator cleared to "Not set" while leaving the other one set. Only an
 * administrator who cleared both looks the same as the gap; those two get
 * ALLOW back.
 *
 * It touches no other role and no other capability. It does not re-run
 * author_role::ensure(), which would reset every author capability to ALLOW.
 *
 * @return int number of capabilities granted (2 or 0)
 */
function local_sentientia_skillsai_backfill_author_caps(): int {
    global $DB;

    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'sentientiaauthor']);
    if (!$roleid) {
        return 0;
    }

    $caps = ['local/sentientia_skillsai:extract', 'local/sentientia_skillsai:review'];
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
        // Any setting for either cap is an administrator's, not the gap.
        return 0;
    }

    foreach ($caps as $cap) {
        // overwrite=false as a second guard: never replace an existing row.
        assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, false);
    }
    $syscontext->mark_dirty();
    return count($caps);
}
