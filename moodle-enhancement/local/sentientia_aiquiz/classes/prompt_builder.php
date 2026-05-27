<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system + user prompts handed to Anthropic Claude.
 *
 * The prompts are versioned via {@see VERSION_V1} / {@see VERSION_V2_HINDI}
 * so future revisions A/B against the recorded baseline. Each draft row
 * carries its `prompt_version` column so we can reproduce exactly what
 * Claude saw.
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
 * Phase G.0 shipped the 'v1' multichoice English prompt. Phase G.1
 * (2026-05-25) adds:
 *   - 'v2-hindi' — full Hindi system prompt + Hindi few-shot examples
 *     in Devanagari script. Selected when the UI locale resolves to 'hi'.
 *   - Per-customer prompt-template override via
 *     {@see \local_airpay_core\customer::get_customer_config()} with key
 *     'aiquiz_prompt_template'. When set, the customer's pasted template
 *     replaces the system-prompt body verbatim; the user-message wrapper
 *     still follows the locale-derived version so source-text framing
 *     (begin/end markers, "exactly N questions" sentence) stays
 *     consistent with the chosen language.
 *
 * @package local_sentientia_aiquiz
 */
class prompt_builder {

    /**
     * Default prompt version (English).
     *
     * Kept for backwards compatibility with Phase G.0 callsites that
     * referenced {@see VERSION}. New code should use {@see VERSION_V1}
     * or {@see VERSION_V2_HINDI} explicitly.
     */
    public const VERSION = 'v1';

    /** English prompt — Phase G.0 baseline. */
    public const VERSION_V1 = 'v1';

    /** Hindi prompt — Phase G.1. */
    public const VERSION_V2_HINDI = 'v2-hindi';

    /**
     * Prefix added to {@see VERSION_V1} / {@see VERSION_V2_HINDI} when the
     * resolved system-prompt body came from a customer-pasted template
     * instead of the in-code baseline. Recorded on the draft row so the
     * audit trail can distinguish "Airpay tweaked the prompt" from "ran
     * the stock prompt".
     *
     * Examples (all fit within the 32-char `prompt_version` column):
     *   'custom:v1', 'custom:v2-hindi'
     */
    public const CUSTOM_PREFIX = 'custom:';

    /** Per-call upper bound on requested questions. */
    public const MAX_QUESTIONS = 30;

    /** Per-call lower bound on requested questions. */
    public const MIN_QUESTIONS = 1;

    /**
     * Valid baseline prompt versions (NOT counting custom-prefixed forms).
     *
     * @return string[]
     */
    public static function valid_versions(): array {
        return [self::VERSION_V1, self::VERSION_V2_HINDI];
    }

    /**
     * Map a UI locale code to a baseline prompt version.
     *
     * Accepts BCP-47-ish input (`hi`, `hi_IN`, `HI-in`, `en`, `en-GB`, ...).
     * Only the first two characters are inspected. Unknown locales fall
     * back to English ({@see VERSION_V1}) — keeping the system safe-by-default
     * when a future locale code lands without a matching prompt body.
     *
     * @param string $locale
     * @return string Either VERSION_V1 or VERSION_V2_HINDI
     */
    public static function version_for_locale(string $locale): string {
        $code = strtolower(substr(trim($locale), 0, 2));
        return $code === 'hi' ? self::VERSION_V2_HINDI : self::VERSION_V1;
    }

    /**
     * Resolve the prompt context for a (customer, locale) pair.
     *
     * Phase G.1 resolution order:
     *   1. Customer-pasted template (if set + non-empty) → use verbatim.
     *      The system prompt body becomes that template literally; the
     *      user message wrapper still follows the locale-derived version.
     *   2. Locale dispatch: 'hi' → v2-hindi, else v1.
     *
     * @param int    $customer_id Customer id (1=Airpay)
     * @param string $locale      UI locale code (e.g. 'en', 'hi')
     * @return array {
     *     version:  string  baseline version (v1 | v2-hindi)
     *     template: ?string customer-pasted template body, or null when none
     * }
     */
    public static function resolve_for(int $customer_id, string $locale): array {
        $version = self::version_for_locale($locale);
        $template = null;

        if ($customer_id > 0 && class_exists('\\local_airpay_core\\customer')) {
            $candidate = \local_airpay_core\customer::get_customer_config(
                'aiquiz_prompt_template', $customer_id, null);
            if (is_string($candidate) && trim($candidate) !== '') {
                $template = $candidate;
            }
        }

        return ['version' => $version, 'template' => $template];
    }

