<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses Claude's extraction response into a normalised candidate-skill
 * array.
 *
 * Expected payload (see {@see prompt_builder}): a JSON object with a single
 * "skills" key. This parser strips markdown fences, decodes strictly,
 * validates each item, and drops malformed items (logged at debugging
 * level).
 *
 * Output: array of normalised stdClass objects:
 *     (object)[
 *       'name'        => 'KYC Verification',
 *       'description' => '...',
 *       'category'    => 'Compliance',
 *       'level'       => 3,
 *       'confidence'  => 0.86,   // float clamped 0..1
 *       'evidence'    => '...',
 *     ]
 *
 * All length checks use mb_strlen()/mb_substr() so Devanagari counts
 * characters, not UTF-8 bytes.
 *
 * @package local_sentientia_skillsai
 */
class response_parser {

    /** Max character length for name. */
    public const MAX_NAME_LEN = 200;

    /** Max character length for description / evidence. */
    public const MAX_TEXT_LEN = 500;

    /** Valid category buckets — items with an unknown category are coerced to 'Process'. */
    public const CATEGORIES = ['Compliance', 'Technical', 'Product', 'Leadership', 'Process', 'Customer'];

    /**
     * Parse Claude's text body into a normalised skill list.
     *
     * @param string $body
     * @return \stdClass[] (may be empty)
     */
    public static function parse(string $body): array {
        $json = self::extract_json($body);
        if ($json === '') {
            debugging('local_sentientia_skillsai: response body had no JSON');
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['skills']) || !is_array($decoded['skills'])) {
            debugging('local_sentientia_skillsai: response JSON malformed (no skills array)');
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($decoded['skills'] as $idx => $item) {
            $parsed = self::normalise_item($item);
            if ($parsed === null) {
                debugging("local_sentientia_skillsai: dropping malformed skill at index {$idx}");
                continue;
            }
            // De-duplicate by lower-cased name within one response.
            $key = \core_text::strtolower($parsed->name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * Pull the JSON object out of a possibly-wrapped response.
     *
     * @param string $body
     * @return string '' when no plausible JSON object found
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
     * Validate + normalise a single skill item.
     *
     * @param mixed $item
     * @return \stdClass|null
     */
    private static function normalise_item($item): ?\stdClass {
        if (!is_array($item)) {
            return null;
        }

        $name = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '';
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LEN) {
            return null;
        }

        $description = '';
        if (isset($item['description']) && is_string($item['description'])) {
            $description = trim($item['description']);
            if (mb_strlen($description) > self::MAX_TEXT_LEN) {
                $description = mb_substr($description, 0, self::MAX_TEXT_LEN);
            }
        }

        $category = isset($item['category']) && is_string($item['category']) ? trim($item['category']) : '';
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'Process';
        }

        $level = 1;
        if (isset($item['level']) && is_int($item['level'])) {
            $level = $item['level'];
        } else if (isset($item['level']) && is_string($item['level']) && ctype_digit($item['level'])) {
            $level = (int)$item['level'];
        }
        if ($level < 1) {
            $level = 1;
        }
        if ($level > 5) {
            $level = 5;
        }

        $confidence = 0.0;
        if (isset($item['confidence']) && (is_float($item['confidence']) || is_int($item['confidence']))) {
            $confidence = (float)$item['confidence'];
        } else if (isset($item['confidence']) && is_string($item['confidence']) && is_numeric($item['confidence'])) {
            $confidence = (float)$item['confidence'];
        }
        if ($confidence < 0.0) {
            $confidence = 0.0;
        }
        if ($confidence > 1.0) {
            $confidence = 1.0;
        }

        $evidence = '';
        if (isset($item['evidence']) && is_string($item['evidence'])) {
            $evidence = trim($item['evidence']);
            if (mb_strlen($evidence) > self::MAX_TEXT_LEN) {
                $evidence = mb_substr($evidence, 0, self::MAX_TEXT_LEN);
            }
        }

        $out = new \stdClass();
        $out->name        = $name;
        $out->description = $description;
        $out->category    = $category;
        $out->level       = $level;
        $out->confidence  = round($confidence, 2);
        $out->evidence    = $evidence;
        return $out;
    }
}
