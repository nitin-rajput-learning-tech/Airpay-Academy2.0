<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

use local_sentientia_live\response_recorder;
use local_sentientia_live\slide_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Rating-scale question type — Phase E.7 implementation (D4, 2026-05-24).
 *
 * Two scale shapes, selected by the `scale_type` config key:
 *
 *   SCALE_TYPE_STARS (`stars`) — 1-5 — default. Audience renders as
 *                                star icons (1 = ☆, 5 = ★★★★★).
 *   SCALE_TYPE_NPS   (`nps`)   — 1-10 by default (Net Promoter Score-
 *                                style). The classic 0-10 NPS shape is
 *                                also accepted — validate_config permits
 *                                any 0 ≤ min < max ≤ 10, so a trainer who
 *                                wants the textbook 0-10 scale can set it.
 *                                The audience renders numeric pills across
 *                                whatever bounds the config defines.
 *
 * Tally adds mean + median to the distribution histogram. Both are
 * exposed under sentinel underscore keys so the chart renderer's
 * foreach uniformly iterates the step counts while the summary panel
 * plucks _mean / _median out.
 *
 * Settings shape:
 *   {scale_type:   'stars' | 'nps',
 *    scale_min:    int (0-10, default 1),
 *    scale_max:    int (>min, ≤10, default 5 stars / 10 nps),
 *    scale_labels: string[] (optional, length = max-min+1)}
 *
 * Tally shape:
 *   ['1' => count, '2' => count, ..., 'N' => count,
 *    '_mean'       => float|null,
 *    '_median'     => float|null,
 *    '_count'      => int,
 *    '_min'        => int,
 *    '_max'        => int,
 *    '_scale_type' => 'stars' | 'nps',
 *    '_avg'        => float|null (back-compat alias for _mean)]
 *
 * Response payload:
 *   ['slide_id' => int, 'value' => int]   (value_int alias accepted)
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_scale extends abstract_question_type {

    public const SLUG = 'rating';
    public const FEATURE_FLAG = 'live.questiontype.rating';
    public const NAME_STRING_KEY = 'qtype_rating_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_rating_desc';

    public const SCALE_TYPE_STARS = 'stars';
    public const SCALE_TYPE_NPS   = 'nps';

    public const STARS_MIN = 1;
    public const STARS_MAX = 5;
    public const NPS_MIN   = 1;
    public const NPS_MAX   = 10;

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
            ?? ('qt-rating-' . (int) $slide->id);

        $scale_type = $this->resolve_scale_type($settings);
        [$min, $max] = $this->scale_bounds($scale_type, $settings);
        $labels = is_array($settings['scale_labels'] ?? null)
            ? $settings['scale_labels'] : [];

        $steps = [];
        for ($v = $min; $v <= $max; $v++) {
            $label_idx = $v - $min;
            $steps[] = [
                'value'    => $v,
                'label'    => $labels[$label_idx] ?? '',
                'has_label' => isset($labels[$label_idx])
                    && trim((string) $labels[$label_idx]) !== '',
                'input_id' => $aria_id_prefix . '-step-' . $v,
            ];
        }

        return $OUTPUT->render_from_template(
            'local_sentientia_live/qt_rating_scale_audience',
            [
                'slideid'        => (int) $slide->id,
                'sessionid'      => (int) $slide->sessionid,
                'aria_id_prefix' => $aria_id_prefix,
                'scale_type'     => $scale_type,
                'is_stars'       => $scale_type === self::SCALE_TYPE_STARS,
                'is_nps'         => $scale_type === self::SCALE_TYPE_NPS,
                'steps'          => $steps,
                'sesskey'        => sesskey(),
                'submit_label'   => get_string('audience_submit_response',
                    'local_sentientia_live'),
                'legend'         => get_string(
                    'qt_rating_audience_legend_' . $scale_type,
                    'local_sentientia_live'),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function persist_response(int $userid, array $payload): int {
        $slideid = $this->payload_slide_id($payload);
        $value = $payload['value']
            ?? $payload['value_int']
            ?? null;
        if ($value === null) {
            throw new \moodle_exception('response_int_required',
                'local_sentientia_live');
        }
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \moodle_exception('response_int_required',
                'local_sentientia_live');
        }
        // response_recorder::submit performs the bound check against the
        // slide's settings.scale_min / scale_max so we don't double-validate.
        return response_recorder::submit($slideid, $userid, (int) $value, null);
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
        $scale_type = $this->resolve_scale_type($settings);
        [$min, $max] = $this->scale_bounds($scale_type, $settings);

        $rows = $DB->get_records('local_sentientia_live_responses',
            ['slideid' => $slideid]);

        $distribution = [];
        for ($v = $min; $v <= $max; $v++) {
            $distribution[$v] = 0;
        }
        $values = [];
        foreach ($rows as $r) {
            if ($r->value_int === null) {
                continue;
            }
            $v = (int) $r->value_int;
            if (isset($distribution[$v])) {
                $distribution[$v]++;
                $values[] = $v;
            }
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $mean = self::compute_mean($values);
        $median = self::compute_median($values);

        // Preserve int key ordering before merging the sentinel keys.
        $out = $distribution;
        $out['_mean']       = $mean;
        $out['_median']     = $median;
        $out['_count']      = $count;
        $out['_min']        = $min;
        $out['_max']        = $max;
        $out['_scale_type'] = $scale_type;
        // Back-compat alias — existing chart_updater + result_panel
        // shape_rating() pluck `_avg`. Keep both keys until callers
        // migrate to the _mean canonical name.
        $out['_avg']        = $mean;
        return $out;
    }

    /**
     * @inheritDoc
     */
    public function validate_config(array $config): array {
        $errors = [];

        $scale_type = $config['scale_type'] ?? self::SCALE_TYPE_STARS;
        if (!in_array($scale_type,
            [self::SCALE_TYPE_STARS, self::SCALE_TYPE_NPS], true)) {
            $errors['scale_type'] = get_string('rating_scale_type_invalid',
                'local_sentientia_live');
            // Fail-fast — every other key's validity depends on this.
            return $errors;
        }

        $min = (int) ($config['scale_min']
            ?? ($scale_type === self::SCALE_TYPE_NPS
                ? self::NPS_MIN : self::STARS_MIN));
        $max = (int) ($config['scale_max']
            ?? ($scale_type === self::SCALE_TYPE_NPS
                ? self::NPS_MAX : self::STARS_MAX));
        if ($min < 0 || $max <= $min || $max > 10) {
            $errors['scale_min_max'] = get_string('rating_scale_invalid',
                'local_sentientia_live');
        }

        if (array_key_exists('scale_labels', $config)) {
            $labels = $config['scale_labels'];
            if (!is_array($labels)) {
                $errors['scale_labels'] = get_string(
                    'rating_scale_labels_must_array',
                    'local_sentientia_live');
            } else {
                $expected = $max - $min + 1;
                if (count($labels) > 0 && count($labels) !== $expected) {
                    $errors['scale_labels'] = get_string(
                        'rating_scale_labels_length_mismatch',
                        'local_sentientia_live', (object) [
                            'expected' => $expected,
                            'got'      => count($labels),
                        ]);
                }
                foreach ($labels as $l) {
                    if (!is_string($l)) {
                        $errors['scale_labels'] = get_string(
                            'rating_scale_labels_must_string',
                            'local_sentientia_live');
                        break;
                    }
                }
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
            'mean_updated'      => get_string('qt_rating_a11y_mean_updated',
                'local_sentientia_live'),
            'median_updated'    => get_string('qt_rating_a11y_median_updated',
                'local_sentientia_live'),
        ];
    }

    /**
     * Mean (a.k.a. arithmetic average). Static + pure so tests can
     * verify against the formula without DB fixtures.
     *
     * @param int[] $values Pre-sorted not required.
     * @return float|null   Rounded to 2 decimals, null when empty.
     */
    public static function compute_mean(array $values): ?float {
        $n = count($values);
        if ($n === 0) {
            return null;
        }
        return round(array_sum($values) / $n, 2);
    }

    /**
     * Median — the middle value of a sorted list. For even N, average
     * of the two middle values. Static + pure (sorts internally so
     * caller doesn't have to).
     *
     * @param int[] $values
     * @return float|null  Rounded to 2 decimals, null when empty.
     */
    public static function compute_median(array $values): ?float {
        $n = count($values);
        if ($n === 0) {
            return null;
        }
        $sorted = $values;
        sort($sorted, SORT_NUMERIC);
        $mid = intdiv($n, 2);
        if ($n % 2 === 1) {
            return (float) $sorted[$mid];
        }
        return round(($sorted[$mid - 1] + $sorted[$mid]) / 2, 2);
    }

    /**
     * Resolve scale_type from a settings blob, inferring from min/max
     * when not explicit (back-compat with pre-D4 slides that didn't
     * record scale_type).
     */
    private function resolve_scale_type(array $settings): string {
        $declared = $settings['scale_type'] ?? null;
        if (in_array($declared,
            [self::SCALE_TYPE_STARS, self::SCALE_TYPE_NPS], true)) {
            return $declared;
        }
        // No explicit scale_type (pre-D4 slide). Infer from the upper
        // bound only: NPS tops out at 10, a star scale never does. Using
        // the max (not the span) avoids misreading a custom 1-8 star
        // scale — span 7 — as NPS.
        $max = (int) ($settings['scale_max'] ?? self::STARS_MAX);
        if ($max >= self::NPS_MAX) {
            return self::SCALE_TYPE_NPS;
        }
        return self::SCALE_TYPE_STARS;
    }

    /**
     * Effective [min, max] for a scale type. Honours settings overrides
     * but falls back to the type's natural defaults.
     */
    private function scale_bounds(string $type, array $settings): array {
        if ($type === self::SCALE_TYPE_NPS) {
            return [
                (int) ($settings['scale_min'] ?? self::NPS_MIN),
                (int) ($settings['scale_max'] ?? self::NPS_MAX),
            ];
        }
        return [
            (int) ($settings['scale_min'] ?? self::STARS_MIN),
            (int) ($settings['scale_max'] ?? self::STARS_MAX),
        ];
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
