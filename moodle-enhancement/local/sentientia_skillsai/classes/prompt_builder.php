<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system + user prompts handed to Anthropic Claude for skill
 * EXTRACTION.
 *
 * Mirrors the sentientia_aiquiz prompt_builder design: versioned prompts
 * (v1 English, v2-hindi Devanagari), optional per-customer template
 * override, unicode-aware validation, and a strict JSON output contract.
 *
 * Output contract — Claude must reply with a JSON object of shape:
 *
 *     {
 *       "skills": [
 *         {
 *           "name": "KYC Verification",
 *           "description": "Verifying customer identity per RBI norms",
 *           "category": "Compliance",
 *           "level": 3,
 *           "confidence": 0.86,
 *           "evidence": "the SOP details the four KYC document checks"
 *         }
 *       ]
 *     }
 *
 * The response_parser validates this shape strictly; malformed items are
 * dropped (logged at debugging level) — we never approximate.
 *
 * @package local_sentientia_skillsai
 */
class prompt_builder {

    /** English prompt — P0.1.0 baseline. */
    public const VERSION_V1 = 'v1';

    /** Hindi prompt — Devanagari. */
    public const VERSION_V2_HINDI = 'v2-hindi';

    /** Prefix recorded when the system prompt body came from a customer template. */
    public const CUSTOM_PREFIX = 'custom:';

    /** Per-call upper bound on extracted skills. */
    public const MAX_SKILLS = 25;

    /** Per-call lower bound. */
    public const MIN_SKILLS = 1;

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
     * Only the first two characters are inspected; unknown locales fall
     * back to English.
     *
     * @param string $locale
     * @return string VERSION_V1 | VERSION_V2_HINDI
     */
    public static function version_for_locale(string $locale): string {
        $code = strtolower(substr(trim($locale), 0, 2));
        return $code === 'hi' ? self::VERSION_V2_HINDI : self::VERSION_V1;
    }

    /**
     * Resolve the prompt context for a (customer, locale) pair.
     *
     * Resolution order:
     *   1. Customer-pasted template (if set + non-empty) → use verbatim as
     *      the system prompt body. The user-message wrapper still follows
     *      the locale-derived version.
     *   2. Locale dispatch: 'hi' → v2-hindi, else v1.
     *
     * @param int    $customer_id Customer id (1=Airpay)
     * @param string $locale      UI locale code
     * @return array{version: string, template: ?string}
     */
    public static function resolve_for(int $customer_id, string $locale): array {
        $version = self::version_for_locale($locale);
        $template = null;

        if ($customer_id > 0 && class_exists('\\local_sentientia_platform\\customer')) {
            $candidate = \local_sentientia_platform\customer::get_customer_config(
                'skillsai_prompt_template', $customer_id, null);
            if (is_string($candidate) && trim($candidate) !== '') {
                $template = $candidate;
            }
        }

        return ['version' => $version, 'template' => $template];
    }

    /**
     * Compute the literal `prompt_version` string recorded on a job row.
     *
     * Examples: 'v1', 'v2-hindi', 'custom:v1', 'custom:v2-hindi'.
     *
     * @param string $version
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
     * @param string      $version
     * @param string|null $custom_template
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
     * Build the user message wrapping the source content.
     *
     * @param string $sourcetext Verbatim source (transcript/SOP/narration)
     * @param int    $maxskills  Upper bound on extracted skills
     * @param string $version    Drives wrapper language
     * @return string
     */
    public static function build_user_message(string $sourcetext, int $maxskills, string $version = self::VERSION_V1): string {
        $maxskills = max(self::MIN_SKILLS, min(self::MAX_SKILLS, $maxskills));
        $source = trim($sourcetext);

        if ($version === self::VERSION_V2_HINDI) {
            return "निम्नलिखित अधिगम-सामग्री से अधिकतम {$maxskills} कौशल (skills) निकालें जो यह सामग्री सिखाती है। "
                . "केवल निर्दिष्ट JSON ऑब्जेक्ट लौटाएँ — कोई अतिरिक्त गद्य या markdown नहीं।\n\n"
                . "----- स्रोत प्रारम्भ -----\n"
                . $source . "\n"
                . "----- स्रोत समाप्त -----";
        }

        return "Extract up to {$maxskills} distinct skills that the following learning material actually teaches. "
            . "Return only the JSON object as specified.\n\n"
            . "----- SOURCE BEGIN -----\n"
            . $source . "\n"
            . "----- SOURCE END -----";
    }

