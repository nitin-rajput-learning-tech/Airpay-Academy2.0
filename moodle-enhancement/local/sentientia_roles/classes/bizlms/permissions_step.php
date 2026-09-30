<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * local_costcenter_permissions (BizLMS local/costcenter): the managers assigned to an organisation.
 *
 * Column map (mapping doc section 4):
 *  - userid (CHAR 225, may be a comma list) -> one assignment per user, in list order;
 *  - costcenterid -> the organisation, and through local_costcenter.category the course category context;
 *  - roleid -> roleid (0 is skipped: no_role);
 *  - value -> only value = 1 is an assignment (decision org_roles.value_filter); the rest is archived;
 *  - timecreated (else timemodified) -> role_assignments.timemodified and the audit row's timecreated;
 *  - usermodified -> modifierid and the audit row's changedby.
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class permissions_step extends assignment_step {

    /**
     * @return string
     */
    public function key(): string {
        return importer::FEATURE . '.permissions';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return importer::PERMISSIONS;
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return ['userid', 'costcenterid', 'roleid', 'value', 'timecreated', 'timemodified', 'usermodified'];
    }

    /**
     * @param \stdClass $row
     * @return \stdClass
     */
    protected function facts(\stdClass $row): \stdClass {
        $assigned = self::first_positive((int) $row->timecreated, (int) $row->timemodified);
        return (object) [
            'included' => (int) $row->value === 1,
            'orgid' => (int) $row->costcenterid,
            'users' => (string) ($row->userid ?? ''),
            'roleid' => (int) $row->roleid,
            'modifier' => (int) $row->usermodified,
            'assigned' => $assigned,
            'audited' => $assigned,
        ];
    }

    /**
     * @return string
     */
    protected function audit_reason(): string {
        return importer::AUDIT_REASON_PERMISSIONS;
    }
}
