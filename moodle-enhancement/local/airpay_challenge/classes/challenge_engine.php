<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Gamification challenge engine — single point of truth for the
 * challenge lifecycle (create → publish → user joins → progress
 * updates → completion → points awarded).
 *
 * Phase 1 supports challenge type "course_completion" only. Phase 2
 * widens this to streak-based and quiz-score-based by adding new
 * type-specific evaluation paths in {@see evaluate_attempt()}.
 *
 * Tenant-scoping rule: a challenge with costcenterid > 0 is visible
 * only to users whose `open_path` starts with `/<costcenterid>/`.
 * costcenterid = 0 means "global" — visible to all tenants.
 *
 * @package    local_airpay_challenge
 * @copyright  2026 Airpay Payment Services
 */
class challenge_engine {

    public const STATUS_DRAFT    = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_ARCHIVED = 2;

    public const TYPE_COURSE_COMPLETION = 'course_completion';
    public const TYPE_STREAK            = 'streak';
    public const TYPE_QUIZ_SCORE        = 'quiz_score';
    public const TYPE_CUSTOM            = 'custom';

    public const ATTEMPT_ENROLLED    = 'enrolled';
    public const ATTEMPT_IN_PROGRESS = 'in_progress';
    public const ATTEMPT_COMPLETED   = 'completed';
    public const ATTEMPT_FAILED      = 'failed';
    public const ATTEMPT_EXPIRED     = 'expired';

    /** @return list<int> */
    public static function valid_statuses(): array {
        return [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED];
    }

    /** @return list<string> */
    public static function valid_types(): array {
        return [self::TYPE_COURSE_COMPLETION, self::TYPE_STREAK,
                self::TYPE_QUIZ_SCORE, self::TYPE_CUSTOM];
    }

