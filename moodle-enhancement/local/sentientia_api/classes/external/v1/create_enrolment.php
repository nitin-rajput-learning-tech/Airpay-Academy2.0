<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_platform\tenant;

/**
 * v1: POST /courses/{id}/enrolments — enrol a user via the manual plugin.
 *
 * Highest-risk endpoint in the surface. It is gated by THREE controls:
 *   1. sentientia.api.enabled       (master)
 *   2. sentientia.api.write.enabled (write sub-flag) — both via open_v1($write=true)
 *   3. local/sentientia_api:write   capability
 * AND both the course AND the target user must be in the caller's tenant.
 *
 * This mirrors the CLAUDE.md [CONFIRM] gate for WRITE operations: a write
 * is impossible unless an admin has deliberately flipped the write flag on.
 *
 * ADR-031 (2026-09-25, adversarial review S2/S3). For a caller who is not
 * tenant::is_cross_tenant():
 *   - the TARGET user goes through tenant::require_same_tenant_user(), which
 *     refuses a target with no tenant. The old check, require_path_access()
 *     on the target's open_path, let an empty or NULL path through, so a
 *     scoped caller could enrol a no-tenant account (a site admin without an
 *     open_path, say) into their course;
 *   - the COURSE must carry a path inside their tenant: an unscoped course
 *     ('' / NULL open_path) cannot be shown to be theirs, and list_courses
 *     never shows it to them either;
 *   - the role may never be a manager-archetype role, even the site's
 *     configured default.
 * For every caller except a site admin, an explicit roleid must be one
 * get_assignable_roles() offers in the course context (what core's
 * enrol_manual_enrol_users requires); the old code accepted any roleid.
 * roleid 0 still means the site's default enrolment role, chosen by a site
 * admin rather than the caller. Site admins are unchanged throughout.
 *
 * @package local_sentientia_api
 */
class create_enrolment extends base {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'userid'   => new external_value(PARAM_INT, 'User id to enrol'),
            'roleid'   => new external_value(PARAM_INT, 'Role id (0 = course default student role)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @param int $roleid
     * @return array
     */
    public static function execute(int $courseid, int $userid, int $roleid = 0): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'userid', 'roleid'));

        // Write gate: requires both master + write flags + :write cap.
        self::open_v1('local_sentientia_api_v1_create_enrolment',
            'local/sentientia_api:write', true, 'POST');
        $crosstenant = tenant::is_cross_tenant();

        // Course must be in caller tenant. A write fails closed on an
        // unscoped course for a scoped caller (require_path_access() lets ''
        // through for legacy reads).
        $course = $DB->get_record('course', ['id' => $params['courseid']],
            'id, open_path', MUST_EXIST);
        $coursepath = (string) ($course->open_path ?? '');
        if ($coursepath === '' && !$crosstenant) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        tenant::require_path_access($coursepath);

        // Target user must exist and be in the caller's tenant - a target with
        // no tenant is refused (ADR-031 decision 5). Cross-tenant callers pass.
        $target = $DB->get_record('user',
            ['id' => $params['userid'], 'deleted' => 0],
            'id, open_path, suspended', MUST_EXIST);
        tenant::require_same_tenant_user((int) $target->id);

        // Resolve the manual enrolment instance.
        $instance = $DB->get_record('enrol',
            ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MISSING);
        if (!$instance) {
            throw new \moodle_exception('error_no_manual_enrol', 'local_sentientia_api');
        }

        require_once($CFG->dirroot . '/lib/enrollib.php');
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new \moodle_exception('error_no_manual_enrol', 'local_sentientia_api');
        }

        // Determine the role to assign.
        $coursecontext = \context_course::instance((int) $course->id);
        if ($params['roleid'] > 0 && !is_siteadmin()) {
            // The caller may only hand out a role they could assign here by hand.
            $assignable = get_assignable_roles($coursecontext, ROLENAME_SHORT);
            if (!array_key_exists((int) $params['roleid'], $assignable)) {
                throw new \moodle_exception('error_role_not_assignable', 'local_sentientia_api');
            }
        }
        $roleidtouse = $params['roleid'] > 0
            ? $params['roleid']
            : (int) get_config('enrol_manual', 'roleid');
        if ($roleidtouse <= 0) {
            $studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id', IGNORE_MISSING);
            $roleidtouse = $studentrole ? (int) $studentrole->id : 0;
        }
        if (!$crosstenant && $roleidtouse > 0
                && $DB->get_field('role', 'archetype', ['id' => $roleidtouse]) === 'manager') {
            // Never a manager-archetype role for a scoped caller, even the site default.
            throw new \moodle_exception('error_role_not_assignable', 'local_sentientia_api');
        }

        $plugin->enrol_user($instance, (int) $target->id, $roleidtouse ?: null,
            time(), 0, ENROL_USER_ACTIVE);

        return [
            'courseid' => (int) $course->id,
            'userid'   => (int) $target->id,
            'roleid'   => (int) $roleidtouse,
            'status'   => 'enrolled',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT,  'Course id'),
            'userid'   => new external_value(PARAM_INT,  'User id enrolled'),
            'roleid'   => new external_value(PARAM_INT,  'Role id assigned'),
            'status'   => new external_value(PARAM_ALPHA, 'Result status'),
        ]);
    }
}
