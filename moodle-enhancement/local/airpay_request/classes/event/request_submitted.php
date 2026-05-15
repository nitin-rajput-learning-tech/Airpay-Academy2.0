<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request\event;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — fired when a learner submits an access request.
 *
 * Audit trail: SOX/SIEM listeners can subscribe via logstore_standard_log.
 *
 * @package local_airpay_request
 */
class request_submitted extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_airpay_request';
    }

    public static function get_name(): string {
        return get_string('event_request_submitted', 'local_airpay_request');
    }

    public function get_description(): string {
        return "User {$this->relateduserid} submitted request {$this->objectid} for course "
            . ($this->other['courseid'] ?? '?') . ".";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/airpay_request/view.php',
            ['id' => $this->objectid]);
    }
}
