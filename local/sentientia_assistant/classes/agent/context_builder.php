<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the RAG context the agent reasons over: the learner's catalog,
 * skills/designation, and progress — STRICTLY scoped to their own tenant.
 *
 * This is read-only retrieval. Everything assembled here describes only
 * the acting user's own situation, so it cannot leak another tenant's or
 * another user's data into the prompt. The returned context is plain text
 * handed to the model; PII beyond first name is deliberately minimised.
 *
 * @package local_sentientia_assistant
 */
class context_builder {

    /** Cap enrolled-course lines included in the context. */
    private const MAX_COURSES = 15;
    /** Cap recommendation candidates surfaced into context. */
    private const MAX_CANDIDATES = 10;

    /**
     * Build the agent's grounding context for a user.
     *
     * @param int $userid Acting user id.
     * @return string Plain-text context block.
     */
    public static function build(int $userid): string {
        global $DB;

        // Schema-portable SELECT: the BizLMS open_* columns don't exist on
        // a vanilla / Customer-N schema, and naming them fatals the query
        // (same fix class as aiquiz draft_manager, 2026-08-04).
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return '';
        }

        $tenantroot = \local_sentientia_platform\tenant::root_for_user($user);

        $lines = [];
        $lines[] = 'Learner: ' . $user->firstname;
        $lines[] = 'Role: ' . (($user->open_designation ?? '') ?: 'Employee');
        $lines[] = 'Tenant root: ' . $tenantroot;

        // ── Enrolled courses + completion status (own data only) ────────
        $enrolled = $DB->get_records_sql(
            "SELECT c.id, c.fullname,
                    CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                         WHEN cc.id IS NOT NULL THEN 'in_progress'
                         ELSE 'enrolled' END AS status
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
          LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = :uid
              WHERE ue.userid = :uid2 AND c.visible = 1 AND c.id > 1
           ORDER BY c.fullname ASC",
            ['uid' => $userid, 'uid2' => $userid], 0, self::MAX_COURSES);

        if ($enrolled) {
            $lines[] = '';
            $lines[] = 'Enrolled courses:';
            foreach ($enrolled as $c) {
                $lines[] = '- [id=' . $c->id . '] ' . format_string($c->fullname)
                    . ' (' . $c->status . ')';
            }
        }

        // ── Gap candidates: visible, tenant-scoped, NOT yet enrolled ────
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('c', 'open_path', true);
        $params = $tnargs + ['uid' => $userid];
        $candidates = $DB->get_records_sql(
            "SELECT c.id, c.fullname
               FROM {course} c
              WHERE c.visible = 1 AND c.id > 1 AND {$tnsql}
                AND NOT EXISTS (
                    SELECT 1 FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid = c.id AND ue.userid = :uid)
           ORDER BY c.fullname ASC",
            $params, 0, self::MAX_CANDIDATES);

        if ($candidates) {
            $lines[] = '';
            $lines[] = 'Available courses (not yet enrolled, this tenant only):';
            foreach ($candidates as $c) {
                $lines[] = '- [id=' . $c->id . '] ' . format_string($c->fullname);
            }
        }

        return implode("\n", $lines);
    }
}
