<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system + user prompts handed to Anthropic Claude.
 *
 * The prompts are versioned via {@see VERSION} so future changes can A/B
 * against the recorded baseline. Each draft row carries its
 * `prompt_version` column so we can reproduce exactly what Claude saw.
 *
 * Output contract — Claude must reply with a JSON object of shape:
 *
 *     {
 *       "questions": [
 *         {
 *           "qtype": "multichoice",
 *           "qtext": "....",
 *           "qoptions": ["A", "B", "C", "D"],
 *           "qanswer_index": 2,
 *           "qexplanation": "..."
 *         }
 *       ]
 *     }
 *
 * The response_parser validates this shape strictly. Anything malformed
 * is dropped (logged at debugging level) — we never approximate.
 *
 * Phase G.0 only ships the 'v1' multichoice prompt. Phase G.1 introduces
 * 'v2-hindi' which asks for Hindi-language stems + options.
 *
 * @package local_sentientia_aiquiz
 */
class prompt_builder {

    /** Current prompt version. Bump when wording materially changes. */
    public const VERSION = 'v1';

    /** Per-call upper bound on requested questions. */
    public const MAX_QUESTIONS = 30;

    /** Per-call lower bound on requested questions. */
    public const MIN_QUESTIONS = 1;

    /**
     * Build the system prompt that conditions Claude to return strict JSON.
     *
     * @return string
     */
    public static function build_system_prompt(): string {
        return <<<PROMPT
You are an expert L&D quiz writer for a corporate compliance + skills LMS used by 3,500+ employees at a fintech in India.

Your job is to read source material the trainer provides and return multiple-choice quiz questions that test comprehension of the material.

RULES (non-negotiable):
1. Output ONLY a single JSON object. No prose before or after. No markdown fences.
2. The JSON object MUST have exactly one top-level key: "questions" — an array.
3. Each item in "questions" is an object with EXACTLY these keys:
     - "qtype": always the string "multichoice"
     - "qtext": the question stem (one sentence, ends with a question mark, max 200 chars)
     - "qoptions": an array of EXACTLY 4 distinct plausible option strings (max 100 chars each)
     - "qanswer_index": an integer 0..3 pointing to the correct option in qoptions
     - "qexplanation": one short sentence (max 200 chars) explaining why the correct answer is correct, grounded in the source
4. Questions MUST be factually answerable from the source text alone. Do not invent facts.
5. Questions MUST NOT contain personally identifiable information (employee names, ID numbers, salary, customer data) even if it appears in the source. If the source mentions a real person by name, write the question in a way that doesn't require knowing that name.
6. Distractors must be plausible — wrong but not absurd. Never use "all of the above" or "none of the above".
7. Stems must be unambiguous — exactly one option is correct.
8. Language: English. Use formal corporate register. Indian English spelling is acceptable.

If the source is too short or too vague to produce the requested number of questions, return fewer — never invent.
PROMPT;
    }

    /**
     * Build the user message for a generation request.
     *
     * @param string $sourcetext   Verbatim trainer-supplied source (already validated for length)
     * @param int    $numrequested 1..MAX_QUESTIONS
     * @return string
     */
    public static function build_user_message(string $sourcetext, int $numrequested): string {
        $numrequested = max(self::MIN_QUESTIONS, min(self::MAX_QUESTIONS, $numrequested));
        $source = trim($sourcetext);

        return "Generate exactly {$numrequested} multiple-choice questions from the following source material. "
            . "Return only the JSON object as specified.\n\n"
            . "----- SOURCE BEGIN -----\n"
            . $source . "\n"
            . "----- SOURCE END -----";
    }

    /**
     * Validate the source text the trainer pasted. Returns an array of
     * problems (empty array = clean).
     *
     * Phase G.0 checks:
     *   - non-empty after trim
     *   - within max_source_words (admin setting, default 4000)
     *   - no obvious PII patterns (Aadhaar 12-digit, PAN 10-alphanumeric)
     *
     * Returned strings are language-string KEYS — the caller looks them
     * up via get_string() so the validator is i18n-friendly.
     *
     * @param string $sourcetext
     * @param int    $maxwords
     * @return string[] Array of language-string keys (empty = valid)
     */
    public static function validate_source(string $sourcetext, int $maxwords = 4000): array {
        $errors = [];
        $trimmed = trim($sourcetext);

        if ($trimmed === '') {
            $errors[] = 'err_source_empty';
            return $errors;
        }

        $wordcount = self::word_count($trimmed);
        if ($wordcount > $maxwords) {
            $errors[] = 'err_source_too_long';
        }

        if (self::contains_pii_pattern($trimmed)) {
            $errors[] = 'err_source_contains_pii';
        }

        return $errors;
    }

    /**
     * Conservative word counter — splits on whitespace, ignores blanks.
     *
     * @param string $text
     * @return int
     */
    public static function word_count(string $text): int {
        if (trim($text) === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', trim($text));
        return $parts === false ? 0 : count($parts);
    }

    /**
     * Heuristic PII detector. Catches the two most common India-specific
     * leaks (Aadhaar + PAN). Not a substitute for a real DLP scan.
     *
     * Aadhaar: 12 digits, optionally with two spaces (XXXX XXXX XXXX)
     * PAN    : 5 letters + 4 digits + 1 letter (ABCDE1234F)
     *
     * @param string $text
     * @return bool
     */
    public static function contains_pii_pattern(string $text): bool {
        // Aadhaar — 12 contiguous digits or three groups of four with spaces.
        if (preg_match('/\b\d{4}\s?\d{4}\s?\d{4}\b/', $text)) {
            return true;
        }
        // PAN — strict 5+4+1 alphanumeric pattern.
        if (preg_match('/\b[A-Z]{5}\d{4}[A-Z]\b/', $text)) {
            return true;
        }
        return false;
    }
}
