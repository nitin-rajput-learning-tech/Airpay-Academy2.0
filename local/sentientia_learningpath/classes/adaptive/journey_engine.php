<?php
namespace local_sentientia_learningpath\adaptive;

defined('MOODLE_INTERNAL') || die();

/**
 * Adaptive journey engine — the decision core for P0.2.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * This class is the single entry point for all adaptive pivot decisions.
 * It is called:
 *   a) By the event observer (observer.php) on mod_quiz_attempt_submitted.
 *   b) By the daily task\adaptive_sweep cron for velocity-only recalculation.
 *
 * FEATURE FLAG GATE
 * -----------------
 * The FIRST thing every public method does is check the feature flag
 * sentientia.learningpath.adaptive.enabled. When the flag is OFF the
 * method returns immediately without reading or writing anything.
 * This guarantees zero behavioural change for v1.7.1 deployments.
 *
 * DECISION LOGIC
 * --------------
 * For a learner in an ADAPTIVE path (adaptive_mode = 1), after a course
 * triggers the engine:
 *
 *   1. Collect signals:
 *      a. Quiz score (mod_quiz attempt data via quiz_signal_reader)
 *      b. Completion velocity (velocity_calculator)
 *      c. Skills-gap feed (skills_gap_feed → skillsai or empty)
 *
 *   2. Determine trigger type: quiz_score | velocity | skills_gap | combined
 *
 *   3. Pivot decision:
 *      REMEDIATE  — quiz_score < path.score_threshold_low
 *                   OR velocity < THRESHOLD_LOW + skill gaps exist
 *      ACCELERATE — quiz_score >= path.score_threshold_high
 *                   AND velocity >= THRESHOLD_HIGH
 *                   AND NO skill gaps above 0.3
 *      BRANCH     — skill gaps exist targeting specific courses not in
 *                   the default sequence
 *      NO_ACTION  — everything in range / not enough data
 *
 *   4. Act on decision:
 *      REMEDIATE  — find and ENROL the learner in the remedial course
 *                   node for source_courseid (if one exists on the path)
 *      ACCELERATE — mark next N accelerator courses as auto-completed
 *                   (NOT skipped — learner must still access) OR insert
 *                   an accelerator course after the current one. For now:
 *                   insert a notification record + log entry. Actual
 *                   skip requires manager confirmation (Phase 2).
 *      BRANCH     — insert gap-addressing courses into the path for this
 *                   user (write to local_sentientia_lp_adaptive_log only;
 *                   actual enrolment requires a follow-up call to
 *                   path_manager::enrol_users()).
 *      NO_ACTION  — write a log entry and return.
 *
 *   5. Write a log entry to local_sentientia_lp_adaptive_log.
 *
 * BACKWARDS COMPATIBILITY
 * -----------------------
 * Paths with adaptive_mode = 0 (all existing paths) are NEVER touched
 * by the engine. The flag gate AND the adaptive_mode column both must
 * be truthy for any pivot to occur.
 *
 * @package local_sentientia_learningpath
 */
class journey_engine {

    private const LOG_TABLE   = 'local_sentientia_lp_adaptive_log';
    private const PATH_TABLE  = 'local_sentientia_learningpath';
    private const COURSE_TABLE = 'local_sentientia_learningpath_courses';
    private const USERS_TABLE = 'local_sentientia_learningpath_users';

    /**
     * Evaluate the adaptive state for a user after a course completion or
     * quiz attempt event. This is the primary entry point called by the
     * event observer.
     *
     * FEATURE FLAG: Returns immediately (no-op) when flag is OFF.
     *
     * @param int $userid
     * @param int $courseid   The course that was just completed / attempted
     * @return bool  True if a pivot occurred, false for no-action or no-op
     */
    public static function evaluate(int $userid, int $courseid): bool {
        // ── Feature flag gate ─────────────────────────────────────────
        if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.learningpath.adaptive.enabled')) {
            return false;
        }

        global $DB;

