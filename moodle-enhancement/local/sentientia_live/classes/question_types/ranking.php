<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Ranking question type — Phase E.9 stub (2026-05-24).
 *
 * Audience drags a list of N items into their preferred order
 * (1 = highest preference). Results render as an aggregated bar chart
 * showing each item's average position — lower average = more
 * preferred overall.
 *
 * Settings shape:
 *   {items: ["a", "b", "c", ...]}   // 2-20 strings, 1-200 chars
 *
 * Tally shape:
 *   ['<item_idx>' => avg_position_float, ...]
 *
 *   Sorted ascending by avg_position so a renderer can foreach() the
 *   array and produce the "winner first, loser last" bar chart
 *   without re-sorting.
 *
 * Response payload (drag-to-order needs JSON, hence value_text not
 * value_int in the responses table):
 *   ['order' => [item_idx_0, item_idx_1, ..., item_idx_{N-1}]]
 *
 *   The array MUST be a complete permutation of [0..N-1]. Partial /
 *   duplicate orderings are rejected at persist time.
 *
 * Mobile UX (deferred to E.9 + E.11):
 *   Sortable.js handles drag-and-drop on desktop + touch. Fallback
 *   for screen readers / no-JS clients: numeric input per item with
 *   server-side ordering — Phase E.11 will land that.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ranking extends abstract_question_type {

    public const SLUG = 'ranking';
    public const FEATURE_FLAG = 'live.questiontype.ranking';
    public const NAME_STRING_KEY = 'qtype_ranking_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_ranking_desc';

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
