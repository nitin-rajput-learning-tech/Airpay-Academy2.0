<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\output;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_live\slide_manager;
use local_sentientia_live\response_recorder;

/**
 * Result panel renderable — Phase E.4.a.
 *
 * Takes a slide + tally(slide) and exports template context for a
 * type-appropriate visualization:
 *
 *   multichoice / quiz : horizontal bar chart (option_label + count + %)
 *   rating             : distribution histogram + avg + count
 *   wordcloud          : sized tag cloud (font size proportional to freq)
 *   openended          : scrolling list of raw text responses
 *   ranking            : ordered table by average position
 *
 * The template only sets text content + bar widths via style attributes
 * (no innerHTML hooks), so the SSE-driven refresh module can update
 * counts in place safely.
 *
 * @package local_sentientia_live
 */
class result_panel implements \renderable, \templatable {

    public function __construct(
        private \stdClass $slide,
        private bool $show_to_audience = true,
    ) {}

    public function export_for_template(\renderer_base $output): array {
        $type     = $this->slide->type;
        $tally    = response_recorder::tally((int) $this->slide->id);
        $total    = response_recorder::count_for_slide((int) $this->slide->id);
        $settings = slide_manager::parse_settings($this->slide);

        $ctx = [
            'slideid'         => (int) $this->slide->id,
            'sessionid'       => (int) $this->slide->sessionid,
            'type'            => $type,
            'is_multichoice'  => $type === 'multichoice',
            'is_quiz'         => $type === 'quiz',
            'is_rating'       => $type === 'rating',
            'is_wordcloud'    => $type === 'wordcloud',
            'is_openended'    => $type === 'openended',
            'is_ranking'      => $type === 'ranking',
            'show_to_audience' => $this->show_to_audience,
            'total_responses' => $total,
            'has_responses'   => $total > 0,
            'tally_json'      => json_encode($tally),
        ];

        // Type-specific data shaping for the template.
        switch ($type) {
            case 'multichoice':
            case 'quiz':
                $ctx['options'] = $this->shape_bar_chart(
                    $settings['options'] ?? [],
                    $tally,
                    $type === 'quiz' ? (int) ($settings['correct_index'] ?? -1) : -1);
                break;

            case 'rating':
                $ctx['rating']  = $this->shape_rating($tally, $settings);
                break;

            case 'wordcloud':
                $ctx['cloud']   = $this->shape_wordcloud($tally);
                break;

            case 'openended':
                $ctx['responses'] = $this->shape_openended((int) $this->slide->id);
                break;

            case 'ranking':
                $ctx['ranking'] = $this->shape_ranking(
                    $settings['items'] ?? [],
                    $tally);
                break;
        }

        return $ctx;
    }

    /**
     * Multichoice / quiz: array of options sorted by count desc with
     * label + count + percentage. For quiz, marks the correct one.
     */
    private function shape_bar_chart(array $option_labels, array $tally,
                                       int $correct_idx): array {
        $total = array_sum($tally);
        $max   = $total > 0 ? max($tally) : 0;
        $rows  = [];
        foreach ($option_labels as $i => $label) {
            $count   = (int) ($tally[$i] ?? 0);
            $percent = $total > 0 ? round(($count / $total) * 100) : 0;
            $bar_pc  = $max > 0 ? round(($count / $max) * 100) : 0;
            $rows[] = [
                'index'        => $i,
                'label'        => $label,
                'count'        => $count,
                'percent'      => $percent,
                'bar_percent'  => $bar_pc,
                'is_correct'   => $i === $correct_idx,
            ];
        }
        return $rows;
    }

    /**
     * Rating: each scale value with its count + bar width.
     */
    private function shape_rating(array $tally, array $settings): array {
        $min = (int) ($settings['scale_min'] ?? 1);
        $max = (int) ($settings['scale_max'] ?? 5);
        $labels = $settings['scale_labels'] ?? [];

        $bars = [];
        $max_count = 0;
        for ($v = $min; $v <= $max; $v++) {
            $c = (int) ($tally[$v] ?? 0);
            if ($c > $max_count) {
                $max_count = $c;
            }
        }
        for ($v = $min; $v <= $max; $v++) {
            $c = (int) ($tally[$v] ?? 0);
            $bars[] = [
                'value'       => $v,
                'count'       => $c,
                'label'       => $labels[$v - $min] ?? '',
                'bar_percent' => $max_count > 0
                    ? round(($c / $max_count) * 100)
                    : 0,
            ];
        }
        return [
            'bars'  => $bars,
            'avg'   => $tally['_avg'] ?? null,
            'count' => $tally['_count'] ?? 0,
            'min'   => $min,
            'max'   => $max,
        ];
    }

    /**
     * Wordcloud: top N words with font-size class.
     */
    private function shape_wordcloud(array $tally): array {
        // tally is already sorted desc by response_recorder::tally.
        // Cap to top 50 to keep the cloud readable.
        $top = array_slice($tally, 0, 50, true);
        if (empty($top)) {
            return [];
        }
        $max = max($top);
        $out = [];
        foreach ($top as $word => $count) {
            // 5 size classes: cloud-1 (small) … cloud-5 (large).
            $bucket = $max > 0
                ? max(1, min(5, (int) ceil(($count / $max) * 5)))
                : 1;
            $out[] = [
                'word'        => $word,
                'count'       => $count,
                'size_class'  => 'cloud-size-' . $bucket,
            ];
        }
        return $out;
    }

    /**
     * Openended: full text array, newest first.
     */
    private function shape_openended(int $slideid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.value_text, p.display_name, r.timecreated
               FROM {local_sentientia_live_responses} r
          LEFT JOIN {local_sentientia_live_participants} p
                    ON p.id = r.participantid
              WHERE r.slideid = :sid
           ORDER BY r.timecreated DESC, r.id DESC",
            ['sid' => $slideid], 0, 100);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'text'         => $r->value_text,
                'display_name' => $r->display_name ?? '?',
                'timeformat'   => userdate((int) $r->timecreated, '%H:%M'),
            ];
        }
        return $out;
    }

    /**
     * Ranking: each item with its average position (lower = more
     * preferred). Sorted by avg ascending.
     */
    private function shape_ranking(array $item_labels, array $tally): array {
        // tally is already asort-sorted by response_recorder::tally
        // (lower avg = more preferred = top of list).
        $out  = [];
        $rank = 1;
        foreach ($tally as $item_idx => $avg) {
            if (!is_int($item_idx)) {
                continue;
            }
            $out[] = [
                'rank'  => $rank,
                'index' => $item_idx,
                'label' => $item_labels[$item_idx] ?? ('Item ' . ($item_idx + 1)),
                'avg'   => $avg,
            ];
            $rank++;
        }
        return $out;
    }
}
