<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * Slide CRUD + position management — Phase E.1.d (2026-05-21).
 *
 * Companion to session_manager. Slides are the question units within a
 * session — one slide = one question/poll/word-cloud, etc.
 *
 * Position management uses simple integer slots starting at 1. add()
 * appends to the end; move_up / move_down swap with adjacent slide;
 * reorder() rewrites positions from an explicit ordering.
 *
 * Type-specific settings_json schemas — each question type carries
 * its own config blob:
 *
 *   multichoice : {options: ["a", "b", "c"]}
 *   rating      : {scale_min: 1, scale_max: 5, scale_labels?: [...]}
 *   quiz        : {options: [...], correct_index: int}
 *   ranking     : {items: [...]}
 *   wordcloud   : {max_word_length?: 50, dedupe?: true}
 *   openended   : {max_chars?: 500}
 *
 * Settings are validated against the type at add/update time.
 *
 * @package local_sentientia_live
 */
class slide_manager {

    public const VALID_TYPES = [
        'multichoice',
        'wordcloud',
        'openended',
        'rating',
        'quiz',
        'ranking',
    ];

    /**
     * Add a new slide to the end of a session's slide deck.
     *
     * @param int    $sessionid
     * @param string $type     One of VALID_TYPES.
     * @param string $title    Question text (1-5000 chars).
     * @param array  $settings Type-specific config blob (see class doc).
     * @return int New slide ID.
     * @throws \moodle_exception on invalid input.
     */
    public static function add(int $sessionid, string $type, string $title,
                                array $settings = []): int {
        global $DB;

        // Validate parent session exists.
        if (!$DB->record_exists('local_sentientia_live_sessions',
                ['id' => $sessionid])) {
            throw new \moodle_exception('invalidsession', 'local_sentientia_live');
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \moodle_exception('invalidslidetype', 'local_sentientia_live',
                '', $type);
        }

        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 5000) {
            throw new \moodle_exception('invalidtitle', 'local_sentientia_live');
        }

        $settings_clean = self::validate_settings($type, $settings);

        // Position = max+1 within this session.
        $max_pos = (int) $DB->get_field('local_sentientia_live_slides',
            'COALESCE(MAX(position), 0)', ['sessionid' => $sessionid]);
        $next_pos = $max_pos + 1;

        $now = time();
        $row = new \stdClass();
        $row->sessionid     = $sessionid;
        $row->position      = $next_pos;
        $row->type          = $type;
        $row->title         = $title;
        $row->settings_json = json_encode($settings_clean);
        $row->timecreated   = $now;
        $row->timemodified  = $now;