    /**
     * Derive the BizLMS top-level tenant ID from a path string.
     * '/1/2/3' → 1, '/77' → 77, '' → 0
     */
    public static function tenant_from_path(?string $path): int {
        if ($path === null || $path === '') return 0;
        $parts = explode('/', trim($path, '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    /**
     * Create a challenge. Returns its new ID.
     *
     * @throws \invalid_parameter_exception on schema/validation failures
     * @throws \moodle_exception on duplicate shortname
     */
    public static function create_challenge(array $data): int {
        global $DB, $USER;
        self::validate_definition($data);

        if (!empty($data['shortname']) &&
                $DB->record_exists('local_airpay_challenge_challenges',
                    ['shortname' => $data['shortname']])) {
            throw new \moodle_exception('err_shortname_taken', 'local_airpay_challenge', '', $data['shortname']);
        }

        $now = time();
        $row = (object) [
            'name'         => (string) $data['name'],
            'shortname'    => (string) ($data['shortname'] ?? ''),
            'description'  => (string) ($data['description'] ?? ''),
            'type'         => (string) ($data['type'] ?? self::TYPE_COURSE_COMPLETION),
            'targetcount'  => max(1, (int) ($data['targetcount'] ?? 1)),
            'courseids'    => json_encode(array_values(array_map('intval', $data['courseids'] ?? []))),
            'pointsreward' => max(0, (int) ($data['pointsreward'] ?? 100)),
            'badge'        => (string) ($data['badge'] ?? ''),
            'cohortid'     => !empty($data['cohortid']) ? (int) $data['cohortid'] : null,
            'status'       => (int) ($data['status'] ?? self::STATUS_DRAFT),
            'startdate'    => !empty($data['startdate']) ? (int) $data['startdate'] : null,
            'enddate'      => !empty($data['enddate']) ? (int) $data['enddate'] : null,
            'costcenterid' => (int) ($data['costcenterid']
                                ?? self::tenant_from_path($USER->open_path ?? '')),
            'open_path'    => (string) ($USER->open_path ?? ''),
            'createdby'    => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        return (int) $DB->insert_record('local_airpay_challenge_challenges', $row);
    }

    /**
     * Update an existing challenge. Subset of fields supported.
     */
    public static function update_challenge(int $id, array $data): void {
        global $DB;
        $existing = $DB->get_record('local_airpay_challenge_challenges',
            ['id' => $id], '*', MUST_EXIST);

        $merged = array_merge((array) $existing, $data);
        // courseids stays JSON-encoded if not in $data.
        if (isset($data['courseids'])) {
            $merged['courseids'] = json_encode(array_values(array_map('intval', $data['courseids'])));
        }
        self::validate_definition($merged);

        if (!empty($data['shortname']) && $data['shortname'] !== $existing->shortname
                && $DB->record_exists_select('local_airpay_challenge_challenges',
                    'shortname = :sn AND id != :id',
                    ['sn' => $data['shortname'], 'id' => $id])) {
            throw new \moodle_exception('err_shortname_taken', 'local_airpay_challenge', '', $data['shortname']);
        }

        $upd = new \stdClass();
        $upd->id = $id;
        foreach (['name', 'shortname', 'description', 'type', 'targetcount', 'courseids',
                  'pointsreward', 'badge', 'cohortid', 'status', 'startdate', 'enddate'] as $f) {
            if (array_key_exists($f, $merged)) {
                $upd->$f = $merged[$f];
            }
        }
        $upd->timemodified = time();
        $DB->update_record('local_airpay_challenge_challenges', $upd);
    }

    /**
     * Delete a challenge AND its attempts AND its leaderboard rows.
     * Wrapped in a transaction so partial-state is impossible.
     */
    public static function delete_challenge(int $id): void {
        global $DB;
        $existing = $DB->get_record('local_airpay_challenge_challenges',
            ['id' => $id], 'id', MUST_EXIST);

        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('local_airpay_challenge_attempts',     ['challengeid' => $id]);
            $DB->delete_records('local_airpay_challenge_leaderboard',  ['challengeid' => $id]);
            $DB->delete_records('local_airpay_challenge_challenges',   ['id' => $id]);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
    }

    /**
     * Self-service: caller joins a challenge.
     *
     * Creates an attempt row in 'enrolled' state. Snapshots the current
     * targetcount so that subsequent challenge edits don't retroactively
     * change what the user signed up for.
     *
     * @return int  attempt ID
     * @throws \moodle_exception if challenge not active or already joined
     */
    public static function join(int $challengeid, int $userid): int {
        global $DB;
        $challenge = $DB->get_record('local_airpay_challenge_challenges',
            ['id' => $challengeid], '*', MUST_EXIST);

        if ((int) $challenge->status !== self::STATUS_ACTIVE) {
            throw new \moodle_exception('err_challenge_not_active', 'local_airpay_challenge');
        }

        // Date window check.
        $now = time();
        if (!empty($challenge->startdate) && $now < (int) $challenge->startdate) {
            throw new \moodle_exception('err_challenge_not_active', 'local_airpay_challenge');
        }
        if (!empty($challenge->enddate) && $now > (int) $challenge->enddate) {
            throw new \moodle_exception('err_challenge_not_active', 'local_airpay_challenge');
        }

        // Cohort gate.
        if (!empty($challenge->cohortid)) {
            $member = $DB->record_exists('cohort_members',
                ['cohortid' => (int) $challenge->cohortid, 'userid' => $userid]);
            if (!$member) {
                throw new \moodle_exception('err_outside_cohort', 'local_airpay_challenge');
            }
        }

        // Idempotency: don't double-join.
        if ($existing = $DB->get_record('local_airpay_challenge_attempts',
                ['challengeid' => $challengeid, 'userid' => $userid])) {
            throw new \moodle_exception('err_already_joined', 'local_airpay_challenge');
        }

        // open_path is a BizLMS-added column on mdl_user; not present on
        // stock Moodle test DBs. Read defensively so PHPUnit + non-BizLMS
        // installs don't trip over the missing column.
        $userpath = '';
        if ($DB->get_manager()->field_exists('user',
                new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255'))) {
            $userpath = (string) ($DB->get_field('user', 'open_path', ['id' => $userid]) ?? '');
        }

        $row = (object) [
            'challengeid'   => $challengeid,
            'userid'        => $userid,
            'status'        => self::ATTEMPT_ENROLLED,
            'progress'      => 0,
            'targetcount'   => (int) $challenge->targetcount,
            'pointsawarded' => 0,
            'completiondate'=> null,
            'costcenterid'  => self::tenant_from_path($userpath),
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        $attemptid = (int) $DB->insert_record('local_airpay_challenge_attempts', $row);

        // Immediately re-evaluate so existing-completion learners can
        // win retroactively (e.g. challenge published after they finished).
        self::evaluate_attempt($attemptid);

        return $attemptid;
    }

    /**
     * Self-service: caller leaves a challenge. If the attempt was
     * completed, points stay awarded but the row is removed (which
     * means the leaderboard recompute will drop them too).
     *
     * @throws \moodle_exception if not joined
     */
    public static function leave(int $challengeid, int $userid): void {
        global $DB;
        if (!$DB->record_exists('local_airpay_challenge_attempts',
                ['challengeid' => $challengeid, 'userid' => $userid])) {
            throw new \moodle_exception('err_not_joined', 'local_airpay_challenge');
        }
        $DB->delete_records('local_airpay_challenge_attempts',
            ['challengeid' => $challengeid, 'userid' => $userid]);
    }

    /**
     * Recompute one attempt's progress + status.
     *
     * Reads the user's course completions, counts qualifying ones, and
     * updates the attempt row. If progress >= targetcount, transitions
     * to 'completed' and awards points.
     *
     * Idempotent — safe to call multiple times.
     */
    public static function evaluate_attempt(int $attemptid): void {
        global $DB;
        $attempt = $DB->get_record('local_airpay_challenge_attempts',
            ['id' => $attemptid], '*', MUST_EXIST);
        $challenge = $DB->get_record('local_airpay_challenge_challenges',
            ['id' => $attempt->challengeid]);
        if (!$challenge) {
            // Challenge deleted underneath us. Mark attempt expired.
            $DB->set_field('local_airpay_challenge_attempts', 'status',
                self::ATTEMPT_EXPIRED, ['id' => $attemptid]);
            return;
        }

        // Already terminal? No-op.
        if (in_array($attempt->status, [self::ATTEMPT_COMPLETED,
                self::ATTEMPT_FAILED, self::ATTEMPT_EXPIRED], true)) {
            return;
        }

        $progress = self::compute_progress($challenge, (int) $attempt->userid);

        // Apply.
        $upd = new \stdClass();
        $upd->id = $attemptid;
        $upd->progress = $progress;
        $upd->timemodified = time();

        if ($progress >= (int) $attempt->targetcount) {
            $upd->status         = self::ATTEMPT_COMPLETED;
            $upd->pointsawarded  = (int) $challenge->pointsreward;
            $upd->completiondate = time();
        } else if ($progress > 0) {
            $upd->status = self::ATTEMPT_IN_PROGRESS;
        }
        $DB->update_record('local_airpay_challenge_attempts', $upd);
    }

    /**
     * Phase 2 — expire any in-progress / enrolled attempts whose parent
     * challenge has an enddate that has passed. Intended to be called by
     * the recompute_leaderboard scheduled task once a day (or on demand).
     *
     * @return int  number of attempts marked expired
     */
    public static function expire_overdue_attempts(): int {
        global $DB;
        $now = time();
        $count = 0;

        $rows = $DB->get_records_sql("
            SELECT a.id, a.challengeid
              FROM {local_airpay_challenge_attempts} a
              JOIN {local_airpay_challenge_challenges} c ON c.id = a.challengeid
             WHERE c.enddate > 0 AND c.enddate < :now
               AND a.status IN (:s1, :s2)",
            ['now' => $now,
             's1' => self::ATTEMPT_ENROLLED,
             's2' => self::ATTEMPT_IN_PROGRESS]);

        foreach ($rows as $r) {
            $upd = (object) [
                'id' => (int) $r->id,
                'status' => self::ATTEMPT_EXPIRED,
                'timemodified' => $now,
            ];
            $DB->update_record('local_airpay_challenge_attempts', $upd);
            $count++;
        }
        return $count;
    }

    /**
     * Re-evaluate ALL active attempts for a given user.
     *
     * Called by the course_completed event observer.
     */
    public static function reevaluate_user(int $userid): void {
        global $DB;
        $ids = $DB->get_records_sql("
            SELECT a.id
              FROM {local_airpay_challenge_attempts} a
              JOIN {local_airpay_challenge_challenges} c ON c.id = a.challengeid
             WHERE a.userid = :uid
               AND a.status IN (:s1, :s2)
               AND c.type = :ctype
               AND c.status = :cstatus",
            ['uid' => $userid,
             's1' => self::ATTEMPT_ENROLLED, 's2' => self::ATTEMPT_IN_PROGRESS,
             'ctype' => self::TYPE_COURSE_COMPLETION,
             'cstatus' => self::STATUS_ACTIVE]);
        foreach ($ids as $row) {
            self::evaluate_attempt((int) $row->id);
        }
    }

    /**
     * Compute progress for a course-completion challenge.
     *
     * Returns the number of qualifying course completions for this user.
     * "Qualifying" = present in challenge.courseids if non-empty;
     * otherwise any completed course (course.id > 1, excluding site).
     */
    private static function compute_progress(\stdClass $challenge, int $userid): int {
        global $DB;

        // Phase 2 — branch on challenge type. Each branch reads a different
        // source table; all defensive against missing fields/tables.
        switch ($challenge->type) {
            case self::TYPE_COURSE_COMPLETION:
                return self::progress_course_completion($challenge, $userid);
            case self::TYPE_STREAK:
                return self::progress_streak($challenge, $userid);
            case self::TYPE_QUIZ_SCORE:
                return self::progress_quiz_score($challenge, $userid);
            default:
                return 0;
        }
    }

    /** Course-completion-based progress (Phase 1). */
    private static function progress_course_completion(\stdClass $challenge, int $userid): int {
        global $DB;
        $courseids = self::decode_courseids($challenge->courseids ?? '');

        if (empty($courseids)) {
            return (int) $DB->count_records_sql("
                SELECT COUNT(*)
                  FROM {course_completions}
                 WHERE userid = :uid
                   AND timecompleted IS NOT NULL
                   AND course > 1",
                ['uid' => $userid]);
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $params['uid'] = $userid;
        return (int) $DB->count_records_sql("
            SELECT COUNT(*)
              FROM {course_completions}
             WHERE userid = :uid
               AND timecompleted IS NOT NULL
               AND course $insql",
            $params);
    }

    /**
     * Streak-based progress (Phase 2).
     *
     * Counts the user's longest CURRENT consecutive-day login streak
     * ending today. Reads from {user_lastaccess}. Falls back to 0 when
     * the table is absent.
     *
     * Algorithm: walk back from today's date checking each day for any
     * lastaccess record. Stop at the first day with no record. The count
     * of consecutive days is the current streak.
     */
    private static function progress_streak(\stdClass $challenge, int $userid): int {
        global $DB;
        $manager = $DB->get_manager();
        if (!$manager->table_exists('user_lastaccess')) {
            return 0;
        }

        // Fetch all distinct day-buckets the user has lastaccess in
        // (over the last 365 days — bounded for performance).
        $since = strtotime('today', time()) - 365 * 86400;
        $rows = $DB->get_records_sql("
            SELECT DISTINCT FLOOR(timeaccess / 86400) AS day_bucket
              FROM {user_lastaccess}
             WHERE userid = :uid AND timeaccess >= :since
          ORDER BY day_bucket DESC",
            ['uid' => $userid, 'since' => $since]);

        if (empty($rows)) return 0;

        $today_bucket = (int) floor(time() / 86400);
        $expected = $today_bucket;
        $streak = 0;
        foreach ($rows as $r) {
            $bucket = (int) $r->day_bucket;
            if ($bucket === $expected) {
                $streak++;
                $expected--;
            } else if ($bucket === $expected + 1) {
                // already counted (today's bucket appears as today+1 if
                // we're in the "still counting today" window) — skip.
                continue;
            } else {
                break;
            }
        }
        return $streak;
    }

    /**
     * Quiz-score-based progress (Phase 2).
     *
     * Counts the number of finished quiz attempts at or above the
     * threshold percentage encoded in challenge.targetcount * 10.
     * targetcount field is reused: a value of 7 means "7+ attempts at
     * 70%+", value of 5 means "5+ at 50%+".
     *
     * Restricted to courses in challenge.courseids when set.
     */
    private static function progress_quiz_score(\stdClass $challenge, int $userid): int {
        global $DB;
        $manager = $DB->get_manager();
        if (!$manager->table_exists('quiz_attempts')) {
            return 0;
        }

        // Threshold derived from targetcount: 7 means 70%, etc. Cap 10..100.
        $threshold = max(10, min(100, (int) $challenge->targetcount * 10));

        $params = ['uid' => $userid, 'threshold' => $threshold];
        $extracourse = '';
        $courseids = self::decode_courseids($challenge->courseids ?? '');
        if (!empty($courseids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $extracourse = ' AND q.course ' . $insql;
            $params = array_merge($params, $inparams);
        }

        return (int) $DB->count_records_sql("
            SELECT COUNT(*) FROM {quiz_attempts} qa
              JOIN {quiz} q ON q.id = qa.quiz
             WHERE qa.userid = :uid
               AND qa.state = 'finished'
               AND q.sumgrades > 0
               AND (qa.sumgrades / q.sumgrades * 100) >= :threshold
               $extracourse",
            $params);
    }


    /** Decode the courseids JSON field. Returns int[]; empty on parse fail. */
    public static function decode_courseids(string $json): array {
        if ($json === '') return [];
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        return array_values(array_filter(array_map('intval', $decoded)));
    }

    /**
     * Validate a challenge definition. Throws on bad input.
     */
    public static function validate_definition(array $data): void {
        if (empty($data['name']) || trim((string) $data['name']) === '') {
            throw new \invalid_parameter_exception('Challenge name is required.');
        }
        if (isset($data['type']) && !in_array($data['type'], self::valid_types(), true)) {
            throw new \invalid_parameter_exception(
                get_string('err_invalid_type', 'local_airpay_challenge'));
        }
        if (isset($data['status']) && !in_array((int) $data['status'], self::valid_statuses(), true)) {
            throw new \invalid_parameter_exception(
                get_string('err_invalid_status', 'local_airpay_challenge'));
        }
        if (isset($data['targetcount']) && (int) $data['targetcount'] < 1) {
            throw new \invalid_parameter_exception(
                get_string('err_targetcount_min', 'local_airpay_challenge'));
        }
        if (isset($data['pointsreward']) && (int) $data['pointsreward'] < 0) {
            throw new \invalid_parameter_exception(
                get_string('err_pointsreward_min', 'local_airpay_challenge'));
        }
        if (!empty($data['startdate']) && !empty($data['enddate'])
                && (int) $data['startdate'] > (int) $data['enddate']) {
            throw new \invalid_parameter_exception('Start date must be before end date.');
        }
    }

    /**
     * Per-tenant list of challenges. Filters applied in PHP because the
     * row count is small (typically < 100 challenges per tenant).
     *
     * @param int    $tenant       0 = no scoping; >0 = only this tenant + global
     * @param string $statusfilter 'all'|'draft'|'active'|'archived'
     * @param string $search       substring on name + shortname
     * @param int    $userid       used to compute "my participation" markers
     */
    public static function list_challenges(int $tenant = 0, string $statusfilter = 'active',
                                            string $search = '', int $userid = 0,
                                            int $page = 0, int $perpage = 25): array {
        global $DB;

        $perpage = max(5, min(100, $perpage));
        $page    = max(0, $page);

        $where = ['1=1'];
        $params = [];

        // Tenant scope: callers see global (cc=0) + their own tenant.
        if ($tenant > 0) {
            $where[] = '(c.costcenterid = 0 OR c.costcenterid = :tn)';
            $params['tn'] = $tenant;
        }

        if ($statusfilter !== 'all') {
            $statusmap = [
                'draft'    => self::STATUS_DRAFT,
                'active'   => self::STATUS_ACTIVE,
                'archived' => self::STATUS_ARCHIVED,
            ];
            if (isset($statusmap[$statusfilter])) {
                $where[] = 'c.status = :st';
                $params['st'] = $statusmap[$statusfilter];
            }
        }

        if ($search !== '') {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('c.name', ':s1', false)
                . ' OR ' . $DB->sql_like('c.shortname', ':s2', false) . ')';
            $params['s1'] = $term;
            $params['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_challenge_challenges} c WHERE $wheresql",
            $params);

        $records = $DB->get_records_sql("
            SELECT c.*,
                   (SELECT COUNT(*) FROM {local_airpay_challenge_attempts} a
                     WHERE a.challengeid = c.id) AS participants,
                   (SELECT COUNT(*) FROM {local_airpay_challenge_attempts} a
                     WHERE a.challengeid = c.id AND a.status = '" . self::ATTEMPT_COMPLETED . "') AS completed
              FROM {local_airpay_challenge_challenges} c
             WHERE $wheresql
          ORDER BY c.status DESC, c.timecreated DESC, c.id DESC",
            $params, $page * $perpage, $perpage);

        // Annotate with "my" attempt status.
        $myattempts = [];
        if ($userid > 0 && !empty($records)) {
            $cids = array_column($records, 'id');
            [$insql, $inparams] = $DB->get_in_or_equal($cids, SQL_PARAMS_NAMED, 'mc');
            $inparams['uid'] = $userid;
            $myattempts = $DB->get_records_sql("
                SELECT a.challengeid, a.id, a.status, a.progress, a.targetcount, a.pointsawarded
                  FROM {local_airpay_challenge_attempts} a
                 WHERE a.challengeid $insql AND a.userid = :uid",
                $inparams);
            $myattempts = array_column((array) $myattempts, null, 'challengeid');
        }

        $rows = [];
        foreach ($records as $c) {
            $myattempt = $myattempts[$c->id] ?? null;
            $rows[] = self::format_challenge_row($c, $myattempt);
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'perpage' => $perpage];
    }

    /**
     * Marshall a DB row to the WS-shaped array.
     *
     * @param \stdClass $c         challenge row (with participants + completed denorm)
     * @param ?\stdClass $myattempt caller's attempt for "my" fields
     */
    public static function format_challenge_row(\stdClass $c, ?\stdClass $myattempt = null): array {
        $statuslabel = match ((int) $c->status) {
            self::STATUS_ACTIVE   => 'Active',
            self::STATUS_ARCHIVED => 'Archived',
            default               => 'Draft',
        };
        $statuscss = match ((int) $c->status) {
            self::STATUS_ACTIVE   => 'badge bg-success',
            self::STATUS_ARCHIVED => 'badge bg-secondary',
            default               => 'badge bg-warning text-dark',
        };
        return [
            'id'           => (int) $c->id,
            'name'         => format_string($c->name),
            'shortname'    => s($c->shortname),
            'description'  => format_text((string) ($c->description ?? ''), FORMAT_HTML),
            'type'         => $c->type,
            'targetcount'  => (int) $c->targetcount,
            'pointsreward' => (int) $c->pointsreward,
            'status'       => (int) $c->status,
            'statuslabel'  => $statuslabel,
            'statuscss'    => $statuscss,
            'startdate'    => $c->startdate ? (int) $c->startdate : 0,
            'enddate'      => $c->enddate   ? (int) $c->enddate   : 0,
            'participants' => (int) ($c->participants ?? 0),
            'completed'    => (int) ($c->completed ?? 0),
            'mystatus'     => $myattempt ? (string) $myattempt->status : '',
            'myprogress'   => $myattempt ? (int) $myattempt->progress  : 0,
            'mytarget'     => $myattempt ? (int) $myattempt->targetcount : (int) $c->targetcount,
            'mypoints'     => $myattempt ? (int) $myattempt->pointsawarded : 0,
            'joined'       => $myattempt !== null,
        ];
    }
}
