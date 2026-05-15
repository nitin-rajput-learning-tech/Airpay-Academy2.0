<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\event;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — fired when a user finishes all mandatory levels of a program.
 *
 * Listened to by:
 *   - `\local_airpay_evaluation\observer::program_completed` (W1-5) — queues
 *     post-program evaluation forms with the configured `days_after` delay.
 *   - logstore_standard_log — SOX/SIEM audit trail.
 *
 * Emit pattern (from program_manager / observer):
 *
 *     \local_airpay_programs\event\program_completed::create([
 *         'context'        => \context_system::instance(),
 *         'objectid'       => $programid,
 *         'relateduserid'  => $userid,
 *         'other'          => ['programid' => $programid],
 *     ])->trigger();
 *
 * @package local_airpay_programs
 */
class program_completed extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'u';  // updated user state (program-complete)
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_airpay_programs';
    }

    public static function get_name(): string {
        return get_string('event_program_completed', 'local_airpay_programs');
    }

    public function get_description(): string {
        return "User {$this->relateduserid} completed program {$this->objectid}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/airpay_programs/view.php',
            ['id' => $this->objectid]);
    }
}
