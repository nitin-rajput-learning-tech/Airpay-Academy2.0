<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Sprint D — fired when a receiving-tenant manager requests that an
 * Airpay course be shared to their tenant.
 *
 * Payload contract — `other`:
 *   request_id          int    id of the new row in local_sentientia_courses_requests
 *   courseid            int    the course being requested
 *   requesting_tenant   int    tenant root (e.g. 77 Public, 177 ZEEA)
 *
 * @package local_sentientia_courses
 */
class course_share_requested extends \core\event\base {

    protected function init(): void {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_courses_requests';
    }

    public static function get_name(): string {
        return get_string('event_course_share_requested', 'local_sentientia_courses');
    }

    public function get_description(): string {
        $tenant   = (int) ($this->other['requesting_tenant'] ?? 0);
        $courseid = (int) ($this->other['courseid'] ?? 0);
        return "User {$this->userid} (tenant $tenant) requested course $courseid.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_courses/manage_requests.php');
    }

    protected function validate_data(): void {
        parent::validate_data();
        foreach (['request_id', 'courseid', 'requesting_tenant'] as $k) {
            if (!isset($this->other[$k])) {
                throw new \coding_exception("course_share_requested: missing $k in other");
            }
        }
    }
}
