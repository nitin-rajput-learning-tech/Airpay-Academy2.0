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
     *
     * Gating order (any failure hides the bubble):
     *   1. User must be logged in and not guest
     *   2. Phase A0 feature flag `ai.assistant.enabled` must be on for the
     *      user's tenant — super admin controls this via /local/airpay_core
     *      /admin/switchboard.php. Tenant-scoped override is supported.
     *   3. Legacy `local_airpay_assistant/enabled` site config must be on
     *      (kept for backward compat — older deploys may still use it).
     *   4. Page must not be a login/upgrade page (chrome-less surfaces).
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $USER, $OUTPUT, $PAGE, $DB;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Phase A0 feature flag — tenant-scoped switchboard gate.
        // Returns false safely when the airpay_core plugin isn't installed
        // (e.g. during initial install) so this never blocks the user.
        if (class_exists('\\local_airpay_core\\feature_flags')
                && !\local_airpay_core\feature_flags::is_enabled('ai.assistant.enabled')) {
            return;
        }

        // Legacy site-wide kill switch — kept for backward compat with
        // any deployment that hasn't migrated to the Switchboard yet.
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
            'quick_actions'     => self::quick_actions(),
        ];

        $hook->add_html($OUTPUT->render_from_template('local_airpay_assistant/chat_bubble', $data));

        // Load the AMD module that actually wires up the bubble — without
        // this the toggle/send/Cmd+K do nothing. Moodle's $PAGE->requires
        // is the standard way to register an AMD module from a hook.
        $PAGE->requires->js_call_amd('local_airpay_assistant/chat', 'init');
    }

    /**
     * Build the role-appropriate quick-action chips for the current user.
     *
     * Refinement (2026-06-01): the chips used to be hardcoded in the template
     * and shown to EVERYONE — so a Public/external self-paced learner saw
     * "Team status" (they have no team) and "Quiz me on compliance" (they
     * aren't compliance-bound). The chips are now derived from the canonical
     * role tier (theme_airpayux\role_detector) + the tenant root
     * (local_sentientia_core\tenant_identity), both class_exists-guarded so
     * the assistant degrades to a safe learner set if the theme/seam is absent.
     *
     *   - Everyone           -> "What to learn next?", "Quiz me"
     *   - Internal learner   -> + "My deadlines"
     *   - Public/external    -> + "My certificates" (instead of deadlines)
     *   - Manager / Admin    -> + "Team status"
     *
     * @return array<int, array{query: string, label: string}>
     */
    private static function quick_actions(): array {
        $s = static function (string $key): string {
            return get_string($key, 'local_airpay_assistant');
        };

        // Role tier — reuse the single source of truth when present.
        if (class_exists('\\theme_airpayux\\role_detector')) {
            $roles = \theme_airpayux\role_detector::detect();
            $ismanagerish = !empty($roles['ismanager']) || !empty($roles['isadmin']);
        } else {
            // Theme absent (standalone / Enterprise N): fall back to the core
            // reports capability as a conservative manager signal.
            $ismanagerish = has_capability('moodle/site:viewreports',
                \context_system::instance());
        }

        // Tenant — Public (root 77) learners are external + self-paced.
        $tenantroot = 0;
        if (class_exists('\\local_sentientia_core\\tenant_identity')) {
            $tenantroot = \local_sentientia_core\tenant_identity::root_for_current_user();
        }
        $isexternal = ($tenantroot === 77);

        // 1. Learning recommendation — everyone.
        $actions = [
            ['query' => $s('qa_learn_q'), 'label' => $s('qa_learn')],
        ];

        // 2. Deadlines (internal, assignment-driven) vs certificates (external,
        //    self-paced). Managers/admins keep deadlines.
        if ($isexternal && !$ismanagerish) {
            $actions[] = ['query' => $s('qa_certs_q'), 'label' => $s('qa_certs')];
        } else {
            $actions[] = ['query' => $s('qa_deadlines_q'), 'label' => $s('qa_deadlines')];
        }

        // 3. Self-quiz — everyone (generic course framing, not compliance-only).
        $actions[] = ['query' => $s('qa_quiz_q'), 'label' => $s('qa_quiz')];

        // 4. Team status — only those who actually manage a team.
        if ($ismanagerish) {
            $actions[] = ['query' => $s('qa_team_q'), 'label' => $s('qa_team')];
        }

        return $actions;
    }
}
