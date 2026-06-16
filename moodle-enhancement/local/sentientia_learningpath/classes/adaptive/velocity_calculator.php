<?php
namespace local_sentientia_learningpath\adaptive;

defined('MOODLE_INTERNAL') || die();

/**
 * Completion velocity calculator.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * Velocity is a ratio of "actual completion rate" vs "expected completion
 * rate" for a learner on a given path. It is used alongside quiz scores to
 * decide whether to accelerate, remediate, or leave the sequence unchanged.
 *
 * Velocity index (VI):
 *   VI = (completed_courses / expected_completed_by_now)
 *
 *   VI > 1.2  → ahead of schedule  → candidate for ACCELERATE / BRANCH
 *   VI < 0.5  → significantly behind → candidate for REMEDIATE
 *   0.5–1.2   → on-track              → NO_ACTION on velocity alone
 *
 * "Expected" is derived from the path's startdate and enddate:
 *   - If both are set: linear interpolation of total courses over the window.
 *   - If only startdate: expected = (days since start / 30) * average_pace,
 *     where average_pace is 1 course/month by default.
 *   - If neither is set: fallback — use days since first enrolment.
 *
 * The index is capped at 2.0 (can't be more than double-speed) and
 * floor'd at 0.0. Returns null when there is not enough data to calculate
 * (e.g., path started today, zero elapsed days).
 *
 * @package local_sentientia_learningpath
 */
class velocity_calculator {

    /** Velocity above this → accelerate candidate. */
    public const THRESHOLD_HIGH = 1.2;

    /** Velocity below this → remediate candidate. */
    public const THRESHOLD_LOW  = 0.5;

    /** Default assumed pace: N courses per 30 days when no end-date given. */
    private const DEFAULT_PACE_PER_MONTH = 1;

    /**
     * Calculate the velocity index for a single user on a path.
     *
     * @param int      $userid
     * @param int      $pathid
     * @param int      $total_courses        Total courses on this path
     * @param int      $completed_courses    Courses completed by this user
     * @param int|null $path_startdate       UNIX ts or null
     * @param int|null $path_enddate         UNIX ts or null
     * @param int      $user_enrol_ts        UNIX ts when user joined the path
     * @return float|null  Velocity index 0.0–2.0, or null if incalculable
     */
    public static function calculate(
        int $userid,
        int $pathid,
        int $total_courses,
        int $completed_courses,
        ?int $path_startdate,
        ?int $path_enddate,
        int $user_enrol_ts
    ): ?float {
        if ($total_courses <= 0) {
            return null;
        }

        $now = time();

        // Determine the effective start reference point.
        $ref_start = $path_startdate ?? $user_enrol_ts;
        $elapsed_days = ($now - $ref_start) / 86400;

        if ($elapsed_days < 1) {
            // Path started today — not enough elapsed time to score velocity.
            return null;
        }

        // Compute expected courses completed by now.
        if ($path_startdate !== null && $path_enddate !== null && $path_enddate > $path_startdate) {
            // Linear interpolation across the window.
            $total_window_days = ($path_enddate - $path_startdate) / 86400;
            $fraction_elapsed  = min(1.0, $elapsed_days / $total_window_days);
            $expected = $total_courses * $fraction_elapsed;
        } else {
            // No window: 1 course per 30-day month expected.
            $expected = ($elapsed_days / 30) * self::DEFAULT_PACE_PER_MONTH;
        }

        if ($expected < 0.5) {
            // Expected rounds to zero — not yet meaningful.
            return null;
        }

        $vi = $completed_courses / $expected;

        // Cap and floor.
        return (float) min(2.0, max(0.0, $vi));
    }

    /**
     * Interpret a velocity index into a human-readable label.
     *
     * @param float|null $vi
     * @return string  'ahead'|'on_track'|'behind'|'unknown'
     */
    public static function label(?float $vi): string {
        if ($vi === null) {
            return 'unknown';
        }
        if ($vi >= self::THRESHOLD_HIGH) {
            return 'ahead';
        }
        if ($vi < self::THRESHOLD_LOW) {
            return 'behind';
        }
        return 'on_track';
    }
}
