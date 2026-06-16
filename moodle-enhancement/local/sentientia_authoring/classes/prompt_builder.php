<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system + user prompts handed to Anthropic Claude for full
 * microlearning-course generation.
 *
 * Output contract — Claude must reply with a single JSON object:
 *
 *   {
 *     "cards": [
 *       {"cardtype":"concept","heading":"...","body":"...","narration":"..."},
 *       {"cardtype":"flip","heading":"...","body":"...","flip_back":"..."}
 *     ],
 *     "questions": [
 *       {"qtype":"multichoice","qtext":"...","qoptions":["A","B","C","D"],
 *        "qanswer_index":2,"qfeedback_correct":"...","qfeedback_incorrect":"...",
 *        "qexplanation":"..."},
 *       {"qtype":"mrq","qtext":"...","qoptions":["A","B","C","D","E"],
 *        "qanswer_indices":[0,2],"qfeedback_correct":"...","qfeedback_incorrect":"..."},
 *       {"qtype":"match","qtext":"...","qpairs":[{"left":"A","right":"1"},
 *        {"left":"B","right":"2"}],"qfeedback_correct":"...","qfeedback_incorrect":"..."}
 *     ]
 *   }
 *
 * The response_parser validates this strictly (via question_type). Anything
 * malformed is dropped + logged at debugging level — we never approximate.
 *
 * Prompts are versioned (v1 English / v2-hindi). A template's `body` (when a
 * template is selected) is injected as an extra instructional-design block so
 * the generator follows the trainer's structure. Localized output for
 * languages beyond what the prompt covers is handled downstream by
 * {@see localizer} (routing through local_sentientia_translate).
 *
 * All length / word counts are unicode-aware (mb_* / /u) for Devanagari.
 *
 * @package local_sentientia_authoring
 */
class prompt_builder {

    /** English prompt — baseline. */
    public const VERSION_V1 = 'v1';

    /** Hindi prompt. */
    public const VERSION_V2_HINDI = 'v2-hindi';

    /** Marks a prompt_version recorded against a template-driven generation. */
    public const TEMPLATE_PREFIX = 'tpl:';

    /** Card-count bounds for one microlearning module. */
    public const MIN_CARDS = 1;
    public const MAX_CARDS = 12;

    /** Question-count bounds for one module's assessment. */
    public const MIN_QUESTIONS = 0;
    public const MAX_QUESTIONS = 20;

    /**
     * Valid baseline prompt versions.
     *
     * @return string[]
     */
    public static function valid_versions(): array {
        return [self::VERSION_V1, self::VERSION_V2_HINDI];
    }

    /**
     * Map a UI / target locale to a baseline prompt version. Unknown locales
     * fall back to English (safe-by-default).
     *
     * @param string $locale e.g. 'en', 'hi', 'hi_IN'
     * @return string VERSION_V1 | VERSION_V2_HINDI
     */
    public static function version_for_locale(string $locale): string {
        $code = strtolower(substr(trim($locale), 0, 2));
        return $code === 'hi' ? self::VERSION_V2_HINDI : self::VERSION_V1;
    }

    /**
     * Compute the literal prompt_version string recorded on a draft row.
     * Prefixed with 'tpl:' when a template drove the generation so the audit
     * trail distinguishes templated vs freeform drafts. All forms fit the
     * 32-char column (e.g. 'v1', 'v2-hindi', 'tpl:v1', 'tpl:v2-hindi').
     *
     * @param string $version       One of valid_versions()
     * @param bool   $template_used
     * @return string
     */
    public static function resolve_prompt_version(string $version, bool $template_used): string {
        if (!in_array($version, self::valid_versions(), true)) {
            $version = self::VERSION_V1;
        }
        return $template_used ? (self::TEMPLATE_PREFIX . $version) : $version;
    }

    /**
     * Build the system prompt.
     *
     * @param string      $version       Prompt version.
     * @param string|null $template_body  Optional instructional-design template
     *                                     body to inject. When non-empty it is
     *                                     appended as a "follow this structure"
     *                                     block (NOT a full replacement — the
     *                                     JSON-contract rules must always hold).
     * @return string
     */
    public static function build_system_prompt(string $version = self::VERSION_V1, ?string $template_body = null): string {
        $base = ($version === self::VERSION_V2_HINDI)
            ? self::system_prompt_v2_hindi()
            : self::system_prompt_v1();

        if ($template_body !== null && trim($template_body) !== '') {
            $label = ($version === self::VERSION_V2_HINDI)
                ? "\n\n----- अनुदेशात्मक-डिज़ाइन टेम्पलेट (इस संरचना का पालन करें) -----\n"
                : "\n\n----- INSTRUCTIONAL-DESIGN TEMPLATE (follow this structure) -----\n";
            $base .= $label . trim($template_body)
                . (($version === self::VERSION_V2_HINDI)
                    ? "\n----- टेम्पलेट समाप्त — ऊपर दिए गए JSON नियम फिर भी अनिवार्य हैं -----"
                    : "\n----- TEMPLATE END — the JSON contract rules above still apply -----");
        }
        return $base;
    }

