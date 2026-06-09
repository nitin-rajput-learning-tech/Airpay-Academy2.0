<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

use local_sentientia_live\response_recorder;
use local_sentientia_live\slide_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Ranking question type — Phase E.9 implementation (D4, 2026-05-24).
 *
 * Audience drags N items into preferred order (position 1 = most
 * preferred). The trainer view aggregates via TWO complementary scores:
 *
 *   1. Average position (lower = more preferred). Familiar at-a-glance
 *      number. Sort: asort() ascending so renderer's foreach() gives
 *      winner-first.
 *
 *   2. Borda count (higher = more preferred). With N items, position 1
 *      awards N points, position 2 awards N-1, ..., position N awards
 *      1. Sum per item across all responses. Borda is more robust to
 *      strategic voting than plain averaging — preferred for cohort
 *      decision-making sessions.
 *
 * Both scores are returned. The renderer picks which to surface
 * (the trainer dashboard shows Borda; the audience preview shows avg
 * position because it's the more intuitive number).
 *
 * Settings shape:
 *   {items: string[] (2-20 strings, 1-200 chars each)}
 *
 * Tally shape:
 *   ['0' => avg_position_float, '1' => ..., ...,    // asort ascending
 *    '_borda'           => ['0' => int, ...],       // item_idx → points
 *    '_avg_position'    => ['0' => float, ...],     // unsorted copy of
 *                                                    //   the avg map
 *    '_borda_ranked'    => [['index', 'label',
 *                            'borda', 'avg_position'], ...],
 *    '_total_responses' => int,
 *    '_item_count'      => int]
 *
 * Response payload (any of these formats):
 *   ['slide_id' => int, 'order' => [item_idx_0, item_idx_1, ...]]
 *   ['slide_id' => int, 'value_text' => '[0,2,1,3]']  (JSON alias)
 *
 *   `order` MUST be a complete permutation of [0..N-1] — partial /
 *   duplicate orderings are rejected at persist time.
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
        global $OUTPUT;

        $slide = $context['slide']
            ?? throw new \coding_exception('render: $context[slide] required');
        $settings = $context['settings']
            ?? slide_manager::parse_settings($slide);
        $aria_id_prefix = $context['aria_id_prefix']
            ?? ('qt-ranking-' . (int) $slide->id);

        $items = [];
        foreach (($settings['items'] ?? []) as $i => $label) {
            $items[] = [
                'index'    => $i,
                'label'    => $label,
                'input_id' => $aria_id_prefix . '-item-' . $i,
            ];
        }

        return $OUTPUT->render_from_template(
            'local_sentientia_live/qt_ranking_audience',
            [
                'slideid'        => (int) $slide->id,
                'sessionid'      => (int) $slide->sessionid,
                'aria_id_prefix' => $aria_id_prefix,
                'items'          => $items,
                'has_items'      => !empty($items),
                'item_count'     => count($items),
                'sesskey'        => sesskey(),
                'submit_label'   => get_string('audience_submit_response',
                    'local_sentientia_live'),
                'intro'          => get_string('ranking_response_intro',
                    'local_sentientia_live'),
                'sortable_hint'  => get_string('qt_ranking_audience_sortable_hint',
                    'local_sentientia_live'),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function persist_response(int $userid, array $payload): int {
        $slideid = $this->payload_slide_id($payload);

        $order = $payload['order'] ?? null;
        if ($order === null) {
            // JSON-string fallback (matches play.php's hidden field shape).
            $raw = (string) ($payload['value_text'] ?? '');
            if ($raw === '') {
                throw new \moodle_exception('response_text_required',
                    'local_sentientia_live');
            }
            $order = json_decode($raw, true);
        }

        if (!is_array($order) || empty($order)) {
            throw new \moodle_exception('response_ranking_bad_json',
                'local_sentientia_live');
        }

        // Reject duplicates so a malicious / buggy client can't bias
        // the aggregate by repeating an item index.
        $seen = [];
        $clean = [];
        foreach ($order as $idx) {
            if (!is_int($idx) && !ctype_digit((string) $idx)) {
                throw new \moodle_exception('response_ranking_bad_json',
                    'local_sentientia_live');
            }
            $i = (int) $idx;
            if (isset($seen[$i])) {
                throw new \moodle_exception('response_ranking_duplicate',
                    'local_sentientia_live');
            }
            $seen[$i] = true;
            $clean[] = $i;
        }

        // response_recorder enforces completeness (count === item_count).
        return response_recorder::submit(
            $slideid, $userid, null, json_encode($clean));
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
        $items = $settings['items'] ?? [];
        $item_count = count($items);

        $rows = $DB->get_records('local_sentientia_live_responses',
            ['slideid' => $slideid]);

        $orders = [];
        foreach ($rows as $r) {
            $order = json_decode((string) $r->value_text, true);
            if (is_array($order)) {
                $orders[] = $order;
            }
        }

        $avg   = self::compute_avg_positions($orders, $item_count);
        $borda = self::compute_borda_scores($orders, $item_count);

        // Build the sorted "ranked" list — Borda desc, then avg asc
        // (lower avg = more preferred on tie).
        $borda_ranked = [];
        foreach ($borda as $idx => $points) {
            $borda_ranked[] = [
                'index'        => $idx,
                'label'        => $items[$idx] ?? get_string(
                    'qt_ranking_item_fallback', 'local_sentientia_live',
                    $idx + 1),
                'borda'        => $points,
                'avg_position' => $avg[$idx] ?? null,
            ];
        }
        usort($borda_ranked, function ($a, $b) {
            if ($b['borda'] !== $a['borda']) {
                return $b['borda'] <=> $a['borda'];
            }
            // Null avg sorts last on tie.
            if ($a['avg_position'] === null) {
                return 1;
            }
            if ($b['avg_position'] === null) {
                return -1;
            }
            return $a['avg_position'] <=> $b['avg_position'];
        });

        // Sort the avg map ascending so the legacy renderer (which
        // foreaches the int-keyed entries) gives winner-first. Use a
        // null-aware comparator — asort(SORT_NUMERIC) would coerce a null
        // avg (an item nobody ranked) to 0 and float it to the FRONT as a
        // false "winner". Unranked items must sort LAST.
        $avg_sorted = $avg;
        uasort($avg_sorted, function ($a, $b) {
            if ($a === null && $b === null) {
                return 0;
            }
            if ($a === null) {
                return 1;
            }
            if ($b === null) {
                return -1;
            }
            return $a <=> $b;
        });

        $out = [];
        // Replicate the int-keyed avg map FIRST so chart_updater can
        // iterate (preserves the existing tally shape contract).
        foreach ($avg_sorted as $k => $v) {
            $out[$k] = $v;
        }
        $out['_borda']           = $borda;
        $out['_avg_position']    = $avg;
        $out['_borda_ranked']    = $borda_ranked;
        $out['_total_responses'] = count($orders);
        $out['_item_count']      = $item_count;
        return $out;
    }

    /**
     * @inheritDoc
     */
    public function validate_config(array $config): array {
        $errors = [];

        $items = $config['items'] ?? null;
        if (!is_array($items) || count($items) < 2 || count($items) > 20) {
            $errors['items'] = get_string('ranking_items_count',
                'local_sentientia_live',
                is_array($items) ? count($items) : 0);
            return $errors;
        }
        foreach ($items as $item) {
            if (!is_string($item)) {
                $errors['items'] = get_string('ranking_item_type',
                    'local_sentientia_live');
                return $errors;
            }
            $trim = trim($item);
            if ($trim === '' || mb_strlen($trim) > 200) {
                $errors['items'] = get_string('ranking_item_length',
                    'local_sentientia_live');
                return $errors;
            }
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
            'item_moved'        => get_string('qt_ranking_a11y_item_moved',
                'local_sentientia_live'),
            'ranking_changed'   => get_string('qt_ranking_a11y_ranking_changed',
                'local_sentientia_live'),
        ];
    }

    // ── Public pure helpers (testable without DB fixtures) ──

    /**
     * Borda count — higher = more preferred. With N items, position 1
     * awards N points, position N awards 1 point. Sum across all
     * responses.
     *
     * @param array<int[]> $orders     Each inner array is one participant's
     *                                  preferred order (item indices).
     * @param int          $item_count How many items live in the slide.
     * @return array<int,int>          item_idx → cumulative Borda points.
     */
    public static function compute_borda_scores(array $orders,
                                                  int $item_count): array {
        $borda = [];
        for ($i = 0; $i < $item_count; $i++) {
            $borda[$i] = 0;
        }
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $seen = [];
            foreach ($order as $pos => $item_idx) {
                $idx = (int) $item_idx;
                if ($idx < 0 || $idx >= $item_count || isset($seen[$idx])) {
                    continue;
                }
                $seen[$idx] = true;
                $borda[$idx] += ($item_count - $pos);
            }
        }
        return $borda;
    }

    /**
     * Average position per item — lower = more preferred. Items that
     * never appeared in any response get null (no responses to average).
     *
     * @return array<int,float|null>  item_idx → avg position (1-based),
     *                                 null when no data.
     */
    public static function compute_avg_positions(array $orders,
                                                   int $item_count): array {
        $sums   = [];
        $counts = [];
        for ($i = 0; $i < $item_count; $i++) {
            $sums[$i] = 0;
            $counts[$i] = 0;
        }
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $seen = [];
            foreach ($order as $pos => $item_idx) {
                $idx = (int) $item_idx;
                if ($idx < 0 || $idx >= $item_count || isset($seen[$idx])) {
                    continue;
                }
                $seen[$idx] = true;
                $sums[$idx] += ($pos + 1);    // 1-based position
                $counts[$idx]++;
            }
        }
        $avg = [];
        foreach ($sums as $idx => $sum) {
            $avg[$idx] = $counts[$idx] > 0
                ? round($sum / $counts[$idx], 2)
                : null;
        }
        return $avg;
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