    /**
     * English system prompt — P0.1.0 baseline.
     *
     * @return string
     */
    private static function system_prompt_v1(): string {
        return <<<PROMPT
You are an expert L&D skills taxonomist for a corporate compliance + skills LMS used by 3,500+ employees at a fintech in India.

Your job is to read learning material (a course/SCORM transcript, an SOP excerpt, or narration text) and identify the discrete, named SKILLS that the material teaches or assesses.

RULES (non-negotiable):
1. Output ONLY a single JSON object. No prose before or after. No markdown fences.
2. The JSON object MUST have exactly one top-level key: "skills" — an array.
3. Each item in "skills" is an object with EXACTLY these keys:
     - "name": a short skill name (2-5 words, max 60 chars), Title Case, e.g. "KYC Verification"
     - "description": one sentence (max 200 chars) describing what mastering the skill demonstrates
     - "category": one of "Compliance", "Technical", "Product", "Leadership", "Process", "Customer" (pick the best fit)
     - "level": an integer 1..5 indicating the depth the material teaches the skill to (1=Awareness, 2=Basic, 3=Intermediate, 4=Advanced, 5=Expert)
     - "confidence": a number 0.0..1.0 — how confident you are the material genuinely teaches this skill
     - "evidence": a short verbatim phrase (max 200 chars) from the source that justifies the skill
4. Only propose skills that are GROUNDED in the source. Do not invent skills the material does not actually teach.
5. NEVER include personally identifiable information (employee names, ID numbers, salary, customer data) in any field, even if it appears in the source.
6. Skill names must be reusable across courses — name the competency, not the specific document or module title.
7. Prefer fewer, higher-confidence skills over many speculative ones. If the source is thin, return fewer.

If the source is too short or vague to identify any skill, return an empty "skills" array — never invent.
PROMPT;
    }

    /**
     * Hindi system prompt (v2-hindi).
     *
     * Devanagari throughout. JSON field names + technical proper nouns
     * (JSON, KYC, SCORM, Aadhaar, PAN) stay in Latin per L&D convention.
     *
     * @return string
     */
    private static function system_prompt_v2_hindi(): string {
        return <<<PROMPT
आप भारत स्थित एक fintech कम्पनी (3,500+ कर्मचारी) के corporate compliance + skills LMS के लिए विशेषज्ञ L&D कौशल-वर्गीकरणकर्ता (skills taxonomist) हैं।

आपका कार्य: अधिगम-सामग्री (course/SCORM transcript, SOP अंश, या narration text) पढ़ें और उन स्पष्ट, नामित कौशलों (skills) की पहचान करें जो यह सामग्री सिखाती या आँकती है।

नियम (अनिवार्य):
1. केवल एक JSON ऑब्जेक्ट लौटाएँ। पहले या बाद में कोई गद्य नहीं। कोई markdown fence नहीं।
2. JSON ऑब्जेक्ट में ठीक एक ही top-level key हो: "skills" — एक array।
3. "skills" का प्रत्येक आइटम एक object है जिसमें ठीक ये keys हों:
     - "name": संक्षिप्त कौशल-नाम (देवनागरी में, अधिकतम 60 अक्षर), जैसे "KYC सत्यापन"
     - "description": एक वाक्य (अधिकतम 200 अक्षर) — कौशल में निपुणता क्या दर्शाती है
     - "category": इनमें से एक — "Compliance", "Technical", "Product", "Leadership", "Process", "Customer"
     - "level": 1..5 का integer (1=जागरूकता, 2=आधारभूत, 3=मध्यम, 4=उन्नत, 5=विशेषज्ञ)
     - "confidence": 0.0..1.0 की संख्या — आप कितने आश्वस्त हैं कि सामग्री वास्तव में यह कौशल सिखाती है
     - "evidence": स्रोत से एक छोटा verbatim वाक्यांश (अधिकतम 200 अक्षर) जो कौशल को न्यायसंगत ठहराए
4. केवल वे कौशल प्रस्तावित करें जो स्रोत में आधारित हों। ऐसे कौशल न गढ़ें जो सामग्री वास्तव में नहीं सिखाती।
5. किसी भी field में व्यक्तिगत पहचान-योग्य सूचना (कर्मचारी का नाम, ID, वेतन, ग्राहक-डेटा) कभी न रखें — भले ही स्रोत में हो।
6. कौशल-नाम पाठ्यक्रमों में पुनः प्रयोग-योग्य हों — दक्षता का नाम दें, न कि किसी विशिष्ट दस्तावेज़/मॉड्यूल का।
7. कम परन्तु उच्च-confidence कौशलों को वरीयता दें। यदि स्रोत पतला है, तो कम कौशल लौटाएँ।

यदि स्रोत किसी कौशल की पहचान हेतु बहुत छोटा या अस्पष्ट हो, तो खाली "skills" array लौटाएँ — गढ़ें नहीं।
PROMPT;
    }

    /**
     * Validate the source text. Returns an array of language-string KEYS
     * (empty = clean) so the caller can i18n the messages.
     *
     * Checks: non-empty after trim, within max words, no obvious PII.
     *
     * @param string $sourcetext
     * @param int    $maxwords
     * @return string[]
     */
    public static function validate_source(string $sourcetext, int $maxwords = 6000): array {
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
     * Heuristic India-specific PII detector (Aadhaar 12-digit + PAN
     * 5+4+1). Not a substitute for a full DLP scan.
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
