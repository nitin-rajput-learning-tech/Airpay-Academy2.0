<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Question-type validation + normalisation for the Authoring Studio.
 *
 * This is the heart of the "expanded question types beyond MCQ" gap (P0.3 #4).
 * It validates and normalises three question types into a persist-ready shape:
 *
 *   - multichoice  — exactly 4 distinct options, EXACTLY ONE correct (index 0..3)
 *   - mrq          — multi-response: 2..MAX_OPTIONS distinct options, 1+ correct,
 *                    and NOT all correct (otherwise it's trivially "select all")
 *   - match        — match-the-following: 2..MAX_PAIRS distinct {left,right}
 *                    pairs, all lefts distinct, all rights distinct
 *
 * Validation is intentionally strict and side-effect-free so it can be unit
 * tested in isolation and reused by both the response_parser (validating
 * Claude's output) and the review UI (validating a reviewer's manual edits).
 *
 * Normalised output shape (\stdClass), ready for draft_manager::persist:
 *
 *   multichoice:
 *     qtype='multichoice', qtext, qoptions=[...], qoptions_json='[...]',
 *     qanswer='2'            (single index as string)
 *   mrq:
 *     qtype='mrq', qtext, qoptions=[...], qoptions_json='[...]',
 *     qanswer='[0,2]'        (JSON array of correct indices)
 *   match:
 *     qtype='match', qtext, qpairs=[{left,right},...], qoptions_json='[...]',
 *     qanswer='{"0":"...","1":"..."}'  (JSON map left-index → right-value)
 *
 * All length checks use mb_strlen so Devanagari (Hindi) content counts
 * characters, not UTF-8 bytes.
 *
 * @package local_sentientia_authoring
 */
class question_type {

    /** Supported question types. */
    public const TYPE_MULTICHOICE = 'multichoice';
    public const TYPE_MRQ         = 'mrq';
    public const TYPE_MATCH       = 'match';

    /** Multichoice must have exactly this many options. */
    public const MULTICHOICE_OPTIONS = 4;

    /** MRQ / match upper bounds (defensive — keeps a question renderable). */
    public const MAX_OPTIONS = 8;
    public const MIN_OPTIONS = 2;
    public const MAX_PAIRS   = 8;
    public const MIN_PAIRS   = 2;

    /** Max character length for any single text field (stem / option / value). */
    public const MAX_TEXT_LEN = 1000;

    /**
     * The full set of supported type strings.
     *
     * @return string[]
     */
    public static function supported_types(): array {
        return [self::TYPE_MULTICHOICE, self::TYPE_MRQ, self::TYPE_MATCH];
    }

    /**
     * Is the given string a supported question type?
     *
     * @param string $qtype
     * @return bool
     */
    public static function is_supported(string $qtype): bool {
        return in_array($qtype, self::supported_types(), true);
    }

    /**
     * Validate + normalise a single raw question item (decoded from JSON).
     *
     * Returns the normalised \stdClass on success, or null if the item fails
     * any validation check (caller drops it + logs at debugging level).
     *
     * @param mixed $item Associative array decoded from Claude's JSON, or a
     *                    reviewer-edited array from the review UI.
     * @return \stdClass|null
     */
    public static function normalise($item): ?\stdClass {
        if (!is_array($item)) {
            return null;
        }
        $qtype = isset($item['qtype']) && is_string($item['qtype']) ? trim($item['qtype']) : '';
        switch ($qtype) {
            case self::TYPE_MULTICHOICE:
                return self::normalise_multichoice($item);
            case self::TYPE_MRQ:
                return self::normalise_mrq($item);
            case self::TYPE_MATCH:
                return self::normalise_match($item);
            default:
                return null;
        }
    }

    /**
     * Shared text-field validator — non-empty after trim + within length.
     *
     * @param mixed $value
     * @return string|null Trimmed string, or null if invalid.
     */
    private static function clean_text($value): ?string {
        if (!is_string($value)) {
            return null;
        }
        $clean = trim($value);
        if ($clean === '' || mb_strlen($clean) > self::MAX_TEXT_LEN) {
            return null;
        }
        return $clean;
    }

    /**
     * Pull the optional feedback + explanation fields off a raw item.
     * These are advisory (AI contextual feedback) so they never fail the
     * whole item — they are simply trimmed/truncated.
     *
     * @param array     $item
     * @param \stdClass $out  Populated in place.
     * @return void
     */
    private static function attach_feedback(array $item, \stdClass $out): void {
        foreach ([
            'qfeedback_correct'   => 'qfeedback_correct',
            'qfeedback_incorrect' => 'qfeedback_incorrect',
            'qexplanation'        => 'qexplanation',
        ] as $src => $dst) {
            $val = '';
            if (isset($item[$src]) && is_string($item[$src])) {
                $val = trim($item[$src]);
                if (mb_strlen($val) > self::MAX_TEXT_LEN) {
                    $val = mb_substr($val, 0, self::MAX_TEXT_LEN);
                }
            }
            $out->{$dst} = $val;
        }
    }

    /**
     * Validate a multichoice item: 4 distinct options, exactly one correct.
     *
     * @param array $item
     * @return \stdClass|null
     */
    private static function normalise_multichoice(array $item): ?\stdClass {
        $qtext = self::clean_text($item['qtext'] ?? null);
        if ($qtext === null) {
            return null;
        }
        $options = self::clean_option_list($item['qoptions'] ?? null,
            self::MULTICHOICE_OPTIONS, self::MULTICHOICE_OPTIONS);
        if ($options === null) {
            return null;
        }

        // Accept qanswer_index (preferred) or a single-int qanswer.
        $idx = self::extract_single_index($item);
        if ($idx === null || $idx < 0 || $idx >= self::MULTICHOICE_OPTIONS) {
            return null;
        }

        $out = new \stdClass();
        $out->qtype         = self::TYPE_MULTICHOICE;
        $out->qtext         = $qtext;
        $out->qoptions      = $options;
        $out->qoptions_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        $out->qanswer       = (string) $idx;
        self::attach_feedback($item, $out);
        return $out;
    }

    /**
     * Validate an MRQ item: 2..MAX_OPTIONS distinct options, 1+ correct,
     * and not ALL correct (a "select everything" question is degenerate).
     *
     * @param array $item
     * @return \stdClass|null
     */
    private static function normalise_mrq(array $item): ?\stdClass {
        $qtext = self::clean_text($item['qtext'] ?? null);
        if ($qtext === null) {
            return null;
        }
        $options = self::clean_option_list($item['qoptions'] ?? null,
            self::MIN_OPTIONS, self::MAX_OPTIONS);
        if ($options === null) {
            return null;
        }
        $count = count($options);

        // Correct answers come as an array of indices.
        $raw = $item['qanswer_indices'] ?? ($item['qanswer'] ?? null);
        if (is_string($raw)) {
            // Tolerate a JSON-string array.
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw) || $raw === []) {
            return null;
        }

        $indices = [];
        foreach ($raw as $v) {
            if (is_int($v)) {
                $i = $v;
            } else if (is_string($v) && ctype_digit($v)) {
                $i = (int) $v;
            } else {
                return null;
            }
            if ($i < 0 || $i >= $count) {
                return null;
            }
            $indices[$i] = true; // dedupe via keys
        }
        $indices = array_keys($indices);
        sort($indices);

        // At least one correct, but not all (degenerate "select all").
        if (count($indices) < 1 || count($indices) >= $count) {
            return null;
        }

        $out = new \stdClass();
        $out->qtype         = self::TYPE_MRQ;
        $out->qtext         = $qtext;
        $out->qoptions      = $options;
        $out->qoptions_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        $out->qanswer       = json_encode(array_values($indices));
        self::attach_feedback($item, $out);
        return $out;
    }

    /**
     * Validate a match item: 2..MAX_PAIRS distinct {left,right} pairs with
     * all lefts distinct and all rights distinct (so each match is unique).
     *
     * @param array $item
     * @return \stdClass|null
     */
    private static function normalise_match(array $item): ?\stdClass {
        $qtext = self::clean_text($item['qtext'] ?? null);
        if ($qtext === null) {
            return null;
        }
        $rawpairs = $item['qpairs'] ?? null;
        if (!is_array($rawpairs)) {
            return null;
        }
        $count = count($rawpairs);
        if ($count < self::MIN_PAIRS || $count > self::MAX_PAIRS) {
            return null;
        }

        $pairs = [];
        $lefts = [];
        $rights = [];
        foreach ($rawpairs as $p) {
            if (!is_array($p)) {
                return null;
            }
            $left  = self::clean_text($p['left'] ?? null);
            $right = self::clean_text($p['right'] ?? null);
            if ($left === null || $right === null) {
                return null;
            }
            $pairs[]  = ['left' => $left, 'right' => $right];
            $lefts[]  = $left;
            $rights[] = $right;
        }
        // All lefts distinct AND all rights distinct.
        if (count(array_unique($lefts)) !== $count
                || count(array_unique($rights)) !== $count) {
            return null;
        }

        // Answer map: left-index → correct right-value (the diagonal mapping;
        // the front-end shuffles the right column at render time).
        $answer = [];
        foreach ($pairs as $i => $pair) {
            $answer[(string) $i] = $pair['right'];
        }

        $out = new \stdClass();
        $out->qtype         = self::TYPE_MATCH;
        $out->qtext         = $qtext;
        $out->qpairs        = $pairs;
        $out->qoptions_json = json_encode($pairs, JSON_UNESCAPED_UNICODE);
        $out->qanswer       = json_encode($answer, JSON_UNESCAPED_UNICODE);
        self::attach_feedback($item, $out);
        return $out;
    }

    /**
     * Validate + clean a flat list of option strings.
     *
     * @param mixed $raw   Expected: array of strings.
     * @param int   $min   Minimum allowed count.
     * @param int   $max   Maximum allowed count.
     * @return string[]|null Cleaned distinct options, or null on any failure.
     */
    private static function clean_option_list($raw, int $min, int $max): ?array {
        if (!is_array($raw)) {
            return null;
        }
        $options = [];
        foreach ($raw as $opt) {
            $clean = self::clean_text($opt);
            if ($clean === null) {
                return null;
            }
            $options[] = $clean;
        }
        $n = count($options);
        if ($n < $min || $n > $max) {
            return null;
        }
        if (count(array_unique($options)) !== $n) {
            return null; // options must be distinct
        }
        return $options;
    }

    /**
     * Extract a single correct-answer index from a raw item, tolerating both
     * `qanswer_index` (int or numeric string) and a numeric `qanswer`.
     *
     * @param array $item
     * @return int|null
     */
    private static function extract_single_index(array $item): ?int {
        if (isset($item['qanswer_index'])) {
            $v = $item['qanswer_index'];
            if (is_int($v)) {
                return $v;
            }
            if (is_string($v) && ctype_digit($v)) {
                return (int) $v;
            }
        }
        if (isset($item['qanswer'])) {
            $v = $item['qanswer'];
            if (is_int($v)) {
                return $v;
            }
            if (is_string($v) && ctype_digit($v)) {
                return (int) $v;
            }
        }
        return null;
    }

    /**
     * Decode a persisted question row's correct-answer field back into a PHP
     * value for the renderer / grader. Shape depends on qtype:
     *
     *   multichoice → int index
     *   mrq         → int[] of indices
     *   match       → array<string,string> left-index → right-value
     *
     * @param string $qtype
     * @param string $qanswer The persisted qanswer column value.
     * @return mixed
     */
    public static function decode_answer(string $qtype, string $qanswer) {
        switch ($qtype) {
            case self::TYPE_MULTICHOICE:
                return ctype_digit($qanswer) ? (int) $qanswer : 0;
            case self::TYPE_MRQ:
                $d = json_decode($qanswer, true);
                return is_array($d) ? array_values(array_map('intval', $d)) : [];
            case self::TYPE_MATCH:
                $d = json_decode($qanswer, true);
                return is_array($d) ? $d : [];
            default:
                return null;
        }
    }
}
