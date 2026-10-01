<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_userssyncdata -> local_sentientia_users_sync_runs (ADR-032, mapping doc section 10): one row per HRMS
 * upload BizLMS wrote after it finished processing.
 *
 * MAP: nothing outside the table stores a legacy run id. Every source row becomes one run; there is no filter,
 * no dedupe and nothing to skip.
 *
 * What BizLMS never stored is filled with what it meant: no file name (the page shows "(no file)"), source
 * "bizlms" (so the list can say where the row came from), status "completed" (a BizLMS row exists only after
 * its loop finished), no error summary.
 *
 * The counters are copied as BizLMS showed them. They are not true totals (the legacy code counted error
 * messages, not rows) and are not de-duplicated ("Report the difference; do not de-duplicate", mapping doc
 * section 10). The difference is reported, per run, as two warning codes the report counts:
 *   - legacy_error_count_differs: the run's errorscount is not the number of error rows matched to it;
 *   - legacy_warning_count_differs: the run's warningscount + supervisorwarningscount is not the number of warning
 *     rows matched to it.
 * Both are only reported when the source has the error table to compare with (sync_index::has_error_source()).
 * They are expected on most real runs: they say how far the old counters are from the rows, not that the import
 * lost anything. The one derived number is totalrows: inserted + updated + the error rows matched to the run.
 *
 * The tenant is the uploader's tenant now (sync_index::tenant_for_uploader()), never the legacy costcenterid.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_run_step extends step {

    /** The only table the runs and the synthetic runs are written to. */
    public const TARGET = 'local_sentientia_users_sync_runs';

    public function key(): string {
        return 'users.sync_runs';
    }

    public function sourcetable(): string {
        return 'local_userssyncdata';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function transform(array $rows, context $ctx): array {
        $index = sync_index::for_context($ctx);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $warnings = [];
            $uploader = clean::id($row->usercreated ?? null);
            [$costcenter, $method] = $index->tenant_for_uploader($ctx, $uploader);

            [$inserted, $c1] = clean::count($row->newuserscount ?? null);
            [$updated, $c2] = clean::count($row->updateduserscount ?? null);
            [$errors, $c3] = clean::count($row->errorscount ?? null);
            [$warned, $c4] = clean::count((int) ($row->warningscount ?? 0) + (int) ($row->supervisorwarningscount ?? 0));
            [$total, $c5] = clean::count($inserted + $updated + $index->attached_errors($id));
            if ($c1 || $c2 || $c3 || $c4 || $c5) {
                $warnings[] = 'count_clamped';
            }
            if ($index->has_error_source()) {
                if ($errors !== $index->attached_errors($id)) {
                    $warnings[] = 'legacy_error_count_differs';
                }
                if ($warned !== $index->attached_warnings($id)) {
                    $warnings[] = 'legacy_warning_count_differs';
                }
            }

            $created = clean::time($row->timecreated ?? null);
            $modified = clean::time($row->timemodified ?? null);
            if ($modified === 0) {
                $modified = $created;
                $warnings[] = 'derived_timestamp';
            }
            if ($uploader === 0) {
                $warnings[] = 'no_uploader';
            }

            $outcome = outcome::insert($id, self::TARGET, (object) [
                'filename' => '',
                'source' => 'bizlms',
                'costcenterid' => $costcenter,
                'totalrows' => $total,
                'insertedcount' => $inserted,
                'updatedcount' => $updated,
                'skippedcount' => 0,
                'errorcount' => $errors,
                'warningcount' => $warned,
                'suspendedcount' => 0,
                'usercreated' => $uploader,
                'status' => 'completed',
                'error_summary' => null,
                'timecreated' => $created,
                'timemodified' => $modified,
            ])->tenant_method($method);
            foreach ($warnings as $code) {
                $outcome->warn($code);
            }
            $out[] = $outcome;
        }
        return $out;
    }
}
