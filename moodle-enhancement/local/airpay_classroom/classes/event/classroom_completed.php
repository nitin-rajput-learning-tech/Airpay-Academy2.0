<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\event;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — fired when an admin closes a classroom (status → COMPLETED).
 *
 * Listened to by:
 *   - `\local_sentientia_evaluation\observer::classroom_ended` (W1-5) — fans out
 *     post-classroom evaluation forms to all attendees.
 *   - logstore_standard_log — SOX/SIEM audit trail.
 *
 * Emit pattern (from session_manager::change_status):
 *
 *     \local_airpay_classroom\event\classroom_completed::create([
 *         'context'  => \context_system::instance(),
 *         'objectid' => $classroomid,
 *         'userid'   => $USER->id,                   // who closed it
 *         'other'    => ['classroomid' => $classroomid],
 *     ])->trigger();
 *
 * @package local_airpay_classroom
 */
class classroom_completed extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_airpay_classroom';
    }

    public static function get_name(): string {
        return get_string('event_classroom_completed', 'local_airpay_classroom');
    }

    public function get_description(): string {
        return "User {$this->userid} marked classroom {$this->objectid} as completed.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/airpay_classroom/view.php',
            ['id' => $this->objectid]);
    }
}