        return (int) $DB->insert_record('local_sentientia_live_slides', $row);
    }

    /**
     * Get one slide by ID.
     */
    public static function get(int $slideid): ?\stdClass {
        global $DB;
        if ($slideid <= 0) {
            return null;
        }
        $row = $DB->get_record('local_sentientia_live_slides', ['id' => $slideid]);
        return $row ?: null;
    }

    /**
     * List all slides for a session in display order.
     */
    public static function list_for_session(int $sessionid): array {
        global $DB;
        return $DB->get_records('local_sentientia_live_slides',
            ['sessionid' => $sessionid],
            'position ASC, id ASC');
    }

    /**
     * Count slides in a session.
     */
    public static function count_for_session(int $sessionid): int {
        global $DB;
        return (int) $DB->count_records('local_sentientia_live_slides',
            ['sessionid' => $sessionid]);
    }

    /**
     * Update one slide's title and / or settings. Type cannot be changed
     * after creation — that would invalidate any responses already given.
     * Delete + re-add if the type is wrong.
     *
     * @return bool True on success, false if slide doesn't exist.
     */
    public static function update(int $slideid, ?string $title,
                                    ?array $settings): bool {
        global $DB;
        $slide = self::get($slideid);
        if (!$slide) {
            return false;
        }
        $patch = new \stdClass();
        $patch->id           = $slideid;
        $patch->timemodified = time();

        if ($title !== null) {
            $title = trim($title);
            if ($title === '' || mb_strlen($title) > 5000) {
                throw new \moodle_exception('invalidtitle',
                    'local_sentientia_live');
            }
            $patch->title = $title;
        }
        if ($settings !== null) {
            $cleaned = self::validate_settings($slide->type, $settings);
            $patch->settings_json = json_encode($cleaned);
        }
        $DB->update_record('local_sentientia_live_slides', $patch);
        return true;
    }

    /**
     * Delete a slide. Cascades to its responses. Compacts positions of
     * subsequent slides so there's no gap (slide 3 deleted → 4 becomes
     * 3, 5 becomes 4, ...).
     *
     * Wrapped in a transaction so we never leave a half-deleted state.
     */
    public static function delete(int $slideid): bool {
        global $DB;
        $slide = self::get($slideid);
        if (!$slide) {
            return false;
        }

        $trans = $DB->start_delegated_transaction();
        try {
            // Delete responses keyed by slideid.
            $DB->delete_records('local_sentientia_live_responses',
                ['slideid' => $slideid]);

            // Delete the slide row.
            $DB->delete_records('local_sentientia_live_slides',
                ['id' => $slideid]);

            // If the session's current_slide_id pointed here, null it.
            $DB->execute(
                "UPDATE {local_sentientia_live_sessions}
                    SET current_slide_id = NULL
                  WHERE current_slide_id = :sid",
                ['sid' => $slideid]
            );

            // Compact positions: decrement every slide in this session
            // whose position > deleted slide's position.
            $DB->execute(
                "UPDATE {local_sentientia_live_slides}
                    SET position = position - 1
                  WHERE sessionid = :sessionid
                    AND position > :pos",
                ['sessionid' => $slide->sessionid, 'pos' => $slide->position]
            );

            $trans->allow_commit();
        } catch (\Throwable $e) {
            $trans->rollback($e);
            throw $e;
        }
        return true;
    }

    /**
     * Move a slide up by one position. Returns true if moved, false if
     * already at position 1 or slide doesn't exist.
     */
    public static function move_up(int $slideid): bool {
        return self::swap_with_offset($slideid, -1);
    }

    /**
     * Move a slide down by one position.
     */
    public static function move_down(int $slideid): bool {
        return self::swap_with_offset($slideid, +1);
    }

    /**
     * Rewrite positions for an entire session from an explicit ordering.
     * Used by drag-and-drop UI. Pass an array of slide IDs in their
     * intended new order — slides not in the list are appended
     * preserving their old relative order.
     *
     * @param int   $sessionid
     * @param int[] $ordered_slide_ids
     * @return void
     */
    public static function reorder(int $sessionid, array $ordered_slide_ids): void {
        global $DB;
        $trans = $DB->start_delegated_transaction();
        try {
            $pos = 1;
            $seen = [];
            foreach ($ordered_slide_ids as $sid) {
                $sid = (int) $sid;
                if ($sid <= 0 || isset($seen[$sid])) {
                    continue;
                }
                // Only update if the slide actually belongs to this session.
                $belongs = $DB->record_exists('local_sentientia_live_slides', [
                    'id' => $sid, 'sessionid' => $sessionid,
                ]);
                if (!$belongs) {
                    continue;
                }
                $DB->set_field('local_sentientia_live_slides',
                    'position', $pos, ['id' => $sid]);
                $seen[$sid] = true;
                $pos++;
            }
            // Append any slides NOT mentioned in the order list, preserving
            // their old order. Caller's responsibility to include them all;
            // this is defensive.
            $remaining = $DB->get_records_sql(
                "SELECT id FROM {local_sentientia_live_slides}
                  WHERE sessionid = :sid
               ORDER BY position ASC, id ASC",
                ['sid' => $sessionid]
            );
            foreach ($remaining as $r) {
                if (isset($seen[(int) $r->id])) {
                    continue;
                }
                $DB->set_field('local_sentientia_live_slides',
                    'position', $pos, ['id' => $r->id]);
                $pos++;
            }
            $trans->allow_commit();
        } catch (\Throwable $e) {
            $trans->rollback($e);
            throw $e;
        }
    }

    /**
     * Parse settings_json into typed array, falling back to per-type defaults
     * on malformed JSON.
     */
    public static function parse_settings(\stdClass $slide): array {
        if (empty($slide->settings_json)) {
            return self::default_settings_for_type($slide->type);
        }
        $decoded = json_decode($slide->settings_json, true);
        if (!is_array($decoded)) {
            return self::default_settings_for_type($slide->type);
        }
        return array_merge(self::default_settings_for_type($slide->type), $decoded);
    }

    /**
     * Default settings shape per slide type.
     */
    public static function default_settings_for_type(string $type): array {
        return match ($type) {
            // Merged C1 (multichoice render_style) + C2 (wordcloud rich
            // fields) + D4 (rating scale_type, openended 500-char cap).
            'multichoice' => ['options' => [], 'render_style' => 'radio'],
            'rating'      => ['scale_type' => 'stars', 'scale_min' => 1, 'scale_max' => 5, 'scale_labels' => []],
            'quiz'        => ['options' => [], 'correct_index' => 0],
            'ranking'     => ['items' => []],
            'wordcloud'   => [
                'max_word_length'        => 50,
                'dedupe'                 => true,
                // Phase E.5 — new fields. Defaults match the admin-
                // wide Switchboard settings (default_min_word_length,
                // default_max_responses); per-slide values override.
                'min_word_length'        => 2,
                'max_responses_per_user' => 3,
                'locale'                 => 'en',
            ],
            'openended'   => ['max_chars' => 500],
            default       => [],
        };
    }

    /**
     * Validate + normalise a settings array against the slide type.
     * Throws moodle_exception on invalid shape; returns clean array
     * with defaults applied otherwise.
     */
    public static function validate_settings(string $type, array $settings): array {
        $defaults = self::default_settings_for_type($type);
        $out = $defaults;

        switch ($type) {
            case 'multichoice':
            case 'quiz':
                $options = $settings['options'] ?? [];
                if (!is_array($options) || count($options) < 2 || count($options) > 20) {
                    throw new \moodle_exception('mc_options_count',
                        'local_sentientia_live', '', count($options));
                }
                // Each option must be a non-empty string.
                $cleaned = [];
                foreach ($options as $opt) {
                    if (!is_string($opt)) {
                        throw new \moodle_exception('mc_option_type', 'local_sentientia_live');
                    }
                    $trimmed = trim($opt);
                    if ($trimmed === '' || mb_strlen($trimmed) > 200) {
                        throw new \moodle_exception('mc_option_length',
                            'local_sentientia_live');
                    }
                    $cleaned[] = $trimmed;
                }
                $out['options'] = $cleaned;
                if ($type === 'quiz') {
                    $correct = (int) ($settings['correct_index'] ?? 0);
                    if ($correct < 0 || $correct >= count($cleaned)) {
                        throw new \moodle_exception('quiz_correct_out_of_range',
                            'local_sentientia_live');
                    }
                    $out['correct_index'] = $correct;
                }
                if ($type === 'multichoice') {
                    // Phase E.4 — multichoice render style (radio | buttons).
                    $rs = $settings['render_style'] ?? 'radio';
                    $out['render_style'] = in_array($rs, ['radio', 'buttons'], true)
                        ? $rs : 'radio';
                    // Phase E.4 — OPTIONAL correct-answer marking. Unlike
                    // quiz (where it's required), multichoice may have no
                    // correct answer. A blank / null / negative value
                    // means "none" and is simply omitted; an explicit
                    // in-range index is persisted; an out-of-range index
                    // is rejected.
                    if (isset($settings['correct_index'])
                        && $settings['correct_index'] !== ''
                        && $settings['correct_index'] !== null) {
                        $ci = (int) $settings['correct_index'];
                        if ($ci >= count($cleaned)) {
                            throw new \moodle_exception('quiz_correct_out_of_range',
                                'local_sentientia_live');
                        }
                        if ($ci >= 0) {
                            $out['correct_index'] = $ci;
                        }
                    }
                }
                break;

            case 'rating':
                // scale_type lets the config pick between a 1-5 star
                // scale and a 1-10 NPS scale. Default 'stars'. Invalid
                // values fall back to 'stars' rather than throwing —
                // the explicit min/max below are the authoritative bounds.
                $scale_type = $settings['scale_type'] ?? 'stars';
                $out['scale_type'] = in_array($scale_type, ['stars', 'nps'], true)
                    ? $scale_type : 'stars';
                $min = (int) ($settings['scale_min'] ?? 1);
                $max = (int) ($settings['scale_max'] ?? 5);
                if ($min < 0 || $max <= $min || $max > 10) {
                    throw new \moodle_exception('rating_scale_invalid',
                        'local_sentientia_live');
                }
                $out['scale_min'] = $min;
                $out['scale_max'] = $max;
                $labels = $settings['scale_labels'] ?? [];
                if (is_array($labels)) {
                    $out['scale_labels'] = array_values(array_map(
                        fn($l) => is_string($l) ? mb_substr(trim($l), 0, 40) : '',
                        $labels));
                }
                break;

            case 'ranking':
                $items = $settings['items'] ?? [];
                if (!is_array($items) || count($items) < 2 || count($items) > 20) {
                    throw new \moodle_exception('ranking_items_count',
                        'local_sentientia_live', '', count($items));
                }
                $cleaned = [];
                foreach ($items as $item) {
                    if (!is_string($item)) {
                        throw new \moodle_exception('ranking_item_type',
                            'local_sentientia_live');
                    }
                    $trimmed = trim($item);
                    if ($trimmed === '' || mb_strlen($trimmed) > 200) {
                        throw new \moodle_exception('ranking_item_length',
                            'local_sentientia_live');
                    }
                    $cleaned[] = $trimmed;
                }
                $out['items'] = $cleaned;
                break;

            case 'wordcloud':
                $mw = (int) ($settings['max_word_length'] ?? 50);
                $out['max_word_length'] = max(3, min(100, $mw));
                $out['dedupe'] = (bool) ($settings['dedupe'] ?? true);
                // Phase E.5 — admin-default fallbacks live in
                // get_config('local_sentientia_live', '…'); slide_form
                // can override per-slide.
                $default_min = (int) (get_config('local_sentientia_live',
                    'default_min_word_length') ?: 2);
                $default_max_resp = (int) (get_config('local_sentientia_live',
                    'default_max_responses') ?: 3);
                $mn = (int) ($settings['min_word_length'] ?? $default_min);
                $out['min_word_length'] = max(1, min(20, $mn));
                $mr = (int) ($settings['max_responses_per_user']
                    ?? $default_max_resp);
                $out['max_responses_per_user'] = max(1, min(10, $mr));
                $out['locale'] = is_string($settings['locale'] ?? null)
                    ? mb_substr(trim($settings['locale']), 0, 16, 'UTF-8')
                    : 'en';
                break;

            case 'openended':
                // D4 ceiling is 500 (was 280 in the E.0 scaffold). Keep
                // this clamp in lockstep with
                // open_ended::MAX_CHARS_CEILING / MAX_CHARS_FLOOR so the
                // storage layer and the question-type layer agree.
                $mc = (int) ($settings['max_chars'] ?? 500);
                $out['max_chars'] = max(10, min(500, $mc));
                break;
        }

        return $out;
    }

    // ── Private helpers ──

    private static function swap_with_offset(int $slideid, int $offset): bool {
        global $DB;
        $slide = self::get($slideid);
        if (!$slide) {
            return false;
        }
        $other = $DB->get_record('local_sentientia_live_slides', [
            'sessionid' => $slide->sessionid,
            'position'  => $slide->position + $offset,
        ]);
        if (!$other) {
            return false;
        }
        $trans = $DB->start_delegated_transaction();
        try {
            $DB->set_field('local_sentientia_live_slides',
                'position', $slide->position + $offset,
                ['id' => $slide->id]);
            $DB->set_field('local_sentientia_live_slides',
                'position', $slide->position,
                ['id' => $other->id]);
            $trans->allow_commit();
        } catch (\Throwable $e) {
            $trans->rollback($e);
            throw $e;
        }
        return true;
    }
}
