<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses Claude's response into a normalised question array.
 *
 * The expected payload is documented in {@see prompt_builder} — a JSON
 * object with a single "questions" key. This parser:
 *
 *   - Strips any markdown code-fence the model might add despite the
 *     system prompt asking for raw JSON
 *   - Decodes strictly (no JSON5, no comments)
 *   - Validates each question item against the schema
 *   - Drops malformed items silently (debugging() logs the reason)
 *
 * Output: array of normalised question objects (stdClass):
 *
 *     [
 *       (object)[
 *         'qtype'         => 'multichoice',
 *         'qtext'         => '....',
 *         'qoptions'      => ['A', 'B', 'C', 'D'],  // PHP array
 *         'qoptions_json' => '["A","B","C","D"]',   // ready to persist
 *         'qanswer'       => '2',                    // stringified index
 *         'qexplanation'  => '...',
 *       ],
 *       ...
 *     ]
 *
 * @package local_sentientia_aiquiz
 */
class response_parser {

    /** Multichoice: each item must have exactly this many options. */
    public const MULTICHOICE_OPTIONS = 4;

    /** Max char length for stems / options / explanations (defensive). */
    public const MAX_TEXT_LEN = 1000;

    /**
     * Parse Claude's text body into a normalised question list.
     *
     * @param string $body Claude's response text (typically content[0].text from the API)
     * @return \stdClass[] Array of parsed question objects (may be empty)
     */
    public static function parse(string $body): array {
        $json = self::extract_json($body);
        if ($json === '') {
            debugging('local_sentientia_aiquiz: response body had no JSON');
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['questions']) || !is_array($decoded['questions'])) {
            debugging('local_sentientia_aiquiz: response JSON malformed (no questions array)');
            return [];
        }

        $out = [];
        foreach ($decoded['questions'] as $idx => $item) {
            $parsed = self::normalise_item($item);
            if ($parsed !== null) {
                $out[] = $parsed;
            } else {
                debugging("local_sentientia_aiquiz: dropping malformed question at index {$idx}");
            }
        }

        return $out;
    }

    /**
     * Pull the JSON object out of a possibly-wrapped response.
     *
     * Even with a strict system prompt, models sometimes wrap output in
     * ```json ... ``` fences or add a leading sentence. We:
     *   1. Strip code fences if present
     *   2. Find the first `{` and the last matching `}`
     *
     * Returns '' if no plausible JSON object found.
     *
     * @param string $body
     * @return string
     */
    public static function extract_json(string $body): string {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        // Strip ```json ... ``` fences.
        if (preg_match('/^```(?:json)?\s*(.+?)\s*```\s*$/su', $body, $m)) {
            $body = trim($m[1]);
        }

        // Find first { and matching last }.
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
     * Validate + normalise a single question item from the decoded JSON.
     *
     * Returns null if the item fails any validation check — caller drops it.
     *
     * @param mixed $item
     * @return \stdClass|null
     */
    private static function normalise_item($item): ?\stdClass {
        if (!is_array($item)) {
            return null;
        }

        $qtype = isset($item['qtype']) && is_string($item['qtype']) ? trim($item['qtype']) : '';
        if ($qtype !== 'multichoice') {
            // Phase G.0: only multichoice supported.
            return null;
        }

        $qtext = isset($item['qtext']) && is_string($item['qtext']) ? trim($item['qtext']) : '';
        if ($qtext === '' || strlen($qtext) > self::MAX_TEXT_LEN) {
            return null;
        }

        if (!isset($item['qoptions']) || !is_array($item['qoptions'])) {
            return null;
        }
        $options = [];
        foreach ($item['qoptions'] as $opt) {
            if (!is_string($opt)) {
                return null;
            }
            $clean = trim($opt);
            if ($clean === '' || strlen($clean) > self::MAX_TEXT_LEN) {
                return null;
            }
            $options[] = $clean;
        }
        if (count($options) !== self::MULTICHOICE_OPTIONS) {
            return null;
        }
        // Require all options distinct.
        if (count(array_unique($options)) !== self::MULTICHOICE_OPTIONS) {
            return null;
        }

        // Accept either qanswer_index (preferred) or qanswer (loose).
        $idx = null;
        if (isset($item['qanswer_index']) && is_int($item['qanswer_index'])) {
            $idx = $item['qanswer_index'];
        } else if (isset($item['qanswer_index']) && is_string($item['qanswer_index']) && ctype_digit($item['qanswer_index'])) {
            $idx = (int)$item['qanswer_index'];
        } else if (isset($item['qanswer']) && is_int($item['qanswer'])) {
            $idx = $item['qanswer'];
        }
        if ($idx === null || $idx < 0 || $idx >= self::MULTICHOICE_OPTIONS) {
            return null;
        }

        $explanation = '';
        if (isset($item['qexplanation']) && is_string($item['qexplanation'])) {
            $explanation = trim($item['qexplanation']);
            if (strlen($explanation) > self::MAX_TEXT_LEN) {
                $explanation = substr($explanation, 0, self::MAX_TEXT_LEN);
            }
        }

        $out = new \stdClass();
        $out->qtype         = 'multichoice';
        $out->qtext         = $qtext;
        $out->qoptions      = $options;
        $out->qoptions_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        $out->qanswer       = (string)$idx;
        $out->qexplanation  = $explanation;
        return $out;
    }
}
