<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class get_attempt extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $sessionid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('sessionid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:viewattempts', $ctx);

        $s = $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $params['sessionid']], '*', MUST_EXIST);
        // ── B2 fix: tenant equality required ────────────────────────────
        \local_sentientia_platform\tenant::require_access((int) $s->costcenterid);

        $events = $DB->get_records('local_sentientia_proctor_events',
            ['sessionid' => $s->id], 'timecreated ASC');
        $recordings = $DB->get_records('local_sentientia_proctor_recordings',
            ['sessionid' => $s->id], 'chunk_idx ASC');
        $identity = $s->identity_id
            ? $DB->get_record('local_sentientia_proctor_identity', ['id' => $s->identity_id])
            : null;

        return [
            'session' => [
                'id'             => (int) $s->id,
                'userid'         => (int) $s->userid,
                'quizid'         => (int) $s->quizid,
                'status'         => $s->status,
                'risk_score'     => (float) ($s->risk_score ?? 0),
                'auto_decision'  => (string) ($s->auto_decision ?? ''),
                'human_decision' => (string) ($s->human_decision ?? ''),
                'started_at'     => $s->timestarted ? userdate($s->timestarted) : '',
                'finished_at'    => $s->timefinished ? userdate($s->timefinished) : '',
            ],
            'identity' => $identity ? [
                'provider'    => $identity->provider,
                'match_score' => (float) $identity->match_score,
                'passed'      => (bool) $identity->passed,
            ] : null,
            'events' => array_map(fn($e) => [
                'event_type'  => $e->event_type,
                'severity'    => $e->severity,
                'at'          => userdate($e->timecreated, '%H:%M:%S'),
            ], array_values($events)),
            'recordings_count' => count($recordings),
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'session' => new external_single_structure([
                'id'             => new external_value(PARAM_INT, ''),
                'userid'         => new external_value(PARAM_INT, ''),
                'quizid'         => new external_value(PARAM_INT, ''),
                'status'         => new external_value(PARAM_ALPHANUMEXT, ''),
                'risk_score'     => new external_value(PARAM_FLOAT, ''),
                'auto_decision'  => new external_value(PARAM_TEXT, ''),
                'human_decision' => new external_value(PARAM_TEXT, ''),
                'started_at'     => new external_value(PARAM_TEXT, ''),
                'finished_at'    => new external_value(PARAM_TEXT, ''),
            ]),
            'identity' => new external_single_structure([
                'provider'    => new external_value(PARAM_TEXT, ''),
                'match_score' => new external_value(PARAM_FLOAT, ''),
                'passed'      => new external_value(PARAM_BOOL, ''),
            ], '', VALUE_OPTIONAL),
            'events' => new external_multiple_structure(new external_single_structure([
                'event_type' => new external_value(PARAM_ALPHANUMEXT, ''),
                'severity'   => new external_value(PARAM_ALPHA, ''),
                'at'         => new external_value(PARAM_TEXT, ''),
            ])),
            'recordings_count' => new external_value(PARAM_INT, ''),
        ]);
    }
}
