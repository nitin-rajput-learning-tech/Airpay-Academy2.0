<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_classroom_users -> local_sentientia_classroom_users, the roster (MAP, grouped by classroom and user).
 *
 * The target is unique on (classroomid, userid). Rows that repeat a pair collapse into the lowest id with the
 * best of each value: completed wins over pending, the earliest completion date among the completed rows, the
 * most hours. The others are merged and stay in the legacy table.
 *
 * Deleted users are imported (history is kept; the readers filter them). A learner from another tenant stays on
 * the roster, and a tenant admin does not see them (ADR-031); the import counts them in the report.
 *
 * Not copied: courseid, supervisorid, the three feedback ids, confirmation, attended_sessions, usermodified.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_step extends step {

    /** Rows per page when the classrooms are read. */
    private const PAGE = 10000;

    /** @var array<int, int>|null Classroom id => tenant root (0 when the classroom has no tenant). */
    private ?array $roots = null;

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.users';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_classroom_users';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom_users';
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['classroomid', 'userid'];
    }

    /**
     * The parent maps this step resolves, loaded once instead of one query per distinct parent.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_classroom', '']];
    }

    /**
     * @param \stdClass[] $rows One classroom and learner, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $winner = reset($rows);
        $legacyclassroom = mapping::int($winner, 'classroomid');
        $classroomid = $ctx->map->resolve('local_classroom', $legacyclassroom);
        $userid = mapping::int($winner, 'userid');

        $reason = null;
        if ($classroomid === null) {
            $reason = 'orphan_classroom';
        } else if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            $reason = 'orphan_user';
        }
        if ($reason !== null) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, $reason);
            }
            return $out;
        }

        $created = PHP_INT_MAX;
        $modified = 0;
        $status = 0;
        $hours = 0;
        $completedat = null;
        foreach ($rows as $row) {
            $rowcreated = mapping::time_created($row);
            $created = min($created, $rowcreated);
            $modified = max($modified, mapping::time_modified($row, $rowcreated));
            $rowstatus = mapping::int($row, 'completion_status') >= 1 ? 1 : 0;
            $status = max($status, $rowstatus);
            $hours = max($hours, mapping::int($row, 'hours'));
            if ($rowstatus === 1) {
                // BizLMS stamps the date on every recompute; only a completed row's date means anything.
                $date = mapping::date_or_null($row, 'completiondate');
                if ($date !== null && ($completedat === null || $date < $completedat)) {
                    $completedat = $date;
                }
            }
        }

        $o = outcome::insert((int) $winner->id, 'local_sentientia_classroom_users', (object) [
            'classroomid' => $classroomid,
            'userid' => $userid,
            'enrolledby' => mapping::actor($winner, 'usercreated'),
            'completion_status' => $status,
            'timecompleted' => $status === 1 ? $completedat : null,
            'hours' => mapping::clamp($hours, mapping::INT_MAX),
            'timecreated' => $created,
            'timemodified' => max($created, $modified),
        ]);
        if ($status === 1 && $completedat === null) {
            $o->warn('completed_without_date');
        }
        if ($this->is_outside_tenant($legacyclassroom, $userid, $ctx)) {
            $o->warn('learner_outside_tenant');
        }
        $out = [$o];
        foreach (array_slice($rows, 1) as $row) {
            $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_natural_key');
        }
        return $out;
    }

    /**
     * Is the learner in a tenant other than the classroom's? A classroom with no tenant has no outsiders.
     *
     * @param int $legacyclassroom
     * @param int $userid
     * @param context $ctx
     * @return bool
     */
    private function is_outside_tenant(int $legacyclassroom, int $userid, context $ctx): bool {
        if ($this->roots === null) {
            $this->roots = $this->load_roots($ctx);
        }
        $root = $this->roots[$legacyclassroom] ?? 0;
        return $root > 0 && $ctx->tenant->root_of_user($userid) !== $root;
    }

    /**
     * The tenant root of every classroom, by the same rule the classroom step applies to its path.
     *
     * @param context $ctx
     * @return array<int, int>
     */
    private function load_roots(context $ctx): array {
        $roots = [];
        if (!$ctx->legacy->exists('local_classroom')) {
            return $roots;
        }
        $pathless = (string) $ctx->decision('classroom.pathless');
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_classroom', $after, self::PAGE,
                ['id', 'open_path', 'costcenter', 'usercreated']);
            foreach ($page as $id => $row) {
                $after = (int) $id;
                [$path] = classroom_step::tenant_of($row, $ctx, $pathless);
                $segments = mapping::path_segments($path);
                $roots[(int) $id] = $segments ? $segments[0] : 0;
            }
        } while (count($page) === self::PAGE);
        return $roots;
    }
}
