<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_learningpath.
 *
 * @package   local_sentientia_learningpath
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: take local/sentientia_learningpath:view back from every LEARNER
 * role that holds it at system context.
 *
 * :view gates the admin surface (view.php's Users tab and exportcsv.php list
 * path rosters with names, emails and employee ids), so db/access.php stopped
 * defaulting it to the student archetype. Step 2026092500 then revoked it
 * from roles whose archetype is 'student'. That missed learner roles whose
 * archetype is not 'student': a role duplicated from Student with the
 * archetype option unticked, one later edited to archetype None, or the
 * BizLMS 'employee' role on a site where it was created without an
 * archetype. Those kept :view from the permissions they were given.
 *
 * "Learner role" is the rule local_sentientia_courses\course_manager::
 * learner_role_ids() already uses: archetype 'student', or shortname
 * 'student' or 'employee'. It is repeated here rather than called, so this
 * upgrade does not depend on another plugin being installed.
 *
 * Only CAP_ALLOW rows are removed. A CAP_PREVENT or CAP_PROHIBIT an admin set
 * on a learner role protects it, and removing that would not take anything
 * away from the role.
 *
 * Every other holder keeps the capability on purpose: the manager archetype
 * (tenant admins, confined to their tenant in code since ADR-031) and any
 * role an admin granted it to deliberately, such as a read-only L&D auditor.
 * Those are returned in 'kept' so the upgrade can print them for review.
 *
 * Used by upgrade step 2026092502; a function so
 * tests/learner_view_revoke_test.php can prove it without replaying the whole
 * upgrade.
 *
 * @return array{revoked: string[], kept: string[]} role shortnames; 'kept'
 *         entries read "shortname (archetype)"
 */
function local_sentientia_learningpath_revoke_learner_view(): array {
    global $DB;

    $cap = 'local/sentientia_learningpath:view';
    $syscontext = \context_system::instance();

    $holders = $DB->get_records_sql(
        "SELECT r.id, r.shortname, r.archetype
           FROM {role_capabilities} rc
           JOIN {role} r ON r.id = rc.roleid
          WHERE rc.capability = :cap
            AND rc.contextid = :ctx
            AND rc.permission = :allow
       ORDER BY r.id",
        ['cap' => $cap, 'ctx' => $syscontext->id, 'allow' => CAP_ALLOW]);

    $revoked = [];
    $kept = [];
    foreach ($holders as $role) {
        $islearner = (string) $role->archetype === 'student'
            || in_array((string) $role->shortname, ['student', 'employee'], true);
        if ($islearner) {
            unassign_capability($cap, (int) $role->id, $syscontext->id);
            $revoked[] = (string) $role->shortname;
        } else {
            $archetype = (string) $role->archetype === '' ? 'none' : (string) $role->archetype;
            $kept[] = $role->shortname . ' (' . $archetype . ')';
        }
    }

    if ($revoked) {
        $syscontext->mark_dirty();
    }
    return ['revoked' => $revoked, 'kept' => $kept];
}
