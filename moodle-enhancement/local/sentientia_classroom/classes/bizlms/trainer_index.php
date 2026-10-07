<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;

defined('MOODLE_INTERNAL') || die();

/**
 * The trainers BizLMS attached to each classroom, read once per import run.
 *
 * The classroom step copies the first trainer of a classroom (the lowest local_classroom_trainers id)
 * into local_sentientia_classroom.trainerid, which is what the assigned-trainer rule of the attendance
 * pages reads. The step sees one classroom at a time, so the table is read in full, in keyset pages,
 * the first time a classroom asks: three columns of a table with a few rows per classroom.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class trainer_index {

    /** Rows per page. */
    private const PAGE = 10000;

    /** @var array<int, int[]>|null Classroom id => trainer user ids in the order of the legacy row ids. */
    private ?array $byclassroom = null;

    /**
     * The trainer user ids of a classroom, in the order BizLMS attached them.
     *
     * @param int $classroomid
     * @param context $ctx
     * @return int[]
     */
    public function for_classroom(int $classroomid, context $ctx): array {
        if ($this->byclassroom === null) {
            $this->byclassroom = $this->load($ctx);
        }
        return $this->byclassroom[$classroomid] ?? [];
    }

    /**
     * @param context $ctx
     * @return array<int, int[]>
     */
    private function load(context $ctx): array {
        $table = 'local_classroom_trainers';
        if (!$ctx->legacy->exists($table)) {
            return [];
        }
        $byclassroom = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page($table, $after, self::PAGE, ['id', 'classroomid', 'trainerid']);
            foreach ($page as $id => $row) {
                $after = (int) $id;
                $trainer = mapping::int($row, 'trainerid');
                if ($trainer > 0) {
                    $byclassroom[mapping::int($row, 'classroomid')][] = $trainer;
                }
            }
        } while (count($page) === self::PAGE);
        return $byclassroom;
    }
}
