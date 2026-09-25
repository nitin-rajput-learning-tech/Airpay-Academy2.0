<?php
namespace local_sentientia_learningpath\adaptive;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface to the skills-gap feed from local_sentientia_skillsai.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * The sibling plugin local_sentientia_skillsai (P0.1) is being built in
 * parallel. This class guards every interaction with class_exists() and
 * get_config() so that when skillsai is absent (not installed, not
 * enabled, or not yet built) the adaptive engine degrades gracefully to
 * completion + quiz-score signals only.
 *
 * Contract (what skillsai must provide when present):
 *
 *   \local_sentientia_skillsai\gap_engine::get_user_gap(int $userid): array
 *
 *   Returns an array of gap objects, each with:
 *     - skill_id    (int)    — skill being measured
 *     - skill_name  (string) — display label
 *     - required    (float)  — required proficiency 0–1
 *     - current     (float)  — current proficiency 0–1
 *     - gap         (float)  — required − current (positive = shortfall)
 *     - course_ids  (int[])  — courses that address this gap
 *
 *   An empty array is a valid return and means "no gaps detected".
 *
 * When skillsai is absent, get_user_gap() returns [] and the engine
 * treats it as "no skills data — rely on quiz + velocity only".
 *
 * @package local_sentientia_learningpath
 */
class skills_gap_feed {

    /**
     * Fetch the skills-gap data for a user.
     *
     * Callers MUST handle an empty array as "no data" — not as "no gaps".
     * The distinction matters: empty = degraded mode, not assessment result.
     *
     * @param int $userid
     * @return array  Array of gap objects as described above; empty if
     *                skillsai is absent or disabled.
     */
    public static function get_user_gap(int $userid): array {
        // Guard 1: plugin class must exist (skillsai installed + autoloaded).
        if (!class_exists('\local_sentientia_skillsai\gap_engine')) {
            return [];
        }

        // Guard 2: skillsai must have its master flag enabled.
        if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.skillsai.enabled')) {
            return [];
        }

        try {
            $gaps = \local_sentientia_skillsai\gap_engine::get_user_gap($userid);
            if (!is_array($gaps)) {
                return [];
            }
            return $gaps;
        } catch (\Throwable $e) {
            // Never let a skillsai failure break the adaptive engine.
            debugging('local_sentientia_learningpath adaptive: skillsai gap_engine threw: '
                . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Does the skills-gap feed have data for this user?
     *
     * Returns false when:
     *   - skillsai is not installed
     *   - skillsai master flag is OFF
     *   - gap_engine returned an empty array
     *
     * @param int $userid
     * @return bool
     */
    public static function has_data(int $userid): bool {
        return count(self::get_user_gap($userid)) > 0;
    }

    /**
     * Get the course IDs that address any gap the user has, filtered to
     * courses that are actually on this learning path.
     *
     * @param int   $userid
     * @param int[] $path_courseids   All course IDs on this path
     * @return int[]  Subset of path_courseids that address a skills gap
     */
    public static function gap_courses_on_path(int $userid, array $path_courseids): array {
        if (empty($path_courseids)) {
            return [];
        }
        $gaps = self::get_user_gap($userid);
        if (empty($gaps)) {
            return [];
        }

        $gap_course_set = [];
        foreach ($gaps as $gap) {
            if (!empty($gap->course_ids) && is_array($gap->course_ids)) {
                foreach ($gap->course_ids as $cid) {
                    $gap_course_set[(int) $cid] = true;
                }
            }
        }

        $path_set = array_flip(array_map('intval', $path_courseids));
        return array_keys(array_intersect_key($gap_course_set, $path_set));
    }

    /**
     * Serialise the gap payload for storage in the adaptive log.
     * Strips PII — only skill IDs, names, gap magnitudes, and course IDs
     * are stored (no userid-linked data beyond what's already in the log row).
     *
     * @param array $gaps  Return value of get_user_gap()
     * @return string|null  JSON string or null if empty
     */
    public static function serialise(array $gaps): ?string {
        if (empty($gaps)) {
            return null;
        }
        $safe = [];
        foreach ($gaps as $gap) {
            $safe[] = [
                'skill_id'   => (int) ($gap->skill_id ?? 0),
                'skill_name' => (string) ($gap->skill_name ?? ''),
                'gap'        => round((float) ($gap->gap ?? 0), 4),
                'course_ids' => array_map('intval', (array) ($gap->course_ids ?? [])),
            ];
        }
        return json_encode($safe, JSON_THROW_ON_ERROR);
    }
}
