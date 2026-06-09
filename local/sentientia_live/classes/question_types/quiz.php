<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

use local_sentientia_live\response_recorder;
use local_sentientia_live\slide_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz question type — Phase E.8 implementation (D4, 2026-05-24).
 *
 * Mechanics:
 *   - Audience picks ONE of N options. After they submit they see a
 *     right/wrong badge instantly.
 *   - Trainer projector shows the option histogram PLUS a top-10
 *     fastest-correct leaderboard.
 *   - Scoring: 1 point per correct response, 0 per wrong (per-response
 *     scoring is exposed as score_response() for the future session
 *     rollup / global leaderboard tie-in).
 *
 * Settings shape:
 *   {options:       string[]   (2-20 strings, 1-200 chars each),
 *    correct_index: int        (REQUIRED — 0 ≤ N < count(options))}
 *
 * Tally shape:
 *   ['0' => count, '1' => count, ...,
 *    '_correct_index' => int,
 *    '_correct_count' => int,
 *    '_total'         => int,
 *    '_leaderboard'   => [{rank, participant_id, display_name,
 *                          elapsed_ms, elapsed_s, is_correct,
 *                          is_winner, score}, ...]]
 *
 * Response payload:
 *   ['slide_id' => int, 'option_index' => int]
 *
 * Leaderboard timing:
 *   `elapsed_ms` = response.timecreated - slide_changed.timecreated for
 *   this slide_id (fallback: session.timestarted, then session.timecreated).
 *   Top-10 winners ordered by elapsed_ms ASC, then response.id ASC for
 *   deterministic tie-breaks.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz extends abstract_question_type {

    public const SLUG = 'quiz';
    public const FEATURE_FLAG = 'live.questiontype.quiz';
    public const NAME_STRING_KEY = 'qtype_quiz_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_quiz_desc';

    /** Per-response point values. Future Phase E.8.b will add speed bonus. */
    public const POINTS_CORRECT = 1;
    public const POINTS_WRONG = 0;

    /** Hard cap on leaderboard entries. */
    public const LEADERBOARD_SIZE = 10;

    /**
     * @inheritDoc
     */
    public function render(array $context): string {
        global $OUTPUT;

        $slide = $context['slide']
            ?? throw new \coding_exception('render: $context[slide] required');
        $settings = $context['settings']
            ?? slide_manager::parse_settings($slide);
        $aria_id_prefix = $context['aria_id_prefix']
            ?? ('qt-quiz-' . (int) $slide->id);

        $options = [];
        foreach (($settings['options'] ?? []) as $i => $label) {
            $options[] = [
                'index'    => $i,
                'label'    => $label,
                'input_id' => $aria_id_prefix . '-opt-' . $i,
            ];
        }

        return $OUTPUT->render_from_template(
            'local_sentientia_live/qt_quiz_audience',
            [
                'slideid'        => (int) $slide->id,
                'sessionid'      => (int) $slide->sessionid,
                'aria_id_prefix' => $aria_id_prefix,
                'options'        => $options,
                'has_options'    => !empty($options),
                'sesskey'        => sesskey(),
                'submit_label'   => get_string('audience_submit_response',
                    'local_sentientia_live'),
                'legend'         => get_string('qt_quiz_audience_legend',
                    'local_sentientia_live'),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function persist_response(int $userid, array $payload): int {
        $slideid = $this->payload_slide_id($payload);
        $raw = $payload['option_index']
            ?? $payload['value_int']
            ?? null;
        if ($raw === null || $raw === '') {
            throw new \moodle_exception('response_int_required',
                'local_sentientia_live');
        }
        if (!is_int($raw) && !ctype_digit((string) $raw)) {
            throw new \moodle_exception('response_int_required',
                'local_sentientia_live');
        }
        return response_recorder::submit($slideid, $userid, (int) $raw, null);
    }

    /**
     * @inheritDoc
     */
    public function tally(int $sessionid, int $slideid): array {
        global $DB;
        $slide = slide_manager::get($slideid);
        if (!$slide) {
            return [];
        }
        $settings = slide_manager::parse_settings($slide);
        $options = $settings['options'] ?? [];
        $correct_index = (int) ($settings['correct_index'] ?? -1);

        $rows = $DB->get_records('local_sentientia_live_responses',
            ['slideid' => $slideid]);

        $distribution = [];
        foreach (array_keys($options) as $i) {
            $distribution[$i] = 0;
        }
        $correct_count = 0;
        $total = 0;
        foreach ($rows as $r) {
            if ($r->value_int === null) {
                continue;
            }
            $v = (int) $r->value_int;
            if (array_key_exists($v, $distribution)) {
                $distribution[$v]++;
                $total++;
                if ($correct_index >= 0 && $v === $correct_index) {
                    $correct_count++;
                }
            }
        }

        $out = $distribution;
        $out['_correct_index'] = $correct_index;
        $out['_correct_count'] = $correct_count;
        $out['_total']         = $total;
        $out['_leaderboard']   = $this->compute_leaderboard(
            $slideid, $sessionid, $correct_index);
        return $out;
    }

    /**
     * @inheritDoc
     */
    public function validate_config(array $config): array {
        $errors = [];

        $options = $config['options'] ?? null;
        if (!is_array($options) || count($options) < 2 || count($options) > 20) {
            $errors['options'] = get_string('mc_options_count',
                'local_sentientia_live',
                is_array($options) ? count($options) : 0);
            return $errors;
        }
        foreach ($options as $opt) {
            if (!is_string($opt)) {
                $errors['options'] = get_string('mc_option_type',
                    'local_sentientia_live');
                return $errors;
            }
            $trim = trim($opt);
            if ($trim === '' || mb_strlen($trim) > 200) {
                $errors['options'] = get_string('mc_option_length',
                    'local_sentientia_live');
                return $errors;
            }
        }

        // correct_index is REQUIRED for quiz.
        if (!array_key_exists('correct_index', $config)) {
            $errors['correct_index'] = get_string('quiz_correct_index_required',
                'local_sentientia_live');
            return $errors;
        }
        $raw = $config['correct_index'];
        if (!is_int($raw) && !ctype_digit((string) $raw)) {
            $errors['correct_index'] = get_string('quiz_correct_index_required',
                'local_sentientia_live');
            return $errors;
        }
        $correct = (int) $raw;
        if ($correct < 0 || $correct >= count($options)) {
            $errors['correct_index'] = get_string('quiz_correct_out_of_range',
                'local_sentientia_live');
        }

        return $errors;
    }

    /**
     * @inheritDoc
     */
    public function get_aria_announcements(): array {
        return [
            'response_recorded' => get_string('a11y_response_recorded',
                'local_sentientia_live'),
            'correct'           => get_string('qt_quiz_a11y_correct',
                'local_sentientia_live'),
            'incorrect'         => get_string('qt_quiz_a11y_incorrect',
                'local_sentientia_live'),
            'leaderboard_changed' => get_string('qt_quiz_a11y_leaderboard',
                'local_sentientia_live'),
        ];
    }

    /**
     * Pure scoring helper. Used by the leaderboard build AND by any
     * future per-session aggregate score (sum across all quiz slides
     * in the session).
     *
     * @param int $option_index   The participant's chosen option.
     * @param int $correct_index  Slide's correct option (or -1 if unset).
     * @return int                POINTS_CORRECT or POINTS_WRONG.
     */
    public static function score_response(int $option_index,
                                            int $correct_index): int {
        if ($correct_index < 0) {
            return self::POINTS_WRONG;
        }
        return $option_index === $correct_index
            ? self::POINTS_CORRECT
            : self::POINTS_WRONG;
    }

    /**
     * Compute top-LEADERBOARD_SIZE fastest-correct responders for a
     * slide. Each entry carries elapsed_ms (relative to slide_changed
     * event time) so the projector view can render "1.4s" badges.
     *
     * @return array Each entry:
     *   ['rank', 'participant_id', 'display_name', 'elapsed_ms',
     *    'elapsed_s', 'is_correct', 'is_winner', 'score']
     */
    private function compute_leaderboard(int $slideid, int $sessionid,
                                          int $correct_index): array {
        global $DB;
        if ($correct_index < 0) {
            return [];
        }
        $slide_start = $this->find_slide_start_time($sessionid, $slideid);

        $rows = $DB->get_records_sql(
            "SELECT r.id, r.participantid, r.value_int, r.timecreated,
                    p.display_name
               FROM {local_sentientia_live_responses} r
          LEFT JOIN {local_sentientia_live_participants} p
                    ON p.id = r.participantid
              WHERE r.slideid   = :slideid
                AND r.value_int = :correct_idx
           ORDER BY r.timecreated ASC, r.id ASC",
            ['slideid' => $slideid, 'correct_idx' => $correct_index],
            0, self::LEADERBOARD_SIZE
        );

        $out = [];
        $rank = 1;
        foreach ($rows as $r) {
            $elapsed_s = max(0, (int) $r->timecreated - $slide_start);
            $out[] = [
                'rank'           => $rank,
                'participant_id' => (int) $r->participantid,
                'display_name'   => $r->display_name ?? '?',
                'elapsed_ms'     => $elapsed_s * 1000,
                'elapsed_s'      => $elapsed_s,
                'is_correct'     => true,
                'is_winner'      => $rank === 1,
                'score'          => self::POINTS_CORRECT,
            ];
            $rank++;
        }
        return $out;
    }

    /**
     * When did this slide become current? Same algorithm as
     * result_panel::find_slide_start_time but lifted here so the quiz
     * type owns its own timing logic (paves Phase E.8.b for per-slide
     * race-window starts).
     */
    private function find_slide_start_time(int $sessionid, int $slideid): int {
        global $DB;
        $events = $DB->get_records(
            'local_sentientia_live_events',
            ['sessionid' => $sessionid, 'type' => 'slide_changed'],
            'timecreated DESC, id DESC',
            'id, payload_json, timecreated',
            0, 100
        );
        foreach ($events as $e) {
            $payload = json_decode($e->payload_json ?? '{}', true);
            if (is_array($payload)
                && (int) ($payload['slide_id'] ?? 0) === $slideid) {
                return (int) $e->timecreated;
            }
        }
        $sess = $DB->get_record('local_sentientia_live_sessions',
            ['id' => $sessionid], 'timestarted, timecreated');
        if (!$sess) {
            return 0;
        }
        return (int) ($sess->timestarted ?: ($sess->timecreated ?? 0));
    }

    private function payload_slide_id(array $payload): int {
        $raw = $payload['slide_id'] ?? $payload['slideid'] ?? 0;
        $id = (int) $raw;
        if ($id <= 0) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        return $id;
    }
}
