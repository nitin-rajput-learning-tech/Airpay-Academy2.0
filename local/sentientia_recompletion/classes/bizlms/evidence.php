<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * What the recompletion steps need to know about rows other than the one they are transforming.
 *
 * A history row needs the archived completion its reset ended; an archived completion needs to know whether a
 * log row of its reset exists; a rule needs the last reset of its course. All of that is a function of the
 * legacy tables and the log, which do not change during a run, so it is read once per run (one context is one
 * run of one feature), held as small integer arrays, and answered to the steps without a query per row.
 *
 * Reads go through the context's legacy reader in keyset pages of ids. The lookups of small core tables (quiz,
 * course modules, SCORM, LTI tools, course names) are read whole the first time they are asked for.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidence {

    /** Rows per page of the keyset reads. */
    private const PAGE = 5000;

    /** The memo of pairings is dropped when it grows past this many pairs. */
    private const MEMO_LIMIT = 50000;

    /** @var \WeakMap<context, self>|null One evidence per context: one context is one run of one feature. */
    private static ?\WeakMap $instances = null;

    /** @var context */
    private context $ctx;

    /** @var array<string, array<int, array{id: int, time: int, origin: string, actor: int}>>|null */
    private ?array $resets = null;

    /** @var array<int, int> Course id => time of its latest reset event. */
    private array $lastreset = [];

    /** @var array<string, array<int, array{id: int, completed: int, started: int, enrolled: int}>>|null */
    private ?array $completions = null;

    /** @var array<string, int[]>|null Quiz attempt times by pair. */
    private ?array $attempts = null;

    /** @var array<int, array<string, string>>|null Course id => config name => value. */
    private ?array $config = null;

    /** @var array<string, int[]>|null Times of core course_completed events by pair, ascending. */
    private ?array $completed = null;

    /** @var array<int, array{id: int, userid: int, course: int, submitted: int, questionnaireid: int}>|null */
    private ?array $responses = null;

    /** @var array<string, array<int, int|string>> Small lookups by name. */
    private array $lookups = [];

    /** @var array<string, array> Memo of pairing results. */
    private array $pairs = [];

    /** @var array<string, array<int, array{id: int, time: int, inferred: bool}>> Memo of the imported resets of a pair. */
    private array $cycles = [];

    /**
     * The evidence of a run.
     *
     * @param context $ctx
     * @return self
     */
    public static function of(context $ctx): self {
        if (self::$instances === null) {
            self::$instances = new \WeakMap();
        }
        if (!isset(self::$instances[$ctx])) {
            self::$instances[$ctx] = new self($ctx);
        }
        return self::$instances[$ctx];
    }

    /**
     * @param context $ctx
     */
    private function __construct(context $ctx) {
        $this->ctx = $ctx;
    }

    // Reset events.

    /**
     * Every legacy reset event of the log, by (learner, course) pair, each list ordered by time.
     *
     * @return array<string, array<int, array{id: int, time: int, origin: string, actor: int}>>
     */
    public function resets(): array {
        if ($this->resets === null) {
            $this->resets = [];
            $columns = ['relateduserid', 'courseid', 'userid', 'timecreated', 'origin'];
            foreach ($this->rows(sources::LOG, $columns, sources::reset_filter()) as $row) {
                $time = (int) $row->timecreated;
                $course = (int) $row->courseid;
                if ($course > 0 && $time > ($this->lastreset[$course] ?? 0)) {
                    $this->lastreset[$course] = $time;
                }
                $user = (int) $row->relateduserid;
                if ($user <= 0 || $course <= 0) {
                    continue;
                }
                $this->resets[mapper::pair_key($user, $course)][] = [
                    'id' => (int) $row->id, 'time' => $time, 'origin' => (string) $row->origin,
                    'actor' => (int) $row->userid,
                ];
            }
            foreach ($this->resets as $key => $list) {
                usort($list, static fn(array $a, array $b): int => [$a['time'], $a['id']] <=> [$b['time'], $b['id']]);
                $this->resets[$key] = $list;
            }
        }
        return $this->resets;
    }

    /**
     * Time of the latest legacy reset event of a course, or null when the log holds none.
     *
     * @param int $courseid
     * @return int|null
     */
    public function last_reset_of_course(int $courseid): ?int {
        $this->resets();
        return $this->lastreset[$courseid] ?? null;
    }

    /**
     * One reset event of a pair.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $eventid
     * @return array{id: int, time: int, origin: string, actor: int}|null
     */
    public function reset_row(int $userid, int $courseid, int $eventid): ?array {
        foreach ($this->resets()[mapper::pair_key($userid, $courseid)] ?? [] as $reset) {
            if ($reset['id'] === $eventid) {
                return $reset;
            }
        }
        return null;
    }

    /**
     * Time of the latest reset of a pair strictly before a time; 0 when there is none.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $before
     * @return int
     */
    public function reset_before(int $userid, int $courseid, int $before): int {
        $latest = 0;
        foreach ($this->resets()[mapper::pair_key($userid, $courseid)] ?? [] as $reset) {
            if ($reset['time'] < $before && $reset['time'] > $latest) {
                $latest = $reset['time'];
            }
        }
        return $latest;
    }

    // Archived completions.

    /**
     * Every archived course completion, by pair.
     *
     * @return array<string, array<int, array{id: int, completed: int, started: int, enrolled: int}>>
     */
    public function completions(): array {
        if ($this->completions === null) {
            $this->completions = [];
            $columns = ['userid', 'course', 'timeenrolled', 'timestarted', 'timecompleted'];
            foreach ($this->rows(sources::CC, $columns) as $row) {
                $user = (int) $row->userid;
                $course = (int) $row->course;
                $this->completions[mapper::pair_key($user, $course)][] = [
                    'id' => (int) $row->id,
                    'completed' => (int) mapper::timestamp($row->timecompleted),
                    'started' => (int) mapper::timestamp($row->timestarted),
                    'enrolled' => (int) mapper::timestamp($row->timeenrolled),
                ];
            }
        }
        return $this->completions;
    }

    /**
     * One archived completion of a pair.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $ccid
     * @return array{id: int, completed: int, started: int, enrolled: int}|null
     */
    public function completion_row(int $userid, int $courseid, int $ccid): ?array {
        foreach ($this->completions()[mapper::pair_key($userid, $courseid)] ?? [] as $completion) {
            if ($completion['id'] === $ccid) {
                return $completion;
            }
        }
        return null;
    }

    /**
     * Which reset ended which archived completion of a pair.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{cc: array<int, int|null>, event: array<int, int|null>}
     */
    public function pairing(int $userid, int $courseid): array {
        $key = mapper::pair_key($userid, $courseid);
        if (!isset($this->pairs[$key])) {
            if (count($this->pairs) >= self::MEMO_LIMIT) {
                $this->pairs = [];
            }
            $this->pairs[$key] = pairing::pair($this->completions()[$key] ?? [], $this->resets()[$key] ?? []);
        }
        return $this->pairs[$key];
    }

    /**
     * The earliest time, after a cycle's completion, at which the learner did something in the course that
     * belongs to a later cycle: the start, enrolment or completion of another archived completion, or an
     * archived activity completion, quiz attempt, quiz grade or SCORM track dated after it.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $ccid The archived completion whose next evidence is wanted.
     * @param int $after The time the cycle ran to: its completion, or its start for a cycle never completed.
     * @return int|null
     */
    public function next_evidence(int $userid, int $courseid, int $ccid, int $after): ?int {
        global $DB;
        $best = null;
        foreach ($this->completions()[mapper::pair_key($userid, $courseid)] ?? [] as $other) {
            if ($other['id'] === $ccid) {
                continue;
            }
            foreach ([$other['started'], $other['enrolled'], $other['completed']] as $time) {
                if ($time > $after && ($best === null || $time < $best)) {
                    $best = $time;
                }
            }
        }
        $queries = [
            [sources::CMC, 'timemodified'], [sources::QA, 'timestart'], [sources::QG, 'timemodified'],
            [sources::SST, 'timemodified'],
        ];
        foreach ($queries as [$table, $column]) {
            $time = $DB->get_field_sql(
                'SELECT MIN(' . $column . ') FROM {' . $table . '} WHERE userid = :u AND course = :c AND ' . $column . ' > :t',
                ['u' => $userid, 'c' => $courseid, 't' => $after]);
            $time = mapper::timestamp($time);
            if ($time !== null && ($best === null || $time < $best)) {
                $best = $time;
            }
        }
        return $best;
    }

    /**
     * The imported reset that ended the cycle an archived row belongs to: the earliest history row of the pair
     * the import wrote (source legacy) at or after the row's time (see ends_cycle_of). It reads the history
     * table, so it sees the rows the events and completion steps have written by the time an archive step asks;
     * a dry run writes none and gets null. The archive_cycles recompute step applies the same rule after the
     * load and is the authority; this only saves it a write per row.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $time The row's own time.
     * @return array{id: int, time: int, inferred: bool}|null
     */
    public function cycle(int $userid, int $courseid, int $time): ?array {
        global $DB;
        if ($this->ctx->dryrun || $userid <= 0) {
            return null;
        }
        $key = mapper::pair_key($userid, $courseid);
        if (!isset($this->cycles[$key])) {
            if (count($this->cycles) >= self::MEMO_LIMIT) {
                $this->cycles = [];
            }
            $this->cycles[$key] = [];
            $rows = $DB->get_records_select(sources::HISTORY,
                'userid = :blmu AND courseid = :blmc AND source = :blms AND dryrun = 0',
                ['blmu' => $userid, 'blmc' => $courseid, 'blms' => sources::LEGACY], 'timecreated ASC, id ASC',
                'id, timecreated, time_inferred');
            foreach ($rows as $row) {
                $this->cycles[$key][] = ['id' => (int) $row->id, 'time' => (int) $row->timecreated,
                    'inferred' => (int) $row->time_inferred === 1];
            }
        }
        foreach ($this->cycles[$key] as $reset) {
            if (self::ends_cycle_of($reset, $time)) {
                return $reset;
            }
        }
        return null;
    }

    /**
     * Does this imported reset end the cycle a row of the given time belongs to?
     *
     * A reset at or after the row's time does, with one exception. The time of an INFERRED reset is capped at the
     * first evidence of the next cycle, so a row that has exactly that time IS that evidence and belongs to the
     * next cycle: against an inferred reset the row must be strictly earlier.
     *
     * @param array{id: int, time: int, inferred: bool} $reset
     * @param int $time The row's own time.
     * @return bool
     */
    public static function ends_cycle_of(array $reset, int $time): bool {
        return $reset['time'] > $time || ($reset['time'] === $time && !$reset['inferred']);
    }

    // Quiz attempts.

    /**
     * Did the learner attempt a quiz of the course in the window (after, upto]? Teacher previews do not count.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $after
     * @param int $upto
     * @return bool
     */
    public function attempted_between(int $userid, int $courseid, int $after, int $upto): bool {
        if ($this->attempts === null) {
            $this->attempts = [];
            $columns = ['userid', 'course', 'quiz', 'preview', 'timestart', 'timefinish', 'timemodified'];
            foreach ($this->rows(sources::QA, $columns) as $row) {
                if ((int) ($row->preview ?? 0) === 1) {
                    continue;
                }
                $time = mapper::first_timestamp([$row->timefinish, $row->timemodified, $row->timestart]);
                $course = (int) $row->course > 0 ? (int) $row->course : $this->quiz_course((int) $row->quiz);
                if ($time === null || $course <= 0) {
                    continue;
                }
                $this->attempts[mapper::pair_key((int) $row->userid, $course)][] = $time;
            }
        }
        foreach ($this->attempts[mapper::pair_key($userid, $courseid)] ?? [] as $time) {
            if ($time > $after && $time <= $upto) {
                return true;
            }
        }
        return false;
    }

    // Course completion events of the core log.

    /**
     * The latest time, at or before a reset, the log recorded the learner completing the course; null when it
     * recorded none.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $upto
     * @return int|null
     */
    public function completed_before(int $userid, int $courseid, int $upto): ?int {
        if ($this->completed === null) {
            $this->completed = [];
            foreach ($this->rows(sources::LOG, ['relateduserid', 'courseid', 'timecreated'], sources::completed_filter()) as $row) {
                $user = (int) $row->relateduserid;
                $course = (int) $row->courseid;
                if ($user > 0 && $course > 0) {
                    $this->completed[mapper::pair_key($user, $course)][] = (int) $row->timecreated;
                }
            }
            foreach ($this->completed as $key => $times) {
                sort($times);
                $this->completed[$key] = $times;
            }
        }
        $found = null;
        foreach ($this->completed[mapper::pair_key($userid, $courseid)] ?? [] as $time) {
            if ($time <= $upto) {
                $found = $time;
            }
        }
        return $found;
    }

    // Rule configuration.

    /**
     * The legacy configuration of a course: name => value.
     *
     * @param int $courseid
     * @return array<string, string>
     */
    public function config(int $courseid): array {
        if ($this->config === null) {
            $this->config = [];
            foreach ($this->rows(sources::CONFIG, ['course', 'name', 'value']) as $row) {
                $this->config[(int) $row->course][(string) $row->name] = (string) $row->value;
            }
        }
        return $this->config[$courseid] ?? [];
    }

    /**
     * How long a completion of the course was valid under the legacy plugin, in seconds: the course's own
     * duration, else the site default, else a year.
     *
     * @param int $courseid
     * @return array{0: int, 1: bool} [seconds, true when the course had no usable duration of its own]
     */
    public function duration(int $courseid): array {
        $own = $this->config($courseid)['recompletionduration'] ?? null;
        if (mapper::period_days($own) !== null) {
            return [(int) (float) trim((string) $own), false];
        }
        return [self::site_duration(), true];
    }

    /**
     * The legacy plugin's site default duration in seconds (config_plugins local_recompletion/duration).
     *
     * @return int
     */
    public static function site_duration(): int {
        $site = get_config('local_recompletion', 'duration');
        if (mapper::period_days($site) !== null) {
            return (int) (float) trim((string) $site);
        }
        return mapper::DEFAULT_PERIOD_DAYS * mapper::DAY;
    }

    // Questionnaire responses.

    /**
     * The archived questionnaire responses by the id the child rows keep (originalresponseid). When two rows
     * carry the same original id the lowest id is the parent.
     *
     * @return array<int, array{id: int, userid: int, course: int, submitted: int, questionnaireid: int}>
     */
    public function responses(): array {
        if ($this->responses === null) {
            $this->responses = [];
            $columns = ['originalresponseid', 'questionnaireid', 'submitted', 'userid', 'course'];
            foreach ($this->rows(sources::QR, $columns) as $row) {
                $original = (int) $row->originalresponseid;
                if (!isset($this->responses[$original])) {
                    $this->responses[$original] = [
                        'id' => (int) $row->id, 'userid' => (int) $row->userid, 'course' => (int) $row->course,
                        'submitted' => (int) $row->submitted, 'questionnaireid' => (int) $row->questionnaireid,
                    ];
                }
            }
        }
        return $this->responses;
    }

    // Small lookups of core tables.

    /**
     * @param int $quizid
     * @return int Course of the quiz; 0 when the quiz is gone.
     */
    public function quiz_course(int $quizid): int {
        return (int) ($this->lookup('quiz_course', 'SELECT id AS k, course AS v FROM {quiz}')[$quizid] ?? 0);
    }

    /**
     * @param int $quizid
     * @return int|null Course module of the quiz; null when it has none.
     */
    public function quiz_cmid(int $quizid): ?int {
        $cmid = $this->lookup('quiz_cm', 'SELECT cm.instance AS k, MIN(cm.id) AS v
                                             FROM {course_modules} cm
                                             JOIN {modules} m ON m.id = cm.module
                                            WHERE m.name = :blmmodule
                                         GROUP BY cm.instance', ['blmmodule' => 'quiz'])[$quizid] ?? null;
        return $cmid === null ? null : (int) $cmid;
    }

    /**
     * @param int $cmid
     * @return int Course of the course module; 0 when it is gone.
     */
    public function module_course(int $cmid): int {
        return (int) ($this->lookup('cm_course', 'SELECT id AS k, course AS v FROM {course_modules}')[$cmid] ?? 0);
    }

    /**
     * @param int $scormid
     * @return int Course of the SCORM activity; 0 when it is gone or the activity type is not installed.
     */
    public function scorm_course(int $scormid): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('scorm')) {
            return 0;
        }
        return (int) ($this->lookup('scorm_course', 'SELECT id AS k, course AS v FROM {scorm}')[$scormid] ?? 0);
    }

    /**
     * @param int $toolid Enrolment LTI tool id.
     * @return int Course of the tool (from its context); 0 when it cannot be told.
     */
    public function lti_course(int $toolid): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('enrol_lti_tools')) {
            return 0;
        }
        return (int) ($this->lookup('lti_course', 'SELECT t.id AS k, ctx.instanceid AS v
                                                     FROM {enrol_lti_tools} t
                                                     JOIN {context} ctx ON ctx.id = t.contextid
                                                                       AND ctx.contextlevel = :blmlevel',
            ['blmlevel' => CONTEXT_COURSE])[$toolid] ?? 0);
    }

    /**
     * @param int $courseid
     * @return string|null Short name of the course; null when it is gone.
     */
    public function course_shortname(int $courseid): ?string {
        $name = $this->lookup('course_shortname', 'SELECT id AS k, shortname AS v FROM {course}')[$courseid] ?? null;
        return $name === null ? null : (string) $name;
    }

    // Plumbing.

    /**
     * A small two-column lookup, read whole once.
     *
     * @param string $name
     * @param string $sql Selects k (unique) and v.
     * @param array $params
     * @return array<int, int|string>
     */
    private function lookup(string $name, string $sql, array $params = []): array {
        global $DB;
        if (!isset($this->lookups[$name])) {
            $this->lookups[$name] = [];
            foreach ($DB->get_records_sql($sql, $params) as $row) {
                $this->lookups[$name][(int) $row->k] = $row->v;
            }
        }
        return $this->lookups[$name];
    }

    /**
     * Every row of a table, in keyset pages of ids.
     *
     * @param string $table
     * @param string[] $columns
     * @param array{0: string, 1: array} $filter
     * @return \Generator<int, \stdClass>
     */
    private function rows(string $table, array $columns, array $filter = ['', []]): \Generator {
        $after = 0;
        do {
            $page = $this->ctx->legacy->page($table, $after, self::PAGE, $columns, $filter);
            foreach ($page as $id => $row) {
                $after = $id;
                yield $row;
            }
        } while (count($page) === self::PAGE);
    }
}
