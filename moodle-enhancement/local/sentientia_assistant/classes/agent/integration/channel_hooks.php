<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent\integration;

use local_sentientia_assistant\agent\tool_result;

defined('MOODLE_INTERNAL') || die();

/**
 * Embedding hooks that let the agentic copilot reach the learner on
 * WhatsApp (reuse local_sentientia_whatsapp) and Microsoft Teams (reuse
 * local_sentientia_m365), WITHOUT this plugin hard-depending on either.
 *
 * Every reuse is class_exists-guarded: if the integration plugin is absent
 * in a given Sentientia deployment, the hook is a silent no-op. No new
 * external HTTP is introduced here — the WhatsApp path delegates to the
 * existing notification_bridge (mock-mode by default; live sends still
 * require that plugin's own live flag), and the Teams path is intentionally
 * a deep-link surface (no Graph write) so this build introduces NO live
 * API spend and NO [CONFIRM]-gated calls.
 *
 * These hooks are fired AFTER a tool has already executed under the full
 * guard chain — they only notify; they never authorise.
 *
 * @package local_sentientia_assistant
 */
class channel_hooks {

    /** Sub-flag the WhatsApp bridge keys its channel gate on. */
    private const WHATSAPP_SUB_FLAG = 'engagement.whatsapp.reminders';

    /**
     * Notify the learner of a completed agent action across whatever
     * embedding channels are available + opted-in. Best-effort, never
     * throws — a notification failure must not affect the action's outcome.
     *
     * @param int         $userid Acting learner id.
     * @param tool_result $result The executed tool's result.
     * @param string      $toolname The tool that ran (for template selection).
     * @return array Map of channel => status string (for tests/telemetry).
     */
    public static function notify(int $userid, tool_result $result, string $toolname): array {
        $statuses = [];

        // Only notify on a genuine state change (executed). No-ops, denials
        // and read-only recommendations don't warrant a push.
        if (!$result->statechanged) {
            return $statuses;
        }

        $statuses['whatsapp'] = self::push_whatsapp($userid, $result, $toolname);
        $statuses['teams']    = self::push_teams($userid, $result, $toolname);
        return $statuses;
    }

    /**
     * WhatsApp embedding hook — reuse local_sentientia_whatsapp's bridge.
     *
     * @return string Status: 'absent'|'no_user'|<bridge status>|'error'.
     */
    private static function push_whatsapp(int $userid, tool_result $result, string $toolname): string {
        global $DB;

        if (!class_exists('\\local_sentientia_whatsapp\\notification_bridge')) {
            return 'absent';
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname');
        if (!$user) {
            return 'no_user';
        }

        try {
            // Reuse the existing bridge. It enforces its own master flag,
            // opt-in, DLT-template approval and mock/live gating — we add
            // nothing live here. We pass a generic copilot-action template
            // key; if the deployment hasn't approved it, the bridge returns
            // 'no_template' and nothing is sent.
            $status = \local_sentientia_whatsapp\notification_bridge::also_send(
                $user,
                self::WHATSAPP_SUB_FLAG,
                'copilot_action_done',
                [
                    'firstname' => $user->firstname,
                    'action'    => $result->message,
                ],
                []
            );
            return $status ?? 'gated';
        } catch (\Throwable $e) {
            debugging('copilot whatsapp hook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 'error';
        }
    }

    /**
     * Microsoft Teams embedding hook — reuse local_sentientia_m365.
     *
     * Deliberately a deep-link surface, not a Graph write: we return a
     * Teams deep-link the UI can render so the learner can open the
     * copilot in Teams. No Graph POST, no API spend, no [CONFIRM] gate.
     * The presence of local_sentientia_m365 is the only signal we use to
     * decide whether Teams embedding is available for this customer.
     *
     * @return string Status: 'absent'|'linked'|'error'.
     */
    private static function push_teams(int $userid, tool_result $result, string $toolname): string {
        global $CFG;

        // Reuse signal: only offer Teams embedding when the m365 plugin is
        // installed (i.e. the customer has the integration).
        if (!class_exists('\\local_sentientia_m365\\graph_client')) {
            return 'absent';
        }

        try {
            // Build a Teams deep-link to the copilot surface. This is a
            // surface hook only — the actual card render lives in the
            // (future) Teams app manifest; here we expose the link so the
            // UI / a future bot can use it. No external call is made.
            $deeplink = 'https://teams.microsoft.com/l/entity/'
                . 'sentientia.copilot/'
                . rawurlencode($CFG->wwwroot . '/local/sentientia_assistant/agent.php');
            // Stash on a request-scoped static so callers/tests can read it.
            self::$lastteamslink = $deeplink;
            return 'linked';
        } catch (\Throwable $e) {
            debugging('copilot teams hook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 'error';
        }
    }

    /** @var string|null Last Teams deep-link produced (request-scoped, for UI/tests). */
    private static ?string $lastteamslink = null;

    /** Accessor for the last Teams deep-link (UI/test helper). */
    public static function last_teams_link(): ?string {
        return self::$lastteamslink;
    }
}
