<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Audience response writer — Phase E.2.a (2026-05-21).
 *
 * Records one response per (slide, participant) tuple. The unique key
 * `uk_slide_participant` enforces idempotency — submitting twice is
 * an UPDATE not a duplicate row, so audience members who reload can't
 * inflate the count.
 *
 * Side effect on first-time write: emit a response_added event with
 * the running response count for the slide. The trainer's SSE stream
 * (Phase E.3) reads these to update the live chart.
 *
 * Re-submissions also emit an event (in case audience changes their
 * mind) but with the same count_now — clients deduplicate via the
 * event id.
 *
 * @package local_sentientia_live
 */
class response_recorder {

    /**
     * Submit one response. Idempotent — calling repeatedly with the
     * same (slideid, participantid) updates the existing row.
     *
     * @param int          $slideid
     * @param int          $participantid
     * @param int|null     $value_int   For multichoice/rating/quiz/ranking.
     * @param string|null  $value_text  For wordcloud/openended/ranking-json.
     * @return int Response row ID (new or updated).
     * @throws \moodle_exception If slide or participant invalid.
     */
    public static function submit(int $slideid, int $participantid,
                                    ?int $value_int = null,
                                    ?string $value_text = null): int {
        global $DB;

        $slide = slide_manager::get($slideid);
        if (!$slide) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        $participant = $DB->get_record('local_sentientia_live_participants',
            ['id' => $participantid]);
        if (!$participant) {
            throw new \moodle_exception('invalidparticipant',
                'local_sentientia_live');
        }
        if ((int) $participant->sessionid !== (int) $slide->sessionid) {
            throw new \moodle_exception('participant_session_mismatch',
                'local_sentientia_live');
        }

        // Validate value against slide type.
        self::validate_value_for_type($slide, $value_int, $value_text);

        $now = time();
        $existing = $DB->get_record('local_sentientia_live_responses', [
            'slideid'       => $slideid,
            'participantid' => $participantid,
        ]);

        if ($existing) {
            $DB->update_record('local_sentientia_live_responses',
                (object) [
                    'id'          => $existing->id,
                    'value_int'   => $value_int,
                    'value_text'  => $value_text,
                    'timecreated' => $now,
                ]);
            $response_id = (int) $existing->id;
        } else {
            $row = new \stdClass();
            $row->slideid       = $slideid;
            $row->participantid = $participantid;
            $row->value_int     = $value_int;
            $row->value_text    = $value_text;
            $row->timecreated   = $now;
            $response_id = (int) $DB->insert_record(
                'local_sentientia_live_responses', $row);
        }

        // Bump participant presence (response = "they're alive").
        participant_manager::heartbeat($participantid);

        // Emit response_added event for trainer-side SSE.
        //
        // Phase E.5 — include the FULL tally in the event payload so
        // SSE clients can mutate bar widths + counts in place without
        // a separate fetch. For chart types where the tally is shape
        // {idx => count}, the JS uses [data-option-index] / [data-rating-value]
        // selectors to find each bar and update its style.width +
        // textContent. For wordcloud / openended / ranking the clients
        // fall back to location.reload (DOM creation needs careful
        // escaping; addressed in a later phase).
        $count_now = self::count_for_slide($slideid);
        $tally     = self::tally($slideid);
        event_journal::write((int) $slide->sessionid, 'response_added', [
            'slide_id'    => $slideid,
            'slide_type'  => $slide->type,
            'count_now'   => $count_now,
            'tally'       => $tally,
        ]);

        return $response_id;
    }

