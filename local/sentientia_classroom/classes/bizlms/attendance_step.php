<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_classroom_attendance -> local_sentientia_classroom_attendance (MAP, grouped by session and learner).
 *
 * BizLMS status 1 (present) becomes 1 and 2 (absent) becomes 0. The value 0 is the placeholder BizLMS wrote
 * for a learner nobody marked; a missing row already reads as Absent in Sentientia, so a placeholder is
 * archived, not copied. The target is unique on (sessionid, userid): of the marked rows of a pair, present
 * beats absent, then the latest change wins, and the rest are merged.
 *
 * The BizLMS row names the classroom as well as the session; a row whose classroom is not the session's is
 * skipped and reported, because it cannot be shown under either.
 *
 * markedby is the user who last touched the row (usermodified), else the one who created it.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attendance_step extends step {

    /** @var array<int, int|null> Session id => the classroom BizLMS filed it under (null: no such session). */
    private array $sessionclassroom = [];

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.attendance';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_classroom_attendance';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom_attendance';
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['sessionid', 'userid'];
    }

    /**
     * The parent maps this step resolves, loaded once instead of one query per distinct parent.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_classroom_sessions', '']];
    }

    /**
     * @param \stdClass[] $rows One session and learner, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $legacysession = mapping::int($first, 'sessionid');
        $sessionid = $ctx->map->resolve('local_classroom_sessions', $legacysession);
        $userid = mapping::int($first, 'userid');

        $reason = null;
        if ($sessionid === null) {
            $reason = 'orphan_session';
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

        $sessionclassroom = $this->classroom_of_session($legacysession, $ctx);
        $out = [];
        $marked = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($sessionclassroom !== null && mapping::int($row, 'classroomid') !== $sessionclassroom) {
                $out[] = outcome::skip($id, 'classroom_mismatch');
                continue;
            }
            $status = mapping::attendance_status(mapping::missing($row, 'status') ? null : mapping::int($row, 'status'));
            if ($status === null) {
                $out[] = outcome::archive($id, 'unmarked_placeholder');
                continue;
            }
            $marked[] = [$row, $status];
        }
        if (!$marked) {
            return $out;
        }

        // Present beats absent; then the latest change; then the lowest id.
        usort($marked, static function (array $a, array $b): int {
            $ma = mapping::time_modified($a[0], mapping::time_created($a[0]));
            $mb = mapping::time_modified($b[0], mapping::time_created($b[0]));
            return [$b[1], $mb, (int) $a[0]->id] <=> [$a[1], $ma, (int) $b[0]->id];
        });
        [$winner, $status] = $marked[0];

        $created = mapping::time_created($winner);
        $markedby = mapping::actor($winner, 'usermodified') ?? mapping::actor($winner, 'usercreated');
        $out[] = outcome::insert((int) $winner->id, 'local_sentientia_classroom_attendance', (object) [
            'sessionid' => $sessionid,
            'userid' => $userid,
            'status' => $status,
            'markedby' => $markedby,
            'notes' => null,
            'timecreated' => $created,
            'timemodified' => max($created, mapping::time_modified($winner, $created)),
        ]);
        foreach (array_slice($marked, 1) as [$row]) {
            $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_natural_key');
        }
        return $out;
    }

    /**
     * The classroom a BizLMS session belongs to, read once per session.
     *
     * @param int $legacysession
     * @param context $ctx
     * @return int|null Null when the session row is gone (the map already refused it, so this is a guard).
     */
    private function classroom_of_session(int $legacysession, context $ctx): ?int {
        if (!array_key_exists($legacysession, $this->sessionclassroom)) {
            $rows = $ctx->legacy->fetch('local_classroom_sessions', [$legacysession], ['id', 'classroomid']);
            $this->sessionclassroom[$legacysession] = isset($rows[$legacysession])
                ? mapping::int($rows[$legacysession], 'classroomid') : null;
        }
        return $this->sessionclassroom[$legacysession];
    }
}