    /**
     * Compute the literal `prompt_version` string that should be recorded
     * on a draft row, given the resolved baseline + whether a custom
     * template was active.
     *
     * Returned value examples:
     *   - 'v1'              — stock English
     *   - 'v2-hindi'        — stock Hindi
     *   - 'custom:v1'       — admin-pasted template, English wrapper
     *   - 'custom:v2-hindi' — admin-pasted template, Hindi wrapper
     *
     * All four forms fit within the 32-char `prompt_version` column.
     *
     * @param string $version            One of {@see valid_versions()}
     * @param bool   $custom_template_used
     * @return string
     */
    public static function resolve_prompt_version(string $version, bool $custom_template_used): string {
        if (!in_array($version, self::valid_versions(), true)) {
            $version = self::VERSION_V1;
        }
        return $custom_template_used ? (self::CUSTOM_PREFIX . $version) : $version;
    }

    /**
     * Build the system prompt that conditions Claude to return strict JSON.
     *
     * @param string      $version         Prompt version — one of valid_versions()
     * @param string|null $custom_template Customer-pasted prompt template; when
     *                                      non-empty replaces the baseline body
     * @return string
     */
    public static function build_system_prompt(string $version = self::VERSION_V1, ?string $custom_template = null): string {
        if ($custom_template !== null && trim($custom_template) !== '') {
            return $custom_template;
        }
        if ($version === self::VERSION_V2_HINDI) {
            return self::system_prompt_v2_hindi();
        }
        return self::system_prompt_v1();
    }

    /**
     * Build the user message for a generation request.
     *
     * The wrapper text (begin/end markers + the "exactly N questions"
     * sentence) follows the version's locale — so a Hindi system prompt
     * is paired with a Hindi user-message wrapper around the trainer's
     * source text. The source text itself is passed through verbatim.
     *
     * @param string $sourcetext   Verbatim trainer-supplied source
     * @param int    $numrequested 1..MAX_QUESTIONS
     * @param string $version      Prompt version — drives wrapper language
     * @return string
     */
    public static function build_user_message(string $sourcetext, int $numrequested, string $version = self::VERSION_V1): string {
        $numrequested = max(self::MIN_QUESTIONS, min(self::MAX_QUESTIONS, $numrequested));
        $source = trim($sourcetext);

        if ($version === self::VERSION_V2_HINDI) {
            return "निम्नलिखित स्रोत-सामग्री से ठीक {$numrequested} बहुविकल्पीय प्रश्न तैयार करें। "
                . "केवल निर्दिष्ट JSON ऑब्जेक्ट लौटाएँ — कोई अतिरिक्त गद्य या markdown नहीं।\n\n"
                . "----- स्रोत प्रारम्भ -----\n"
                . $source . "\n"
                . "----- स्रोत समाप्त -----";
        }

        return "Generate exactly {$numrequested} multiple-choice questions from the following source material. "
            . "Return only the JSON object as specified.\n\n"
            . "----- SOURCE BEGIN -----\n"
            . $source . "\n"
            . "----- SOURCE END -----";
    }