    /**
     * Get all responses for one slide. Returns rows joined with
     * participant display_name. Used by the trainer's results panel.
     *
     * @param int $slideid
     * @return array Each row: ['id', 'participantid', 'display_name',
     *                          'value_int', 'value_text', 'timecreated']
     */
    public static function get_for_slide(int $slideid): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT r.id, r.participantid, p.display_name,
                    r.value_int, r.value_text, r.timecreated
               FROM {local_sentientia_live_responses} r
               JOIN {local_sentientia_live_participants} p
                    ON p.id = r.participantid
              WHERE r.slideid = :slideid
           ORDER BY r.timecreated ASC, r.id ASC",
            ['slideid' => $slideid]
        );
    }

    /**
     * Count responses for one slide.
     */
    public static function count_for_slide(int $slideid): int {
        global $DB;
        return (int) $DB->count_records(
            'local_sentientia_live_responses',
            ['slideid' => $slideid]
        );
    }

    /**
     * Has this participant already responded to this slide?
     */
    public static function has_responded(int $slideid,
                                          int $participantid): bool {
        global $DB;
        return $DB->record_exists('local_sentientia_live_responses', [
            'slideid'       => $slideid,
            'participantid' => $participantid,
        ]);
    }

    /**
     * Tally for chart rendering. Different shape per type:
     *
     *   multichoice/quiz: ['option_idx' => count]
     *   rating:           ['scale_value' => count, 'avg' => float]
     *   wordcloud:        ['word' => count, ...] (frequency map)
     *   openended:        flat array of strings (raw)
     *   ranking:          aggregate average position per item
     *
     * @param int $slideid
     * @return array
     */
    public static function tally(int $slideid): array {
        global $DB;
        $slide = slide_manager::get($slideid);
        if (!$slide) {
            return [];
        }
        $rows = $DB->get_records('local_sentientia_live_responses',
            ['slideid' => $slideid]);

        $settings = slide_manager::parse_settings($slide);

        switch ($slide->type) {
            case 'multichoice':
            case 'quiz':
                // Initialize counts for every option (even 0).
                $tally = [];
                foreach (array_keys($settings['options']) as $i) {
                    $tally[$i] = 0;
                }
                foreach ($rows as $r) {
                    $v = (int) $r->value_int;
                    if (array_key_exists($v, $tally)) {
                        $tally[$v]++;
                    }
                }
                return $tally;

            case 'rating':
                $tally = [];
                $min = (int) ($settings['scale_min'] ?? 1);
                $max = (int) ($settings['scale_max'] ?? 5);
                for ($i = $min; $i <= $max; $i++) {
                    $tally[$i] = 0;
                }
                $sum = 0;
                $count = 0;
                foreach ($rows as $r) {
                    $v = (int) $r->value_int;
                    if (isset($tally[$v])) {
                        $tally[$v]++;
                        $sum += $v;
                        $count++;
                    }
                }
                $tally['_avg'] = $count > 0
                    ? round($sum / $count, 2)
                    : null;
                $tally['_count'] = $count;
                return $tally;

            case 'wordcloud':
                // Phase E.5 — value_text now carries a JSON array of
                // words per participant ("trust","innovation",...).
                // word_cloud::decode_words() also handles legacy plain-
                // string rows (single-word, pre-E.5) by tokenising on
                // whitespace, so in-flight sessions don't break when
                // this chip lands.
                $tally = [];
                foreach ($rows as $r) {
                    $words = \local_sentientia_live\question_types\word_cloud
                        ::decode_words((string) ($r->value_text ?? ''));
                    foreach ($words as $w) {
                        $w = mb_strtolower(trim($w), 'UTF-8');
                        if ($w === '') {
                            continue;
                        }
                        $tally[$w] = ($tally[$w] ?? 0) + 1;
                    }
                }
                arsort($tally);
                return $tally;

            case 'openended':
                $out = [];
                foreach ($rows as $r) {
                    $t = trim((string) $r->value_text);
                    if ($t !== '') {
                        $out[] = $t;
                    }
                }
                return $out;

            case 'ranking':
                // Each response.value_text is a JSON array of item indices
                // in the responder's preferred order. Aggregate average
                // position per item — lower = more preferred.
                $sums = [];
                $count = 0;
                foreach ($rows as $r) {
                    $order = json_decode((string) $r->value_text, true);
                    if (!is_array($order)) {
                        continue;
                    }
                    foreach ($order as $pos => $item_idx) {
                        $item_idx = (int) $item_idx;
                        if (!isset($sums[$item_idx])) {
                            $sums[$item_idx] = 0;
                        }
                        // 1-based position for human readability.
                        $sums[$item_idx] += ($pos + 1);
                    }
                    $count++;
                }
                $avg = [];
                foreach ($sums as $item_idx => $sum) {
                    $avg[$item_idx] = $count > 0
                        ? round($sum / $count, 2)
                        : null;
                }
                asort($avg);
                return $avg;

            default:
                return [];
        }
    }

    /**
     * Validate the submitted value against the slide's type rules.
     * Throws moodle_exception with a clear error if invalid.
     */
    private static function validate_value_for_type(\stdClass $slide,
                                                     ?int $value_int,
                                                     ?string $value_text): void {
        $settings = slide_manager::parse_settings($slide);

        switch ($slide->type) {
            case 'multichoice':
            case 'quiz':
                if ($value_int === null) {
                    throw new \moodle_exception('response_int_required',
                        'local_sentientia_live');
                }
                $option_count = count($settings['options'] ?? []);
                if ($value_int < 0 || $value_int >= $option_count) {
                    throw new \moodle_exception('response_out_of_range',
                        'local_sentientia_live', '', $value_int);
                }
                break;

            case 'rating':
                if ($value_int === null) {
                    throw new \moodle_exception('response_int_required',
                        'local_sentientia_live');
                }
                $min = (int) ($settings['scale_min'] ?? 1);
                $max = (int) ($settings['scale_max'] ?? 5);
                if ($value_int < $min || $value_int > $max) {
                    throw new \moodle_exception('response_out_of_range',
                        'local_sentientia_live', '', $value_int);
                }
                break;

            case 'wordcloud':
                // Phase E.5 — value_text is now a JSON array of cleaned
                // tokens prepared by word_cloud::persist_response. The
                // per-word length cap was applied during tokenisation,
                // so here we only assert non-empty.
                if ($value_text === null || trim($value_text) === '') {
                    throw new \moodle_exception('response_text_required',
                        'local_sentientia_live');
                }
                $decoded = json_decode($value_text, true);
                if (is_array($decoded)) {
                    // Canonical JSON-array shape — non-empty assertion only.
                    if (empty($decoded)) {
                        throw new \moodle_exception('response_text_required',
                            'local_sentientia_live');
                    }
                } else {
                    // Legacy single-word path — still honour the per-word cap.
                    $max_len = (int) ($settings['max_word_length'] ?? 50);
                    if (mb_strlen($value_text) > $max_len) {
                        throw new \moodle_exception('response_text_too_long',
                            'local_sentientia_live', '', $max_len);
                    }
                }
                break;

            case 'openended':
                if ($value_text === null || trim($value_text) === '') {
                    throw new \moodle_exception('response_text_required',
                        'local_sentientia_live');
                }
                $max_chars = (int) ($settings['max_chars'] ?? 280);
                if (mb_strlen($value_text) > $max_chars) {
                    throw new \moodle_exception('response_text_too_long',
                        'local_sentientia_live', '', $max_chars);
                }
                break;

            case 'ranking':
                if ($value_text === null) {
                    throw new \moodle_exception('response_text_required',
                        'local_sentientia_live');
                }
                $order = json_decode($value_text, true);
                if (!is_array($order)) {
                    throw new \moodle_exception('response_ranking_bad_json',
                        'local_sentientia_live');
                }
                $item_count = count($settings['items'] ?? []);
                if (count($order) !== $item_count) {
                    throw new \moodle_exception('response_ranking_incomplete',
                        'local_sentientia_live');
                }
                break;
        }
    }
}
