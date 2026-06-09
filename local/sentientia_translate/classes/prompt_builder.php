<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system + user prompts handed to Anthropic Claude for the
 * Sentientia LMS AI Content Translation feature.
 *
 * The prompts are versioned via {@see VERSION} so future changes can A/B
 * against the recorded baseline. Each translation row carries its
 * `prompt_version` column so we can reproduce exactly what Claude saw.
 *
 * Output contract — Claude must reply with a JSON object of shape:
 *
 *     {
 *       "translated_text": "....",
 *       "target_lang": "hi",
 *       "brand_terms_preserved": ["Airpay", "UPI"]
 *     }
 *
 * The response_parser validates this shape strictly. The translate_engine
 * then runs a deterministic brand-override post-processing pass over
 * translated_text so brand rendering is guaranteed regardless of model
 * behaviour.
 *
 * @package local_sentientia_translate
 */
class prompt_builder {

    /** Current prompt version. Bump when wording materially changes. */
    public const VERSION = 'v1';

    /** Max source words per translation request (admin-overridable). */
    public const DEFAULT_MAX_SOURCE_WORDS = 4000;

    /** Native-script names for each target language, for the prompt. */
    private const LANG_NATIVE = [
        'hi' => 'Hindi (हिन्दी), written in Devanagari script',
        'mr' => 'Marathi (मराठी), written in Devanagari script',
        'kn' => 'Kannada (ಕನ್ನಡ), written in Kannada script',
        'sw' => 'Swahili (Kiswahili), written in Latin script',
    ];

    /**
     * Build the system prompt that conditions Claude to return strict JSON.
     *
     * @param string   $targetlang     Target language code (hi|mr|kn|sw)
     * @param string[] $protectedterms Brand / proper-noun terms to keep verbatim
     * @return string
     */
    public static function build_system_prompt(string $targetlang, array $protectedterms = []): string {
        $langdesc = self::LANG_NATIVE[$targetlang] ?? $targetlang;

        $protectedline = '(none specified)';
        if (!empty($protectedterms)) {
            // De-dupe + bound the list size in the prompt.
            $clean = array_values(array_unique(array_map('strval', $protectedterms)));
            if (count($clean) > 100) {
                $clean = array_slice($clean, 0, 100);
            }
            $protectedline = implode(', ', $clean);
        }

        return <<<PROMPT
You are an expert localisation translator for a corporate compliance + skills LMS used by 3,500+ employees at a fintech in India.

Your job is to translate English course content into {$langdesc}. The translation must read naturally to a native speaker while staying faithful to the source meaning.

RULES (non-negotiable):
1. Output ONLY a single JSON object. No prose before or after. No markdown fences.
2. The JSON object MUST have EXACTLY these keys:
     - "translated_text": the full translation in the target language and script
     - "target_lang": the target language code you translated into
     - "brand_terms_preserved": an array of the protected terms you kept verbatim (may be empty)
3. PRESERVE these brand / product / regulatory terms EXACTLY as written — do NOT translate or transliterate them unless you are certain of the established local rendering: {$protectedline}
4. Preserve all formatting: line breaks, lists, numbers, punctuation, and any HTML tags present in the source.
5. Do NOT add explanations, footnotes, or translator's notes.
6. Do NOT translate numbers, dates, currency amounts, or code identifiers.
7. The translation MUST NOT introduce personally identifiable information that was not in the source.
8. Use a formal, professional corporate register appropriate for compliance training.

If the source contains a term you cannot confidently translate, keep it in the source language rather than guessing.
PROMPT;
    }

    /**
     * Build the user message for a translation request.
     *
     * @param string $sourcetext Verbatim English source (already length-validated)
     * @param string $targetlang Target language code
     * @return string
     */
    public static function build_user_message(string $sourcetext, string $targetlang): string {
        $source = trim($sourcetext);
        return "Translate the following source content into the target language '{$targetlang}'. "
            . "Return only the JSON object as specified.\n\n"
            . "----- SOURCE BEGIN -----\n"
            . $source . "\n"
            . "----- SOURCE END -----";
    }

    /**
     * Validate a translation request. Returns an array of problem KEYS
     * (empty array = clean) — the caller looks them up via get_string().
     *
     * @param string $sourcetext
     * @param string $targetlang
     * @param int    $maxwords
     * @return string[]
     */
    public static function validate_request(string $sourcetext, string $targetlang, int $maxwords = self::DEFAULT_MAX_SOURCE_WORDS): array {
        $errors = [];
        $trimmed = trim($sourcetext);

        if ($trimmed === '') {
            $errors[] = 'err_source_empty';
            return $errors;
        }

        if (!brand_manager::is_supported_lang($targetlang)) {
            $errors[] = 'err_unsupported_lang';
        }

        if (self::word_count($trimmed) > $maxwords) {
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
     * Heuristic PII detector. Catches Aadhaar (12 digits) + PAN (5+4+1).
     *
     * @param string $text
     * @return bool
     */
    public static function contains_pii_pattern(string $text): bool {
        if (preg_match('/\b\d{4}\s?\d{4}\s?\d{4}\b/', $text)) {
            return true;
        }
        if (preg_match('/\b[A-Z]{5}\d{4}[A-Z]\b/', $text)) {
            return true;
        }
        return false;
    }
}
