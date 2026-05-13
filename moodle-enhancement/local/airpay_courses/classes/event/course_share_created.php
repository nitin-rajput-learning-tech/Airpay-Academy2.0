<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired when an admin shares a course to a tenant.
 *
 * Sprint C (2026-05-13). The standard Moodle logstore records this
 * event automatically, which means `\local_airpay_core\audit_log`
 * surfaces it in the "sensitive actions" audit dashboard for free
 * (after we add the eventname to audit_log::SENSITIVE_EVENTS).
 *
 * Payload contract — `other`:
 *   tenant_id   int     Tenant root the course was shared to (1, 77, 177)
 *   courseid    int     The course made available
 *
 * @package local_airpay_courses
 */
class course_share_created extends \core\event\base {

    protected function init(): void {
        $this->data['crud']        = 'c';                  // create
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_airpay_courses_tenant_share';
    }

    public static function get_name(): string {
        return get_string('event_course_share_created', 'local_airpay_courses');
    }

    public function get_description(): string {
        $tenant   = (int) ($this->other['tenant_id'] ?? 0);
        $courseid = (int) ($this->other['courseid'] ?? 0);
        return "User {$this->userid} shared course $courseid to tenant $tenant.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/airpay_courses/share.php', [
            'id' => (int) ($this->other['courseid'] ?? 0),
        ]);
    }

    /**
     * Validate that the event payload has the keys we declared.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['tenant_id'])) {
            throw new \coding_exception('course_share_created: missing tenant_id in other');
        }
        if (!isset($this->other['courseid'])) {
            throw new \coding_exception('course_share_created: missing courseid in other');
        }
    }
}