    /**
     * English system prompt — Phase G.0 baseline. Unchanged from G.0
     * except for being moved out of build_system_prompt() so the new
     * Hindi variant can sit beside it.
     *
     * @return string
     */
    private static function system_prompt_v1(): string {
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
     * Hindi system prompt (v2-hindi) — Phase G.1.
     *
     * Devanagari throughout. Technical proper nouns (JSON, qtype,
     * qoptions, Anthropic, Aadhaar, PAN, multichoice) stay in Latin
     * script per L&D-content convention — they're API field names and
     * regulatory identifiers, not translatable terms. Includes a
     * one-question few-shot example so Claude has a concrete shape to
     * match.
     *
     * @return string
     */
    private static function system_prompt_v2_hindi(): string {
        return <<<PROMPT
आप भारत स्थित एक fintech कम्पनी (3,500+ कर्मचारी) के corporate compliance + skills LMS के लिए विशेषज्ञ L&D क्विज़-लेखक हैं।

आपका कार्य: प्रशिक्षक द्वारा दी गई स्रोत-सामग्री पढ़ें और उसकी समझ की परीक्षा हेतु बहुविकल्पीय (multichoice) क्विज़ प्रश्न देवनागरी हिन्दी में लौटाएँ।

नियम (अनिवार्य):
1. केवल एक JSON ऑब्जेक्ट लौटाएँ। पहले या बाद में कोई गद्य नहीं। कोई markdown fence नहीं।
2. JSON ऑब्जेक्ट में ठीक एक ही top-level key हो: "questions" — एक array।
3. "questions" का प्रत्येक आइटम एक object है जिसमें ठीक ये keys हों:
     - "qtype": सदैव string "multichoice"
     - "qtext": प्रश्न का stem — एक ही वाक्य, प्रश्न-चिह्न से समाप्त, अधिकतम 200 अक्षर, देवनागरी में
     - "qoptions": ठीक 4 भिन्न संभावित विकल्पों का array (प्रत्येक अधिकतम 100 अक्षर, देवनागरी में)
     - "qanswer_index": 0..3 का integer — qoptions में सही विकल्प की position
     - "qexplanation": एक छोटा वाक्य (अधिकतम 200 अक्षर) जो स्रोत के आधार पर बताए कि सही उत्तर क्यों सही है, देवनागरी में
4. प्रश्न केवल स्रोत-पाठ से ही उत्तरयोग्य हों। तथ्य गढ़ें नहीं।
5. प्रश्नों में कोई व्यक्तिगत पहचान-योग्य सूचना (कर्मचारी का नाम, ID संख्या, वेतन, ग्राहक-डेटा) न हो — भले ही स्रोत में हो। यदि स्रोत किसी वास्तविक व्यक्ति का नाम लेता है, तो प्रश्न ऐसे लिखें कि नाम जानने की आवश्यकता न पड़े।
6. Distractor (गलत विकल्प) सम्भाव्य हों — गलत परन्तु बेतुके नहीं। "उपरोक्त सभी" / "उपरोक्त में से कोई नहीं" का प्रयोग न करें।
7. Stem अस्पष्ट न हो — ठीक एक ही विकल्प सही हो।
8. भाषा: देवनागरी हिन्दी। औपचारिक कॉर्पोरेट रजिस्टर। तकनीकी proper nouns (JSON, API, SCORM, Aadhaar, PAN, Sonnet) Latin script में रहें — वे अनुवाद-योग्य नहीं हैं।

उदाहरण (केवल आकार दिखाने हेतु — विषयवस्तु अनदेखी करें):
{"questions":[{"qtype":"multichoice","qtext":"अनुपालन-प्रशिक्षण का मुख्य उद्देश्य क्या है?","qoptions":["कर्मचारियों को नियमों से अवगत कराना","नई नौकरी प्रदान करना","वेतन-वृद्धि की समीक्षा","ग्राहक-शिकायत निवारण"],"qanswer_index":0,"qexplanation":"अनुपालन-प्रशिक्षण कर्मचारियों को लागू कानूनों एवं आन्तरिक नीतियों से अवगत कराने हेतु होता है।"}]}

यदि स्रोत-पाठ अनुरोधित प्रश्न-संख्या के लिए बहुत छोटा या अस्पष्ट हो, तो कम प्रश्न लौटाएँ — गढ़ें नहीं।
PROMPT;
    }

    /**
     * Validate the source text the trainer pasted. Returns an array of
     * problems (empty array = clean).
     *
     * Phase G.0 / G.1 checks:
     *   - non-empty after trim
     *   - within max_source_words (admin setting, default 4000)
     *   - no obvious PII patterns (Aadhaar 12-digit, PAN 10-alphanumeric)
     *
     * Word counting is unicode-aware so a Hindi (Devanagari) source counts
     * its words correctly, not its bytes.
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
     * Unicode-aware (`/u` flag) so Devanagari sources count words and
     * not byte-runs.
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
     * The patterns are script-agnostic — Aadhaar/PAN numbers are issued
     * in Latin digits regardless of the surrounding text language, so
     * the same regex covers English and Hindi sources.
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
