<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses Claude's translation response into a normalised object.
 *
 * The expected payload is documented in {@see prompt_builder} — a JSON
 * object with translated_text + target_lang + brand_terms_preserved.
 * This parser:
 *
 *   - Strips any markdown code-fence the model might add
 *   - Decodes strictly (no JSON5)
 *   - Validates the shape
 *   - Returns null when there is no usable translation
 *
 * Output: a normalised object (stdClass):
 *
 *     (object)[
 *       'translated_text'        => '...',
 *       'target_lang'            => 'hi',
 *       'brand_terms_preserved'  => ['Airpay', 'UPI'],
 *     ]
 *
 * @package local_sentientia_translate
 */
class response_parser {

    /** Defensive cap — translations longer than this are almost certainly junk. */
    public const MAX_TRANSLATION_LEN = 200000;

    /**
     * Parse Claude's text body into a normalised translation object.
     *
     * @param string $body Claude's response text
     * @return \stdClass|null Normalised object, or null when unusable
     */
    public static function parse(string $body): ?\stdClass {
        $json = self::extract_json($body);
        if ($json === '') {
            debugging('local_sentientia_translate: response body had no JSON');
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            debugging('local_sentientia_translate: response JSON malformed');
            return null;
        }

        if (!isset($decoded['translated_text']) || !is_string($decoded['translated_text'])) {
            debugging('local_sentientia_translate: response missing translated_text');
            return null;
        }

        $translated = trim($decoded['translated_text']);
        if ($translated === '') {
            debugging('local_sentientia_translate: translated_text empty');
            return null;
        }
        if (strlen($translated) > self::MAX_TRANSLATION_LEN) {
            $translated = substr($translated, 0, self::MAX_TRANSLATION_LEN);
        }

        $targetlang = '';
        if (isset($decoded['target_lang']) && is_string($decoded['target_lang'])) {
            $targetlang = trim($decoded['target_lang']);
        }

        $preserved = [];
        if (isset($decoded['brand_terms_preserved']) && is_array($decoded['brand_terms_preserved'])) {
            foreach ($decoded['brand_terms_preserved'] as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $preserved[] = trim($t);
                }
            }
        }

        $out = new \stdClass();
        $out->translated_text       = $translated;
        $out->target_lang           = $targetlang;
        $out->brand_terms_preserved = $preserved;
        return $out;
    }

    /**
     * Pull the JSON object out of a possibly-wrapped response.
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
}