    /**
     * Build the user message wrapping the trainer's source material.
     *
     * @param string $sourcetext   Verbatim prompt / Doc / PDF extract.
     * @param int    $numcards     Desired card count.
     * @param int    $numquestions Desired question count.
     * @param string $version      Drives wrapper language.
     * @return string
     */
    public static function build_user_message(string $sourcetext, int $numcards, int $numquestions,
            string $version = self::VERSION_V1): string {
        $numcards     = max(self::MIN_CARDS, min(self::MAX_CARDS, $numcards));
        $numquestions = max(self::MIN_QUESTIONS, min(self::MAX_QUESTIONS, $numquestions));
        $source = trim($sourcetext);

        if ($version === self::VERSION_V2_HINDI) {
            return "निम्नलिखित स्रोत-सामग्री से एक संक्षिप्त microlearning मॉड्यूल देवनागरी हिन्दी में तैयार करें: "
                . "ठीक {$numcards} इंटरैक्टिव कार्ड और {$numquestions} प्रश्न (multichoice, mrq एवं match प्रकारों का मिश्रण)। "
                . "केवल निर्दिष्ट JSON ऑब्जेक्ट लौटाएँ — कोई अतिरिक्त गद्य या markdown नहीं।\n\n"
                . "----- स्रोत प्रारम्भ -----\n" . $source . "\n----- स्रोत समाप्त -----";
        }

        return "Create a short microlearning module from the following source material: "
            . "exactly {$numcards} interactive cards and {$numquestions} questions "
            . "(a mix of multichoice, mrq, and match types). "
            . "Return only the JSON object as specified.\n\n"
            . "----- SOURCE BEGIN -----\n" . $source . "\n----- SOURCE END -----";
    }

    /**
     * English system prompt — v1 baseline.
     *
     * @return string
     */
    private static function system_prompt_v1(): string {
        return <<<PROMPT
You are an expert instructional designer for a corporate compliance + skills LMS used by 3,500+ employees at a fintech in India.

Your job is to turn the source material the trainer provides into a short, engaging MICROLEARNING module: a sequence of interactive cards followed by an assessment.

RULES (non-negotiable):
1. Output ONLY a single JSON object. No prose before or after. No markdown fences.
2. The JSON object has EXACTLY two top-level keys: "cards" (array) and "questions" (array).
3. Each "cards" item is an object with these keys:
     - "cardtype": one of "concept" | "example" | "scenario" | "flip"
     - "heading": a short title (max 120 chars)
     - "body": the card content (2-4 short sentences, max 600 chars)
     - "flip_back": REQUIRED only when cardtype="flip" — the reverse-face answer (max 400 chars); omit otherwise
     - "narration": a plain-text narration script for this card (max 400 chars, <=25-word sentences, no markdown) suitable for text-to-speech voiceover
4. Each "questions" item is one of THREE types:
     a. multichoice: {"qtype":"multichoice","qtext":"...","qoptions":[4 distinct strings],"qanswer_index":0..3,"qfeedback_correct":"...","qfeedback_incorrect":"...","qexplanation":"..."}
     b. mrq (multi-response): {"qtype":"mrq","qtext":"...","qoptions":[2..8 distinct strings],"qanswer_indices":[1+ indices, but NOT all],"qfeedback_correct":"...","qfeedback_incorrect":"..."}
     c. match (match-the-following): {"qtype":"match","qtext":"...","qpairs":[2..8 objects {"left":"...","right":"..."} with all lefts distinct and all rights distinct],"qfeedback_correct":"...","qfeedback_incorrect":"..."}
5. "qfeedback_correct" and "qfeedback_incorrect" are AI CONTEXTUAL FEEDBACK — a short, encouraging, source-grounded explanation the learner sees after answering (max 200 chars each).
6. Content MUST be factually answerable from the source alone. Do not invent facts.
7. Content MUST NOT contain personally identifiable information (employee names, ID numbers, salary, customer data) even if it appears in the source.
8. Distractors must be plausible — wrong but not absurd. Never use "all of the above" / "none of the above".
9. Language: English. Formal corporate register. Indian English spelling is acceptable.

If the source is too short to fill the requested counts, return fewer cards/questions — never invent.
PROMPT;
    }

