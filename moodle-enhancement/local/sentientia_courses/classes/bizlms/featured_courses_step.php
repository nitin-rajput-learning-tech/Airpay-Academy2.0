<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_dashboardcourses -> local_sentientia_featured_courses (MAP).
 *
 * BizLMS stored the dashboard's featured courses as comma lists of course ids, one list per row, and the
 * form inserted a new row every time its id was not set (adddashboardcourse_form.php:132-136). The reader
 * ran FIND_IN_SET across EVERY row and ordered the courses by id, newest first (renderer.php:499-504). So
 * the featured set is the union of all the lists, de-duplicated, and the rows are only a way of storing it.
 * Sentientia keeps one row per (course, tenant list).
 *
 * How a source row turns into outcomes:
 *  - The ids are split, trimmed and de-duplicated. Anything that is not a positive integer is ignored and
 *    counted (warning invalid_course_id).
 *  - A course that does not exist is not featured (warning featured_course_missing).
 *  - A course an EARLIER row already lists is a duplicate (warning featured_course_duplicate): the earliest
 *    row that lists it owns it, which makes the result independent of batch size and of resume.
 *  - A course the row owns and that has a home becomes a featured row. The first one is the row's primary
 *    outcome (an insert, sub-key empty); each further one is an insert with the sub-key course:<id>. The
 *    framework needs exactly one primary outcome per source row and a primary needs a target row, so the
 *    first featured row of a list carries it.
 *  - A row that imports nothing is settled by its content: no usable id at all is archived
 *    (empty_course_list); courses that all belonged to earlier rows are merged into the earliest of those
 *    rows (duplicate_course); otherwise it is skipped (tenant_unresolved when a course had a tenant that could
 *    not be resolved, else course_missing).
 *
 * Tenant home (decision course_lookups.featured_scope = rehome, the ADR-031 follow-up rule that
 * db/upgradelib.php applies to native rows): a course whose open_path names a registered tenant is featured on
 * that tenant's list (costcenterid = the root); a course that is actively shared to another tenant stays
 * on the global list (costcenterid 0), because sharing makes it available to other tenants on purpose; a legacy
 * course with no open_path at all is global, because the catalogue lists it to every tenant. A course whose
 * open_path names a tenant that cannot be resolved is NOT imported: costcenterid 0 means every tenant, and the
 * decision tenant.unresolved.course_lookups says such a row is never global. With featured_scope = global every
 * course is costcenterid 0, as BizLMS had it. The rows are written with their home already set, so no
 * re-homing pass runs afterwards: that pass (local_sentientia_courses_rehome_global_featured()) rewrites rows the
 * import did not create, and the writer is the only code that writes.
 *
 * sort_order is the course's rank among every existing course of the union, by course id, newest first,
 * times ten (the step size of featured_manager::reorder(), the renderer's order). timecreated is the import
 * time: BizLMS stored no timestamp for these rows.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class featured_courses_step extends step {

    /** Rows read per page when the whole source is scanned. */
    private const PAGE = 5000;

    /** Largest sort_order the target column (INT 6) holds. */
    private const MAX_SORT_ORDER = 999999;

    /** @var array{first: array<int, int>, rank: array<int, int>}|null Earliest listing row per course, rank per course. */
    private ?array $claims = null;

    /** @var array<int, bool>|null Course ids that have an active share to another tenant. */
    private ?array $shared = null;

    /** @var int|null The import time, one value for the whole run. */
    private ?int $now = null;

    public function key(): string {
        return course_lookups_importer::FEATURE . '.featured';
    }

    public function sourcetable(): string {
        return 'local_dashboardcourses';
    }

    public function targettable(): string {
        return course_lookups_importer::FEATURED_TABLE;
    }

    public function columns(): array {
        return ['courseids'];
    }

    /**
     * Split a comma list of course ids.
     *
     * @param string|null $list
     * @return array{0: int[], 1: int} [distinct positive ids in order of first appearance, ignored tokens]
     */
    public static function parse_course_ids(?string $list): array {
        $ids = [];
        $invalid = 0;
        foreach (explode(',', (string) $list) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            if (!ctype_digit($token) || strlen($token) > 10 || (int) $token <= 0 || (int) $token > 2147483647) {
                $invalid++;
                continue;
            }
            $ids[(int) $token] = (int) $token;
        }
        return [array_values($ids), $invalid];
    }

    public function transform(array $rows, context $ctx): array {
        $claims = $this->claims($ctx);
        $global = $ctx->decision('course_lookups.featured_scope') === 'global';
        $out = [];
        foreach ($rows as $row) {
            $out = array_merge($out, $this->settle_row((int) $row->id, (string) ($row->courseids ?? ''), $claims, $global, $ctx));
        }
        return $out;
    }

    /**
     * The outcomes of one source row.
     *
     * @param int $rowid
     * @param string $list
     * @param array{first: array<int, int>, rank: array<int, int>} $claims
     * @param bool $global featured_scope = global
     * @param context $ctx
     * @return outcome[]
     */
    private function settle_row(int $rowid, string $list, array $claims, bool $global, context $ctx): array {
        [$ids, $invalid] = self::parse_course_ids($list);
        $warnings = array_fill(0, $invalid, 'invalid_course_id');
        if (!$ids) {
            return [$this->with_warnings(outcome::archive($rowid, 'empty_course_list'), $warnings)];
        }

        $new = [];
        $claimant = 0;
        $missing = 0;
        $unresolved = 0;
        foreach ($ids as $courseid) {
            if (!$ctx->lookups->course_exists($courseid)) {
                $missing++;
                $warnings[] = 'featured_course_missing';
                continue;
            }
            $home = $global ? [0, 'fallback:global_scope'] : $this->home($courseid, $ctx);
            if ($home[0] === null) {
                $unresolved++;
                $warnings[] = 'featured_tenant_unresolved';
                continue;
            }
            $owner = $claims['first'][$courseid] ?? $rowid;
            if ($owner < $rowid) {
                $claimant = $claimant === 0 ? $owner : min($claimant, $owner);
                $warnings[] = 'featured_course_duplicate';
                continue;
            }
            $new[] = [$courseid, (int) $home[0], (string) $home[1]];
        }

        if (!$new) {
            if ($claimant > 0) {
                return [$this->with_warnings(outcome::merge($rowid, $claimant, 'duplicate_course'), $warnings)];
            }
            $reason = $unresolved > 0 ? 'tenant_unresolved' : 'course_missing';
            return [$this->with_warnings(outcome::skip($rowid, $reason), $warnings)];
        }

        $out = [];
        foreach ($new as $i => [$courseid, $costcenterid, $method]) {
            $fields = (object) [
                'courseid' => $courseid,
                'costcenterid' => $costcenterid,
                'sort_order' => min(self::MAX_SORT_ORDER, (int) ($claims['rank'][$courseid] ?? 10)),
                'label' => null,
                'timecreated' => $this->now(),
            ];
            if ($i === 0) {
                $o = outcome::insert($rowid, course_lookups_importer::FEATURED_TABLE, $fields)->tenant_method($method);
                $out[] = $this->with_warnings($o, $warnings);
            } else {
                $out[] = outcome::insert($rowid, course_lookups_importer::FEATURED_TABLE, $fields, 'course:' . $courseid);
            }
        }
        return $out;
    }

    /**
     * The featured list a course belongs on.
     *
     * @param int $courseid An existing course.
     * @param context $ctx
     * @return array{0: ?int, 1: string} [costcenterid, tenant method]; costcenterid null when the course names a
     *         tenant that cannot be resolved.
     */
    private function home(int $courseid, context $ctx): array {
        $course = $ctx->legacy->fetch('course', [$courseid], ['open_path'])[$courseid] ?? null;
        $path = trim((string) ($course->open_path ?? ''));
        if ($path === '') {
            return [0, 'fallback:no_open_path'];
        }
        [, $root, $method] = $ctx->tenant->resolve(['course' => $path]);
        if ($root === null) {
            return [null, 'unresolved'];
        }
        if (isset($this->shared($ctx)[$courseid])) {
            return [0, 'fallback:shared_course'];
        }
        return [(int) $root, $method];
    }

    /**
     * Every course that is actively shared to a tenant, as a set. The share table is small and static during a run.
     *
     * @param context $ctx
     * @return array<int, bool>
     */
    private function shared(context $ctx): array {
        if ($this->shared !== null) {
            return $this->shared;
        }
        $this->shared = [];
        $table = 'local_sentientia_courses_tenant_share';
        if (!$ctx->legacy->exists($table)) {
            return $this->shared;
        }
        $after = 0;
        do {
            $page = $ctx->legacy->page($table, $after, self::PAGE, ['courseid'], ['t.status = :blmshare', ['blmshare' => 'active']]);
            foreach ($page as $id => $share) {
                $this->shared[(int) $share->courseid] = true;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $this->shared;
    }

    /**
     * Who listed each course first, and where each existing course ranks. Built once from the whole source.
     *
     * @param context $ctx
     * @return array{first: array<int, int>, rank: array<int, int>}
     */
    private function claims(context $ctx): array {
        if ($this->claims !== null) {
            return $this->claims;
        }
        $first = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page($this->sourcetable(), $after, self::PAGE, ['courseids']);
            foreach ($page as $id => $row) {
                foreach (self::parse_course_ids($row->courseids ?? '')[0] as $courseid) {
                    if (!isset($first[$courseid])) {
                        $first[$courseid] = (int) $id;
                    }
                }
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);

        $existing = [];
        foreach (array_keys($first) as $courseid) {
            if ($ctx->lookups->course_exists($courseid)) {
                $existing[] = $courseid;
            }
        }
        rsort($existing);
        $rank = [];
        foreach ($existing as $position => $courseid) {
            $rank[$courseid] = ($position + 1) * 10;
        }
        $this->claims = ['first' => $first, 'rank' => $rank];
        return $this->claims;
    }

    /**
     * @return int The import time, the same for every row of the run.
     */
    private function now(): int {
        if ($this->now === null) {
            $this->now = time();
        }
        return $this->now;
    }

    /**
     * @param outcome $o
     * @param string[] $warnings
     * @return outcome
     */
    private function with_warnings(outcome $o, array $warnings): outcome {
        foreach ($warnings as $code) {
            $o->warn($code);
        }
        return $o;
    }
}
