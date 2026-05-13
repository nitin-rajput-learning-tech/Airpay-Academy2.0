<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Sprint D — fired when an Airpay Super Admin rejects a pending
 * course-share request. No share row is created; the request row
 * stays in the DB with status='rejected' so the requesting manager
 * can see the decision and (optionally) the rejection reason.
 *
 * Payload contract — `other`:
 *   request_id          int    request being rejected
 *   courseid            int    the course
 *   requesting_tenant   int    tenant root that asked
 *   has_reason          bool   true when the admin provided text rationale
 *
 * @package local_airpay_courses
 */
class course_share_request_rejected extends \core\event\base {

    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_airpay_courses_requests';
    }

    public static function get_name(): string {
        return get_string('event_course_share_request_rejected', 'local_airpay_courses');
    }

    public function get_description(): string {
        $tenant   = (int) ($this->other['requesting_tenant'] ?? 0);
        $courseid = (int) ($this->other['courseid'] ?? 0);
        $hasreason = !empty($this->other['has_reason']) ? ' (with reason)' : '';
        return "User {$this->userid} rejected request for course $courseid "
            . "from tenant $tenant{$hasreason}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/airpay_courses/manage_requests.php');
    }

    protected function validate_data(): void {
        parent::validate_data();
        foreach (['request_id', 'courseid', 'requesting_tenant'] as $k) {
            if (!isset($this->other[$k])) {
                throw new \coding_exception("course_share_request_rejected: missing $k in other");
            }
        }
    }
}
