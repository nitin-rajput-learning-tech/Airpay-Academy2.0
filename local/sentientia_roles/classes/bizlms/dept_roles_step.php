<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * local_org_dept_roles (BizLMS local/assignroles): a user's role in a department.
 *
 * Column map (mapping doc section 4):
 *  - departmentid (else costcenterid) -> the organisation, and through local_costcenter.category the course
 *    category context. With a department, costcenterid is the organisation it should live under: the tenant comes
 *    from the department alone, and a department outside that organisation is reported (dept_outside_costcenter);
 *  - userid, roleid -> the same columns of role_assignments (one user per row, so userid is a single id);
 *  - user_modified (else user_created) -> modifierid and the audit row's changedby;
 *  - timemodified (else timecreated) -> role_assignments.timemodified; timecreated (else that) -> the audit row's
 *    timecreated, the time the assignment was made.
 *
 * The table has no value column: every row with a role is an assignment.
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dept_roles_step extends assignment_step {

    /**
     * @return string
     */
    public function key(): string {
        return importer::FEATURE . '.dept_roles';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return importer::DEPT_ROLES;
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return ['costcenterid', 'departmentid', 'userid', 'roleid', 'user_created', 'user_modified', 'timemodified',
            'timecreated'];
    }

    /**
     * @param \stdClass $row
     * @return \stdClass
     */
    protected function facts(\stdClass $row): \stdClass {
        $assigned = self::first_positive((int) $row->timemodified, (int) $row->timecreated);
        return (object) [
            'included' => true,
            'orgid' => (int) $row->departmentid > 0 ? (int) $row->departmentid : (int) $row->costcenterid,
            // The organisation the department is said to live under; checked against the department's own path.
            'containerid' => (int) $row->departmentid > 0 ? (int) $row->costcenterid : 0,
            'users' => (string) ((int) $row->userid),
            'roleid' => (int) $row->roleid,
            'modifier' => self::first_positive((int) $row->user_modified, (int) $row->user_created),
            'assigned' => $assigned,
            'audited' => self::first_positive((int) $row->timecreated, $assigned),
        ];
    }

    /**
     * @return string
     */
    protected function audit_reason(): string {
        return importer::AUDIT_REASON_DEPT_ROLES;
    }
}
