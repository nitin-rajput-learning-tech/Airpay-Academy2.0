<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\event;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — fired when an approver approves an access request.
 *
 * High-value SOX event: triggers user enrolment + counts toward
 * cost-attribution + must show on the audit trail.
 *
 * @package local_sentientia_request
 */
class request_approved extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_request';
    }

    public static function get_name(): string {
        return get_string('event_request_approved', 'local_sentientia_request');
    }

    public function get_description(): string {
        return "User {$this->userid} approved request {$this->objectid} for user "
            . ($this->relateduserid ?? '?') . " on course "
            . ($this->other['courseid'] ?? '?') . ".";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_request/view.php',
            ['id' => $this->objectid]);
    }
}
