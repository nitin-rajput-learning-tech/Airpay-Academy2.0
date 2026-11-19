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
use local_sentientia_proctoring\quiz_integration;
use local_sentientia_proctoring\secure_mode;

/**
 * The secure-mode attempt page reports its events and its heartbeat (B2, ADR-038 D3 and D6, 2026-11-20).
 *
 * Called by local_sentientia_proctoring/secure_mode through core/ajax, so the session key is checked by core's ajax
 * endpoint (lib/ajax/service.php) on every call. The browser names only the attempt it shows; the server finds the
 * session from it and refuses anything but the caller's own open attempt with an active secure-mode session. The
 * server stores its own time on every event, decides which events count as violations, and may submit the attempt
 * (hiring assessments at their limit). Rate limits and batch size: secure_mode::report().
 *
 * The flag is not read here: a secure session exists only because the flag was ON at the pre-start check, and its
 * attempt keeps the rules the learner accepted until it ends.
 */
class secure_report extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The open quiz attempt the page shows'),
            'events'    => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'One of the secure-mode event types'),
                    'page' => new external_value(PARAM_INT, 'Quiz page number; -1 on the summary page', VALUE_DEFAULT, 0),
                ]),
                'Events since the last report, oldest first; at most 20 are read. Empty for a heartbeat only.',
                VALUE_DEFAULT, []
            ),
        ]);
    }

    public static function execute(int $attemptid, array $events = []): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            ['attemptid' => $attemptid, 'events' => $events]);

        $attempt = $DB->get_record('quiz_attempts', ['id' => (int) $params['attemptid']],
            'id, quiz, userid, state, preview', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', (int) $attempt->quiz, 0, false, MUST_EXIST);
        self::validate_context(\context_module::instance((int) $cm->id));
        require_capability('local/sentientia_proctoring:attempt', \context_system::instance());

        if ((int) $attempt->userid !== (int) $USER->id) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }
        $viewurl = (new \moodle_url('/mod/quiz/view.php', ['id' => (int) $cm->id]))->out(false);
        if (quiz_integration::open_attempt((int) $attempt->id) === null) {
            // Submitted elsewhere (another tab, the timer, cron) or a preview: the page stops reporting.
            return ['closed' => true, 'autosubmitted' => false, 'violations' => 0, 'limit' => 0, 'counted' => 0,
                'redirecturl' => $viewurl];
        }
        $session = quiz_integration::session_for_attempt((int) $USER->id, (int) $attempt->quiz, (int) $attempt->id);
        if (!$session || $session->status !== quiz_integration::ACTIVE
                || !secure_mode::is_level($session->securitylevel ?? null)) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }

        $state = secure_mode::report($session, $attempt, $params['events']);
        return [
            'closed'        => $state['autosubmitted'],
            'autosubmitted' => $state['autosubmitted'],
            'violations'    => $state['violations'],
            'limit'         => $state['limit'],
            'counted'       => $state['counted'],
            'redirecturl'   => $state['autosubmitted'] ? $viewurl : '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'closed'        => new external_value(PARAM_BOOL, 'The attempt is no longer open: stop reporting'),
            'autosubmitted' => new external_value(PARAM_BOOL, 'This call submitted the attempt (violation limit)'),
            'violations'    => new external_value(PARAM_INT, 'Violations the server has counted on this attempt'),
            'limit'         => new external_value(PARAM_INT, 'Violations at which the attempt is submitted; 0 = never'),
            'counted'       => new external_value(PARAM_INT, 'Violations counted by this call'),
            'redirecturl'   => new external_value(PARAM_LOCALURL, 'Where to go once the attempt is closed, or empty'),
        ]);
    }
}
