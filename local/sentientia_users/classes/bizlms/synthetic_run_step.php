<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * The synthetic runs a sync error that matches no BizLMS run is attached to (ADR-032, mapping doc section 10,
 * "Run matching" rules 1 and 4).
 *
 * A Sentientia error row needs a run. BizLMS stored an error with its uploader and time and never a run, so an
 * error that matches no legacy run (or that the HR web service wrote) goes to a run made for it: one per
 * uploader and server-timezone day, with source "bizlms", status "completed", no inserted or updated users,
 * its counts taken from the errors it holds, and the time of the last of them.
 *
 * The step is a DERIVED group source (named #local_syncerrors.<kind>_day) over the error table, grouped by
 * uploader (modified_by): the framework can only group on a raw column, and one uploader holds all the
 * synthetic runs the uploader needs. Its map key is the lowest error id of the uploader's group (a non-personal
 * integer, rule R12), and the group is settled as follows:
 *
 *   - no error of this kind: the group is archived (no_unattached_errors, no_service_errors), nothing is made;
 *   - otherwise the chronologically first synthetic run is the group's primary row (sub-key ''), and every
 *     further day is a sub-row named by its day key ("day:20260930"). sync_error_step finds a run by the
 *     uploader's lowest error id and sync_index::subkey_for().
 *
 * The runs hold only counts and a time: no person. The cost centre is the uploader's tenant (orphan runs) or 0
 * (service runs: the web service has no tenant).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class synthetic_run_step extends step {

    /**
     * ORPHAN or SERVICE.
     *
     * @return string
     */
    abstract protected function kind(): string;

    /**
     * Reason code for an uploader group with no run of this kind.
     *
     * @return string
     */
    abstract protected function empty_reason(): string;

    /**
     * The derived source name: #local_syncerrors.orphan_day or #local_syncerrors.service_day.
     *
     * @return string
     */
    public function sourcetable(): string {
        return '#local_syncerrors.' . $this->kind() . '_day';
    }

    public function targettable(): string {
        return sync_run_step::TARGET;
    }

    public function group_by(): array {
        return ['modified_by'];
    }

    /**
     * Only the ids and the grouping column are needed: the counts come from the index, which saw every row.
     *
     * @return string[]
     */
    public function columns(): array {
        return ['id', 'modified_by'];
    }

    public function transform(array $rows, context $ctx): array {
        $index = sync_index::for_context($ctx);
        $first = reset($rows);
        $ukey = sync_index::uploader_key($first->modified_by ?? null);
        $minid = $index->min_error_id($ukey);
        if ($minid === null || $minid !== (int) $first->id) {
            // The group the framework read and the index disagree about the uploader's first error: the source
            // changed between the two reads. Failing is right; importing a guess is not.
            throw new bizlms_exception('sync_index_out_of_step:' . (int) $first->id);
        }

        $keys = $index->synthetic_keys($this->kind(), $ukey);
        if (!$keys) {
            return [outcome::archive($minid, $this->empty_reason())];
        }

        $uploader = clean::id($first->modified_by ?? null);
        if ($this->kind() === sync_index::SERVICE) {
            [$costcenter, $method] = [0, 'fallback:service'];
        } else {
            [$costcenter, $method] = $index->tenant_for_uploader($ctx, $uploader);
        }

        $out = [];
        foreach ($keys as $key) {
            $stats = $index->synthetic_stats($this->kind(), $ukey, $key);
            $time = clean::time($stats['last']);
            $subkey = $index->subkey_for($this->kind(), $ukey, $key);
            $out[] = outcome::insert($minid, sync_run_step::TARGET, (object) [
                'filename' => '',
                'source' => 'bizlms',
                'costcenterid' => $costcenter,
                'totalrows' => $stats['errors'],
                'insertedcount' => 0,
                'updatedcount' => 0,
                'skippedcount' => 0,
                'errorcount' => $stats['errors'],
                'warningcount' => $stats['warnings'],
                'suspendedcount' => 0,
                'usercreated' => $uploader,
                'status' => 'completed',
                'error_summary' => null,
                'timecreated' => $time,
                'timemodified' => $time,
            ], $subkey)->tenant_method($method)->warn('synthetic_run')->warn('derived_timestamp');
        }
        return $out;
    }
}
