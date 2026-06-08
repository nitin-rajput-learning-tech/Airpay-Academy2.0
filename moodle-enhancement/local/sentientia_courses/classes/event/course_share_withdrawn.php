<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired when an admin withdraws a previously-shared course from a tenant.
 *
 * Sprint C (2026-05-13). Pairs with `course_share_created`; together
 * they give the audit trail the full lifecycle of a tenant-share.
 *
 * Payload contract — `other`:
 *   tenant_id   int    Tenant root the share was withdrawn from
 *   courseid    int    The course
 *
 * @package local_sentientia_courses
 */
class course_share_withdrawn extends \core\event\base {

    protected function init(): void {
        $this->data['crud']        = 'u';                  // update (status flip, not delete)
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_courses_tenant_share';
    }

    public static function get_name(): string {
        return get_string('event_course_share_withdrawn', 'local_sentientia_courses');
    }

    public function get_description(): string {
        $tenant   = (int) ($this->other['tenant_id'] ?? 0);
        $courseid = (int) ($this->other['courseid'] ?? 0);
        return "User {$this->userid} withdrew the share of course $courseid from tenant $tenant.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_courses/share.php', [
            'id' => (int) ($this->other['courseid'] ?? 0),
        ]);
    }

    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['tenant_id'])) {
            throw new \coding_exception('course_share_withdrawn: missing tenant_id in other');
        }
        if (!isset($this->other['courseid'])) {
            throw new \coding_exception('course_share_withdrawn: missing courseid in other');
        }
    }
}
