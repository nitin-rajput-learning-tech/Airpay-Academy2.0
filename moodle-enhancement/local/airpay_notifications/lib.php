<?php
/**
 * Lib functions for Airpay Smart Notifications.
 *
 * @package    local_airpay_notifications
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get recent notifications for navbar dropdown.
 *
 * @param int $userid User ID
 * @param int $limit  Max notifications to return
 * @return array Template-ready data
 */
function local_airpay_notifications_get_for_navbar(int $userid, int $limit = 10): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT id, ruleid, subject, message, channel, status, courseid, timecreated, timeread
           FROM {local_airpay_notif_log}
          WHERE userid = :uid
       ORDER BY timecreated DESC",
        ['uid' => $userid], 0, $limit);

    $unread = 0;
    $notifications = [];
    $now = time();

    foreach ($records as $r) {
        $is_unread = ($r->status === 'sent' && !$r->timeread);
        if ($is_unread) {
            $unread++;
        }

        // Determine type based on rule or content.
        $type = 'system';
        $rule = $DB->get_record('local_airpay_notif_rules', ['id' => $r->ruleid], 'rule_type');
        if ($rule) {
            $typemap = [
                'deadline_approaching' => 'urgent',
                'course_not_started'   => 'learning',
                'streak_broken'        => 'learning',
                'manager_nudge'        => 'urgent',
                'achievement_earned'   => 'achievement',
                'new_course'           => 'learning',
            ];
            $type = $typemap[$rule->rule_type] ?? 'system';
        }

        // Time ago.
        $diff = $now - $r->timecreated;
        if ($diff < 60) {
            $timeago = 'Just now';
        } else if ($diff < 3600) {
            $timeago = round($diff / 60) . 'm ago';
        } else if ($diff < 86400) {
            $timeago = round($diff / 3600) . 'h ago';
        } else {
            $timeago = round($diff / 86400) . 'd ago';
        }

        $notifications[] = [
            'id'             => $r->id,
            'subject'        => format_string($r->subject),
            'message'        => format_string(substr($r->message, 0, 120)),
            'channel'        => $r->channel,
            'status'         => $r->status,
            'courseid'       => $r->courseid,
            'timecreated'    => $r->timecreated,
            'timeago'        => $timeago,
            'type'           => $type,
            'is_unread'      => $is_unread,
            'is_urgent'      => ($type === 'urgent'),
            'is_learning'    => ($type === 'learning'),
            'is_achievement' => ($type === 'achievement'),
            'is_system'      => ($type === 'system'),
        ];
    }

    return [
        'notifications'    => $notifications,
        'unread_count'     => $unread,
        'has_notifications' => !empty($notifications),
        'has_unread'       => ($unread > 0),
    ];
}

/**
 * Mark a notification as read.
 */
function local_airpay_notifications_mark_read(int $notifid, int $userid): bool {
    global $DB;
    return $DB->set_field_select('local_airpay_notif_log',
        'status', 'read',
        'id = :id AND userid = :uid AND status = :sent',
        ['id' => $notifid, 'uid' => $userid, 'sent' => 'sent']);
}

/**
 * Mark all notifications as read for a user.
 */
function local_airpay_notifications_mark_all_read(int $userid): void {
    global $DB;
    $DB->execute(
        "UPDATE {local_airpay_notif_log} SET status = 'read', timeread = :now
          WHERE userid = :uid AND status = 'sent'",
        ['now' => time(), 'uid' => $userid]);
}
