<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The pure value rules of the BizLMS recompletion import (mapping doc, section 12).
 *
 * Nothing here reads the database or the clock, so every rule is tested on its own and a dry run and an
 * apply run cannot disagree about one. The steps call these functions; they do not repeat them.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mapper {

    /** Seconds in a day: the legacy duration is in seconds, the Sentientia rule counts whole days. */
    public const DAY = 86400;

    /** A rule period when the course config has no usable duration and the site default is unset. */
    public const DEFAULT_PERIOD_DAYS = 365;

    /** number(10,5) holds an absolute value below this. */
    public const NUMBER_LIMIT = 100000;

    /** Largest value of an int(10) column on every supported engine. */
    public const MAX_INT = 2147483647;

    /** Item types of the archive table. */
    public const COURSE_COMPLETION = 'course_completion';
    public const CRITERIA_COMPLETION = 'criteria_completion';
    public const ACTIVITY_COMPLETION = 'activity_completion';
    public const QUIZ_ATTEMPT = 'quiz_attempt';
    public const QUIZ_GRADE = 'quiz_grade';
    public const SCORM_TRACK = 'scorm_track';
    public const LTI_GRADE = 'lti_grade';
    public const QUESTIONNAIRE_RESPONSE = 'questionnaire_response';
    public const QUESTIONNAIRE_ANSWER = 'questionnaire_answer';
    /** Only the Sentientia engine writes this type (the BizLMS plugin deleted grades without copying them). */
    public const GRADEBOOK_GRADE = 'gradebook_grade';

    /**
     * Every archive item type, in the order a reader shows them.
     *
     * @return string[]
     */
    public static function item_types(): array {
        return [
            self::COURSE_COMPLETION, self::CRITERIA_COMPLETION, self::ACTIVITY_COMPLETION, self::QUIZ_ATTEMPT,
            self::QUIZ_GRADE, self::SCORM_TRACK, self::LTI_GRADE, self::QUESTIONNAIRE_RESPONSE,
            self::QUESTIONNAIRE_ANSWER, self::GRADEBOOK_GRADE,
        ];
    }

    /**
     * The rule period in whole days from the legacy duration (seconds).
     *
     * max(1, ceil(seconds / 86400)). Zero, empty, negative, non-numeric and absurdly large values return null,
     * which the caller answers with the site default (and reports the course).
     *
     * @param mixed $seconds The value column of the recompletionduration config row.
     * @return int|null
     */
    public static function period_days($seconds): ?int {
        if ($seconds === null || is_bool($seconds) || !is_scalar($seconds)) {
            return null;
        }
        $text = trim((string) $seconds);
        if ($text === '' || !is_numeric($text)) {
            return null;
        }
        $number = (float) $text;
        if (!is_finite($number) || $number <= 0) {
            return null;
        }
        $days = ceil($number / self::DAY);
        if ($days > self::MAX_INT) {
            // Compared as a float: casting a float this large to int first is undefined.
            return null;
        }
        return max(1, (int) $days);
    }

    /**
     * A legacy on/off switch: only the value 1 is on.
     *
     * @param mixed $value
     * @return int 1 or 0
     */
    public static function switch_on($value): int {
        return ($value !== null && is_scalar($value) && trim((string) $value) === '1') ? 1 : 0;
    }

    /**
     * A legacy number as the archive stores it: a number of at most five decimals with an absolute value below
     * 100000, else null. The raw value stays in the payload.
     *
     * @param mixed $value
     * @return float|null
     */
    public static function bounded_number($value): ?float {
        if ($value === null || is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || !is_numeric($text)) {
            return null;
        }
        $number = (float) $text;
        if (!is_finite($number) || abs($number) >= self::NUMBER_LIMIT) {
            return null;
        }
        return round($number, 5);
    }

    /**
     * A timestamp column: a positive integer that fits an int(10), else null.
     *
     * @param mixed $value
     * @return int|null
     */
    public static function timestamp($value): ?int {
        if ($value === null || is_bool($value) || !is_scalar($value) || !preg_match('/^\s*[0-9]+\s*$/', (string) $value)) {
            return null;
        }
        $time = (int) trim((string) $value);
        return ($time > 0 && $time <= self::MAX_INT) ? $time : null;
    }

    /**
     * The first positive timestamp of a list.
     *
     * @param mixed[] $values
     * @return int|null
     */
    public static function first_timestamp(array $values): ?int {
        foreach ($values as $value) {
            $time = self::timestamp($value);
            if ($time !== null) {
                return $time;
            }
        }
        return null;
    }

    /**
     * State name of a legacy activity completion state (course_modules_completion.completionstate).
     *
     * @param mixed $state
     * @return string
     */
    public static function activity_state($state): string {
        $names = [0 => 'incomplete', 1 => 'complete', 2 => 'complete_pass', 3 => 'complete_fail'];
        $key = is_scalar($state) ? (int) $state : -1;
        return $names[$key] ?? 'unknown';
    }

    /**
     * Why a course completion was reset, from the standard log row of the legacy completion_reset event.
     *
     * The cron task fires the event from the command line (the scheduled task's user is an administrator, so
     * the log row's userid is not the learner): origin cli is the scheduled reset and nobody pressed it. A web
     * reset by the learner is a self reset. A web reset by somebody else is either the reset page or an
     * administrator running the cron from a browser, and the log row cannot tell which, so it is reported as
     * legacy and nobody is named as the actor.
     *
     * @param mixed $origin logstore_standard_log.origin
     * @param int $actor logstore_standard_log.userid
     * @param int $subject logstore_standard_log.relateduserid
     * @return array{0: string, 1: int|null, 2: bool} [reason, reset_by_userid, unclear]
     */
    public static function reset_source($origin, int $actor, int $subject): array {
        $origin = strtolower(trim((string) $origin));
        if ($origin === 'cli') {
            return ['cron', null, false];
        }
        if ($origin === 'web' && $actor > 0 && $actor === $subject) {
            return ['manual', $actor, false];
        }
        return ['legacy', null, true];
    }

    /**
     * The reset time of a completion archived without a log row of its reset, when the legacy data gives one.
     *
     * The earlier of: completion + the legacy duration, and the first evidence of the next cycle, provided that
     * time is not later than the import (a time after the import is not a reset that can have happened). It is
     * never earlier than the completion. Null when neither candidate exists or both lie after the import: the
     * caller then dates the reset from the cycle's last evidence (inferred_time).
     *
     * @param int|null $completed Completion time, null for a cycle that was never completed.
     * @param int $duration Legacy duration in seconds.
     * @param int|null $next First evidence of the next cycle, after the completion.
     * @param int $now Import time.
     * @return int|null
     */
    public static function inferred_candidate(?int $completed, int $duration, ?int $next, int $now): ?int {
        $candidates = [];
        if ($completed !== null && $completed > 0 && $duration > 0) {
            $candidates[] = $completed + $duration;
        }
        if ($next !== null && $next > 0) {
            $candidates[] = $next;
        }
        if (!$candidates) {
            return null;
        }
        $time = min($candidates);
        if ($time > $now) {
            return null;
        }
        if ($completed !== null && $completed > 0) {
            $time = max($time, min($completed, $now));
        }
        return $time;
    }

    /**
     * The reset time of a completion archived without a log row of its reset (owner decision
     * recompletion.inferred_reset_without_evidence).
     *
     * 1. The earlier of completion + the legacy duration and the first evidence of the next cycle, when that is
     *    not later than the import (inferred_candidate).
     * 2. Otherwise one second after the latest source evidence of the cycle (its own enrolled, started and
     *    completed dates and its archived activity rows), which is the earliest moment the data allows. The second
     *    keeps the cycle's own last row strictly before the reset (evidence::ends_cycle_of).
     *
     * The import time is only ever an UPPER CLAMP, never the value: a reset dated at cutover would claim a
     * completion stood until then, and would give a different answer on every run. The result is also never
     * earlier than the completion. The caller lifts it to the end of the cycle before it.
     *
     * @param int|null $completed Completion time, null for a cycle that was never completed.
     * @param int $duration Legacy duration in seconds.
     * @param int|null $next First evidence of the next cycle, after the completion.
     * @param int $now Import time: the upper clamp.
     * @param int|null $lastevidence Latest source evidence of the cycle; null when it has none.
     * @return int
     */
    public static function inferred_time(?int $completed, int $duration, ?int $next, int $now,
                                         ?int $lastevidence = null): int {
        $time = self::inferred_candidate($completed, $duration, $next, $now);
        if ($time !== null) {
            return $time;
        }
        return self::inferred_from_evidence($completed, $lastevidence, $now);
    }

    /**
     * One second after the latest evidence of a cycle, never later than now and never earlier than the completion.
     * A cycle with no evidence at all gets second 1: the earliest time there is, which the caller lifts to the end
     * of the cycle before it and which the reader shows as an estimate.
     *
     * @param int|null $completed Completion time, null for a cycle that was never completed.
     * @param int|null $lastevidence Latest source evidence of the cycle; null when it has none.
     * @param int $now Import time: the upper clamp.
     * @return int
     */
    public static function inferred_from_evidence(?int $completed, ?int $lastevidence, int $now): int {
        $latest = max($completed ?? 0, $lastevidence ?? 0, 0);
        return max(1, min($latest + 1, $now));
    }

    /**
     * Would inferred_from_evidence() have to date the reset AT the import time? That is the one case in which the import
     * time is the value, not just the upper clamp: the cycle's completion or latest evidence is at or after the import (a
     * completion dated in the future, or a learner active during the cutover), so the second after it does not exist yet.
     * The owner decision says the import time is never the value, so the importer reports such a row
     * (warning evidence_at_or_after_import) instead of leaving it to look like any other estimate (review of 2026-10-07).
     *
     * @param int|null $completed Completion time, null for a cycle that was never completed.
     * @param int|null $lastevidence Latest source evidence of the cycle; null when it has none.
     * @param int $now Import time.
     * @return bool
     */
    public static function dated_at_import(?int $completed, ?int $lastevidence, int $now): bool {
        return max($completed ?? 0, $lastevidence ?? 0, 0) + 1 >= $now;
    }

    /**
     * Is a SCORM element one that carries the learner's status?
     *
     * @param string $element
     * @return bool
     */
    public static function is_scorm_status(string $element): bool {
        return (bool) preg_match('/(^|\.)(lesson_status|completion_status|success_status)$/', $element);
    }

    /**
     * Is a SCORM element the raw score?
     *
     * @param string $element
     * @return bool
     */
    public static function is_scorm_score(string $element): bool {
        return (bool) preg_match('/(^|\.)score\.raw$/', $element);
    }

    /**
     * Is a SCORM element one that carries text the learner typed, or the name or id the package was given? Those are
     * personal data in the tracking row, unlike the status and the score.
     *
     * @param string $element
     * @return bool
     */
    public static function is_scorm_free_text(string $element): bool {
        return (bool) preg_match(
            '/(^|\.)(suspend_data|comments|comment|student_response|learner_response|student_name|learner_name)$/', $element);
    }

    /**
     * The archive payload: the whole source row as JSON, every value exactly as the database returned it.
     *
     * @param \stdClass|array $row A source row, or a name => value list.
     * @return string
     */
    public static function payload($row): string {
        $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \coding_exception('the archive payload of a row could not be encoded');
        }
        return $json;
    }

    /**
     * The key of a (user, course) pair in the evidence index.
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    public static function pair_key(int $userid, int $courseid): string {
        return $userid . ':' . $courseid;
    }
}
