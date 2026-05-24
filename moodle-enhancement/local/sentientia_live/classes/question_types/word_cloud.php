<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Word-cloud question type — Phase E.5 stub (2026-05-24).
 *
 * Audience each submits ONE short word (default max 50 chars).
 * Results render as a tag-cloud — repeated words grow in font size
 * proportional to their frequency.
 *
 * Settings shape:
 *   {max_word_length: int (3-100, default 50),
 *    dedupe: bool (default true)}
 *
 *   When dedupe = true, the response_recorder's
 *   uk_slide_participant unique key already enforces "one word per
 *   participant" — re-submission overwrites the previous response.
 *
 * Tally shape:
 *   ['word' => count, 'other_word' => count, ...]  sorted desc by count
 *
 * Response payload:
 *   ['word' => string]
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_cloud extends abstract_question_type {

    public const SLUG = 'wordcloud';
    public const FEATURE_FLAG = 'live.questiontype.wordcloud';
    public const NAME_STRING_KEY = 'qtype_wordcloud_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_wordcloud_desc';

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
