<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Multiple-choice question type — Phase E.4 stub (2026-05-24).
 *
 * Audience picks ONE of N options (2 ≤ N ≤ 20). Results render as a
 * horizontal bar chart with one bar per option, percentage labels and
 * a running count.
 *
 * Settings shape (validated by slide_manager::validate_settings):
 *   {options: ["a", "b", "c", ...]}      // 2-20 strings, 1-200 chars
 *
 * Tally shape:
 *   ['0' => count_of_option_index_0, '1' => count_of_option_index_1, ...]
 *
 * Response payload shape (POST body):
 *   ['option_index' => int]
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class multiple_choice extends abstract_question_type {

    public const SLUG = 'multichoice';
    public const FEATURE_FLAG = 'live.questiontype.multichoice';
    public const NAME_STRING_KEY = 'qtype_multichoice_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_multichoice_desc';

    /**
     * @inheritDoc
     */
    public function render(array $context): string {
        // Phase E.4 chip will fill this with the audience-facing
        // radio-group HTML. Today it's intentionally a no-op so the
        // registry interface tests pass without depending on the
        // template ecosystem.
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
