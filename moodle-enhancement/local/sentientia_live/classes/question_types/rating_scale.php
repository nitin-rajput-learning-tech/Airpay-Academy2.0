<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Rating-scale question type — Phase E.7 stub (2026-05-24).
 *
 * Audience picks one integer on a configurable scale. Two common
 * shapes:
 *
 *   1-5 Likert  : scale_min=1, scale_max=5,
 *                 scale_labels=["Strongly disagree", ..., "Strongly agree"]
 *   0-10 NPS    : scale_min=0, scale_max=10
 *
 * Results render as a histogram with one bar per scale step plus an
 * average + count summary above the chart.
 *
 * Settings shape:
 *   {scale_min: int (0-10),
 *    scale_max: int (>min, ≤10),
 *    scale_labels: string[]}  // optional, length = (max-min+1)
 *
 * Tally shape:
 *   ['1' => count, '2' => count, ..., 'N' => count,
 *    '_avg'   => float,
 *    '_count' => int]
 *
 *   The underscore-prefixed keys are sentinel (not real scale values)
 *   — keeps the chart renderer's foreach uniform while letting the
 *   summary panel pluck them out.
 *
 * Response payload:
 *   ['value' => int]   // must satisfy scale_min ≤ value ≤ scale_max
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
