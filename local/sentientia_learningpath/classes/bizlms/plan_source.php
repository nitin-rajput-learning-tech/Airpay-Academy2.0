<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * Bounded reads of one plan's child rows in the legacy tables, for the steps that need a fact about the
 * whole plan (its tenant, its courses, its first created time).
 *
 * Every read goes through the context's legacy reader, which pages by keyset, so a plan with many learner
 * rows is never loaded in one query. A step calls these only for a plan it is handling, and only when it
 * needs the answer.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_source {

    /** Rows per page. */
    private const PAGE = 1000;

    /**
     * The tenant root shared by the plan's enrolled users (mapping doc, section 17, tenant rule 2).
     *
     * Users with no resolvable tenant do not count against the others. NULL when no user has a tenant or
     * when two different roots are present.
     *
     * @param context $ctx
     * @param int $planid Legacy plan id.
     * @return int|null
     */
    public static function enrolled_root(context $ctx, int $planid): ?int {
        $roots = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(importer::SRC_USER, $after, self::PAGE, ['userid'],
                ['t.planid = :lpplan', ['lpplan' => $planid]]);
            foreach ($page as $id => $row) {
                $after = (int) $id;
                $root = $ctx->tenant->root_of_user((int) $row->userid);
                if ($root > 0) {
                    $roots[$root] = true;
                    if (count($roots) > 1) {
                        return null;
                    }
                }
            }
        } while (count($page) === self::PAGE);
        return count($roots) === 1 ? (int) array_key_first($roots) : null;
    }

    /**
     * The Moodle courses on a plan that exist, without duplicates.
     *
     * @param context $ctx
     * @param int $planid Legacy plan id.
     * @return int[]
     */
    public static function course_ids(context $ctx, int $planid): array {
        $ids = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(importer::SRC_COURSE, $after, self::PAGE, ['courseid'],
                ['t.planid = :lpplan', ['lpplan' => $planid]]);
            foreach ($page as $id => $row) {
                $after = (int) $id;
                $courseid = (int) ($row->courseid ?? 0);
                if ($courseid > 1 && $ctx->lookups->course_exists($courseid)) {
                    $ids[$courseid] = $courseid;
                }
            }
        } while (count($page) === self::PAGE);
        return array_values($ids);
    }

    /**
     * The created time of a legacy plan, 0 when the plan is gone or has none.
     *
     * @param context $ctx
     * @param int $planid
     * @return int
     */
    public static function plan_timecreated(context $ctx, int $planid): int {
        $rows = $ctx->legacy->fetch(importer::SRC_PLAN, [$planid], ['timecreated']);
        return isset($rows[$planid]) ? max(0, (int) $rows[$planid]->timecreated) : 0;
    }
}
