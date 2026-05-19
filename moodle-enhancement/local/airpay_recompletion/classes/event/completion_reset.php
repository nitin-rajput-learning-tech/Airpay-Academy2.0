<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_recompletion\event;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #20 (2026-05-16) — fires from `recompletion_engine::reset_user_in_course()`
 * after a successful reset commits.
 *
 * Closes audit item #19 from
 * parity-audit-2026-05-15/airpay_recompletion.md.
 *
 * BizLMS shipped `\local_recompletion\event\completion_reset` so other
 * plugins (notifications, analytics, custom reports) could observe
 * resets. Airpay shipped without one, leaving the audit trail invisible
 * to observers. This event closes that gap.
 *
 * Recipients can register a handler in their own `db/events.php`:
 *
 *   $observers = [[
 *     'eventname' => '\\local_airpay_recompletion\\event\\completion_reset',
 *     'callback'  => '\\my_plugin\\observer::on_recompletion_reset',
 *   ]];
 *
 * The event also lands in `mdl_logstore_standard_log` automatically
 * (every triggered event does), giving compliance auditors a SIEM-
 * compatible record of every reset without writing custom code.
 *
 * `other` payload:
 *   reset_by_userid  — Site admin who triggered the reset (null for cron).
 *   reset_grades     — 1 if grades were cleared.
 *   reset_attempts   — 1 if quiz attempts were cleared.
 *   reason           — One of: 'cron', 'manual', 'bulk' (matches the
 *                       reason column on `local_airpay_recompletion_history`).
 *
 * No `objecttable` is set because the `course_completions` row is
 * deleted by the time the event fires; there's no stable objectid.
 * Observers should rely on `relateduserid` + `courseid` to identify
 * the affected pair, and use `local_airpay_recompletion_history` for
 * the persisted previous_timecompleted timestamp.
 *
 * @package    local_airpay_recompletion
 */
class completion_reset extends \core\event\base {

    protected function init(): void {
        // CRUD type 'u' = update. We're modifying a user's completion
        // state, even though the underlying course_completions row is
        // deleted (Moodle rebuilds it on next access).
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        // Deliberately NOT setting objecttable — the course_completions
        // row is gone by the time the event fires, so there's no stable
        // objectid to reference. relateduserid + courseid fully identify
        // the affected data; observers should use those.
    }

    public static function get_name(): string {
        return get_string('event_completion_reset',
            'local_airpay_recompletion');
    }

    public function get_description(): string {
        $reset_by = $this->other['reset_by_userid'] ?? null;
        $reason   = $this->other['reason'] ?? 'cron';

        if ($reset_by) {
            return "User {$reset_by} reset the course completion of "
                . "user with id '{$this->relateduserid}' in course "
                . "with id '{$this->courseid}' (reason: {$reason}).";
        }

        return "The recompletion engine reset the course completion of "
            . "user with id '{$this->relateduserid}' in course "
            . "with id '{$this->courseid}' (reason: {$reason}).";
    }

    public function get_url(): \moodle_url {
        // History page is the canonical audit view. Filter by user via
        // `?userid=N` once that filter exists; for now just deep-link.
        return new \moodle_url(
            '/local/airpay_recompletion/history.php',
            ['courseid' => $this->courseid]);
    }

    /**
     * Validate required fields on triggered events. Moodle 5 calls this
     * in development mode to catch missing fields.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception(
                'completion_reset event must set relateduserid');
        }
        if (!isset($this->courseid)) {
            throw new \coding_exception(
                'completion_reset event must set courseid');
        }
    }
}
