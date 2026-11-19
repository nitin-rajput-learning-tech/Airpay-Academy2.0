<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * The browser monitor reports one behavioural event on the learner's own session.
 *
 * B1 (ADR-038 D2, 2026-11-20) tightens what a browser may write, without changing the signature:
 *  - only the event types the monitor produces (CLIENT_EVENTS); server events such as consent_given, attempt_* or
 *    session_end cannot be forged, and an unknown type is refused;
 *  - the severity is the server's, from CLIENT_EVENTS; the `severity` parameter is accepted and ignored;
 *  - only on an active session whose attempt is still open; a closed session is refused (error_session_state),
 *    which tells the monitor to stop;
 *  - the payload keeps only the quiz page number;
 *  - at most MAX_EVENTS per session; beyond that the call succeeds and stores nothing (eventid 0).
 * The time stored is always the server's.
 */
class report_event extends external_api {

    /** Event types the browser monitor may report, with the severity the server gives them. */
    public const CLIENT_EVENTS = [
        'page_shown'      => 'info',
        'tab_switch'      => 'warn',
        'window_blur'     => 'warn',
        'window_focus'    => 'info',
        'fullscreen_exit' => 'warn',
        'clipboard_paste' => 'critical',
    ];

    /** Upper bound on browser-reported events per session (a two-hour attempt rarely needs a hundred). */
    public const MAX_EVENTS = 2000;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'    => new external_value(PARAM_INT, 'The learner\'s proctoring session, as the attempt page was given it'),
            'event_type'   => new external_value(PARAM_ALPHANUMEXT, 'One of the monitor\'s event types'),
            'severity'     => new external_value(PARAM_ALPHA, 'Ignored since B1: the server sets the severity',
                VALUE_DEFAULT, 'info'),
            'payload_json' => new external_value(PARAM_RAW, 'JSON object; only "page" (int) is kept',
                VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $sessionid, string $event_type,
                                    string $severity = 'info',
                                    string $payload_json = '{}'): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'sessionid', 'event_type', 'severity', 'payload_json'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:attempt', $ctx);

        $type = (string) $params['event_type'];
        if (!array_key_exists($type, self::CLIENT_EVENTS)) {
            throw new \moodle_exception('error_event_type', 'local_sentientia_proctoring');
        }

        $session = $DB->get_record('local_sentientia_proctor_sessions', ['id' => (int) $params['sessionid']]);
        if (!$session || (int) $session->userid !== (int) $USER->id
                || !\local_sentientia_proctoring\quiz_integration::accepts_client_events($session)) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }

        if ($DB->count_records('local_sentientia_proctor_events', ['sessionid' => (int) $session->id])
                >= self::MAX_EVENTS) {
            return ['eventid' => 0];
        }

        $payload = [];
        $raw = (string) $params['payload_json'];
        if ($raw !== '' && strlen($raw) <= 4096) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['page']) && is_numeric($decoded['page'])) {
                $payload['page'] = max(0, min(10000, (int) $decoded['page']));
            }
        }

        $id = \local_sentientia_proctoring\session_manager::record_event(
            (int) $session->id, $type, self::CLIENT_EVENTS[$type], $payload);

        return ['eventid' => (int) $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'eventid' => new external_value(PARAM_INT, 'The stored event, or 0 when the session is at its cap'),
        ]);
    }
}
