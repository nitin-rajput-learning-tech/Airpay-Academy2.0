<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * Step 3 of the enrolments importer: decide, for every BizLMS enrol instance that has enrolments, whether it can be
 * switched off now that its learners are manual enrolments (owner decision CRS-01, 2026-10-07; decision key
 * enrolments.bizlms_instances_after_verify = disable_when_converted).
 *
 * The unit is the instance (derived unit #enrol.id, one group per instance row), so every instance gets exactly one primary
 * outcome and one report line:
 *
 *  - the course is gone: skipped, course_missing;
 *  - the instance is already disabled: skipped, already_disabled. BizLMS granted nothing on it, its rows were converted as
 *    suspended (decision enrolments.disabled_instance_row_status), and there is nothing to switch off;
 *  - a row on it is not settled (an account that is deleted, a suspended or shorter manual enrolment that the import did not
 *    touch): skipped, rows_unsettled. The instance stays enabled, so those learners keep the access they have until L&D acts;
 *  - a learner-course pair on it would lose access (enrolments_access): skipped, access_regression, which needs the owner.
 *    One regression keeps the whole instance enabled;
 *  - otherwise one row in the trail table local_sentientia_courses_enroloff, which the recompute step
 *    (enrolments.legacy_instances_off) turns into the reviewed UPDATE of enrol.status. The prior status is kept in the trail.
 *
 * This step writes no core row. It runs after enrolments.enrolments, so the manual enrolments it compares against exist (in
 * the same transaction when the feature is atomic). A DRY RUN writes none, so the access proof cannot run: the step then
 * decides from the legacy map alone (rows_unsettled or "would be switched off") and says so with the warning
 * access_proof_not_run_in_a_dry_run. The decision of an apply run is the proof.
 *
 * Reads. The verdicts of every instance come from one pass over the enrolments (enrolments_access::verdicts()), on the first
 * row; nothing is read per instance.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolments_legacy_instances_step extends step {

    /** @var array<int, array>|null Instance id => verdict (enrolments_access::verdicts()), judged once on first use. */
    private ?array $verdicts = null;

    /** @var int|null The moment access is judged at, fixed on first use so one run judges every instance alike. */
    private ?int $now = null;

    public function key(): string {
        return 'enrolments.legacy_instances';
    }

    public function sourcetable(): string {
        return enrolments_importer::UNIT_LEGACY_INSTANCES;
    }

    public function targettable(): string {
        return enrolments_importer::TRAIL;
    }

    public function group_by(): array {
        return ['id'];
    }

    public function columns(): array {
        return ['id', 'courseid', 'enrol', 'status', 'timecreated', 'timemodified'];
    }

    public function source_filter(): array {
        return enrolments_importer::instance_filter();
    }

    /**
     * @param \stdClass[] $rows The BizLMS instance of ONE group (one row).
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->decide($row, $ctx);
        }
        return $out;
    }

    /**
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function decide(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;
        $courseid = (int) $row->courseid;
        if ($courseid <= 0 || !$ctx->lookups->course_exists($courseid)) {
            return outcome::skip($id, 'course_missing');
        }
        if ((int) $row->status !== 0) {
            return outcome::skip($id, 'already_disabled');
        }

        $verdict = $this->verdicts($ctx)[$id] ?? null;
        if ($verdict === null) {
            // The instance row came from the table the verdicts read, so this cannot happen; an impossible row is not guessed.
            throw new bizlms_exception('legacy_instance_not_judged:' . $id);
        }
        if ($verdict['unsettled'] > 0) {
            return outcome::skip($id, 'rows_unsettled');
        }
        if ($verdict['regressions'] > 0) {
            return outcome::skip($id, 'access_regression', 'learners_would_lose_access');
        }

        $trail = outcome::insert($id, enrolments_importer::TRAIL, (object) [
            'enrolid' => $id,
            'courseid' => $courseid,
            'method' => (string) $row->enrol,
            'priorstatus' => (int) $row->status,
            // Source timestamps are kept: the trail row says what the instance looked like before the import touched it.
            'timecreated' => (int) $row->timecreated,
            'timemodified' => (int) $row->timemodified,
        ]);
        if ($ctx->dryrun) {
            $trail->warn('access_proof_not_run_in_a_dry_run');
        }
        return $trail;
    }

    /**
     * The verdicts of every BizLMS instance that has enrolments, judged once.
     *
     * @param context $ctx
     * @return array<int, array>
     */
    private function verdicts(context $ctx): array {
        if ($this->verdicts === null) {
            $this->now ??= time();
            $this->verdicts = enrolments_access::verdicts($ctx, enrolments_access::candidate_instances($ctx), $this->now, [],
                !$ctx->dryrun);
        }
        return $this->verdicts;
    }
}
