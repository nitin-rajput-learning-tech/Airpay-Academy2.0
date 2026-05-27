<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

use local_sentientia_live\profanity_filter;
use local_sentientia_live\response_recorder;

defined('MOODLE_INTERNAL') || die();

/**
 * Word-cloud question type — Phase E.5 full implementation (2026-05-25).
 *
 * Audience submits one to N short words (default 3) as free-text. We
 * tokenise on whitespace + punctuation, filter against a profanity
 * denylist, drop too-short tokens, lower-case for aggregation, and
 * append to the participant's existing word list. Re-submitting updates
 * the same row — the list never grows past max_responses_per_user.
 *
 * The result panel renders a tag-cloud where each word's font size is
 * proportional to its global frequency, with 5 buckets
 * (cloud-size-1 … cloud-size-5).
 *
 * Storage model
 * -------------
 * The DB schema enforces ONE row per (slideid, participantid) via
 * uk_slide_participant. So we can't store one row per word. Instead
 * value_text carries the participant's list of words as a JSON array:
 *
 *     ["trust", "innovation", "speed"]
 *
 * Legacy single-word strings (from pre-Phase E.5 word-cloud rows) are
 * still readable — both tally() and the response_recorder fallback
 * tokenise on whitespace if json_decode fails. So no upgrade is needed
 * for in-flight sessions when this chip ships.
 *
 * Settings shape (validated by validate_config())
 * -----------------------------------------------
 *   max_word_length      int, 3-100, default 50    — per-word cap
 *   max_responses_per_user int, 1-10, default 3    — words per learner
 *   min_word_length      int, 1-20, default 2      — drop too-short
 *   dedupe               bool, default true        — see below
 *   locale               string, default 'en'      — denylist scope hint
 *
 * Tally shape
 * -----------
 *   ['trust' => 12, 'innovation' => 7, ...]   sorted desc by count
 *
 * Response payload
 * ----------------
 *   ['value_text' => string]   ← free text; may contain multiple words
 *
 * Profanity filter
 * ----------------
 * Each tokenised word is checked against profanity_filter. Customer-
 * specific overrides come from local_airpay_core::get_customer_config
 * (resolved inside profanity_filter::resolve_denylist). When a token
 * is denied we silently drop it — the audience UX shows their accepted
 * words; the denied ones never appear.
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

    /** Hard cap on words a single participant can contribute. */
    public const MAX_RESPONSES_PER_USER_CAP = 10;

    /** Default per-word character cap. Aligns with slide_manager default. */
    public const DEFAULT_MAX_WORD_LENGTH = 50;

    /** Default min word length — drop one-letter tokens by default. */
    public const DEFAULT_MIN_WORD_LENGTH = 2;

    /** Default max submissions per participant per slide. */
    public const DEFAULT_MAX_RESPONSES_PER_USER = 3;

    /**
     * Render the audience-facing response form for one word-cloud slide.
     *
     * @param array $context Must contain:
     *                       - 'slide'         : stdClass slide row
     *                       - 'settings'      : parsed settings (validate_config shape)
     *                       - 'aria_id_prefix': string id stem
     *                       - 'session'       : stdClass session row
     *                       - 'participant'   : stdClass|null (current participant)
     *                       - 'action_url'    : moodle_url|string POST target
     *                       - 'sesskey'       : optional CSRF token (auto-fetched if absent)
     * @return string HTML safe to echo.
     */
    public function render(array $context): string {
        $slide    = $context['slide'] ?? null;
        $settings = $context['settings']
            ?? ($slide ? \local_sentientia_live\slide_manager::parse_settings($slide) : []);
        $aria_prefix = (string) ($context['aria_id_prefix'] ?? 'wc_' . random_string(6));

        $max_len = max(3, min(100,
            (int) ($settings['max_word_length']
                ?? self::DEFAULT_MAX_WORD_LENGTH)));
        $max_resp = max(1, min(self::MAX_RESPONSES_PER_USER_CAP,
            (int) ($settings['max_responses_per_user']
                ?? self::DEFAULT_MAX_RESPONSES_PER_USER)));
        $min_len = max(1, min(20,
            (int) ($settings['min_word_length']
                ?? self::DEFAULT_MIN_WORD_LENGTH)));

        // How many submissions has this participant already made? Drives
        // the "n of m remaining" hint + disables the form when capped.
        // Nullsafe ?-> : participant may be null (e.g. preview render) —
        // avoids a PHP 8 "property on null" warning.
        $already = $this->count_existing_words(
            $slide ? (int) $slide->id : 0,
            (int) ($context['participant']?->id ?? 0));
        $remaining = max(0, $max_resp - $already);

        $action_url = $context['action_url'] ?? '#';
        if ($action_url instanceof \moodle_url) {
            $action_url = $action_url->out(false);
        }
        $sesskey = (string) ($context['sesskey'] ?? sesskey());

        $input_id = $aria_prefix . '_input';
        $hint_id  = $aria_prefix . '_hint';
        $remaining_label = get_string('wc_remaining_hint',
            'local_sentientia_live', (object) [
                'remaining' => $remaining,
                'max'       => $max_resp,
            ]);

        $placeholder = get_string('wc_response_placeholder',
            'local_sentientia_live');

        $disabled_attr = $remaining > 0 ? '' : 'disabled';

        // Hand-rolled HTML so the audience surface stays mobile-friendly
        // (no moodleform chrome). Every dynamic value is escaped via
        // s() or format_string() before the concat.
        $form  = '<form method="post" action="' . s($action_url) . '" '
               . 'class="sentientia-wordcloud-form" '
               . 'data-min-length="' . (int) $min_len . '" '
               . 'data-max-length="' . (int) $max_len . '" '
               . 'data-max-responses="' . (int) $max_resp . '" '
               . 'data-already-submitted="' . (int) $already . '">'
               . '<input type="hidden" name="sesskey" value="'
                    . s($sesskey) . '">'
               . '<input type="hidden" name="slideid" value="'
                    . (int) ($slide->id ?? 0) . '">'
               . '<label for="' . s($input_id) . '" class="form-label visually-hidden">'
                    . s(get_string('wc_audience_input_label',
                        'local_sentientia_live')) . '</label>'
               . '<input type="text" '
                    . 'id="' . s($input_id) . '" '
                    . 'name="value_text" '
                    . 'class="form-control form-control-lg text-center mb-2" '
                    . 'maxlength="' . (int) $max_len . '" '
                    . 'placeholder="' . s($placeholder) . '" '
                    . 'aria-describedby="' . s($hint_id) . '" '
                    . 'autocomplete="off" '
                    . 'autofocus '
                    . 'required '
                    . $disabled_attr . '>'
               . '<div id="' . s($hint_id) . '" '
                    . 'class="text-muted small mb-3 text-center" '
                    . 'role="status" aria-live="polite">'
                    . s($remaining_label) . '</div>'
               . '<button type="submit" '
                    . 'class="btn btn-primary btn-lg w-100" '
                    . ($remaining > 0 ? '' : 'disabled') . '>'
                    . s(get_string('audience_submit_response',
                        'local_sentientia_live'))
               . '</button>'
               . '</form>';

        return $form;
    }

    /**
     * Validate + persist one word-cloud submission. Tokenises the raw
     * input, drops empties + too-shorts + profanity, then appends to
     * the participant's existing word list (capped at
     * max_responses_per_user).
     *
     * Returns the response row ID. Re-submissions update the same row
     * via response_recorder::submit's idempotent uk_slide_participant
     * handling.
     *
     * Note: $userid in this contract is the participantid (caller
     * resolved Moodle userid → participantid via participant_manager).
     * That keeps anonymous + logged-in flows uniform.
     *
     * @param int   $userid  participantid (NOT $USER->id).
     * @param array $payload Must contain 'slideid' (int) and 'value_text'.
     *                       'customerid' (int) optional — controls
     *                       which profanity denylist applies.
     * @return int Response row ID.
     * @throws \moodle_exception on validation failure.
     */
    public function persist_response(int $userid, array $payload): int {
        global $DB;

        $slideid = (int) ($payload['slideid'] ?? 0);
        if ($slideid <= 0) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        $raw = isset($payload['value_text'])
            ? trim((string) $payload['value_text'])
            : '';

        $slide = \local_sentientia_live\slide_manager::get($slideid);
        if (!$slide || $slide->type !== self::SLUG) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        $settings = \local_sentientia_live\slide_manager::parse_settings($slide);

        $max_word_len = max(3, min(100,
            (int) ($settings['max_word_length']
                ?? self::DEFAULT_MAX_WORD_LENGTH)));
        $max_resp = max(1, min(self::MAX_RESPONSES_PER_USER_CAP,
            (int) ($settings['max_responses_per_user']
                ?? self::DEFAULT_MAX_RESPONSES_PER_USER)));
        $min_word_len = max(1, min(20,
            (int) ($settings['min_word_length']
                ?? self::DEFAULT_MIN_WORD_LENGTH)));
        $customerid = (int) ($payload['customerid'] ?? 0);

        if ($raw === '') {
            throw new \moodle_exception('response_text_required',
                'local_sentientia_live');
        }

        // Tokenise on whitespace + punctuation. Keep Unicode letters so
        // Devanagari etc. survive (Hindi rollout depends on this).
        $tokens = self::tokenise($raw);

        // Drop too-short, too-long (already truncated by maxlength on
        // input, but defense-in-depth), and profanity.
        $clean = [];
        foreach ($tokens as $tok) {
            $lower = mb_strtolower($tok, 'UTF-8');
            if (mb_strlen($lower, 'UTF-8') < $min_word_len) {
                continue;
            }
            if (mb_strlen($lower, 'UTF-8') > $max_word_len) {
                $lower = mb_substr($lower, 0, $max_word_len, 'UTF-8');
            }
            $clean[] = $lower;
        }
        $clean = profanity_filter::filter($clean, $customerid);

        if (empty($clean)) {
            throw new \moodle_exception('response_text_required',
                'local_sentientia_live');
        }

        // Load existing words for this (slide, participant) and append.
        // The uk_slide_participant unique key ensures we update — never
        // duplicate — when the participant submits again.
        $existing_row = $DB->get_record(
            'local_sentientia_live_responses',
            ['slideid' => $slideid, 'participantid' => $userid]);

        $existing_words = $existing_row
            ? self::decode_words((string) ($existing_row->value_text ?? ''))
            : [];

        // Reject when the participant is already at the cap — surfaces a
        // clear error instead of a silent no-op.
        if (count($existing_words) >= $max_resp) {
            throw new \moodle_exception('wc_max_responses_reached',
                'local_sentientia_live', '', $max_resp);
        }

        $merged = array_merge($existing_words, $clean);

        // dedupe (default ON) collapses a single participant's repeated
        // words case-insensitively, so one person can't inflate a word's
        // tally weight by submitting it twice. The max_responses_per_user
        // cap governs HOW MANY words; dedupe governs UNIQUENESS — they're
        // orthogonal. When dedupe is OFF the trainer has explicitly opted
        // into allowing duplicates.
        $dedupe = (bool) ($settings['dedupe'] ?? true);
        if ($dedupe) {
            $seen = [];
            $deduped = [];
            foreach ($merged as $w) {
                $k = mb_strtolower($w, 'UTF-8');
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $deduped[] = $w;
            }
            $merged = $deduped;
        }

        if (count($merged) > $max_resp) {
            $merged = array_slice($merged, 0, $max_resp);
        }

        $value_text = json_encode(array_values($merged),
            JSON_UNESCAPED_UNICODE);

        return response_recorder::submit($slideid, $userid,
            null, $value_text);
    }

    /**
     * Compute the live tally for this slide: word frequency map sorted
     * desc by count. Used by the result panel renderer.
     *
     * @param int $sessionid (unused — kept for interface conformance)
     * @param int $slideid
     * @return array<string, int> word => count, sorted desc.
     */
    public function tally(int $sessionid, int $slideid): array {
        global $DB;
        $rows = $DB->get_records(
            'local_sentientia_live_responses',
            ['slideid' => $slideid],
            '', 'id, value_text');

        $freq = [];
        foreach ($rows as $r) {
            $words = self::decode_words((string) ($r->value_text ?? ''));
            foreach ($words as $w) {
                $w = mb_strtolower(trim($w), 'UTF-8');
                if ($w === '') {
                    continue;
                }
                $freq[$w] = ($freq[$w] ?? 0) + 1;
            }
        }
        arsort($freq);
        return $freq;
    }

    /**
     * Validate slide-creation-time settings. Returns array of error
     * messages (empty = valid). Never throws.
     */
    public function validate_config(array $config): array {
        $errors = [];

        if (isset($config['max_responses_per_user'])) {
            $mr = (int) $config['max_responses_per_user'];
            if ($mr < 1 || $mr > self::MAX_RESPONSES_PER_USER_CAP) {
                $errors['max_responses_per_user'] = get_string(
                    'wc_max_responses_invalid', 'local_sentientia_live',
                    (object) [
                        'min' => 1,
                        'max' => self::MAX_RESPONSES_PER_USER_CAP,
                    ]);
            }
        }
        if (isset($config['min_word_length'])) {
            $mw = (int) $config['min_word_length'];
            if ($mw < 1 || $mw > 20) {
                $errors['min_word_length'] = get_string(
                    'wc_min_word_length_invalid',
                    'local_sentientia_live');
            }
        }
        if (isset($config['max_word_length'])) {
            $mx = (int) $config['max_word_length'];
            if ($mx < 3 || $mx > 100) {
                $errors['max_word_length'] = get_string(
                    'wc_max_word_length_help',
                    'local_sentientia_live');
            }
        }
        if (isset($config['min_word_length'])
            && isset($config['max_word_length'])) {
            if ((int) $config['min_word_length']
                > (int) $config['max_word_length']) {
                // Attach to max_word_length with a dedicated message —
                // the min field is valid in isolation; it's the max that
                // was set too low relative to min.
                $errors['max_word_length'] = get_string(
                    'wc_min_exceeds_max',
                    'local_sentientia_live');
            }
        }
        if (isset($config['locale'])
            && !is_string($config['locale'])) {
            $errors['locale'] = 'locale must be a string';
        }
        return $errors;
    }

    /**
     * Static a11y announcements the chart_updater / wordcloud_updater
     * register with the panel's aria-live region.
     */
    public function get_aria_announcements(): array {
        return [
            'response_recorded' => get_string('a11y_response_recorded',
                'local_sentientia_live'),
            'new_word_added'    => get_string('wc_a11y_new_word_added',
                'local_sentientia_live'),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Tokenise free text on whitespace + common punctuation. Keeps
     * Unicode letters (Devanagari, accented Latin, etc.) intact.
     *
     * @param string $raw User input.
     * @return string[] Token list (still in original case — caller
     *                  lowercases for aggregation).
     */
    public static function tokenise(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        // Split on whitespace + common punctuation. The character
        // class includes ASCII punctuation that's universal across
        // locales; we keep letters from any script via PCRE's \PL
        // (NOT-letter) approach. Empty tokens drop in the array_filter.
        $parts = preg_split('/[\s\.,;:!?\(\)\[\]\{\}"\'\/\\\\<>@#\$%\^&\*\+=\|~`]+/u',
            $raw, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? array_values($parts) : [];
    }

    /**
     * Decode the value_text column into a list of words. Handles three
     * shapes for back-compat:
     *
     *   (a) JSON array  ["a","b","c"]   — the canonical Phase E.5 shape
     *   (b) JSON string "a"             — legacy single-word JSON
     *   (c) plain string "a b c"        — legacy free-text (pre-E.5)
     *
     * Case (a) returns the array as-is. Case (b) and (c) tokenise
     * on whitespace.
     *
     * @param string $value_text Raw column value.
     * @return string[]
     */
    public static function decode_words(string $value_text): array {
        $value_text = trim($value_text);
        if ($value_text === '') {
            return [];
        }
        $decoded = json_decode($value_text, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $w) {
                if (!is_string($w)) {
                    continue;
                }
                $w = trim($w);
                if ($w !== '') {
                    $out[] = $w;
                }
            }
            return $out;
        }
        if (is_string($decoded) && $decoded !== '') {
            // Legacy single-word JSON string — one token, as stored.
            return [$decoded];
        }
        // Plain-string legacy row (pre-E.5 stored the whole entry as a
        // single "word", and the old tally counted it as one key). Keep
        // that 1-row = 1-token semantics — do NOT split on whitespace,
        // or in-flight sessions' tallies AND the per-user cap shift on
        // upgrade (CLAUDE.md: never break current production behaviour).
        // New submissions never reach here: persist_response always
        // stores a JSON array, which the is_array() branch above handles.
        return [$value_text];
    }

    /**
     * Count how many words a participant has already submitted on a
     * given slide. Used by render() to compute the "N of M remaining"
     * hint shown beneath the input field.
     */
    private function count_existing_words(int $slideid,
                                            int $participantid): int {
        global $DB;
        if ($slideid <= 0 || $participantid <= 0) {
            return 0;
        }
        $row = $DB->get_record(
            'local_sentientia_live_responses',
            ['slideid' => $slideid, 'participantid' => $participantid],
            'id, value_text');
        if (!$row) {
            return 0;
        }
        return count(self::decode_words((string) ($row->value_text ?? '')));
    }
}
