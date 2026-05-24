<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Open-ended question type — Phase E.6 stub (2026-05-24).
 *
 * Audience submits FREE-FORM text up to a configurable max char cap
 * (default 280, Twitter-style). Results render as a scrolling list of
 * responses on the trainer's projector view; trainer can hide/star
 * individual responses (deferred to Phase E.6.b).
 *
 * Settings shape:
 *   {max_chars: int (10-2000, default 280)}
 *
 * Tally shape:
 *   [string, string, ...]   // raw list, arrival order, no dedupe
 *
 * Response payload:
 *   ['text' => string]
 *
 * Anti-abuse:
 *   - Per-slide rate limit via response_recorder's idempotent
 *     uk_slide_participant unique key (one response per participant
 *     per slide).
 *   - HTML stripped at persist time (text-only display).
 *   - Trainer-only moderation panel deferred to Phase E.6.b.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class open_ended extends abstract_question_type {

    public const SLUG = 'openended';
    public const FEATURE_FLAG = 'live.questiontype.openended';
    public const NAME_STRING_KEY = 'qtype_openended_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_openended_desc';

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
