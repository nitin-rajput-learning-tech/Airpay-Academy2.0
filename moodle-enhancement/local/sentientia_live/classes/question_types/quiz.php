<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz question type — Phase E.8 stub (2026-05-24).
 *
 * Like multichoice, but with a designated CORRECT answer. After the
 * audience submits, they see an instant right/wrong badge; the
 * trainer's projector shows a leaderboard sorted by (correct, then
 * fastest_response_time).
 *
 * Settings shape:
 *   {options: ["a", "b", "c", ...],      // 2-20 strings
 *    correct_index: int}                  // 0-based, 0 ≤ N < count(options)
 *
 * Tally shape (same option-index histogram as multichoice PLUS):
 *   ['0' => count, '1' => count, ...,
 *    '_correct_index' => int,
 *    '_correct_count' => int,
 *    '_total'         => int,
 *    '_leaderboard'   => [ {participant_id, display_name,
 *                            elapsed_ms, is_correct}, ... ]]
 *
 *   The leaderboard is computed by sorting responses by
 *   timecreated - slide.timecreated (oldest slide_changed event
 *   marks "Q starts now"). Phase E.8.b will swap this naive
 *   computation for a per-slide "race window started at" timestamp
 *   captured on slide_changed.
 *
 * Response payload:
 *   ['option_index' => int]
 *
 * Scoring policy (deferred to E.8.b):
 *   Default: 1 point for correct, 0 for incorrect. Speed-tiebreak
 *   only — no speed bonus. Per-slide custom scoring config flag.
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

    /**
     * @inheritDoc
     */
    public function render(array $context): string {
        throw new \coding_exception('not_implemented: ' . __METHOD__);
    }

    /**
     * @inheritDoc
     */
    public function persist_response(int $userid, array $payload): int {
        throw new \coding_exception('not_implemented: ' . __METHOD__);
    }

    /**
     * @inheritDoc
     */
    public function tally(int $sessionid, int $slideid): array {
        throw new \coding_exception('not_implemented: ' . __METHOD__);
    }

    /**
     * @inheritDoc
     */
    public function validate_config(array $config): array {
        throw new \coding_exception('not_implemented: ' . __METHOD__);
    }

    /**
     * @inheritDoc
     */
    public function get_aria_announcements(): array {
        throw new \coding_exception('not_implemented: ' . __METHOD__);
    }
}
