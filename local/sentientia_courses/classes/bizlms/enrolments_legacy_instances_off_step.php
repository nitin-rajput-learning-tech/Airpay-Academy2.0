<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Last step of the enrolments importer: the reviewed UPDATE that switches off the BizLMS enrol instances the previous step
 * proved safe (owner decision CRS-01, 2026-10-07).
 *
 * It works on the trail rows the load step imported (recompute steps run over every imported row of the feature, so it is
 * idempotent by construction) and sets enrol.status to 1 on each instance the trail names. Only the status and
 * timemodified change: the instance row stays, its enrolments stay, nothing is deleted, and the trail keeps the prior status,
 * so one UPDATE ({enrol} SET status = priorstatus) puts an instance back. A repeat run writes nothing: an instance that is
 * already off is left alone, and a row that is not a BizLMS instance is never touched.
 *
 * The direct UPDATE is made by the writer, not the enrol API: enrol_plugin::update_status() would fire events and could call
 * plugin code that is no longer deployed.
 *
 * Two guards. After the site has opened (local_sentientia_platform/bizlms_production_open), the step writes nothing, so an
 * administrator who switches an instance back on keeps it on if the feature is applied again. And before it switches
 * anything off, it proves the instances again inside the same transaction (the same function the load step used): if the
 * proof fails now, the run fails instead of switching off an instance whose learners would lose access.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolments_legacy_instances_off_step extends recompute_step {

    public function key(): string {
        return 'enrolments.legacy_instances_off';
    }

    public function targettable(): string {
        return enrolments_importer::TRAIL;
    }

    /**
     * @param int[] $targetids Ids of trail rows.
     * @param context $ctx
     * @return outcome[] Update outcomes of table enrol only.
     */
    public function recompute(array $targetids, context $ctx): array {
        if ((int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0) {
            return [];
        }

        $instanceids = [];
        foreach ($ctx->legacy->fetch(enrolments_importer::TRAIL, $targetids, ['enrolid']) as $trail) {
            $instanceids[] = (int) $trail->enrolid;
        }
        $switch = [];
        foreach ($ctx->legacy->fetch('enrol', $instanceids, ['enrol', 'status']) as $id => $instance) {
            if (in_array((string) $instance->enrol, enrolments_importer::METHODS, true) && (int) $instance->status === 0) {
                $switch[] = (int) $id;
            }
        }
        if (!$switch) {
            return [];
        }

        $now = time();
        $verdicts = enrolments_access::verdicts($ctx, $switch, $now);
        foreach ($switch as $id) {
            $verdict = $verdicts[$id] ?? null;
            if ($verdict === null || $verdict['unsettled'] > 0 || $verdict['regressions'] > 0) {
                throw new bizlms_exception('legacy_instance_not_proved:' . $id);
            }
        }

        $out = [];
        foreach ($switch as $id) {
            $out[] = outcome::update('enrol', $id, (object) ['status' => 1, 'timemodified' => $now]);
        }
        return $out;
    }
}
