<?php
/**
 * Per-user notification preferences manager.
 *
 * @package    local_airpay_notifications
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_notifications;

defined('MOODLE_INTERNAL') || die();

class prefs_manager {

    /** All rule_type values that can be toggled per-user. */
    public const RULE_TYPES = [
        'deadline_approaching'   => 'Course deadline approaching',
        'course_not_started'     => 'Enrolled but never started',
        'streak_broken'          => 'Streak about to break',
        'manager_nudge'          => 'Manager team-nudge digest',
        'new_course'             => 'New courses in my org',
        'compliance_overdue'     => 'Compliance overdue',
        'certificate_expiring'   => 'Certificate expiring',
        'ilt_feedback_pending'   => 'Pending ILT feedback',
        'learning_path_stalled'  => 'Learning path stalled',
        'enrolment_anniversary'  => 'Enrolment anniversary',
        'inactive_user'          => "We miss you (inactive)",
        'quiz_low_score'         => 'Retry suggestion (quiz)',
        'monthly_summary'        => 'Monthly team summary (managers)',
    ];

    public const DIGEST_FREQUENCIES = ['none', 'daily', 'weekly'];

    /**
     * Load (or default) one user's prefs row.
     *
     * @return object  with bool channel_inapp/email/push, string digest_frequency,
     *                 array disabled_rule_types, int|null quiet_hours_start/end,
     *                 int timemodified, int|null id (null if never saved).
     */
    public static function get_for_user(int $userid): object {
        global $DB;
        $row = $DB->get_record('local_airpay_notif_prefs', ['userid' => $userid]);
        if (!$row) {
            return (object) [
                'id'                  => null,
                'userid'              => $userid,
                'channel_inapp'       => 1,
                'channel_email'       => 1,
                'channel_push'        => 0,
                'digest_frequency'    => 'daily',
                'disabled_rule_types' => [],
                'quiet_hours_start'   => null,
                'quiet_hours_end'     => null,
                'timemodified'        => 0,
            ];
        }
        $row->disabled_rule_types = !empty($row->disabled_rule_types)
            ? array_values(array_filter(array_map('trim',
                explode(',', (string) $row->disabled_rule_types))))
            : [];
        $row->quiet_hours_start = $row->quiet_hours_start === null
            ? null : (int) $row->quiet_hours_start;
        $row->quiet_hours_end = $row->quiet_hours_end === null
            ? null : (int) $row->quiet_hours_end;
        return $row;
    }

    /**
     * Upsert one user's preference row.
     *
     * @param int   $userid              Target user
     * @param bool  $channel_inapp       Allow in-app messages?
     * @param bool  $channel_email       Allow email messages?
     * @param bool  $channel_push        Allow push messages?
     * @param string $digest_frequency   none|daily|weekly
     * @param string[] $disabled_rule_types  Rule-types to silence
     * @param int|null $quiet_hours_start  0..23 or null
     * @param int|null $quiet_hours_end    0..23 or null
     * @return int  Saved row ID
     */
    public static function save_for_user(int $userid,
                                          bool $channel_inapp,
                                          bool $channel_email,
                                          bool $channel_push,
                                          string $digest_frequency,
                                          array $disabled_rule_types,
                                          ?int $quiet_hours_start,
                                          ?int $quiet_hours_end): int {
        global $DB;
        if (!in_array($digest_frequency, self::DIGEST_FREQUENCIES, true)) {
            throw new \invalid_parameter_exception('Invalid digest_frequency');
        }
        $clean_rule_types = [];
        foreach ($disabled_rule_types as $rt) {
            $rt = trim((string) $rt);
            if ($rt !== '' && isset(self::RULE_TYPES[$rt])) {
                $clean_rule_types[] = $rt;
            }
        }
        if ($quiet_hours_start !== null
                && ($quiet_hours_start < 0 || $quiet_hours_start > 23)) {
            throw new \invalid_parameter_exception('quiet_hours_start out of range');
        }
        if ($quiet_hours_end !== null
                && ($quiet_hours_end < 0 || $quiet_hours_end > 23)) {
            throw new \invalid_parameter_exception('quiet_hours_end out of range');
        }
        $now = time();
        $existing = $DB->get_record('local_airpay_notif_prefs', ['userid' => $userid]);
        $payload = (object) [
            'userid'              => $userid,
            'channel_inapp'       => $channel_inapp ? 1 : 0,
            'channel_email'       => $channel_email ? 1 : 0,
            'channel_push'        => $channel_push ? 1 : 0,
            'digest_frequency'    => $digest_frequency,
            'disabled_rule_types' => implode(',', $clean_rule_types),
            'quiet_hours_start'   => $quiet_hours_start,
            'quiet_hours_end'     => $quiet_hours_end,
            'timemodified'        => $now,
        ];
        if ($existing) {
            $payload->id = $existing->id;
            $DB->update_record('local_airpay_notif_prefs', $payload);
            return (int) $existing->id;
        }
        return (int) $DB->insert_record('local_airpay_notif_prefs', $payload);
    }
}