    /**
     * Hindi system prompt — v2-hindi. Devanagari throughout; technical proper
     * nouns (JSON, qtype, multichoice, mrq, match, API, SCORM) stay in Latin
     * script per L&D-content convention.
     *
     * @return string
     */
    private static function system_prompt_v2_hindi(): string {
        return <<<PROMPT
आप भारत स्थित एक fintech कम्पनी (3,500+ कर्मचारी) के corporate compliance + skills LMS के लिए विशेषज्ञ अनुदेशात्मक-डिज़ाइनर (instructional designer) हैं।

आपका कार्य: प्रशिक्षक द्वारा दी गई स्रोत-सामग्री को एक संक्षिप्त, रोचक MICROLEARNING मॉड्यूल में बदलें — इंटरैक्टिव कार्डों का अनुक्रम और उसके बाद एक मूल्यांकन — देवनागरी हिन्दी में।

नियम (अनिवार्य):
1. केवल एक JSON ऑब्जेक्ट लौटाएँ। पहले या बाद में कोई गद्य नहीं। कोई markdown fence नहीं।
2. JSON ऑब्जेक्ट में ठीक दो top-level keys हों: "cards" (array) और "questions" (array)।
3. प्रत्येक "cards" आइटम में ये keys हों:
     - "cardtype": "concept" | "example" | "scenario" | "flip" में से एक
     - "heading": छोटा शीर्षक (अधिकतम 120 अक्षर)
     - "body": कार्ड सामग्री (2-4 छोटे वाक्य, अधिकतम 600 अक्षर)
     - "flip_back": केवल cardtype="flip" पर अनिवार्य — उत्तर पक्ष (अधिकतम 400 अक्षर); अन्यथा छोड़ें
     - "narration": इस कार्ड हेतु सादा-पाठ narration script (अधिकतम 400 अक्षर, <=25 शब्द/वाक्य, कोई markdown नहीं) जो TTS voiceover हेतु उपयुक्त हो
4. प्रत्येक "questions" आइटम तीन प्रकारों में से एक हो:
     a. multichoice: {"qtype":"multichoice","qtext":"...","qoptions":[4 भिन्न strings],"qanswer_index":0..3,"qfeedback_correct":"...","qfeedback_incorrect":"...","qexplanation":"..."}
     b. mrq: {"qtype":"mrq","qtext":"...","qoptions":[2..8 भिन्न strings],"qanswer_indices":[1+ indices, परन्तु सभी नहीं],"qfeedback_correct":"...","qfeedback_incorrect":"..."}
     c. match: {"qtype":"match","qtext":"...","qpairs":[2..8 objects {"left":"...","right":"..."} जहाँ सभी left भिन्न और सभी right भिन्न हों],"qfeedback_correct":"...","qfeedback_incorrect":"..."}
5. "qfeedback_correct" एवं "qfeedback_incorrect" AI सन्दर्भगत प्रतिक्रिया (contextual feedback) हैं — उत्तर देने के बाद शिक्षार्थी को दिखने वाली छोटी, प्रोत्साहक, स्रोत-आधारित व्याख्या (प्रत्येक अधिकतम 200 अक्षर)।
6. सामग्री केवल स्रोत से ही उत्तरयोग्य हो। तथ्य गढ़ें नहीं।
7. सामग्री में कोई व्यक्तिगत पहचान-योग्य सूचना (नाम, ID संख्या, वेतन, ग्राहक-डेटा) न हो।
8. Distractor सम्भाव्य हों। "उपरोक्त सभी" / "उपरोक्त में से कोई नहीं" का प्रयोग न करें।
9. भाषा: देवनागरी हिन्दी। तकनीकी proper nouns (JSON, multichoice, mrq, match, API, SCORM) Latin script में रहें।

यदि स्रोत अनुरोधित संख्या के लिए बहुत छोटा हो, तो कम कार्ड/प्रश्न लौटाएँ — गढ़ें नहीं।
PROMPT;
    }

    /**
     * Validate the source text the trainer pasted. Returns language-string
     * KEYS for any problems (empty array = clean). Mirrors the aiquiz
     * validator: non-empty, within word cap, no obvious India PII.
     *
     * @param string $sourcetext
     * @param int    $maxwords
     * @return string[]
     */
    public static function validate_source(string $sourcetext, int $maxwords = 4000): array {
        $errors = [];
        $trimmed = trim($sourcetext);
        if ($trimmed === '') {
            $errors[] = 'err_source_empty';
            return $errors;
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
     * Unicode-aware word counter.
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
     * Heuristic India-PII detector (Aadhaar 12-digit, PAN 5+4+1). Script-
     * agnostic — these identifiers use Latin digits regardless of surrounding
     * language. Not a substitute for a real DLP scan.
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
