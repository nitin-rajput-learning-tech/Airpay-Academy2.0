<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses Claude's course-generation response into normalised cards + questions.
 *
 * Expected payload: a JSON object with "cards" and "questions" arrays (see
 * {@see prompt_builder}). This parser:
 *
 *   - strips any markdown code-fence the model might add
 *   - decodes strictly (no JSON5, no comments)
 *   - validates each card against the card schema, dropping malformed items
 *   - delegates each question to {@see question_type::normalise()}, which
 *     enforces the multichoice / mrq / match rules and drops malformed items
 *
 * Output: a \stdClass with two arrays:
 *
 *   ->cards     = [ {cardtype, heading, body, flip_back, narration}, ... ]
 *   ->questions = [ normalised question_type::normalise() objects, ... ]
 *
 * All length checks are unicode-aware for Devanagari.
 *
 * @package local_sentientia_authoring
 */
class response_parser {

    /** Allowed card types. */
    public const CARD_TYPES = ['concept', 'example', 'scenario', 'flip'];

    /** Defensive max lengths (characters). */
    public const MAX_HEADING_LEN   = 120;
    public const MAX_BODY_LEN      = 600;
    public const MAX_FLIP_LEN      = 400;
    public const MAX_NARRATION_LEN = 400;

    /**
     * Parse Claude's text body into normalised cards + questions.
     *
     * @param string $body Claude's response text (content[0].text from the API).
     * @return \stdClass {cards: \stdClass[], questions: \stdClass[]}
     */
    public static function parse(string $body): \stdClass {
        $out = new \stdClass();
        $out->cards = [];
        $out->questions = [];

        $json = self::extract_json($body);
        if ($json === '') {
            debugging('local_sentientia_authoring: response body had no JSON');
            return $out;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            debugging('local_sentientia_authoring: response JSON malformed');
            return $out;
        }

        if (isset($decoded['cards']) && is_array($decoded['cards'])) {
            foreach ($decoded['cards'] as $idx => $item) {
                $card = self::normalise_card($item);
                if ($card !== null) {
                    $out->cards[] = $card;
                } else {
                    debugging("local_sentientia_authoring: dropping malformed card at index {$idx}");
                }
            }
        }

        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            foreach ($decoded['questions'] as $idx => $item) {
                $q = question_type::normalise($item);
                if ($q !== null) {
                    $out->questions[] = $q;
                } else {
                    debugging("local_sentientia_authoring: dropping malformed question at index {$idx}");
                }
            }
        }

        return $out;
    }

    /**
     * Pull the JSON object out of a possibly-wrapped response. Strips ```json
     * fences and trims to the first { … last }.
     *
     * @param string $body
     * @return string '' when no plausible JSON object is found.
     */
    public static function extract_json(string $body): string {
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        if (preg_match('/^```(?:json)?\s*(.+?)\s*```\s*$/su', $body, $m)) {
            $body = trim($m[1]);
        }
        $start = strpos($body, '{');
        if ($start === false) {
            return '';
        }
        $end = strrpos($body, '}');
        if ($end === false || $end <= $start) {
            return '';
        }
        return substr($body, $start, $end - $start + 1);
    }

    /**
     * Validate + normalise a single card item.
     *
     * @param mixed $item
     * @return \stdClass|null
     */
    private static function normalise_card($item): ?\stdClass {
        if (!is_array($item)) {
            return null;
        }
        $cardtype = isset($item['cardtype']) && is_string($item['cardtype'])
            ? trim($item['cardtype']) : '';
        if (!in_array($cardtype, self::CARD_TYPES, true)) {
            return null;
        }

        $heading = self::clean($item['heading'] ?? '', self::MAX_HEADING_LEN, true);
        if ($heading === null) {
            // Heading may be empty for some card types — coerce to ''.
            $heading = '';
        }
        $bodytext = self::clean($item['body'] ?? null, self::MAX_BODY_LEN, false);
        if ($bodytext === null) {
            return null; // body is required + non-empty
        }

        $flipback = null;
        if ($cardtype === 'flip') {
            $flipback = self::clean($item['flip_back'] ?? null, self::MAX_FLIP_LEN, false);
            if ($flipback === null) {
                return null; // flip cards REQUIRE a back face
            }
        }

        $narration = null;
        if (isset($item['narration']) && is_string($item['narration'])) {
            $n = trim($item['narration']);
            if ($n !== '') {
                $narration = mb_substr($n, 0, self::MAX_NARRATION_LEN);
            }
        }

        $out = new \stdClass();
        $out->cardtype  = $cardtype;
        $out->heading   = $heading;
        $out->body      = $bodytext;
        $out->flip_back = $flipback;
        $out->narration = $narration;
        return $out;
    }

    /**
     * Trim + length-check a text value.
     *
     * @param mixed $value
     * @param int   $maxlen
     * @param bool  $allow_empty When true, empty string is returned as ''
     *                            instead of being treated as a failure.
     * @return string|null Trimmed (and truncated) string, or null on failure.
     */
    private static function clean($value, int $maxlen, bool $allow_empty): ?string {
        if (!is_string($value)) {
            return $allow_empty ? '' : null;
        }
        $clean = trim($value);
        if ($clean === '') {
            return $allow_empty ? '' : null;
        }
        if (mb_strlen($clean) > $maxlen) {
            $clean = mb_substr($clean, 0, $maxlen);
        }
        return $clean;
    }
}
