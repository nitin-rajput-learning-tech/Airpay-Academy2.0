<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The owner's map from a BizLMS course level id to a skill level 1..5 (decision skills.level_proficiency).
 *
 * The decision value is an object: a method name and its rules (the name heuristic the owner approved), a
 * default, and csv, the concrete level-id map the operator generates from the rehearsal preflight and writes
 * into the signed decisions file. The heuristic only PREFILLS that list (suggest()). The import never applies
 * it on its own: a level the csv does not name stops the feature, so nothing is guessed.
 *
 * csv is accepted as text (one "levelid,proficiency" pair per line or per semicolon; "=" and ":" also separate
 * the two; a header line is ignored) or as a JSON object of levelid => proficiency.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class level_map {

    /** Lowest and highest skill level a course level can map to. */
    public const MIN = 1;
    public const MAX = 5;

    /** What a course teaches when it has no level, or one the map does not know. */
    public const FALLBACK = 1;

    /**
     * Read the concrete map out of the decision value.
     *
     * @param mixed $value The value of skills.level_proficiency.
     * @return array{map: array<int, int>, problems: string[]} level id => proficiency, and blocker codes.
     *         No csv at all is one problem (level_proficiency_csv_missing).
     */
    public static function parse(mixed $value): array {
        $csv = is_array($value) ? ($value['csv'] ?? null) : null;
        if ($csv === null || $csv === '' || $csv === []) {
            return ['map' => [], 'problems' => ['level_proficiency_csv_missing']];
        }

        $pairs = [];
        if (is_array($csv)) {
            foreach ($csv as $levelid => $proficiency) {
                $pairs[] = [$levelid, $proficiency, 'key ' . $levelid];
            }
        } else if (is_string($csv)) {
            $entries = preg_split('/[\r\n;]+/', $csv) ?: [];
            $first = true;
            foreach ($entries as $position => $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }
                $parts = preg_split('/[\s,=:]+/', $entry, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($first && (!isset($parts[0]) || !ctype_digit($parts[0]))) {
                    // A header line such as levelid,proficiency.
                    $first = false;
                    continue;
                }
                $first = false;
                if (count($parts) !== 2) {
                    $pairs[] = [null, null, 'line ' . ($position + 1)];
                    continue;
                }
                $pairs[] = [$parts[0], $parts[1], 'line ' . ($position + 1)];
            }
        } else {
            return ['map' => [], 'problems' => ['level_proficiency_csv_invalid']];
        }

        $map = [];
        $problems = [];
        foreach ($pairs as [$levelid, $proficiency, $where]) {
            if (!is_scalar($levelid) || !is_scalar($proficiency) || !preg_match('/^[0-9]+$/', (string) $levelid)
                    || !preg_match('/^[0-9]+$/', (string) $proficiency)
                    || (int) $levelid <= 0
                    || (int) $proficiency < self::MIN || (int) $proficiency > self::MAX) {
                $problems[] = 'level_proficiency_csv_invalid:' . $where;
                continue;
            }
            $levelid = (int) $levelid;
            $proficiency = (int) $proficiency;
            if (isset($map[$levelid]) && $map[$levelid] !== $proficiency) {
                $problems[] = 'level_proficiency_csv_conflict:' . $levelid;
                continue;
            }
            $map[$levelid] = $proficiency;
        }
        if (!$map && !$problems) {
            $problems[] = 'level_proficiency_csv_missing';
        }
        return ['map' => $map, 'problems' => $problems];
    }

    /**
     * The proficiency a course level gives, the fallback for no level or one the map does not name.
     *
     * @param array<int, int> $map As returned by parse().
     * @param int $levelid course.open_level (0 or null when the course has none).
     * @return int 1..5
     */
    public static function proficiency(array $map, int $levelid): int {
        return $map[$levelid] ?? self::FALLBACK;
    }

    /**
     * The owner's name heuristic, for prefilling the list an operator writes into the decisions file.
     * The first word of the name that a rule knows decides; none means the default.
     *
     * @param string $name Level name.
     * @param mixed $value The decision value (its rules and default are used when present).
     * @return int 1..5
     */
    public static function suggest(string $name, mixed $value): int {
        $rules = is_array($value) && is_array($value['rules'] ?? null) ? $value['rules'] : [
            'awareness' => 1, 'basic' => 2, 'beginner' => 2, 'foundation' => 2,
            'intermediate' => 3, 'advanced' => 4, 'expert' => 5,
        ];
        $default = is_array($value) && isset($value['default']) ? (int) $value['default'] : self::FALLBACK;
        $words = preg_split('/[^a-z]+/', \core_text::strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            if (isset($rules[$word])) {
                return max(self::MIN, min(self::MAX, (int) $rules[$word]));
            }
        }
        return max(self::MIN, min(self::MAX, $default));
    }
}
