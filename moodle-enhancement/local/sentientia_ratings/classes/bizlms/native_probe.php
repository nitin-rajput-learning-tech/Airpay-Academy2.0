<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * Finds a row Sentientia already holds for a (user, item, area) key, before the import tries to insert one.
 *
 * The ratings and reactions tables have a UNIQUE key on (userid, itemid, ratearea). A learner who rated or
 * reacted on Sentientia before the import (a rehearsal on a live UAT copy, say) must not make the import fail
 * on that key and roll back its batch: the row Sentientia holds is newer than anything BizLMS kept, so it is
 * left untouched and the legacy row folds into it (reason native_row_kept).
 *
 * Reads go through the context's bounded legacy reader. A step must not run a query per row on a table of
 * unknown size, so the probe first asks once whether the target holds ANY row; on a clean cutover it holds
 * none, and no group ever pays for a lookup. When the target holds rows (a native one, or this run's own after
 * a resume) each group is looked up by the unique key.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_probe {

    /** @var bool|null Does the target hold any row? Null until the first lookup of a run. */
    private ?bool $hasrows = null;

    /** @var int The run the answer above belongs to. */
    private int $run = -1;

    /**
     * The id of the row the target holds for the key.
     *
     * @param context $ctx
     * @param string $table Target table with a (userid, itemid, ratearea) key.
     * @param int $userid
     * @param int $itemid Target item id.
     * @param string $area Target area.
     * @return int|null Null when there is none.
     */
    public function find(context $ctx, string $table, int $userid, int $itemid, string $area): ?int {
        if (!$ctx->legacy->exists($table)) {
            return null;
        }
        if ($this->hasrows === null || $this->run !== $ctx->runid) {
            $this->hasrows = $ctx->legacy->count($table) > 0;
            $this->run = $ctx->runid;
        }
        if (!$this->hasrows) {
            return null;
        }
        $rows = $ctx->legacy->page($table, 0, 1, ['id'], [
            'userid = :blmnu AND itemid = :blmni AND ratearea = :blmna',
            ['blmnu' => $userid, 'blmni' => $itemid, 'blmna' => $area],
        ]);
        $row = reset($rows);
        return $row === false ? null : (int) $row->id;
    }
}