        // ── Find all adaptive paths this user is enrolled in that contain
        //    this course ─────────────────────────────────────────────────
        $paths = $DB->get_records_sql(
            "SELECT lp.id, lp.costcenterid, lp.adaptive_mode,
                    lp.score_threshold_low, lp.score_threshold_high,
                    lp.startdate, lp.enddate
               FROM {" . self::PATH_TABLE . "} lp
               JOIN {" . self::COURSE_TABLE . "} lpc ON lpc.pathid = lp.id
               JOIN {" . self::USERS_TABLE . "} lpu ON lpu.pathid = lp.id
              WHERE lp.adaptive_mode  = 1
                AND lp.status         = 1
                AND lpc.courseid      = :cid
                AND lpu.userid        = :uid",
            ['cid' => $courseid, 'uid' => $userid]
        );

        if (empty($paths)) {
            return false;
        }

        $pivoted = false;
        foreach ($paths as $path) {
            $result = self::evaluate_for_path($userid, $courseid, $path);
            if ($result !== 'no_action') {
                $pivoted = true;
            }
        }

        return $pivoted;
    }

    /**
     * Run the daily velocity sweep for all adaptive paths.
     * Called by task\adaptive_sweep.
     *
     * FEATURE FLAG: Returns immediately (no-op) when flag is OFF.
     *
     * @return int  Count of pivot decisions made
     */
    public static function velocity_sweep(): int {
        if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.learningpath.adaptive.enabled')) {
            return 0;
        }

        global $DB;

        // Fetch all active adaptive paths with enrolled users.
        $path_users = $DB->get_records_sql(
            "SELECT lpu.userid, lpu.pathid, lpu.timecreated AS enrol_ts,
                    lp.costcenterid, lp.score_threshold_low, lp.score_threshold_high,
                    lp.startdate, lp.enddate
               FROM {" . self::USERS_TABLE . "} lpu
               JOIN {" . self::PATH_TABLE . "} lp ON lp.id = lpu.pathid
              WHERE lp.adaptive_mode = 1
                AND lp.status        = 1
                AND lpu.status       < 2",  // not already completed
            []
        );

        if (empty($path_users)) {
            return 0;
        }

        $pivots = 0;
        foreach ($path_users as $pu) {
            $result = self::evaluate_velocity_only(
                (int) $pu->userid,
                (int) $pu->pathid,
                (object) [
                    'id'                   => (int) $pu->pathid,
                    'costcenterid'         => (int) $pu->costcenterid,
                    'score_threshold_low'  => $pu->score_threshold_low,
                    'score_threshold_high' => $pu->score_threshold_high,
                    'startdate'            => $pu->startdate,
                    'enddate'              => $pu->enddate,
                ],
                (int) $pu->enrol_ts
            );
            if ($result !== 'no_action') {
                $pivots++;
            }
        }

        return $pivots;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Full evaluation for one (user, course, path) tuple.
     *
     * @param int    $userid
     * @param int    $courseid
     * @param object $path     Row from local_sentientia_learningpath
     * @return string  pivot_type: 'remediate'|'accelerate'|'branch'|'no_action'
     */
    private static function evaluate_for_path(
        int $userid,
        int $courseid,
        object $path
    ): string {
        global $DB;

        $now = time();
        $costcenterid = (int) $path->costcenterid;

        // ── 1. Collect signals ────────────────────────────────────────

        // a) Quiz score
        $quiz_score = quiz_signal_reader::trigger_score($userid, $courseid);

        // b) Velocity
        $progress     = \local_sentientia_learningpath\path_manager::get_user_progress(
            (int) $path->id, $userid);
        $enrol_record = $DB->get_record(self::USERS_TABLE,
            ['pathid' => (int) $path->id, 'userid' => $userid], 'timecreated');
        $enrol_ts = $enrol_record ? (int) $enrol_record->timecreated : $now;

        $velocity = velocity_calculator::calculate(
            $userid,
            (int) $path->id,
            $progress->total_courses,
            $progress->completed_courses,
            !empty($path->startdate)  ? (int) $path->startdate  : null,
            !empty($path->enddate)    ? (int) $path->enddate    : null,
            $enrol_ts
        );

        // c) Skills gap
        $gap_data    = skills_gap_feed::get_user_gap($userid);
        $gap_json    = skills_gap_feed::serialise($gap_data);
        $has_gaps    = !empty($gap_data);

        // ── 2. Determine trigger type ─────────────────────────────────
        $trigger_type = self::determine_trigger(
            $quiz_score, $velocity, $has_gaps);

        // ── 3. Pivot decision ─────────────────────────────────────────
        $threshold_low  = $path->score_threshold_low  !== null
            ? (float) $path->score_threshold_low  : 50.0;
        $threshold_high = $path->score_threshold_high !== null
            ? (float) $path->score_threshold_high : 80.0;

        $pivot_type = self::decide(
            $quiz_score,
            $velocity,
            $has_gaps,
            $threshold_low,
            $threshold_high
        );

        // ── 4. Act ────────────────────────────────────────────────────
        $target_courseid = 0;
        $notes = '';

        switch ($pivot_type) {
            case 'remediate':
                [$target_courseid, $notes] = self::act_remediate(
                    $userid, (int) $path->id, $courseid);
                break;

            case 'accelerate':
                [$target_courseid, $notes] = self::act_accelerate(
                    $userid, (int) $path->id, $courseid);
                break;

            case 'branch':
                [$target_courseid, $notes] = self::act_branch(
                    $userid, (int) $path->id, $gap_data);
                break;

            case 'no_action':
            default:
                $notes = 'All signals within normal range. No pivot required.';
                break;
        }

        // ── 5. Write log ──────────────────────────────────────────────
        $DB->insert_record(self::LOG_TABLE, (object) [
            'pathid'           => (int) $path->id,
            'userid'           => $userid,
            'costcenterid'     => $costcenterid,
            'pivot_type'       => $pivot_type,
            'trigger_type'     => $trigger_type,
            'source_courseid'  => $courseid,
            'target_courseid'  => $target_courseid,
            'quiz_score'       => $quiz_score,
            'velocity_score'   => $velocity,
            'skills_gap_json'  => $gap_json,
            'decision_notes'   => $notes,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);

        return $pivot_type;
    }

    /**
     * Velocity-only evaluation (called from daily sweep).
     * Uses the last completed course as the trigger source (or 0 if none).
     *
     * @param int    $userid
     * @param int    $pathid
     * @param object $path
     * @param int    $enrol_ts
     * @return string  pivot_type
     */
    private static function evaluate_velocity_only(
        int $userid,
        int $pathid,
        object $path,
        int $enrol_ts
    ): string {
        global $DB;

        $now = time();

        $progress = \local_sentientia_learningpath\path_manager::get_user_progress(
            $pathid, $userid);

        $velocity = velocity_calculator::calculate(
            $userid,
            $pathid,
            $progress->total_courses,
            $progress->completed_courses,
            !empty($path->startdate)  ? (int) $path->startdate  : null,
            !empty($path->enddate)    ? (int) $path->enddate    : null,
            $enrol_ts
        );

        if ($velocity === null) {
            return 'no_action';
        }

        // Velocity-only: only remediate or no_action (no quiz score = can't accelerate).
        $pivot_type = 'no_action';
        $notes = '';

        if ($velocity < velocity_calculator::THRESHOLD_LOW) {
            $pivot_type = 'remediate';
            $notes = sprintf('Velocity sweep: VI=%.2f below threshold %.2f.',
                $velocity, velocity_calculator::THRESHOLD_LOW);
        } else {
            $notes = sprintf('Velocity sweep: VI=%.2f within normal range.', $velocity);
        }

        // Find most-recently completed course on this path as "source".
        $last_course = $DB->get_field_sql(
            "SELECT lpc.courseid
               FROM {" . self::COURSE_TABLE . "} lpc
               JOIN {course_completions} cc ON cc.course = lpc.courseid
              WHERE lpc.pathid     = :pid
                AND cc.userid      = :uid
                AND cc.timecompleted > 0
           ORDER BY cc.timecompleted DESC
              LIMIT 1",
            ['pid' => $pathid, 'uid' => $userid]
        );
        $source_courseid = $last_course ? (int) $last_course : 0;

        $target_courseid = 0;
        if ($pivot_type === 'remediate' && $source_courseid > 0) {
            [$target_courseid, $notes] = self::act_remediate(
                $userid, $pathid, $source_courseid);
        }

        $DB->insert_record(self::LOG_TABLE, (object) [
            'pathid'          => $pathid,
            'userid'          => $userid,
            'costcenterid'    => (int) $path->costcenterid,
            'pivot_type'      => $pivot_type,
            'trigger_type'    => 'velocity',
            'source_courseid' => $source_courseid,
            'target_courseid' => $target_courseid,
            'quiz_score'      => null,
            'velocity_score'  => $velocity,
            'skills_gap_json' => null,
            'decision_notes'  => $notes,
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);

        return $pivot_type;
    }

    /**
     * Determine the trigger type from available signals.
     *
     * @param float|null $quiz_score
     * @param float|null $velocity
     * @param bool       $has_gaps
     * @return string  trigger_type
     */
    private static function determine_trigger(
        ?float $quiz_score,
        ?float $velocity,
        bool $has_gaps
    ): string {
        $has_quiz = $quiz_score !== null;
        $has_vel  = $velocity  !== null;

        if ($has_quiz && $has_vel && $has_gaps) {
            return 'combined';
        }
        if ($has_quiz && ($has_vel || $has_gaps)) {
            return 'combined';
        }
        if ($has_gaps) {
            return 'skills_gap';
        }
        if ($has_vel) {
            return 'velocity';
        }
        return 'quiz_score';
    }

    /**
     * Decide the pivot action from the collected signals.
     *
     * @param float|null $quiz_score
     * @param float|null $velocity
     * @param bool       $has_gaps
     * @param float      $threshold_low
     * @param float      $threshold_high
     * @return string  'remediate'|'accelerate'|'branch'|'no_action'
     */
    private static function decide(
        ?float $quiz_score,
        ?float $velocity,
        bool $has_gaps,
        float $threshold_low,
        float $threshold_high
    ): string {
        // REMEDIATE wins if quiz score is below low threshold.
        if ($quiz_score !== null && $quiz_score < $threshold_low) {
            return 'remediate';
        }

        // REMEDIATE if significantly behind on velocity with skills gaps.
        if ($velocity !== null
            && $velocity < velocity_calculator::THRESHOLD_LOW
            && $has_gaps) {
            return 'remediate';
        }

        // BRANCH if skills gaps exist (even if quiz score is okay).
        // Branch inserts gap-addressing courses, doesn't remove anything.
        if ($has_gaps && ($quiz_score === null || $quiz_score < $threshold_high)) {
            return 'branch';
        }

        // ACCELERATE only when everything looks good: high score, ahead on
        // velocity, and no meaningful skill gaps.
        if ($quiz_score !== null
            && $quiz_score >= $threshold_high
            && ($velocity === null || $velocity >= velocity_calculator::THRESHOLD_HIGH)
            && !$has_gaps) {
            return 'accelerate';
        }

        return 'no_action';
    }

    /**
     * Act on a REMEDIATE decision: find the remedial course for source_courseid
     * on this path and enrol the user in it.
     *
     * @param int $userid
     * @param int $pathid
     * @param int $source_courseid
     * @return array{int, string}  [target_courseid, notes]
     */
    private static function act_remediate(
        int $userid,
        int $pathid,
        int $source_courseid
    ): array {
        global $DB, $CFG;

        // Look for a remedial node on this path that maps to source_courseid.
        $remedial = $DB->get_record_sql(
            "SELECT lpc.courseid
               FROM {" . self::COURSE_TABLE . "} lpc
              WHERE lpc.pathid                 = :pid
                AND lpc.is_remedial            = 1
                AND lpc.remedial_for_courseid  = :src",
            ['pid' => $pathid, 'src' => $source_courseid]
        );

        if (!$remedial) {
            return [0, 'Remediate: no remedial node configured for course ' . $source_courseid];
        }

        $target = (int) $remedial->courseid;

        // Enrol user in the remedial course via the path's standard enrolment.
        require_once($CFG->libdir . '/enrollib.php');
        $context = \context_course::instance($target, IGNORE_MISSING);
        if ($context && !is_enrolled($context, $userid)) {
            enrol_try_internal_enrol($target, $userid, null, time(), 0);
        }

        return [$target,
            "Remediate: enrolled user in remedial course {$target} for source {$source_courseid}."
        ];
    }

    /**
     * Act on an ACCELERATE decision. Phase 1: log intent and enrol user in
     * any accelerator course nodes that follow the source on this path.
     *
     * Full skip logic (manager confirmation) is planned for Phase 2.
     *
     * @param int $userid
     * @param int $pathid
     * @param int $source_courseid
     * @return array{int, string}
     */
    private static function act_accelerate(
        int $userid,
        int $pathid,
        int $source_courseid
    ): array {
        global $DB, $CFG;

        // Find the next accelerator node after source_courseid on this path.
        $source_order = (int) $DB->get_field_sql(
            "SELECT sortorder FROM {" . self::COURSE_TABLE . "}
              WHERE pathid = :pid AND courseid = :cid",
            ['pid' => $pathid, 'cid' => $source_courseid]
        );

        $accel = $DB->get_record_sql(
            "SELECT courseid FROM {" . self::COURSE_TABLE . "}
              WHERE pathid        = :pid
                AND is_accelerator = 1
                AND sortorder     > :so
           ORDER BY sortorder ASC
              LIMIT 1",
            ['pid' => $pathid, 'so' => $source_order]
        );

        if (!$accel) {
            return [0,
                "Accelerate: no accelerator node found after sortorder {$source_order}."
            ];
        }

        $target = (int) $accel->courseid;

        // Enrol user in the accelerator course.
        require_once($CFG->libdir . '/enrollib.php');
        $context = \context_course::instance($target, IGNORE_MISSING);
        if ($context && !is_enrolled($context, $userid)) {
            enrol_try_internal_enrol($target, $userid, null, time(), 0);
        }

        return [$target,
            "Accelerate: enrolled user in accelerator course {$target}. " .
            "Full sequence skip pending manager confirmation (Phase 2)."
        ];
    }

    /**
     * Act on a BRANCH decision: find gap-addressing courses from skillsai
     * that are not yet on the path for this user, and log them for follow-up
     * enrolment (manager-review pattern to avoid auto-flooding learners).
     *
     * @param int   $userid
     * @param int   $pathid
     * @param array $gap_data  From skills_gap_feed::get_user_gap()
     * @return array{int, string}
     */
    private static function act_branch(
        int $userid,
        int $pathid,
        array $gap_data
    ): array {
        global $DB;

        if (empty($gap_data)) {
            return [0, 'Branch: no gap data available.'];
        }

        // Collect course IDs from gap data.
        $gap_courses = [];
        foreach ($gap_data as $gap) {
            if (!empty($gap->course_ids)) {
                foreach ($gap->course_ids as $cid) {
                    $gap_courses[(int) $cid] = true;
                }
            }
        }

        if (empty($gap_courses)) {
            return [0, 'Branch: skill gaps detected but no recommended courses in gap payload.'];
        }

        // Filter to courses not already on this path.
        $path_courses = $DB->get_fieldset_select(
            self::COURSE_TABLE, 'courseid', 'pathid = :pid', ['pid' => $pathid]);
        $on_path_set  = array_flip(array_map('intval', $path_courses));

        $new_branch_courses = array_keys(array_diff_key($gap_courses, $on_path_set));

        if (empty($new_branch_courses)) {
            return [0,
                'Branch: all gap-addressing courses already on this path — no action needed.'
            ];
        }

        // Phase 1: log intent only. Manager reviews the log and approves
        // adding branch courses to the path via path_manager::assign_courses().
        $first_target = reset($new_branch_courses);
        $all_targets  = implode(', ', $new_branch_courses);

        return [(int) $first_target,
            "Branch: {" . count($new_branch_courses) . "} gap-addressing course(s) " .
            "identified for manager review: [{$all_targets}]. " .
            "Pending explicit enrolment approval (see adaptive log)."
        ];
    }
}
