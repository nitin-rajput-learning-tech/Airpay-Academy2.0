<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_proctoring\secure_mode;
use local_sentientia_proctoring\session_manager;

/**
 * The integrity report of one secure-mode attempt, for the teacher on the quiz's attempt review page (B2, ADR-038
 * D3 and D6, 2026-11-20): the level and policy, the violations the server counted, the counts per event, and the
 * timeline with the server's times and, for each violation, whether it counted and why not.
 *
 * Who may read it: a user with mod/quiz:viewreports in the quiz (its teachers), who may also see the session's tenant
 * (session_manager::require_session_access(), ADR-031). A teacher outside that tenant gets available = false, not an
 * error, so the review page itself is never affected. The learner does not see it (no mod/quiz:viewreports).
 */
class integrity_report extends external_api {

    /** Events listed in the timeline at most (the newest). */
    public const TIMELINE_MAX = 200;

    /** Bookkeeping events left out of the counts (they are in the timeline). */
    private const NOT_COUNTED_TYPES = ['page_served', 'page_shown', 'session_start', 'session_end', 'attempt_linked',
        'secure_rules_accepted', 'consent_given', 'identity_confirmed', 'window_focus', 'fullscreen_enter',
        'attempt_submitted', 'attempt_abandoned', 'attempt_overdue'];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The quiz attempt shown on the review page'),
        ]);
    }

    public static function execute(int $attemptid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        $attempt = $DB->get_record('quiz_attempts', ['id' => (int) $params['attemptid']], 'id, quiz, userid', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', (int) $attempt->quiz, 0, false, MUST_EXIST);
        $context = \context_module::instance((int) $cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:viewreports', $context);

        $rows = $DB->get_records('local_sentientia_proctor_sessions',
            ['attemptid' => (int) $attempt->id, 'userid' => (int) $attempt->userid], 'id DESC', '*', 0, 1);
        $session = $rows ? reset($rows) : null;
        if (!$session || !secure_mode::is_level($session->securitylevel ?? null)) {
            return self::unavailable();
        }
        try {
            session_manager::require_session_access((int) $session->costcenterid);
        } catch (\moodle_exception $e) {
            return self::unavailable();
        }
        return self::build($session);
    }

    /**
     * The report data of a secure session.
     *
     * @param \stdClass $session
     * @return array
     */
    public static function build(\stdClass $session): array {
        global $DB;
        $rule = 'quizaccess_sentientia_proctoring';
        $local = 'local_sentientia_proctoring';
        $sm = get_string_manager();

        $total = $DB->count_records('local_sentientia_proctor_events', ['sessionid' => (int) $session->id]);
        $countrows = $DB->get_records_sql(
            'SELECT event_type, COUNT(1) AS n
               FROM {local_sentientia_proctor_events}
              WHERE sessionid = :s
           GROUP BY event_type', ['s' => (int) $session->id]);
        $bytype = [];
        foreach ($countrows as $row) {
            $bytype[$row->event_type] = (int) $row->n;
        }
        $counts = [];
        foreach ($bytype as $type => $n) {
            if (in_array($type, self::NOT_COUNTED_TYPES, true)) {
                continue;
            }
            $counts[] = ['type' => $type, 'label' => self::label($type), 'count' => $n];
        }
        usort($counts, fn($a, $b) => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);

        $newest = $DB->get_records('local_sentientia_proctor_events', ['sessionid' => (int) $session->id],
            'timecreated DESC, id DESC', '*', 0, self::TIMELINE_MAX);
        $timeline = [];
        foreach (array_reverse($newest) as $event) {
            $payload = json_decode((string) ($event->payload_json ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $isviolation = in_array($event->event_type, secure_mode::VIOLATIONS, true) && isset($payload['counted']);
            $counted = $isviolation && !empty($payload['counted']);
            $timeline[] = [
                'time'       => userdate((int) $event->timecreated, '%d %b, %H:%M:%S'),
                'label'      => self::label($event->event_type),
                'page'       => self::page_label($payload),
                'severity'   => (string) $event->severity,
                'counted'    => $counted,
                'notcounted' => $isviolation && !$counted,
                'note'       => self::note($event->event_type, $payload),
            ];
        }

        $limit = (int) ($session->violationlimit ?? 0);
        $level = (string) $session->securitylevel;
        $served = $bytype['page_served'] ?? 0;
        $shown = $bytype['page_shown'] ?? 0;
        $reviewurl = '';
        if (has_capability('local/sentientia_proctoring:viewattempts', \context_system::instance())) {
            $reviewurl = (new \moodle_url('/local/sentientia_proctoring/attempt.php', ['id' => (int) $session->id]))
                ->out(false);
        }
        $decision = function(?string $value) use ($sm, $local): string {
            if ($value === null || $value === '') {
                return '';
            }
            return $sm->string_exists('review_decision_' . $value, $local)
                ? get_string('review_decision_' . $value, $local) : $value;
        };

        return [
            'available'       => true,
            'level'           => $sm->string_exists('securitylevel_' . $level, $rule)
                ? get_string('securitylevel_' . $level, $rule) : $level,
            'policy'          => $limit > 0 ? get_string('report_policy_autosubmit', $local, $limit)
                : get_string('report_policy_warn', $local),
            'violations'      => (int) ($session->violations ?? 0),
            'limit'           => $limit,
            'haslimit'        => $limit > 0,
            'autosubmitted'   => !empty($session->timeautosubmitted),
            'autosubmittedat' => !empty($session->timeautosubmitted) ? userdate((int) $session->timeautosubmitted) : '',
            'status'          => $sm->string_exists('status_' . $session->status, $local)
                ? get_string('status_' . $session->status, $local) : (string) $session->status,
            'autodecision'    => $decision($session->auto_decision ?? null),
            'humandecision'   => $decision($session->human_decision ?? null),
            'gaps'            => $bytype['heartbeat_gap'] ?? 0,
            'unmonitored'     => max(0, $served - $shown),
            'reviewurl'       => $reviewurl,
            'counts'          => $counts,
            'hascounts'       => !empty($counts),
            'timeline'        => $timeline,
            'hastimeline'     => !empty($timeline),
            'truncated'       => $total > count($timeline),
            'shown'           => count($timeline),
            'total'           => $total,
        ];
    }

    /**
     * The empty answer: no secure session on this attempt, or not one this viewer may see.
     *
     * @return array
     */
    private static function unavailable(): array {
        return ['available' => false, 'level' => '', 'policy' => '', 'violations' => 0, 'limit' => 0,
            'haslimit' => false, 'autosubmitted' => false, 'autosubmittedat' => '', 'status' => '', 'autodecision' => '',
            'humandecision' => '', 'gaps' => 0, 'unmonitored' => 0, 'reviewurl' => '', 'counts' => [],
            'hascounts' => false, 'timeline' => [], 'hastimeline' => false, 'truncated' => false, 'shown' => 0,
            'total' => 0];
    }

    /**
     * The reviewer's label of an event type.
     *
     * @param string $type
     * @return string
     */
    private static function label(string $type): string {
        $key = 'event_' . $type;
        return get_string_manager()->string_exists($key, 'local_sentientia_proctoring')
            ? get_string($key, 'local_sentientia_proctoring') : $type;
    }

    /**
     * The page an event happened on, as the teacher counts pages (from 1), or the summary page.
     *
     * @param array $payload
     * @return string
     */
    private static function page_label(array $payload): string {
        if (!isset($payload['page']) || !is_numeric($payload['page'])) {
            return '';
        }
        $page = (int) $payload['page'];
        return $page === secure_mode::SUMMARY_PAGE
            ? get_string('report_summarypage', 'local_sentientia_proctoring') : (string) ($page + 1);
    }

    /**
     * What the timeline says about an event beyond its label.
     *
     * @param string $type
     * @param array $payload
     * @return string
     */
    private static function note(string $type, array $payload): string {
        $c = 'local_sentientia_proctoring';
        if (in_array($type, secure_mode::VIOLATIONS, true) && isset($payload['counted'])) {
            if (!empty($payload['counted'])) {
                // B2 review: a lost focus that began in the grace after a page opened and outlasted it.
                return ($payload['reason'] ?? '') === 'grace_exceeded'
                    ? get_string('report_counted_grace_exceeded', $c, secure_mode::GRACE_SECONDS)
                    : get_string('report_counted', $c);
            }
            return ($payload['reason'] ?? '') === 'grace'
                ? get_string('report_notcounted_grace', $c, secure_mode::GRACE_SECONDS)
                : get_string('report_notcounted_duplicate', $c, secure_mode::DEDUP_SECONDS);
        }
        switch ($type) {
            case 'heartbeat_gap':
                return get_string('report_note_gap', $c, (int) ($payload['seconds'] ?? 0));
            case 'monitor_missing':
                return get_string('report_note_missing', $c,
                    (object) ['served' => (int) ($payload['served'] ?? 0), 'shown' => (int) ($payload['shown'] ?? 0)]);
            case 'ip_changed':
                return get_string('report_note_ip', $c,
                    (object) ['from' => (string) ($payload['from'] ?? ''), 'to' => (string) ($payload['to'] ?? '')]);
            case 'integrity_autosubmitted':
                return get_string('report_note_autosubmitted', $c,
                    (object) ['violations' => (int) ($payload['violations'] ?? 0), 'limit' => (int) ($payload['limit'] ?? 0)]);
            default:
                return '';
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available'       => new external_value(PARAM_BOOL, 'A secure-mode session exists and the viewer may see it'),
            'level'           => new external_value(PARAM_TEXT, 'Security level, as a label'),
            'policy'          => new external_value(PARAM_TEXT, 'What happens on violations'),
            'violations'      => new external_value(PARAM_INT, 'Violations the server counted'),
            'limit'           => new external_value(PARAM_INT, 'Auto-submit limit; 0 = never'),
            'haslimit'        => new external_value(PARAM_BOOL, 'The attempt had an auto-submit limit'),
            'autosubmitted'   => new external_value(PARAM_BOOL, 'The server submitted the attempt'),
            'autosubmittedat' => new external_value(PARAM_TEXT, 'When, or empty'),
            'status'          => new external_value(PARAM_TEXT, 'Session status, as a label'),
            'autodecision'    => new external_value(PARAM_TEXT, 'Automatic verdict, as a label, or empty'),
            'humandecision'   => new external_value(PARAM_TEXT, 'Reviewer verdict, as a label, or empty'),
            'gaps'            => new external_value(PARAM_INT, 'Heartbeat gaps'),
            'unmonitored'     => new external_value(PARAM_INT, 'Pages the server sent that never reported'),
            'reviewurl'       => new external_value(PARAM_LOCALURL, 'The session in the proctoring pages, or empty'),
            'counts'          => new external_multiple_structure(new external_single_structure([
                'type'  => new external_value(PARAM_ALPHANUMEXT, 'Event type'),
                'label' => new external_value(PARAM_TEXT, 'Event label'),
                'count' => new external_value(PARAM_INT, 'How many'),
            ])),
            'hascounts'       => new external_value(PARAM_BOOL, 'There are counts to show'),
            'timeline'        => new external_multiple_structure(new external_single_structure([
                'time'       => new external_value(PARAM_TEXT, 'Server time, in the viewer\'s time zone'),
                'label'      => new external_value(PARAM_TEXT, 'Event label'),
                'page'       => new external_value(PARAM_TEXT, 'Quiz page (from 1), "Summary", or empty'),
                'severity'   => new external_value(PARAM_ALPHA, 'info, warn or critical'),
                'counted'    => new external_value(PARAM_BOOL, 'A violation that counted'),
                'notcounted' => new external_value(PARAM_BOOL, 'A violation that did not count'),
                'note'       => new external_value(PARAM_TEXT, 'Why, or other details'),
            ])),
            'hastimeline'     => new external_value(PARAM_BOOL, 'There are events to show'),
            'truncated'       => new external_value(PARAM_BOOL, 'Only the newest events are listed'),
            'shown'           => new external_value(PARAM_INT, 'Events listed'),
            'total'           => new external_value(PARAM_INT, 'Events recorded'),
        ]);
    }
}
