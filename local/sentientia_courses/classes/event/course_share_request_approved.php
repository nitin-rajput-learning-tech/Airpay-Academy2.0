<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Sprint D — fired when an Airpay Super Admin approves a pending
 * course-share request. The handler that calls this also inserts
 * the matching active row into local_sentientia_courses_tenant_share
 * (which itself fires `course_share_created` — so a single approval
 * produces TWO audit events: the request decision and the resulting
 * share). That's intentional: the request decision tracks who said
 * yes, and the share row tracks the catalog effect.
 *
 * Payload contract — `other`:
 *   request_id          int    request being approved
 *   courseid            int    the course
 *   requesting_tenant   int    tenant root the course was shared TO
 *
 * @package local_sentientia_courses
 */
class course_share_request_approved extends \core\event\base {

    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_courses_requests';
    }

    public static function get_name(): string {
        return get_string('event_course_share_request_approved', 'local_sentientia_courses');
    }

    public function get_description(): string {
        $tenant   = (int) ($this->other['requesting_tenant'] ?? 0);
        $courseid = (int) ($this->other['courseid'] ?? 0);
        return "User {$this->userid} approved request for course $courseid → tenant $tenant.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_courses/manage_requests.php');
    }

    protected function validate_data(): void {
        parent::validate_data();
        foreach (['request_id', 'courseid', 'requesting_tenant'] as $k) {
            if (!isset($this->other[$k])) {
                throw new \coding_exception("course_share_request_approved: missing $k in other");
            }
        }
    }
}
