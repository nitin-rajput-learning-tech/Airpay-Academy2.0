<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * The proof behind switching off a BizLMS enrol instance (owner decision CRS-01, 2026-10-07).
 *
 * Why it exists. require_login() grants course access through enrol_get_enrolment_end(), which filters only the instance
 * status and the enrolment status and never asks whether the enrol plugin exists (moodlelib.php:2575; enrollib.php:1281).
 * So an enabled enrol_classroom, enrol_program or enrol_learningplan instance keeps granting access after the BizLMS code is
 * gone, and a Sentientia unenrol or suspend of the converted manual enrolment would not take the access away (unenrol_user()
 * keeps the roles while another enrolment row exists). The converted instance has to be switched off. It is switched off
 * (status 1, never deleted) only when that cannot cost any learner the access they have today, and this class is how that is
 * known.
 *
 * What it proves, per learner-course pair that holds a row on the instance. Core's access window of the pair with only the
 * ENABLED MANUAL enrolments (what remains after the step) covers the window of the pair with the manual enrolments plus
 * that BizLMS row (what the learner has today). A window covers another when it does not start later and does not end
 * earlier; an end of 0 never comes. The comparison is exact from now onwards: every moment at or after "now" at which the
 * BizLMS row grants access must also be granted by the manual enrolments, so a start in the future and a gap between two
 * manual enrolments are both seen (a hull "earliest start to latest end" would not see the gap). Past history never
 * matters; it grants nothing now. Only active enrolments of users who are not deleted count on either side, as in
 * enrol_get_enrolment_end(). A row on a DISABLED instance grants nothing today, so it has nothing to keep.
 *
 * What it also checks. An instance is "settled" only when every row on it has an outcome in the legacy map that means "this
 * learner is carried over or has nothing to carry": imported or folded into a manual enrolment, or skipped because the
 * learner, the course or the instance is gone. A row skipped for a reason that needs the owner (an account that is deleted,
 * a suspended or shorter manual enrolment) is unsettled and keeps the whole instance enabled, so those learners keep the
 * access they have until L&D acts. Nothing here writes anything.
 *
 * The same function serves the step that decides (before anything is switched off), the recompute step that does it (a last
 * look inside the same transaction), verify() (after, with the switched-off instances treated as they were before) and the
 * read-only report CLI. Reads go through the context, in pages, never one query per row.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolments_access {

    /** Rows per page of the keyset reads. */
    private const PAGE = 5000;

    /** An end that never comes. */
    private const FOREVER = PHP_INT_MAX;

    /** Most row ids a verdict names (ids only, for a report line). */
    public const MAX_LISTED = 25;

    /** Map outcomes that settle a legacy enrolment: it is a manual enrolment now, or follows one. */
    public const SETTLED_OUTCOMES = ['imported', 'folded'];

    /**
     * Skip reasons of a legacy enrolment that has nothing to carry over: the learner, the course or the instance is gone.
     * The skips that need the owner (user_deleted, manual_enrolment_inactive, manual_enrolment_ends_sooner) are not here.
     */
    public const NO_ACCESS_REASONS = ['user_missing', 'course_missing', 'instance_missing'];

    /**
     * The BizLMS enrol instances that have at least one enrolment: the instances the proof is about.
     *
     * @param context $ctx
     * @return int[] Instance ids, ascending.
     */
    public static function candidate_instances(context $ctx): array {
        $ids = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('enrol', $after, self::PAGE, ['id'], enrolments_importer::instance_filter());
            foreach ($page as $id => $row) {
                $ids[] = (int) $id;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $ids;
    }

    /**
     * Judge BizLMS enrol instances.
     *
     * @param context $ctx
     * @param int[] $instanceids Instances to judge. An id that is not a BizLMS instance is ignored.
     * @param int $now The moment access is judged at.
     * @param int[] $enabledbefore Instances to treat as ENABLED before the step even when their status says otherwise: the
     *        ones the import itself switched off, which verify() judges afterwards.
     * @param bool $prove False reads only the map (how many rows are unsettled) and proves nothing: the dry run, which has no
     *        manual enrolments to look at because it writes none.
     * @return array<int, array{courseid: int, method: string, status: int, rows: int, unsettled: int, pairs: int,
     *         regressions: int, regressionrows: int[]}> Per instance: its rows, the rows that are not settled, the pairs
     *         with access to keep (rows that grant access today), the pairs whose access the manual enrolments do not
     *         cover, and the ids of up to MAX_LISTED of those legacy enrolments (ids only, no learner).
     */
    public static function verdicts(context $ctx, array $instanceids, int $now, array $enabledbefore = [],
                                    bool $prove = true): array {
        global $DB;

        $instanceids = array_values(array_unique(array_filter(array_map('intval', $instanceids), static fn(int $id): bool => $id > 0)));
        if (!$instanceids) {
            return [];
        }
        $enabledbefore = array_flip(array_map('intval', $enabledbefore));

        $verdicts = [];
        foreach ($ctx->legacy->fetch('enrol', $instanceids, ['courseid', 'enrol', 'status']) as $id => $instance) {
            if (!in_array((string) $instance->enrol, enrolments_importer::METHODS, true)) {
                continue;
            }
            $verdicts[(int) $id] = [
                'courseid' => (int) $instance->courseid,
                'method' => (string) $instance->enrol,
                'status' => (int) $instance->status,
                'rows' => 0,
                'unsettled' => 0,
                'pairs' => 0,
                'regressions' => 0,
                'regressionrows' => [],
            ];
        }
        if (!$verdicts) {
            return [];
        }

        // Every enrolment on those instances.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($verdicts), SQL_PARAMS_NAMED, 'blmai');
        $rows = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('user_enrolments', $after, self::PAGE,
                ['enrolid', 'userid', 'status', 'timestart', 'timeend'], ["t.enrolid {$insql}", $inparams]);
            foreach ($page as $id => $row) {
                $rows[(int) $id] = [(int) $row->enrolid, (int) $row->userid, (int) $row->status, (int) $row->timestart,
                    (int) $row->timeend];
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);

        // What the map says became of each one.
        $entries = $rows ? $ctx->map->entries(enrolments_importer::UNIT_ENROLMENTS, array_keys($rows)) : [];

        $manual = $prove && $rows ? self::manual_windows($ctx, $verdicts) : [];

        $union = [];
        foreach ($rows as $ueid => [$enrolid, $userid, $status, $start, $end]) {
            $verdicts[$enrolid]['rows']++;
            if (!self::settled($entries[$ueid] ?? null)) {
                $verdicts[$enrolid]['unsettled']++;
            }
            if (!$prove) {
                continue;
            }
            $enabled = $verdicts[$enrolid]['status'] === 0 || isset($enabledbefore[$enrolid]);
            if (!$enabled || $status !== 0 || !$ctx->lookups->user_active($userid)) {
                continue;
            }
            $window = self::window($start, $end, $now);
            if ($window === null) {
                continue;
            }
            $verdicts[$enrolid]['pairs']++;
            $courseid = $verdicts[$enrolid]['courseid'];
            $key = $courseid . ':' . $userid;
            $union[$key] ??= self::merge($manual[$courseid][$userid] ?? [], $now);
            if (!self::covers($union[$key], $window)) {
                $verdicts[$enrolid]['regressions']++;
                if (count($verdicts[$enrolid]['regressionrows']) < self::MAX_LISTED) {
                    $verdicts[$enrolid]['regressionrows'][] = (int) $ueid;
                }
            }
        }
        return $verdicts;
    }

    /**
     * Is a legacy enrolment settled: carried over, or with nothing to carry?
     *
     * @param array|null $entry Its entry in the legacy map, or null when it has none.
     * @return bool
     */
    public static function settled(?array $entry): bool {
        if ($entry === null) {
            return false;
        }
        $outcome = (string) ($entry['outcome'] ?? '');
        if (in_array($outcome, self::SETTLED_OUTCOMES, true)) {
            return ($entry['targetid'] ?? null) !== null;
        }
        return $outcome === 'skipped' && in_array((string) ($entry['reason'] ?? ''), self::NO_ACCESS_REASONS, true);
    }

    /**
     * The active enrolments on the enabled manual instances of the courses of these instances, as raw windows by course
     * and learner.
     *
     * @param context $ctx
     * @param array<int, array{courseid: int}> $verdicts
     * @return array<int, array<int, array<int, array{0: int, 1: int}>>> courseid => userid => [[start, end], ...]
     */
    private static function manual_windows(context $ctx, array $verdicts): array {
        global $DB;

        $courseids = array_values(array_unique(array_column($verdicts, 'courseid')));
        if (!$courseids) {
            return [];
        }
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'blmac');
        $instances = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('enrol', $after, self::PAGE, ['courseid'],
                ["t.enrol = :blmaman AND t.status = 0 AND t.courseid {$csql}",
                    $cparams + ['blmaman' => enrolments_importer::MANUAL]]);
            foreach ($page as $id => $row) {
                $instances[(int) $id] = (int) $row->courseid;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        if (!$instances) {
            return [];
        }

        [$msql, $mparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED, 'blmam');
        $manual = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('user_enrolments', $after, self::PAGE, ['enrolid', 'userid', 'timestart', 'timeend'],
                ["t.status = 0 AND t.enrolid {$msql}", $mparams]);
            foreach ($page as $id => $row) {
                $manual[$instances[(int) $row->enrolid]][(int) $row->userid][] = [(int) $row->timestart, (int) $row->timeend];
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $manual;
    }

    /**
     * The part of an enrolment's validity that matters from now on.
     *
     * An enrolment whose end is before its start is ignored by core (a debugging notice there), one that has ended grants
     * nothing now or later, and a start in the past is no earlier than now for this purpose.
     *
     * @param int $start user_enrolments.timestart
     * @param int $end user_enrolments.timeend, 0 = never
     * @param int $now
     * @return array{0: int, 1: int}|null [from, until) with PHP_INT_MAX for "never"; null when nothing is left.
     */
    public static function window(int $start, int $end, int $now): ?array {
        if ($end !== 0 && $end < $start) {
            return null;
        }
        if ($end !== 0 && $end <= $now) {
            return null;
        }
        return [max($start, $now), $end === 0 ? self::FOREVER : $end];
    }

    /**
     * The union of raw enrolment windows as sorted, disjoint windows (touching windows are one).
     *
     * @param array<int, array{0: int, 1: int}> $raw [start, end] pairs as stored.
     * @param int $now
     * @return array<int, array{0: int, 1: int}>
     */
    public static function merge(array $raw, int $now): array {
        $windows = [];
        foreach ($raw as [$start, $end]) {
            $window = self::window((int) $start, (int) $end, $now);
            if ($window !== null) {
                $windows[] = $window;
            }
        }
        usort($windows, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($windows as $window) {
            $last = count($merged) - 1;
            if ($last >= 0 && $window[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $window[1]);
            } else {
                $merged[] = $window;
            }
        }
        return $merged;
    }

    /**
     * Does one of the merged windows hold the whole of this window?
     *
     * @param array<int, array{0: int, 1: int}> $merged As returned by merge().
     * @param array{0: int, 1: int} $window
     * @return bool
     */
    public static function covers(array $merged, array $window): bool {
        foreach ($merged as $candidate) {
            if ($candidate[0] <= $window[0] && $candidate[1] >= $window[1]) {
                return true;
            }
        }
        return false;
    }
}
