<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\file_rehome;
use local_sentientia_platform\bizlms\legacy_reader;

/**
 * The cohort description files of the cohort_scope import.
 *
 * BizLMS stored the files of a cohort description under its own component: `local_groups` when
 * the cohort was edited, `groups` when it was added (BizLMS local/groups/edit.php). Core serves a
 * cohort description from component `cohort`, file area `description`, item id = the cohort id, in
 * the cohort's own context. Without the copy, every image in an imported description is a broken
 * link.
 *
 * The originals stay where they are (the legacy archive is never altered) and a file that already
 * exists at the target is left alone, so running this twice copies nothing the second time. It is
 * meant for finalise(), which runs outside any transaction.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_files {

    /** @var string[] BizLMS components that hold a cohort description's files, the edit path first. */
    public const SOURCE_COMPONENTS = ['local_groups', 'groups'];

    /** Core component that serves a cohort description. */
    public const COMPONENT = 'cohort';

    /** File area of a cohort description, at BizLMS and in core. */
    public const FILEAREA = 'description';

    /**
     * Copy every description file of every imported cohort into core's cohort description area.
     *
     * @param context $ctx
     * @return int Files copied in this call.
     */
    public static function copy_all(context $ctx): int {
        $copied = 0;
        foreach (self::plan($ctx) as $cohortid => $plan) {
            foreach ($plan['sources'] as [$contextid, $component]) {
                $copied += file_rehome::copy_area(
                    ['contextid' => $contextid, 'component' => $component, 'filearea' => self::FILEAREA,
                        'itemid' => $cohortid],
                    ['contextid' => $plan['target'], 'component' => self::COMPONENT, 'filearea' => self::FILEAREA,
                        'itemid' => $cohortid]);
            }
        }
        return $copied;
    }

    /**
     * How many source files have no twin in core's cohort description area.
     *
     * @param context $ctx
     * @return int
     */
    public static function count_missing(context $ctx): int {
        $fs = get_file_storage();
        $missing = 0;
        foreach (self::plan($ctx) as $cohortid => $plan) {
            foreach ($plan['sources'] as [$contextid, $component]) {
                $files = $fs->get_area_files($contextid, $component, self::FILEAREA, $cohortid, 'id', false);
                foreach ($files as $file) {
                    if (!$fs->file_exists($plan['target'], self::COMPONENT, self::FILEAREA, $cohortid,
                            $file->get_filepath(), $file->get_filename())) {
                        $missing++;
                    }
                }
            }
        }
        return $missing;
    }

    /**
     * Which BizLMS file areas belong to which imported cohort, and where they go.
     *
     * Only cohorts that have a scope row and still exist in core are planned. Sources are ordered edit
     * path first (local_groups), then add path (groups), then by context id, so when both hold a file of
     * the same name the later edit wins, deterministically.
     *
     * @param context $ctx
     * @return array<int, array{target: int, sources: array<int, array{0: int, 1: string}>}> Cohort id => plan.
     */
    private static function plan(context $ctx): array {
        $imported = self::imported_cohorts($ctx);
        if (!$imported) {
            return [];
        }
        $info = new cohort_context();
        $found = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('files', $after, legacy_reader::MAX_PAGE, ['contextid', 'component', 'itemid'], [
                't.component IN (:blmc1, :blmc2) AND t.filearea = :blmfa AND t.filename <> :blmdot',
                ['blmc1' => self::SOURCE_COMPONENTS[0], 'blmc2' => self::SOURCE_COMPONENTS[1],
                    'blmfa' => self::FILEAREA, 'blmdot' => '.'],
            ]);
            foreach ($page as $id => $row) {
                $after = $id;
                $cohortid = (int) $row->itemid;
                if (isset($imported[$cohortid])) {
                    $found[$cohortid][(int) $row->contextid . '|' . $row->component] = [(int) $row->contextid, (string) $row->component];
                }
            }
        } while (count($page) === legacy_reader::MAX_PAGE);

        $plans = [];
        ksort($found);
        foreach ($found as $cohortid => $areas) {
            $cohort = $info->cohort($ctx, $cohortid);
            if ($cohort === null) {
                continue;
            }
            $sources = array_values($areas);
            usort($sources, static function (array $a, array $b): int {
                return [(int) array_search($a[1], self::SOURCE_COMPONENTS, true), $a[0]]
                    <=> [(int) array_search($b[1], self::SOURCE_COMPONENTS, true), $b[0]];
            });
            $plans[$cohortid] = ['target' => $cohort['contextid'], 'sources' => $sources];
        }
        return $plans;
    }

    /**
     * Cohort ids that have a scope row.
     *
     * @param context $ctx
     * @return array<int, bool> Cohort id => true.
     */
    private static function imported_cohorts(context $ctx): array {
        $out = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(cohort_scope_importer::TARGET, $after, legacy_reader::MAX_PAGE, ['cohortid']);
            foreach ($page as $id => $row) {
                $out[(int) $row->cohortid] = true;
                $after = $id;
            }
        } while (count($page) === legacy_reader::MAX_PAGE);
        return $out;
    }
}
