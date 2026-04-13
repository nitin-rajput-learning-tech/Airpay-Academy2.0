<?php
/**
 * Lib — injects the chat bubble on all pages for logged-in users.
 *
 * @package    local_airpay_assistant
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject the chat bubble into every page via before_footer callback.
 */
function local_airpay_assistant_before_footer() {
    global $USER, $OUTPUT, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // Check if admin has disabled the chatbot site-wide.
    if (!get_config('local_airpay_assistant', 'enabled')) {
        return '';
    }

    // Don't show on login/admin upgrade pages.
    $pagetype = $PAGE->pagetype ?? '';
    if (strpos($pagetype, 'login') !== false || strpos($pagetype, 'admin-index') !== false) {
        return '';
    }

    // Calculate remaining queries today.
    global $DB;
    $today_start = strtotime('today');
    $used = $DB->count_records_select('local_airpay_chat_log',
        "userid = :uid AND role = 'user' AND timecreated >= :today",
        ['uid' => $USER->id, 'today' => $today_start]);
    $limit = get_config('local_airpay_assistant', 'rate_limit') ?: 20;
    $remaining = max(0, $limit - $used);

    $data = [
        'firstname'         => format_string($USER->firstname),
        'queries_remaining' => $remaining,
    ];

    return $OUTPUT->render_from_template('local_airpay_assistant/chat_bubble', $data);
}
