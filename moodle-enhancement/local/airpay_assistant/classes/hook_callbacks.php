<?php
/**
 * Hook callbacks for local_airpay_assistant.
 *
 * Replaces the deprecated `local_airpay_assistant_before_footer()` function
 * (Moodle pre-5.x callback pattern) with the Moodle 5.x hook system.
 *
 * @package    local_airpay_assistant
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_assistant;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for output-related hooks.
 */
class hook_callbacks {

    /**
     * Inject the chat bubble into every page footer for logged-in users.
     *
     * Migrated from the legacy `before_footer` function (Moodle 5.x hook system).
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $USER, $OUTPUT, $PAGE, $DB;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Check if admin has disabled the chatbot site-wide.
        if (!get_config('local_airpay_assistant', 'enabled')) {
            return;
        }

        // Don't show on login/admin upgrade pages.
        $pagetype = $PAGE->pagetype ?? '';
        if (strpos($pagetype, 'login') !== false || strpos($pagetype, 'admin-index') !== false) {
            return;
        }

        // Calculate remaining queries today.
        $todaystart = strtotime('today');
        $used = $DB->count_records_select(
            'local_airpay_chat_log',
            "userid = :uid AND role = 'user' AND timecreated >= :today",
            ['uid' => $USER->id, 'today' => $todaystart]
        );
        $limit = get_config('local_airpay_assistant', 'rate_limit') ?: 20;
        $remaining = max(0, $limit - $used);

        $data = [
            'firstname'         => format_string($USER->firstname),
            'queries_remaining' => $remaining,
        ];

        $hook->add_html($OUTPUT->render_from_template('local_airpay_assistant/chat_bubble', $data));
    }
}
