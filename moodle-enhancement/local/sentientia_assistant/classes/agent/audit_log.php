<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * Append-only audit logger for the agentic copilot.
 *
 * Every tool proposal — whether it ends up executed, denied, or no-op —
 * writes exactly one immutable row to {local_sentientia_agent_audit}. This
 * is the "platform authorizes and executes; the LLM only proposes" trail:
 * a reviewer can reconstruct precisely what the model asked for and what
 * the platform decided.
 *
 * SECURITY: args_json stores the UNTRUSTED proposed arguments verbatim
 * (for forensics) but is NEVER rendered raw to a browser — readers escape
 * it. `detail` must never contain secrets (the tools enforce this).
 *
 * @package local_sentientia_assistant
 */
class audit_log {

    /**
     * Record one proposal + outcome.
     *
     * @param int        $userid Acting user id.
     * @param tool_call  $call   The (untrusted) proposal.
     * @param tool_result $result The platform's authorisation/execution outcome.
     * @param string|null $idempotencykey Optional idempotency key for the call.
     * @return int New audit row id.
     */
    public static function record(int $userid, tool_call $call, tool_result $result,
                                   ?string $idempotencykey = null): int {
        global $DB;

        $costcenterid = \local_sentientia_platform\tenant::root_for_current_user();

        // Bound the stored args JSON so a hostile/huge proposal can't bloat
        // the table. Truncate defensively.
        $argsjson = json_encode($call->args);
        if (is_string($argsjson) && \core_text::strlen($argsjson) > 4000) {
            $argsjson = \core_text::substr($argsjson, 0, 4000);
        }

        return $DB->insert_record('local_sentientia_agent_audit', (object) [
            'userid'          => $userid,
            'costcenterid'    => $costcenterid,
            'tool'            => \core_text::substr($call->tool, 0, 64),
            'args_json'       => $argsjson !== false ? $argsjson : null,
            'proposed_by'     => \core_text::substr($call->proposedby, 0, 20),
            'outcome'         => $result->outcome,
            'detail'          => \core_text::substr($result->message, 0, 1000),
            'idempotency_key' => $idempotencykey,
            'timecreated'     => time(),
        ]);
    }

    /**
     * Recent audit rows for a manager view — STRICTLY tenant-scoped.
     *
     * The caller MUST hold local/sentientia_assistant:manageall. Site
     * admins see all tenants; everyone else sees only their own tenant's
     * rows. Enforced here so no caller can forget the scope.
     *
     * @param int $limit Max rows.
     * @return array
     */
    public static function recent_for_manager(int $limit = 100): array {
        global $DB;

        require_capability('local/sentientia_assistant:manageall',
            \context_system::instance());

        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('a');

        return $DB->get_records_sql(
            "SELECT a.id, a.userid, a.costcenterid, a.tool, a.outcome,
                    a.proposed_by, a.detail, a.timecreated,
                    u.firstname, u.lastname
               FROM {local_sentientia_agent_audit} a
          LEFT JOIN {user} u ON u.id = a.userid
              WHERE {$tnsql}
           ORDER BY a.timecreated DESC",
            $tnargs, 0, $limit);
    }
}
