<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses Claude's response into a normalised recommendation list.
 *
 * The expected payload is documented in {@see prompt_builder} — a JSON
 * object with a single "recommendations" key. This parser:
 *
 *   - Strips any markdown code-fence the model might add
 *   - Decodes strictly (no JSON5)
 *   - Validates each item against the schema
 *   - Drops malformed items silently (debugging() logs the reason)
 *
 * Output: array of normalised recommendation objects (stdClass):
 *
 *     [
 *       (object)[
 *         'course_id' => 42,
 *         'score'     => 87,
 *         'reasoning' => '...',
 *       ],
 *       ...
 *     ]
 *
 * @package local_sentientia_recommendations
 */
class response_parser {

    /** Max char length for reasoning strings (defensive). */
    public const MAX_REASONING_LEN = 500;

    /**
     * Parse Claude's text body into a normalised recommendation list.
     *
     * @param string $body                Claude's response text
     * @param int[]  $allowedcourseids    Course IDs allowed in the output (any
     *                                    other ID returned by Claude is dropped).
     *                                    Pass empty array to disable filtering.
     * @return \stdClass[] Array of parsed recommendation objects (may be empty)
     */
    public static function parse(string $body, array $allowedcourseids = []): array {
        $json = self::extract_json($body);
        if ($json === '') {
            debugging('local_sentientia_recommendations: response body had no JSON');
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['recommendations']) || !is_array($decoded['recommendations'])) {
            debugging('local_sentientia_recommendations: response JSON malformed (no recommendations array)');
            return [];
        }

        $allowed_set = !empty($allowedcourseids)
            ? array_flip(array_map('intval', $allowedcourseids))
            : null;

        $out = [];
        $seen = [];  // de-dupe by course_id
        foreach ($decoded['recommendations'] as $idx => $item) {
            $parsed = self::normalise_item($item, $allowed_set);
            if ($parsed === null) {
                debugging("local_sentientia_recommendations: dropping malformed recommendation at index {$idx}");
                continue;
            }
            if (isset($seen[$parsed->course_id])) {
                continue;  // duplicate course_id — keep first occurrence
            }
            $seen[$parsed->course_id] = true;
            $out[] = $parsed;
        }
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
     * Validate + normalise a single recommendation item from the decoded JSON.
     *
     * Returns null if the item fails any validation check — caller drops it.
     *
     * @param mixed              $item
     * @param array<int,int>|null $allowed_set  array_flip-style set of allowed course IDs, or null to allow all
     * @return \stdClass|null
     */
    private static function normalise_item($item, ?array $allowed_set): ?\stdClass {
        if (!is_array($item)) {
            return null;
        }

        // course_id (accept int or string-encoded int).
        $cid = null;
        if (isset($item['course_id'])) {
            if (is_int($item['course_id'])) {
                $cid = $item['course_id'];
            } else if (is_string($item['course_id']) && ctype_digit($item['course_id'])) {
                $cid = (int)$item['course_id'];
            }
        }
        if ($cid === null || $cid <= 0) {
            return null;
        }
        if ($allowed_set !== null && !isset($allowed_set[$cid])) {
            return null;  // Claude invented a course_id not in our catalog
        }

        // score (clamp 0..100; accept int or string-encoded int).
        $score = 0;
        if (isset($item['score'])) {
            if (is_int($item['score'])) {
                $score = $item['score'];
            } else if (is_string($item['score']) && ctype_digit($item['score'])) {
                $score = (int)$item['score'];
            } else if (is_float($item['score'])) {
                $score = (int)round($item['score']);
            }
        }
        $score = max(0, min(100, $score));

        $reasoning = '';
        if (isset($item['reasoning']) && is_string($item['reasoning'])) {
            $reasoning = trim($item['reasoning']);
            if (strlen($reasoning) > self::MAX_REASONING_LEN) {
                $reasoning = substr($reasoning, 0, self::MAX_REASONING_LEN);
            }
        }

        $out = new \stdClass();
        $out->course_id = $cid;
        $out->score     = $score;
        $out->reasoning = $reasoning;
        return $out;
    }
}
