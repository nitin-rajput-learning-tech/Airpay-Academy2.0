<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_classroom_waitlist -> local_sentientia_classroom_waitlist (MAP, grouped by classroom).
 *
 * One group is one classroom's whole queue, because the places that stay waiting are renumbered 1..N together
 * (by BizLMS sort order, then id), as waitlist_manager::renumber_positions() does.
 *
 * What a BizLMS row becomes (mapping::waitlist_status()):
 *  - enrolstatus 1, or a learner who is on the roster anyway: promoted;
 *  - waiting on a classroom that has ended: removed, unless the owner decided they stay (classroom.waitlist_closed);
 *  - waiting on any other classroom: waiting, unless the owner decided otherwise (classroom.waitlist_open). The
 *    value waiting_after_guard_else_removed means "waiting once waitlist_manager::auto_promote() refuses a
 *    classroom that is not active"; that guard ships in the same plugin version this importer requires, so the
 *    row stays waiting and nothing promotes it on its own.
 *
 * A row with no classroom, learner or sort order (BizLMS upgraded installs allowed NULL there) is skipped.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class waitlist_step extends step {

    /** Largest roster of one classroom read in one page. */
    private const ROSTER_PAGE = 10000;

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.waitlist';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_classroom_waitlist';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom_waitlist';
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['classroomid'];
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
     * @param \stdClass[] $rows One classroom's waiting-list rows, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $closedwaiting = $ctx->decision('classroom.waitlist_closed') === 'waiting';
        $openwaiting = $ctx->decision('classroom.waitlist_open') !== 'removed';

        $legacyclassroom = mapping::int(reset($rows), 'classroomid');
        $classroomid = $legacyclassroom > 0 ? $ctx->map->resolve('local_classroom', $legacyclassroom) : null;
        $legacystatus = -1;
        $roster = [];
        if ($classroomid !== null) {
            $legacystatus = $this->classroom_status($legacyclassroom, $ctx);
            $roster = $this->roster($legacyclassroom, $ctx);
        }

        // First pass: what each row becomes. Second pass: the positions of the ones that keep waiting.
        $plan = [];
        $out = [];
        $waiting = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $userid = mapping::int($row, 'userid');
            if ($legacyclassroom <= 0 || $userid <= 0 || mapping::missing($row, 'sortorder')) {
                $out[] = outcome::skip($id, 'incomplete_row');
                continue;
            }
            if ($classroomid === null) {
                $out[] = outcome::skip($id, 'orphan_classroom');
                continue;
            }
            if (!$ctx->lookups->user_exists($userid)) {
                $out[] = outcome::skip($id, 'orphan_user');
                continue;
            }
            [$status, $note] = mapping::waitlist_status(mapping::int($row, 'enrolstatus'),
                isset($roster[$userid]), $legacystatus, $closedwaiting, $openwaiting);
            $plan[$id] = [$row, $status, $note];
            if ($status === 'waiting') {
                $waiting[$id] = max(0, mapping::int($row, 'sortorder'));
            }
        }

        $positions = mapping::dense_positions($waiting);
        foreach ($plan as $id => [$row, $status, $note]) {
            $created = mapping::time_created($row);
            $modified = max($created, mapping::time_modified($row, $created));
            $position = $status === 'waiting'
                ? $positions[$id]
                : mapping::clamp(mapping::int($row, 'sortorder'), mapping::INT_MAX);
            $out[] = outcome::insert($id, 'local_sentientia_classroom_waitlist', (object) [
                'classroomid' => $classroomid,
                'userid' => mapping::int($row, 'userid'),
                'position' => $position,
                'status' => $status,
                'reason' => mapping::waitlist_reason($note),
                'promoted_at' => $status === 'promoted' ? $modified : null,
                'removed_at' => $status === 'removed' ? $modified : null,
                'timecreated' => $created,
                'timemodified' => $modified,
            ]);
        }
        return $out;
    }

    /**
     * The BizLMS status of a classroom.
     *
     * @param int $legacyclassroom
     * @param context $ctx
     * @return int -1 when the row is gone
     */
    private function classroom_status(int $legacyclassroom, context $ctx): int {
        $rows = $ctx->legacy->fetch('local_classroom', [$legacyclassroom], ['id', 'status']);
        return isset($rows[$legacyclassroom]) ? mapping::int($rows[$legacyclassroom], 'status', -1) : -1;
    }

    /**
     * The learners on a classroom's roster, as a set.
     *
     * @param int $legacyclassroom
     * @param context $ctx
     * @return array<int, bool> user id => true
     */
    private function roster(int $legacyclassroom, context $ctx): array {
        if (!$ctx->legacy->exists('local_classroom_users')) {
            return [];
        }
        $set = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_classroom_users', $after, self::ROSTER_PAGE, ['id', 'userid'],
                ['t.classroomid = :blmwlclassroom', ['blmwlclassroom' => $legacyclassroom]]);
            foreach ($page as $id => $row) {
                $after = (int) $id;
                $set[mapping::int($row, 'userid')] = true;
            }
        } while (count($page) === self::ROSTER_PAGE);
        return $set;
    }
}
