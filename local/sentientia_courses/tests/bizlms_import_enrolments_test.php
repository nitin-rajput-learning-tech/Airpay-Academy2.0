<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_courses\bizlms\enrolments_access;
use local_sentientia_courses\bizlms\enrolments_importer;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\static_scanner;

/**
 * The enrolments importer (ADR-032, mapping doc section 21, gap G6): the importer contract plus the feature's own cases.
 *
 * Enrolments on the three BizLMS enrol methods (classroom, program, learningplan) become manual enrolments in the same
 * course. The sources are core enrol and user_enrolments, which the PHPUnit database already has, so the BizLMS enrol
 * plugins leave no legacy table to create: the fixture file says so (tests/fixtures/bizlms/enrol_methods.install.xml).
 *
 * What the contract cannot say for a feature that reads and writes core tables is overridden here, with the reason:
 * the claimed tables cannot be dropped (test_contract_not_applicable_without_tables), and a clean run creates core rows
 * that contract_clear_import must remove before a second run on the same seed.
 *
 * There is no tenant column, no PRESERVE step and no person column in this feature, so the org dependency, the
 * collision/adoption and the privacy cases of the contract skip themselves.
 *
 * The seed (course 1..4, learners a..h; every legacy row has an explicit id so a re-seed gives the same map):
 *
 *   instances  lp1, lp2, lp5 learningplan in course 1 (lp5 has no enrolment)   pr1 program in course 2
 *              cl1 classroom in course 3   lp3 learningplan in course 4   lp4 learningplan in a course that is gone
 *   manual     course 1, 2: one enabled manual instance   course 3: none   course 4: only a DISABLED one
 *   7001 lp1 a      owner of the pair (course 1, a): converted
 *   7002 lp2 a      the same pair: folded into 7001's enrolment (duplicate_pair)
 *   7003 lp1 b      converted (b also has a self enrolment, which is not manual and stays)
 *   7004 pr1 c      c is already enrolled manually and active: folded (already_manual)
 *   7005 cl1 d      suspended: converted, suspended, on the instance created for course 3
 *   7006 lp3 e      ended in the past: converted with the same end, on a new enabled instance beside the disabled one
 *   7007 lp1 f      the account is deleted: skipped (user_deleted, which needs the owner: mapping rule R11 says otherwise)
 *   7008 lp1 g      no such account: skipped (user_missing)
 *   7009 lp1 h      h has a SUSPENDED manual enrolment, the legacy one is active: skipped (manual_enrolment_inactive)
 *   7010 lp2 h      the same pair: skipped with the owner's reason
 *   7011 lp4 b      the course is gone: skipped (course_missing)
 *   7012 pr1 a      converted
 *
 * The instance step (owner decision CRS-01, 2026-10-07) then judges the six BizLMS instances that hold enrolments: pr1, cl1
 * and lp3 are proved (every learner keeps the access through manual enrolments) and switched off, in the trail; lp1 and lp2
 * hold a row nobody settled (7007, 7009, 7010) and stay enabled; lp4 is in a course that is gone. lp5 holds no enrolment and
 * is not judged.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @covers     \local_sentientia_courses\bizlms\enrolments_importer
 * @covers     \local_sentientia_courses\bizlms\enrolments_instances_step
 * @covers     \local_sentientia_courses\bizlms\enrolments_step
 * @covers     \local_sentientia_courses\bizlms\enrolments_access
 * @covers     \local_sentientia_courses\bizlms\enrolments_legacy_instances_step
 * @covers     \local_sentientia_courses\bizlms\enrolments_legacy_instances_off_step
 *
 * @group local_sentientia_courses
 * @group bizlms_import
 */
final class bizlms_import_enrolments_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract {
        contract_clear_import as protected trait_contract_clear_import;
    }

    /** @var int Timestamp base of the seed (2023: in the past on any run date). */
    private const T0 = 1700000000;

    /** @var array<int, array{0: string, 1: string, 2: ?string}> The outcome of every legacy enrolment: outcome, target table, reason. */
    private const OUTCOMES = [
        7001 => ['imported', 'user_enrolments', null],
        7002 => ['folded', 'user_enrolments', 'duplicate_pair'],
        7003 => ['imported', 'user_enrolments', null],
        7004 => ['folded', 'user_enrolments', 'already_manual'],
        7005 => ['imported', 'user_enrolments', null],
        7006 => ['imported', 'user_enrolments', null],
        7007 => ['skipped', '', 'user_deleted'],
        7008 => ['skipped', '', 'user_missing'],
        7009 => ['skipped', '', 'manual_enrolment_inactive'],
        7010 => ['skipped', '', 'manual_enrolment_inactive'],
        7011 => ['skipped', '', 'course_missing'],
        7012 => ['imported', 'user_enrolments', null],
    ];

    /** @var \stdClass|null What the last seed created. */
    private ?\stdClass $seeded = null;

    /** @var int The student-archetype role, which the seed gives to the legacy instances. */
    private int $studentrole = 0;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/enrol_methods.install.xml'];
    }

    protected function contract_importer(): importer {
        return new enrolments_importer();
    }

    protected function contract_decisions(): decisions {
        // Every decision of the importer, with the only value its code implements: the gap decision and the three owner
        // decisions of 2026-10-07 (CRS-01, CRS-02, CRS-03).
        return decisions::from_array(enrolments_importer::SIGNED_VALUES);
    }

    protected function contract_seed(): void {
        $this->seed();
    }

    protected function contract_mutate_source(): void {
        // A new instance with an enrolment changes the count and the highest id of BOTH steps' sources, on every
        // engine, so detection does not depend on the CRC. The first step to start is enrolments.instances, which is
        // the one a resume compares first.
        $seed = $this->seeded ?? $this->seed();
        $instance = $this->add_instance('learningplan', $seed->course[1], 99);
        $this->add_enrolment(7900, $instance, $seed->user['b']);
    }

    /**
     * A clean run creates rows in core enrol and user_enrolments. Remove them (they are named by the map, which the
     * contract's version clears next) so a second run on the same seed decides what the first one did.
     *
     * @param importer $importer
     * @return void
     */
    protected function contract_clear_import(importer $importer): void {
        global $DB;
        // The instances the import switched off go back to the status the trail kept (that is how an operator undoes it),
        // or the second run would see a seed that is not the one the first run saw.
        foreach ($DB->get_records_sql('SELECT id, enrolid, priorstatus FROM {' . enrolments_importer::TRAIL . '}') as $trail) {
            $DB->set_field('enrol', 'status', $trail->priorstatus, ['id' => $trail->enrolid]);
        }
        foreach (['user_enrolments', 'enrol'] as $table) {
            $ids = $DB->get_fieldset_select(legacymap::TABLE, 'targetid',
                "feature = :f AND targettable = :t AND outcome = 'imported'", ['f' => enrolments_importer::FEATURE, 't' => $table]);
            if ($ids) {
                $DB->delete_records_list($table, 'id', $ids);
            }
        }
        $this->trait_contract_clear_import($importer);
    }

    /**
     * The contract's version drops the claimed tables. This importer claims core enrol and user_enrolments, which
     * always exist and must not be dropped, so there is no "not applicable": on a database that never had BizLMS the
     * feature is applicable, finds nothing to convert, and completes with an empty ledger.
     *
     * @return void
     */
    public function test_contract_not_applicable_without_tables(): void {
        global $DB;
        $importer = $this->contract_begin();
        $enrol = $DB->count_records('enrol');
        $ues = $DB->count_records('user_enrolments');

        [$result] = $this->contract_run(true);

        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features'][$importer->feature()]);
        $this->assertTrue(legacymap::feature_complete($importer->feature()));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertSame(0, $DB->count_records(enrolments_importer::LEDGER));
        $this->assertSame($enrol, $DB->count_records('enrol'));
        $this->assertSame($ues, $DB->count_records('user_enrolments'));
    }

    public function test_the_registry_accepts_the_importer(): void {
        $this->contract_begin();
        $importers = registry::load();
        $importer = $importers['enrolments'];
        $this->assertSame(['enrolments'], array_keys($importers));
        $this->assertSame([], $importer->depends());
        $this->assertSame([], $importer->tenant_columns());
        $this->assertSame(['enrol', 'user_enrolments'], array_keys($importer->core_writes()));
        $this->assertSame(['enrol', 'user_enrolments'], array_keys($importer->sources()));
        $this->assertSame([enrolments_importer::LEDGER, enrolments_importer::TRAIL], $importer->target_tables());
        // Every rule that departs from the map or from the signed decision is the owner's call, not a silent default.
        $needsowner = array_map(fn($reason) => $reason->code, array_filter($importer->reasons(), fn($reason) => $reason->needsowner));
        sort($needsowner);
        $this->assertSame(['access_regression', 'manual_enrolment_ends_sooner', 'manual_enrolment_inactive', 'user_deleted'],
            $needsowner);
        $this->assertSame(['enrolments.instances', 'enrolments.enrolments', 'enrolments.legacy_instances',
            'enrolments.legacy_instances_off'], array_map(fn($step) => $step->key(), $importer->steps()));
    }

    public function test_every_decision_is_declared_required_with_the_one_value_the_code_implements(): void {
        $importer = new enrolments_importer();
        $declared = [];
        foreach ($importer->decisions() as $decision) {
            $declared[$decision->key] = $decision;
        }
        $this->assertSame(array_keys(enrolments_importer::SIGNED_VALUES), array_keys($declared));
        $this->assertSame([
            'gap.orphan_enrol_instances', 'enrolments.bizlms_instances_after_verify',
            'enrolments.disabled_instance_row_status', 'enrolments.disabled_only_manual_instance',
        ], array_keys($declared));
        foreach (enrolments_importer::SIGNED_VALUES as $key => $value) {
            $this->assertTrue($declared[$key]->required, "{$key} is required: the rule is signed, so an absent key blocks");
            $this->assertNull($declared[$key]->default, "{$key} has no default: an owner choice is data");
            $this->assertSame([$value], $declared[$key]->allowed, "{$key} allows the one value the code implements");
        }
        $this->assertSame('disable_when_converted', enrolments_importer::SIGNED_VALUES['enrolments.bizlms_instances_after_verify']);
        $this->assertSame('convert_as_suspended', enrolments_importer::SIGNED_VALUES['enrolments.disabled_instance_row_status']);
        $this->assertSame('add_enabled_beside', enrolments_importer::SIGNED_VALUES['enrolments.disabled_only_manual_instance']);
    }

    public function test_each_owner_decision_blocks_the_feature_when_it_is_absent(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $ues = $DB->count_records('user_enrolments');
        $enrol = $DB->get_records('enrol', null, 'id');

        foreach (array_keys(enrolments_importer::SIGNED_VALUES) as $key) {
            $without = enrolments_importer::SIGNED_VALUES;
            unset($without[$key]);
            [$result] = $this->contract_run(true, ['decisions' => decisions::from_array($without)]);
            $this->assertSame(1, $result['exit'], $key);
            $this->assertStringContainsString('missing_decision:' . $key, implode(' ', $result['blockers']), $key);
            $this->assertSame($ues, $DB->count_records('user_enrolments'), "nothing is converted without {$key}");
            $this->assertEquals($enrol, $DB->get_records('enrol', null, 'id'), "no instance is switched off without {$key}");
        }
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
    }

    public function test_an_owner_decision_with_another_value_is_refused(): void {
        $this->contract_begin();
        $this->seed();
        foreach (['enrolments.bizlms_instances_after_verify' => 'leave_enabled',
                  'enrolments.disabled_instance_row_status' => 'convert_keep_status',
                  'enrolments.disabled_only_manual_instance' => 'enable_existing'] as $key => $value) {
            [$result] = $this->contract_run(true, ['decisions' => decisions::from_array(
                [$key => $value] + enrolments_importer::SIGNED_VALUES)]);
            $this->assertSame(1, $result['exit'], $key);
            $this->assertStringContainsString('decision_value_not_allowed:' . $key, implode(' ', $result['blockers']), $key);
        }
    }

    public function test_the_importer_code_passes_the_static_scan(): void {
        $files = static_scanner::php_files(__DIR__ . '/../classes/bizlms');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertSame([], static_scanner::scan((string) file_get_contents($file), false, false), basename($file));
        }
    }

    public function test_every_legacy_enrolment_has_one_primary_map_row_with_the_expected_outcome(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();

        [$result, $report] = $this->contract_run(true);

        // Rows that need the owner are not accepted, so the run is done but unproven.
        $this->assertSame(2, $result['exit'], implode('; ', $result['blockers']));
        $this->assertContains('enrolments:manual_enrolment_inactive=2', $result['unproven']);
        $this->assertContains('enrolments:user_deleted=1', $result['unproven']);

        $rows = $DB->get_records(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS, 'subkey' => ''],
            'sourceid', 'sourceid, outcome, targettable, targetid, reason');
        $this->assertSame(array_keys(self::OUTCOMES), array_map('intval', array_keys($rows)));
        foreach (self::OUTCOMES as $id => [$outcome, $table, $reason]) {
            $this->assertSame($outcome, $rows[$id]->outcome, "{$id} outcome");
            $this->assertSame($table, $rows[$id]->targettable, "{$id} target table");
            $this->assertSame($reason, $rows[$id]->reason, "{$id} reason");
            $this->assertSame($table === '', $rows[$id]->targetid === null, "{$id} has a target exactly when it has a table");
        }
        // The legacy status, start and end are the importer's own business: the generic accounting identity is its verify.
        $importer = new enrolments_importer();
        $this->assertSame([], $importer->verify(context::build($importer, false, 0, $this->contract_decisions())));

        $steps = $report->to_array()['features']['enrolments']['steps'];
        $this->assertSame(5, $steps['enrolments.instances']['counters']['processed']);
        $this->assertSame(2, $steps['enrolments.instances']['counters']['imported']);
        $this->assertSame(2, $steps['enrolments.instances']['counters']['folded']);
        $this->assertSame(1, $steps['enrolments.instances']['counters']['skipped']);
        $this->assertSame(12, $steps['enrolments.enrolments']['counters']['processed']);
        $this->assertSame(5, $steps['enrolments.enrolments']['counters']['imported']);
        $this->assertSame(2, $steps['enrolments.enrolments']['counters']['folded']);
        $this->assertSame(5, $steps['enrolments.enrolments']['counters']['skipped']);
        // The third unit: one primary map row per BizLMS instance that holds enrolments.
        $this->assertSame(6, $steps['enrolments.legacy_instances']['counters']['processed']);
        $this->assertSame(3, $steps['enrolments.legacy_instances']['counters']['imported']);
        $this->assertSame(3, $steps['enrolments.legacy_instances']['counters']['skipped']);
    }

    public function test_every_bizlms_instance_with_enrolments_gets_one_primary_map_row_and_the_trail_names_the_proven_ones(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();

        $this->contract_run(true);

        $rows = $DB->get_records(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_LEGACY_INSTANCES, 'subkey' => ''],
            'sourceid', 'sourceid, outcome, targettable, targetid, reason');
        $expected = [
            // Rows the owner has not decided (a deleted account, an administrator's suspended manual enrolment).
            $seed->inst['lp1'] => ['skipped', 'rows_unsettled'],
            $seed->inst['lp2'] => ['skipped', 'rows_unsettled'],
            // Proved, switched off, in the trail.
            $seed->inst['pr1'] => ['imported', null],
            $seed->inst['cl1'] => ['imported', null],
            $seed->inst['lp3'] => ['imported', null],
            // The course is gone.
            $seed->inst['lp4'] => ['skipped', 'course_missing'],
        ];
        $this->assertEqualsCanonicalizing(array_keys($expected), array_map('intval', array_keys($rows)));
        foreach ($expected as $instance => [$outcome, $reason]) {
            $this->assertSame($outcome, $rows[$instance]->outcome, "instance {$instance}");
            $this->assertSame($reason, $rows[$instance]->reason, "instance {$instance}");
            $this->assertSame($outcome === 'imported' ? enrolments_importer::TRAIL : '', $rows[$instance]->targettable);
        }

        // The trail: ids and source timestamps only, the prior status, the method and the course.
        $this->assertSame(3, $DB->count_records(enrolments_importer::TRAIL));
        $trail = $DB->get_record(enrolments_importer::TRAIL, ['enrolid' => $seed->inst['pr1']], '*', MUST_EXIST);
        $this->assertSame('program', $trail->method);
        $this->assertSame($seed->course[2], (int) $trail->courseid);
        $this->assertSame(0, (int) $trail->priorstatus);
        $this->assertSame(self::T0, (int) $trail->timecreated, 'the source\'s timestamps');
        $this->assertSame(self::T0, (int) $trail->timemodified, 'the instance row before the import changed it');
        $this->assertSame((int) $trail->id, (int) $rows[$seed->inst['pr1']]->targetid);
    }

    public function test_the_needs_owner_reason_is_the_only_thing_that_keeps_the_run_from_exit_0(): void {
        $this->contract_begin();
        $this->seed();

        [$result] = $this->contract_run(true, ['decisions' => decisions::from_array([
            enrolments_importer::DECISION => enrolments_importer::DECISION_VALUE,
            'accepted_reasons' => ['enrolments:manual_enrolment_inactive', 'enrolments:user_deleted'],
        ])]);

        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        $this->assertSame([], $result['unproven']);
    }

    public function test_converted_enrolments_keep_status_start_end_and_timestamps_on_a_manual_instance(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();

        $this->contract_run(true);

        // Legacy id => course, learner, status, start, end, method, original instance.
        $expected = [
            7001 => [$seed->course[1], $seed->user['a'], 0, self::T0, 0, 'learningplan', $seed->inst['lp1']],
            7003 => [$seed->course[1], $seed->user['b'], 0, self::T0, 0, 'learningplan', $seed->inst['lp1']],
            7005 => [$seed->course[3], $seed->user['d'], 1, self::T0 + 5, 0, 'classroom', $seed->inst['cl1']],
            7006 => [$seed->course[4], $seed->user['e'], 0, self::T0, self::T0 + 1000, 'learningplan', $seed->inst['lp3']],
            7012 => [$seed->course[2], $seed->user['a'], 0, self::T0, 0, 'program', $seed->inst['pr1']],
        ];
        $this->assertSame(count($expected), $DB->count_records(enrolments_importer::LEDGER));
        foreach ($expected as $id => [$course, $user, $status, $start, $end, $method, $original]) {
            $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS,
                'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
            $ue = $DB->get_record('user_enrolments', ['id' => $map->targetid], '*', MUST_EXIST);
            $instance = $DB->get_record('enrol', ['id' => $ue->enrolid], '*', MUST_EXIST);
            $this->assertSame('manual', $instance->enrol, "{$id} is on a manual instance");
            $this->assertSame('0', (string) $instance->status, "{$id} is on an enabled instance");
            $this->assertSame($course, (int) $instance->courseid, "{$id} course");
            $this->assertSame($user, (int) $ue->userid, "{$id} learner");
            $this->assertSame($status, (int) $ue->status, "{$id} status");
            $this->assertSame($start, (int) $ue->timestart, "{$id} start");
            $this->assertSame($end, (int) $ue->timeend, "{$id} end");
            $this->assertSame(self::T0 + $id, (int) $ue->timecreated, "{$id} timecreated is the source's");
            $this->assertSame(self::T0 + 100 + $id, (int) $ue->timemodified, "{$id} timemodified is the source's");

            // The ledger names where it came from, in ids.
            $ledger = $DB->get_record(enrolments_importer::LEDGER, ['legacyueid' => $id], '*', MUST_EXIST);
            $this->assertSame($original, (int) $ledger->legacyenrolid, "{$id} original instance");
            $this->assertSame($method, $ledger->method, "{$id} original method");
            $this->assertSame($course, (int) $ledger->courseid);
            $this->assertSame((int) $ue->enrolid, (int) $ledger->targetenrolid);
            $this->assertSame(self::T0 + $id, (int) $ledger->timecreated);
            $this->assertSame(self::T0 + 100 + $id, (int) $ledger->timemodified);
        }
    }

    public function test_followers_and_already_manual_learners_point_at_the_enrolment_that_does_the_job(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $nativec = $DB->get_record('user_enrolments', ['id' => $seed->native['c']], '*', MUST_EXIST);

        $this->contract_run(true);

        $map = fn(int $id) => $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS,
            'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame((int) $map(7001)->targetid, (int) $map(7002)->targetid, 'the duplicate row follows its owner');
        $this->assertSame($seed->native['c'], (int) $map(7004)->targetid, 'an existing manual enrolment is reused');
        $this->assertEquals($nativec, $DB->get_record('user_enrolments', ['id' => $seed->native['c']]),
            'and left exactly as it was');
        // Only an owner has a ledger row.
        $this->assertSame(0, $DB->count_records(enrolments_importer::LEDGER, ['legacyueid' => 7002]));
        $this->assertSame(0, $DB->count_records(enrolments_importer::LEDGER, ['legacyueid' => 7004]));
        // One enrolment per learner and instance: the learner of 7001 and 7002 has exactly one in course 1.
        $this->assertSame(1, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :c AND e.enrol = 'manual' AND ue.userid = :u",
            ['c' => $seed->course[1], 'u' => $seed->user['a']]));
    }

    public function test_the_manual_instance_is_reused_created_or_added_beside_a_disabled_one(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $disabled = $DB->get_record('enrol', ['id' => $seed->manual['c4']], '*', MUST_EXIST);

        $this->contract_run(true);

        $manual = fn(int $course) => $DB->get_records('enrol', ['courseid' => $course, 'enrol' => 'manual'], 'id');
        // Courses 1 and 2 had one: nothing is created.
        $this->assertSame([$seed->manual['c1']], array_map('intval', array_keys($manual($seed->course[1]))));
        $this->assertSame([$seed->manual['c2']], array_map('intval', array_keys($manual($seed->course[2]))));

        // Course 3 had none: one enabled instance is created, with the role of the BizLMS method and its first moment.
        $created = $manual($seed->course[3]);
        $this->assertCount(1, $created);
        $new = reset($created);
        $this->assertSame('0', (string) $new->status);
        $this->assertSame($this->studentrole, (int) $new->roleid);
        $this->assertSame(self::T0, (int) $new->timecreated, 'the instance starts when the first BizLMS instance did');
        $others = $DB->get_fieldset_select('enrol', 'sortorder', 'courseid = :c AND id <> :id',
            ['c' => $seed->course[3], 'id' => $new->id]);
        $this->assertSame(max($others) + 1, (int) $new->sortorder, 'placed after the other instances of the course');

        // Course 4 had only a disabled one: it is untouched, and a new enabled one sits beside it.
        $both = $manual($seed->course[4]);
        $this->assertCount(2, $both);
        $this->assertEquals($disabled, $both[$seed->manual['c4']], 'the administrator\'s disabled instance is not changed');
        $beside = array_filter($both, fn($i) => (int) $i->id !== $seed->manual['c4']);
        $this->assertSame('0', (string) reset($beside)->status);

        // The map names each instance decision.
        $instances = $DB->get_records(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_INSTANCES, 'subkey' => ''],
            'sourceid', 'sourceid, outcome, targettable, reason');
        $this->assertSame('folded', $instances[$seed->course[1]]->outcome);
        $this->assertSame('manual_instance_exists', $instances[$seed->course[1]]->reason);
        $this->assertSame('imported', $instances[$seed->course[3]]->outcome);
        $this->assertSame('enrol', $instances[$seed->course[3]]->targettable);
        $this->assertSame('skipped', $instances[$seed->missingcourse]->outcome);
        $this->assertSame('course_missing', $instances[$seed->missingcourse]->reason);
        $this->assertCount(5, $instances, 'one primary map row per course with a BizLMS enrolment');
    }

    public function test_nothing_legacy_is_changed_and_no_role_is_touched(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $legacyids = range(7001, 7012);
        $instances = $DB->get_records_list('enrol', 'id', array_values($seed->inst), 'id');
        $legacy = $DB->get_records_list('user_enrolments', 'id', $legacyids, 'id');
        $bystanders = $DB->get_records_list('user_enrolments', 'id', array_values($seed->native), 'id');
        $roles = $DB->get_records('role_assignments', null, 'id');
        $this->assertNotEmpty($roles, 'the seed gives learner a a role in course 1');

        $this->contract_run(true);

        // Owner decision CRS-01: the only change to a BizLMS instance is the status (and timemodified) of one the import
        // proved safe to switch off, and the trail names exactly those. Nothing is deleted.
        $after = $DB->get_records_list('enrol', 'id', array_values($seed->inst), 'id');
        $this->assertSame(array_keys($instances), array_keys($after), 'no BizLMS instance is deleted');
        $off = array_map('intval', $DB->get_fieldset_sql('SELECT enrolid FROM {' . enrolments_importer::TRAIL . '} ORDER BY enrolid'));
        $this->assertSame([$seed->inst['pr1'], $seed->inst['cl1'], $seed->inst['lp3']], $off,
            'proved: program, classroom and the plan whose only learner has ended; the others hold an unsettled row or have no course');
        sort($off);
        foreach ($instances as $id => $before) {
            if (in_array((int) $id, $off, true)) {
                $this->assertSame('1', (string) $after[$id]->status, "{$id} is switched off");
                $changed = clone $after[$id];
                $changed->status = $before->status;
                $changed->timemodified = $before->timemodified;
                $this->assertEquals($before, $changed, "{$id}: nothing but status and timemodified changed");
            } else {
                $this->assertEquals($before, $after[$id], "{$id} is not touched");
            }
        }
        $this->assertEquals($legacy, $DB->get_records_list('user_enrolments', 'id', $legacyids, 'id'),
            'no legacy enrolment is changed or deleted');
        $this->assertEquals($bystanders, $DB->get_records_list('user_enrolments', 'id', array_values($seed->native), 'id'),
            'no other enrolment is touched: not the manual ones, not the self one');
        $this->assertEquals($roles, $DB->get_records('role_assignments', null, 'id'),
            'the role the legacy instance gave stays as it is: none is added, changed or removed');
    }

    public function test_a_converted_learner_keeps_access_without_the_legacy_instance(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();

        $this->contract_run(true);

        $enrolled = fn(string $learner, int $course): bool => is_enrolled(\context_course::instance($seed->course[$course]),
            $seed->user[$learner], '', true);
        $this->assertTrue($enrolled('a', 1));
        $this->assertTrue($enrolled('b', 1));
        $this->assertTrue($enrolled('a', 2));
        $this->assertTrue($enrolled('c', 2), 'already manual');
        $this->assertFalse($enrolled('d', 3), 'suspended stays suspended');
        $this->assertFalse($enrolled('e', 4), 'an enrolment that ended stays ended');

        // The point: the access no longer depends on the instance of a plugin that is gone. Core's is_enrolled() does
        // not look at the plugin, so the legacy rows alone would keep working until the instance is switched off.
        foreach (['lp1', 'lp2', 'pr1'] as $key) {
            $DB->set_field('enrol', 'status', 1, ['id' => $seed->inst[$key]]);
        }
        $this->assertTrue($enrolled('a', 1));
        $this->assertTrue($enrolled('b', 1));
        $this->assertTrue($enrolled('a', 2));
        $this->assertTrue($enrolled('c', 2), 'already manual');
        // Learner h had an administrator's SUSPENDED manual enrolment and active legacy rows: with the legacy
        // instances off, h has no access, and the import did not reactivate what the administrator suspended.
        $this->assertFalse($enrolled('h', 1));
        $this->assertSame('1', (string) $DB->get_field('user_enrolments', 'status', ['id' => $seed->native['h']]));
    }

    public function test_the_best_row_of_a_pair_decides_the_values(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $learner = (int) $this->getDataGenerator()->create_user()->id;
        // The owner (lowest id) is suspended; the other row of the pair is active and starts later.
        $this->add_enrolment(7021, $seed->inst['lp1'], $learner, 1, self::T0);
        $this->add_enrolment(7022, $seed->inst['lp2'], $learner, 0, self::T0 + 3);

        $this->contract_run(true);

        $owner = $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS,
            'sourceid' => 7021, 'subkey' => ''], '*', MUST_EXIST);
        $follower = $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS,
            'sourceid' => 7022, 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame('imported', $owner->outcome);
        $this->assertSame('folded', $follower->outcome);
        $this->assertSame((int) $owner->targetid, (int) $follower->targetid);
        $ue = $DB->get_record('user_enrolments', ['id' => $owner->targetid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $ue->status, 'the learner keeps the access the second row gave');
        $this->assertSame(self::T0 + 3, (int) $ue->timestart);
        $this->assertSame(self::T0 + 7022, (int) $ue->timecreated, 'the values are the best row\'s, timestamps included');
    }

    public function test_a_row_on_a_disabled_bizlms_instance_is_converted_as_suspended(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        // BizLMS gives nothing on a disabled instance: the import must not give the learner access either.
        $DB->set_field('enrol', 'status', 1, ['id' => $seed->inst['lp3']]);
        $learner = (int) $this->getDataGenerator()->create_user()->id;
        $this->add_enrolment(7031, $seed->inst['lp3'], $learner, 0, self::T0, 0);

        $this->contract_run(true);

        $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS,
            'sourceid' => 7031, 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame('imported', $map->outcome);
        $this->assertSame('1', (string) $DB->get_field('user_enrolments', 'status', ['id' => $map->targetid]));
        $this->assertFalse(is_enrolled(\context_course::instance($seed->course[4]), $learner, '', true));
        $this->assertSame('0', (string) $DB->get_field('user_enrolments', 'status', ['id' => 7031]), 'the legacy row is as it was');

        // Owner decision CRS-02: nothing to switch off on an instance that is already off, and nothing recorded for it.
        $unit = $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_LEGACY_INSTANCES,
            'sourceid' => $seed->inst['lp3'], 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame('skipped', $unit->outcome);
        $this->assertSame('already_disabled', $unit->reason);
        $this->assertSame(0, $DB->count_records(enrolments_importer::TRAIL, ['enrolid' => $seed->inst['lp3']]));
        $this->assertSame('1', (string) $DB->get_field('enrol', 'status', ['id' => $seed->inst['lp3']]), 'left as the administrator had it');
    }

    public function test_the_import_fires_no_enrolment_event(): void {
        $this->contract_begin();
        $this->seed();
        $events = $this->redirectEvents();
        $this->contract_run(true);
        $this->assertSame([], array_map('get_class', $events->get_events()),
            'a direct write: no user_enrolment_created, role_assigned or any other event');
    }

    public function test_a_dry_run_decides_the_same_and_changes_nothing(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $enrol = $DB->get_records('enrol', null, 'id');
        $ues = $DB->get_records('user_enrolments', null, 'id');

        [$result, $report] = $this->contract_run(false);

        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertEquals($enrol, $DB->get_records('enrol', null, 'id'));
        $this->assertEquals($ues, $DB->get_records('user_enrolments', null, 'id'));
        $steps = $report->to_array()['features']['enrolments']['steps'];
        $this->assertSame(5, $steps['enrolments.instances']['counters']['processed']);
        $this->assertSame(2, $steps['enrolments.instances']['counters']['imported']);
        $this->assertSame(12, $steps['enrolments.enrolments']['counters']['processed']);
        $this->assertSame(5, $steps['enrolments.enrolments']['counters']['imported'], 'a pair is decided once, as in apply');
        $this->assertSame(2, $steps['enrolments.enrolments']['counters']['folded']);
        $this->assertSame(5, $steps['enrolments.enrolments']['counters']['skipped']);
        // The access proof needs the manual enrolments a dry run does not write, so the step decides from the map alone
        // (what is settled) and says so; it predicts the same three switch-offs, and the recompute step is not simulated.
        $this->assertSame(6, $steps['enrolments.legacy_instances']['counters']['processed']);
        $this->assertSame(3, $steps['enrolments.legacy_instances']['counters']['imported']);
        $this->assertSame(3, $steps['enrolments.legacy_instances']['counters']['skipped']);
        $this->assertSame('not_simulated_in_a_dry_run', $steps['enrolments.legacy_instances_off']['status']);
        $this->assertSame(0, $DB->count_records(enrolments_importer::TRAIL), 'a dry run writes no trail row');
    }

    public function test_a_second_apply_writes_nothing_to_core(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $enrol = $DB->get_records('enrol', null, 'id');
        $ues = $DB->get_records('user_enrolments', null, 'id');
        $ledger = $DB->get_records(enrolments_importer::LEDGER, null, 'id');
        $roles = $DB->get_records('role_assignments', null, 'id');

        [$result] = $this->contract_run(true);

        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertEquals($enrol, $DB->get_records('enrol', null, 'id'));
        $this->assertEquals($ues, $DB->get_records('user_enrolments', null, 'id'));
        $this->assertEquals($ledger, $DB->get_records(enrolments_importer::LEDGER, null, 'id'));
        $this->assertEquals($roles, $DB->get_records('role_assignments', null, 'id'));
    }

    public function test_preflight_reports_what_will_happen_and_what_the_database_holds(): void {
        $this->contract_begin();
        $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $out = $runner->preflight(['enrolments']);

        $pf = $out['preflights']['enrolments'];
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        $counts = $pf->counts();
        $this->assertSame(7, $counts['bizlms_instances']);
        $this->assertSame(12, $counts['bizlms_enrolments']);
        $this->assertSame(10, $counts['pairs']);
        $this->assertSame(2, $counts['pairs_with_several_legacy_rows']);
        $this->assertSame(7, $counts['pairs_enrolled_only_through_bizlms']);
        $this->assertSame(5, $counts['courses']);
        $this->assertSame(1, $counts['courses_missing']);
        $this->assertSame(2, $counts['will_create_manual_instances']);
        $this->assertSame(2, $counts['will_reuse_manual_instances']);
        $this->assertSame(6, $counts['rows:enrolments.instances']);
        $this->assertSame(12, $counts['rows:enrolments.enrolments']);
        // Only learner a holds a role in a course (the seed's one role assignment): the rest is reported, not changed.
        $this->assertSame(9, $counts['pairs_without_a_role_in_the_course']);
        // 7006 is the one BizLMS enrolment with an end; no course has two enabled manual instances; no manual enrolment ends sooner.
        $this->assertSame(1, $counts['bizlms_enrolments_with_an_end']);
        $this->assertSame(0, $counts['courses_with_several_enabled_manual_instances']);
        $this->assertSame(0, $counts['pairs_where_the_manual_enrolment_ends_sooner']);
        // What the instance step will look at (owner decision CRS-01): the instances that hold enrolments, none disabled yet.
        $this->assertSame(6, $counts['legacy_instances_with_enrolments']);
        $this->assertSame(0, $counts['legacy_instances_already_disabled']);
        $this->assertSame(6, $counts['legacy_instances_to_prove']);
        $this->assertSame(6, $counts['rows:enrolments.legacy_instances']);
        $warnings = implode(' ', $pf->warnings());
        $this->assertStringContainsString('courses_with_only_a_disabled_manual_instance:1', $warnings);
        $this->assertStringContainsString('legacy_enrolments_without_a_course_role:9', $warnings);
        $histograms = $pf->histograms();
        $this->assertEquals(['learningplan' => 5, 'program' => 1, 'classroom' => 1],
            $histograms['enrol.enrol[bizlms instances]']);
        $this->assertEquals(['(none)' => 1], $histograms['role_assignments.component[converted learners, course context]']);
    }

    public function test_a_role_assignment_owned_by_a_component_is_reported(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $DB->set_field('role_assignments', 'component', 'enrol_learningplan', ['userid' => $seed->user['a']]);
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];

        $this->assertStringContainsString('role_assignments_owned_by_a_component:1', implode(' ', $pf->warnings()));
    }

    public function test_an_unknown_enrolment_status_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $DB->set_field('user_enrolments', 'status', 11, ['id' => 7001]);

        [$result] = $this->contract_run(true);

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:user_enrolments.status=11', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE), 'a blocked run writes nothing');
    }

    public function test_a_site_with_manual_enrolment_switched_off_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        // Converted learners would be enrolled on a plugin that grants nothing: exactly the loss this import prevents.
        set_config('enrol_plugins_enabled', 'guest,self');

        [$result] = $this->contract_run(true);

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('manual_enrolment_plugin_disabled', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertSame(0, $DB->count_records(enrolments_importer::LEDGER));
    }

    public function test_the_decision_must_be_the_accepted_one(): void {
        global $DB;
        $this->contract_begin();
        $this->seed();
        $ues = $DB->count_records('user_enrolments');

        [$none] = $this->contract_run(true, ['decisions' => decisions::none()]);
        $this->assertSame(1, $none['exit']);
        $this->assertStringContainsString('missing_decision:gap.orphan_enrol_instances', implode(' ', $none['blockers']));

        [$other] = $this->contract_run(true, ['decisions' => decisions::from_array([
            enrolments_importer::DECISION => 'keep_a_shim'])]);
        $this->assertSame(1, $other['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:gap.orphan_enrol_instances', implode(' ', $other['blockers']));

        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertSame($ues, $DB->count_records('user_enrolments'), 'nothing is converted without the decision');
    }

    public function test_verify_reports_a_converted_enrolment_that_is_gone_or_no_longer_gives_access(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));

        $target = fn(int $id) => (int) $DB->get_field(legacymap::TABLE, 'targetid', [
            'sourcetable' => enrolments_importer::UNIT_ENROLMENTS, 'sourceid' => $id, 'subkey' => '']);

        // Learner b is suspended after the import: the legacy enrolment gave access, the manual one no longer does.
        $DB->set_field('user_enrolments', 'status', 1, ['id' => $target(7003)]);
        $this->assertContains('learners_whose_access_was_not_kept:1', $importer->verify($ctx));
        $DB->set_field('user_enrolments', 'status', 0, ['id' => $target(7003)]);
        $this->assertSame([], $importer->verify($ctx));

        // The manual enrolment of 7001 is deleted: 7001 (converted) and 7002 (its follower) name a row that is gone.
        $DB->delete_records('user_enrolments', ['id' => $target(7001)]);
        $failures = $importer->verify($ctx);
        $this->assertNotEmpty(array_filter($failures, fn($f) => strpos($f,
            'map_names_an_enrolment_that_is_not_a_manual_enrolment_of_the_same_learner_and_course:2') === 0), implode('; ', $failures));

        // Once the site is open an administrator may do either: only the accounting and the ledger are still checked.
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_verify_reports_a_ledger_that_differs_from_the_map(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());

        $DB->insert_record(enrolments_importer::LEDGER, (object) ['legacyueid' => 7777, 'legacyenrolid' => 1, 'method' => 'program',
            'courseid' => 1, 'targetenrolid' => 1, 'timecreated' => 1, 'timemodified' => 1]);

        $failures = $importer->verify($ctx);
        $this->assertContains('ledger_rows_differ_from_imported_map_rows: ledger=6 imported=5', $failures);
        $this->assertContains('ledger_rows_without_an_imported_map_row:1', $failures);
    }

    public function test_a_manual_enrolment_that_ends_sooner_is_not_folded_into(): void {
        global $DB;
        $importer = $this->contract_begin();
        $seed = $this->seed();
        $ids = $this->seed_manual_ends($seed);

        [$result] = $this->contract_run(true);

        $this->assertSame(2, $result['exit'], implode('; ', $result['blockers']));
        $this->assertContains('enrolments:manual_enrolment_ends_sooner=2', $result['unproven']);
        $map = fn(int $id) => $DB->get_record(legacymap::TABLE, ['sourcetable' => enrolments_importer::UNIT_ENROLMENTS,
            'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
        // No end against an end, and a later end against an end: the access would be cut short, so the owner decides.
        foreach (['noend', 'laterend'] as $case) {
            $row = $map($ids[$case]['legacy']);
            $this->assertSame('skipped', $row->outcome, $case);
            $this->assertSame('manual_enrolment_ends_sooner', $row->reason, $case);
            $this->assertNull($row->targetid, $case);
            $this->assertSame(0, $DB->count_records(enrolments_importer::LEDGER, ['legacyueid' => $ids[$case]['legacy']]), $case);
        }
        // An earlier end, the same end and a legacy enrolment that gives no access: nothing to keep, folded as before.
        foreach (['earlierend', 'sameend', 'legacyinactive'] as $case) {
            $row = $map($ids[$case]['legacy']);
            $this->assertSame('folded', $row->outcome, $case);
            $this->assertSame('already_manual', $row->reason, $case);
            $this->assertSame($ids[$case]['manual'], (int) $row->targetid, $case);
        }
        // The administrators' manual enrolments are exactly as they were: never lengthened, never replaced.
        foreach ($ids as $case => $row) {
            $this->assertSame($row['end'], (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $row['manual']]), $case);
        }
        $this->assertSame([], $importer->verify(context::build($importer, false, 0, $this->contract_decisions())));
    }

    public function test_preflight_counts_the_manual_enrolments_that_end_sooner(): void {
        $this->contract_begin();
        $seed = $this->seed();
        $this->seed_manual_ends($seed);
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];

        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        $this->assertSame(2, $pf->counts()['pairs_where_the_manual_enrolment_ends_sooner']);
        $this->assertStringContainsString('manual_enrolments_ending_before_the_legacy_one:2', implode(' ', $pf->warnings()));
    }

    public function test_preflight_counts_courses_with_several_enabled_manual_instances(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];
        $this->assertSame(0, $pf->counts()['courses_with_several_enabled_manual_instances']);
        $this->assertStringNotContainsString('courses_with_several_enabled_manual_instances', implode(' ', $pf->warnings()));

        // A second ENABLED manual instance in course 1, and a disabled one in course 2 (which does not count).
        $DB->insert_record('enrol', (object) ['enrol' => 'manual', 'status' => 0, 'courseid' => $seed->course[1], 'sortorder' => 99,
            'roleid' => $this->studentrole, 'timecreated' => self::T0, 'timemodified' => self::T0]);
        $DB->insert_record('enrol', (object) ['enrol' => 'manual', 'status' => 1, 'courseid' => $seed->course[2], 'sortorder' => 99,
            'roleid' => $this->studentrole, 'timecreated' => self::T0, 'timemodified' => self::T0]);

        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];
        $this->assertSame(1, $pf->counts()['courses_with_several_enabled_manual_instances']);
        $this->assertStringContainsString('courses_with_several_enabled_manual_instances:1', implode(' ', $pf->warnings()));
    }

    public function test_a_site_that_expires_manual_enrolments_blocks_while_converted_enrolments_have_an_end(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);
        $preflight = fn() => $runner->preflight(['enrolments'])['preflights']['enrolments'];

        // The seed has one BizLMS enrolment with an end (7006). Unset and KEEP are the plugin's default: nothing happens.
        $pf = $preflight();
        $this->assertSame(1, $pf->counts()['bizlms_enrolments_with_an_end']);
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        set_config('expiredaction', ENROL_EXT_REMOVED_KEEP, 'enrol_manual');
        $this->assertFalse($preflight()->has_blockers());

        // Every other action acts on the ended manual enrolments at the first cron; UNENROL and SUSPENDNOROLES also
        // remove the roles BizLMS wrote (component empty).
        foreach ([ENROL_EXT_REMOVED_UNENROL, ENROL_EXT_REMOVED_SUSPEND, ENROL_EXT_REMOVED_SUSPENDNOROLES] as $action) {
            set_config('expiredaction', $action, 'enrol_manual');
            $this->assertContains("manual_expiredaction_not_keep:{$action}:enrolments_with_an_end=1", $preflight()->blockers());
        }

        // A blocked run writes nothing.
        set_config('expiredaction', ENROL_EXT_REMOVED_UNENROL, 'enrol_manual');
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertSame(0, $DB->count_records(enrolments_importer::LEDGER));

        // With no BizLMS enrolment that has an end there is nothing for the sync to act on.
        $DB->set_field('user_enrolments', 'timeend', 0, ['id' => 7006]);
        $pf = $preflight();
        $this->assertSame(0, $pf->counts()['bizlms_enrolments_with_an_end']);
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
    }

    public function test_preflight_warns_about_expiry_notices_on_the_instances_the_learners_are_put_on(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];
        $this->assertStringNotContainsString('expiry_notification', implode(' ', $pf->warnings()));

        // Course 1 has an enabled manual instance the learners go on; course 2's is not set.
        $DB->set_field('enrol', 'expirynotify', 1, ['id' => $seed->manual['c1']]);
        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];
        $this->assertStringContainsString('reused_manual_instances_with_expiry_notification:1', implode(' ', $pf->warnings()));
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
    }

    public function test_manual_enrolment_switched_off_does_not_block_a_database_with_nothing_to_convert(): void {
        $this->contract_begin();
        set_config('enrol_plugins_enabled', 'guest,self');
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);

        $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];

        $this->assertSame(0, $pf->counts()['bizlms_enrolments']);
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
    }

    public function test_preflight_counts_learners_enrolled_across_tenants(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 2]);
        $dbman = $DB->get_manager();

        // Without open_path on both tables there are no tenants to compare: nothing is counted and nothing is warned.
        $added = [];
        foreach (['user', 'course'] as $table) {
            if (!isset($DB->get_columns($table)['open_path'])) {
                $added[] = $table;
            }
        }
        if ($added === ['user', 'course']) {
            $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];
            $this->assertArrayNotHasKey('pairs_across_tenants', $pf->counts());
            $this->assertStringNotContainsString('across_tenants', implode(' ', $pf->warnings()));
        }

        foreach ($added as $table) {
            $dbman->add_field(new \xmldb_table($table), new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '254', null, null, null, null));
        }
        try {
            // Courses 1 and 2 are in tenant 1, course 3 in tenant 1, course 4 has no tenant.
            foreach ([1 => '/1/4', 2 => '/1/4', 3 => '/1'] as $n => $path) {
                $DB->set_field('course', 'open_path', $path, ['id' => $seed->course[$n]]);
            }
            // Learner a (courses 1, 2) is in tenant 77 and c (course 2) in tenant 177: three pairs across tenants.
            // b and h are in tenant 1 (a sub-path counts as the same tenant), d has no tenant, e is in a course without one,
            // and the deleted account f is in tenant 77 but is not converted, so it is not counted.
            foreach (['a' => '/77/3', 'b' => '/1/9', 'c' => '/177', 'e' => '/1', 'f' => '/77', 'h' => '/1'] as $key => $path) {
                $DB->set_field('user', 'open_path', $path, ['id' => $seed->user[$key]]);
            }

            $pf = $runner->preflight(['enrolments'])['preflights']['enrolments'];

            $this->assertSame(3, $pf->counts()['pairs_across_tenants']);
            $this->assertStringContainsString('legacy_enrolments_across_tenants:3', implode(' ', $pf->warnings()));
            $this->assertEquals(['77->1' => 2, '177->1' => 1], $pf->histograms()['pairs_across_tenants[user root->course root]']);
            $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        } finally {
            foreach ($added as $table) {
                $dbman->drop_field(new \xmldb_table($table), new \xmldb_field('open_path'));
            }
        }
    }

    public function test_verify_after_go_live_tolerates_a_source_that_shrank(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));

        // Deleting an account removes its enrolments (enrol_user_delete) and a course takes its instances with it: after
        // go-live the BizLMS source is smaller than the map, for good. 7004 and 7012 are the only enrolments of course 2.
        $DB->delete_records('user_enrolments', ['id' => 7003]);
        $DB->delete_records('user_enrolments', ['id' => 7004]);
        $DB->delete_records('user_enrolments', ['id' => 7012]);

        // Before go-live that is a failure: the source and the map must agree.
        $failures = $importer->verify($ctx);
        $this->assertContains('accounting:' . enrolments_importer::UNIT_ENROLMENTS . ': source=9 mapped=12', $failures);
        $this->assertContains('accounting:' . enrolments_importer::UNIT_INSTANCES . ': source=4 mapped=5', $failures);

        // After it, the identity is not asserted any more. A source row without a map row still is.
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $importer->verify($ctx));
        $this->add_enrolment(7950, $this->seeded->inst['lp1'], $this->seeded->user['b']);
        $this->assertContains('unmapped_source_rows:' . enrolments_importer::UNIT_ENROLMENTS . ':1', $importer->verify($ctx));
    }

    public function test_verify_reports_a_converted_enrolment_that_now_ends_before_the_legacy_one(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $target = (int) $DB->get_field(legacymap::TABLE, 'targetid', [
            'sourcetable' => enrolments_importer::UNIT_ENROLMENTS, 'sourceid' => 7003, 'subkey' => '']);

        // The legacy enrolment of 7003 has no end; the manual one is given one in the future: access is still there today
        // but it would stop, which the import must not have done.
        $DB->set_field('user_enrolments', 'timeend', time() + 100 * DAYSECS, ['id' => $target]);
        $this->assertContains('learners_whose_access_was_not_kept:1', $importer->verify($ctx));

        $DB->set_field('user_enrolments', 'timeend', 0, ['id' => $target]);
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_the_required_version_has_exactly_one_upgrade_savepoint_and_is_not_above_the_plugin(): void {
        $dir = dirname(__DIR__);
        $required = enrolments_importer::REQUIRES_VERSION;

        // upgrade_plugin_savepoint() throws downgrade_exception when the stored version is already at or above the
        // savepoint: two blocks with the same number (two importers of this plugin sharing one) fail the second.
        $upgrade = (string) file_get_contents($dir . '/db/upgrade.php');
        $this->assertSame(1, substr_count($upgrade, "upgrade_plugin_savepoint(true, {$required}, 'local', 'sentientia_courses')"));
        $this->assertSame(1, preg_match_all('/\$oldversion < ' . $required . '\)/', $upgrade));

        // The trail table of the owner decision CRS-01 comes from exactly this step, and from install.xml for a new site.
        $this->assertSame(1, substr_count($upgrade, "'" . enrolments_importer::TRAIL . "'"));
        $xml = (string) file_get_contents($dir . '/db/install.xml');
        $this->assertSame(1, substr_count($xml, 'TABLE NAME="' . enrolments_importer::TRAIL . '"'));

        $plugin = new \stdClass();
        include($dir . '/version.php');
        $this->assertGreaterThanOrEqual($required, (int) $plugin->version);
    }

    // Owner decision CRS-01 (2026-10-07): switching off the converted BizLMS instances.

    public function test_a_switched_off_instance_no_longer_grants_access_and_one_update_puts_it_back(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();
        $this->contract_run(true);
        $a = $seed->user['a'];
        $enrolled = fn(): bool => is_enrolled(\context_course::instance($seed->course[2]), $a, '', true);
        $this->assertTrue($enrolled());

        // A Sentientia unenrol removes the converted manual enrolment. With the program instance still ON, core would go on
        // granting access through it (the plugin does not have to exist); switched off, the access is gone, as the
        // administrator intended.
        $target = (int) $DB->get_field(legacymap::TABLE, 'targetid', [
            'sourcetable' => enrolments_importer::UNIT_ENROLMENTS, 'sourceid' => 7012, 'subkey' => '']);
        $DB->delete_records('user_enrolments', ['id' => $target]);
        $this->assertFalse($enrolled(), 'the BizLMS instance is off: removing the manual enrolment removes the access');

        // The undo of the runbook: UPDATE {enrol} SET status = priorstatus, from the trail.
        foreach ($DB->get_records_sql('SELECT id, enrolid, priorstatus FROM {' . enrolments_importer::TRAIL . '}') as $trail) {
            $DB->set_field('enrol', 'status', $trail->priorstatus, ['id' => $trail->enrolid]);
        }
        $this->assertTrue($enrolled(), 'one UPDATE reverses it: the legacy instance grants the access again');
    }

    public function test_a_pair_the_conversion_does_not_carry_keeps_its_instance_enabled_and_needs_the_owner(): void {
        global $DB;
        $importer = $this->contract_begin();
        $seed = $this->seed();
        $now = time();
        $learner = (int) $this->getDataGenerator()->create_user()->id;
        // One learner, two plans in course 2. The owner of the pair (lowest id) gives access now and ends in three days; the
        // other starts in five days and never ends. The conversion carries the best row, the one that gives access now, so the
        // second plan's future access is NOT carried: switching its instance off would take it away.
        $plan9 = $this->add_instance('learningplan', $seed->course[2], 9);
        $plan8 = $this->add_instance('learningplan', $seed->course[2], 8);
        $this->add_enrolment(7061, $plan9, $learner, 0, self::T0, $now + 3 * DAYSECS);
        $this->add_enrolment(7062, $plan8, $learner, 0, $now + 5 * DAYSECS, 0);

        [$result] = $this->contract_run(true);

        $this->assertSame(2, $result['exit'], implode('; ', $result['blockers']));
        $this->assertContains('enrolments:access_regression=1', $result['unproven']);
        $unit = fn(int $instance) => $DB->get_record(legacymap::TABLE, [
            'sourcetable' => enrolments_importer::UNIT_LEGACY_INSTANCES, 'sourceid' => $instance, 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame('imported', $unit($plan9)->outcome, 'its only learner keeps the window through the manual enrolment');
        $this->assertSame('1', (string) $DB->get_field('enrol', 'status', ['id' => $plan9]));
        $held = $unit($plan8);
        $this->assertSame('skipped', $held->outcome);
        $this->assertSame('access_regression', $held->reason);
        $this->assertSame('learners_would_lose_access', $held->detail);
        $this->assertSame('0', (string) $DB->get_field('enrol', 'status', ['id' => $plan8]), 'the instance stays enabled');
        $this->assertSame(0, $DB->count_records(enrolments_importer::TRAIL, ['enrolid' => $plan8]));
        // verify() has nothing to say about an instance that was left enabled.
        $this->assertSame([], $importer->verify(context::build($importer, false, 0, $this->contract_decisions())));
    }

    public function test_an_instance_that_holds_an_unsettled_row_stays_enabled_even_when_its_reasons_are_accepted(): void {
        global $DB;
        $this->contract_begin();
        $seed = $this->seed();

        [$result] = $this->contract_run(true, ['decisions' => decisions::from_array(enrolments_importer::SIGNED_VALUES + [
            'accepted_reasons' => ['enrolments:manual_enrolment_inactive', 'enrolments:user_deleted']])]);

        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        foreach (['lp1', 'lp2'] as $key) {
            $this->assertSame('0', (string) $DB->get_field('enrol', 'status', ['id' => $seed->inst[$key]]),
                "{$key} holds a row the owner accepted as skipped, and that learner keeps today's access until L&D acts");
        }
        // And learner h, whose administrator-suspended manual enrolment the import left alone, still has access through
        // the BizLMS instance, exactly as BizLMS gave it.
        $this->assertTrue(is_enrolled(\context_course::instance($seed->course[1]), $seed->user['h'], '', true));
    }

    public function test_verify_proves_the_access_of_the_switched_off_instances_again(): void {
        global $DB;
        $importer = $this->contract_begin();
        $seed = $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));
        $target = (int) $DB->get_field(legacymap::TABLE, 'targetid', [
            'sourcetable' => enrolments_importer::UNIT_ENROLMENTS, 'sourceid' => 7012, 'subkey' => '']);

        // The converted enrolment of 7012 is given an end in the future: the program instance is off, and the learner would
        // lose the access the legacy row never limited.
        $DB->set_field('user_enrolments', 'timeend', time() + 10 * DAYSECS, ['id' => $target]);
        $failures = implode(' | ', $importer->verify($ctx));
        $this->assertStringContainsString(
            'switched_off_instances_where_access_was_not_kept:instances=' . $seed->inst['pr1'] . ' pairs=1 enrolments=7012',
            $failures);

        // The converted enrolment is gone altogether.
        $DB->set_field('user_enrolments', 'timeend', 0, ['id' => $target]);
        $DB->delete_records('user_enrolments', ['id' => $target]);
        $this->assertStringContainsString('switched_off_instances_where_access_was_not_kept:instances=' . $seed->inst['pr1'],
            implode(' | ', $importer->verify($ctx)));
    }

    public function test_verify_says_so_when_the_trail_names_an_instance_that_is_on(): void {
        global $DB;
        $importer = $this->contract_begin();
        $seed = $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());

        $DB->set_field('enrol', 'status', 0, ['id' => $seed->inst['pr1']]);

        $this->assertContains('trail_instances_still_enabled:1:' . $seed->inst['pr1'], $importer->verify($ctx));
        // Once the site is open an administrator may switch an instance back on: that is not a failure any more.
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_verify_reports_a_trail_that_differs_from_the_map(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());

        $DB->insert_record(enrolments_importer::TRAIL, (object) ['enrolid' => 7777, 'courseid' => 1, 'method' => 'program',
            'priorstatus' => 0, 'timecreated' => 1, 'timemodified' => 1]);

        $failures = $importer->verify($ctx);
        $this->assertContains('trail_rows_differ_from_imported_map_rows: trail=4 imported=3', $failures);
        $this->assertContains('trail_rows_without_an_imported_map_row:1', $failures);
    }

    public function test_the_recompute_step_is_idempotent_and_stops_after_go_live(): void {
        global $DB;
        $importer = $this->contract_begin();
        $seed = $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $step = new \local_sentientia_courses\bizlms\enrolments_legacy_instances_off_step();
        $trailids = array_map('intval', $DB->get_fieldset_sql('SELECT id FROM {' . enrolments_importer::TRAIL . '} ORDER BY id'));
        $this->assertCount(3, $trailids);

        $this->assertSame([], $step->recompute($trailids, $ctx), 'the instances are already off: nothing to write');

        // An administrator switched the program instance back on: a repeat run switches it off again, by update only.
        $DB->set_field('enrol', 'status', 0, ['id' => $seed->inst['pr1']]);
        $out = $step->recompute($trailids, $ctx);
        $this->assertCount(1, $out);
        $this->assertSame('update', $out[0]->kind);
        $this->assertSame('enrol', $out[0]->table);
        $this->assertSame($seed->inst['pr1'], $out[0]->targetid);
        $this->assertSame(1, (int) $out[0]->row->status);
        $this->assertSame(['status', 'timemodified'], array_keys(get_object_vars($out[0]->row)), 'nothing else is changed');

        // After go-live the administrator's choice stays.
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $step->recompute($trailids, $ctx));
    }

    public function test_the_recompute_step_refuses_an_instance_that_is_no_longer_proved(): void {
        global $DB;
        $importer = $this->contract_begin();
        $seed = $this->seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $step = new \local_sentientia_courses\bizlms\enrolments_legacy_instances_off_step();
        $trailids = array_map('intval', $DB->get_fieldset_sql('SELECT id FROM {' . enrolments_importer::TRAIL . '} ORDER BY id'));
        // The program instance is on again and the learner's converted enrolment is gone: switching it off would cost access.
        $DB->set_field('enrol', 'status', 0, ['id' => $seed->inst['pr1']]);
        $target = (int) $DB->get_field(legacymap::TABLE, 'targetid', [
            'sourcetable' => enrolments_importer::UNIT_ENROLMENTS, 'sourceid' => 7012, 'subkey' => '']);
        $DB->delete_records('user_enrolments', ['id' => $target]);

        $this->expectException(\local_sentientia_platform\bizlms\bizlms_exception::class);
        $this->expectExceptionMessage('legacy_instance_not_proved:' . $seed->inst['pr1']);
        $step->recompute($trailids, $ctx);
    }

    public function test_the_access_windows_cover_exactly_what_core_would_grant(): void {
        $now = 1000;
        $window = fn(int $s, int $e) => enrolments_access::window($s, $e, $now);
        $never = PHP_INT_MAX;

        $this->assertNull($window(0, 500), 'an enrolment that has ended grants nothing now or later');
        $this->assertNull($window(0, 1000), 'it ends at "now"');
        $this->assertNull($window(2000, 1500), 'core ignores an end before the start');
        $this->assertSame([1000, $never], $window(10, 0), 'a start in the past is "now" from here on; 0 never ends');
        $this->assertSame([3000, 5000], $window(3000, 5000), 'a start in the future is kept');

        $this->assertSame([[1000, 4000]], enrolments_access::merge([[10, 2000], [2000, 4000]], $now), 'touching windows are one');
        $this->assertSame([[1000, 2000], [3000, 4000]], enrolments_access::merge([[10, 2000], [3000, 4000]], $now), 'a gap stays');
        $this->assertSame([[1000, $never]], enrolments_access::merge([[10, 0], [50, 3000]], $now));
        $this->assertSame([], enrolments_access::merge([[0, 500]], $now));

        $covers = fn(array $raw, int $s, int $e): bool => enrolments_access::covers(enrolments_access::merge($raw, $now),
            enrolments_access::window($s, $e, $now));
        $this->assertTrue($covers([[10, 0]], 10, 5000), 'a never-ending window holds a shorter one');
        $this->assertTrue($covers([[10, 5000]], 10, 5000), 'the same end');
        $this->assertFalse($covers([[10, 5000]], 10, 0), 'a shorter manual enrolment does not hold a never-ending one');
        $this->assertFalse($covers([[10, 5000]], 10, 5001), 'one second short');
        $this->assertFalse($covers([[10, 2000], [3000, 0]], 10, 0), 'a gap in the manual enrolments is a gap in the access');
        $this->assertTrue($covers([[10, 2000], [2000, 0]], 10, 0), 'two touching manual enrolments hold it');
        $this->assertFalse($covers([[3000, 0]], 10, 0), 'a manual enrolment that starts later does not hold access that exists now');
        $this->assertTrue($covers([[10, 0]], 3000, 5000), 'access that starts in the future is held by a manual enrolment already running');
        $this->assertFalse($covers([], 10, 0), 'no manual enrolment holds nothing');
    }

    public function test_a_row_is_settled_only_when_it_is_carried_over_or_has_nothing_to_carry(): void {
        $entry = fn(string $outcome, ?int $target, ?string $reason = null): array => [
            'outcome' => $outcome, 'targetid' => $target, 'reason' => $reason, 'targettable' => '', 'id' => 1];

        $this->assertTrue(enrolments_access::settled($entry('imported', 5)));
        $this->assertTrue(enrolments_access::settled($entry('folded', 5, 'duplicate_pair')));
        $this->assertFalse(enrolments_access::settled($entry('folded', null, 'duplicate_pair')), 'a fold with no target carries nothing');
        $this->assertTrue(enrolments_access::settled($entry('skipped', null, 'user_missing')));
        $this->assertTrue(enrolments_access::settled($entry('skipped', null, 'course_missing')));
        $this->assertTrue(enrolments_access::settled($entry('skipped', null, 'instance_missing')));
        foreach (['user_deleted', 'manual_enrolment_inactive', 'manual_enrolment_ends_sooner'] as $reason) {
            $this->assertFalse(enrolments_access::settled($entry('skipped', null, $reason)), $reason);
        }
        $this->assertFalse(enrolments_access::settled($entry('archived', null, 'x')));
        $this->assertFalse(enrolments_access::settled(null), 'a row nobody decided about is not settled');
    }

    public function test_the_unsettled_and_no_access_reasons_match_the_importers_vocabulary(): void {
        $importer = new enrolments_importer();
        $reasons = [];
        foreach ($importer->reasons() as $reason) {
            $reasons[$reason->code] = $reason;
        }
        foreach (enrolments_access::NO_ACCESS_REASONS as $code) {
            $this->assertArrayHasKey($code, $reasons, $code);
            $this->assertFalse($reasons[$code]->needsowner, "{$code} leaves nothing for the owner to decide");
        }
        // Every row-level reason that needs the owner keeps the instance enabled: none of them is a "no access" reason.
        foreach ($reasons as $code => $reason) {
            if ($reason->needsowner && $code !== 'access_regression') {
                $this->assertNotContains($code, enrolments_access::NO_ACCESS_REASONS, $code);
            }
        }
        foreach (['already_disabled', 'rows_unsettled', 'access_regression'] as $code) {
            $this->assertArrayHasKey($code, $reasons, $code);
        }
        $this->assertTrue($reasons['access_regression']->needsowner);
        $this->assertFalse($reasons['rows_unsettled']->needsowner, 'the rows carry their own reasons');
        $this->assertFalse($reasons['already_disabled']->needsowner);
    }

    // Seed.

    /**
     * Five learners in course 1, each with one legacy enrolment on lp1 and an administrator's manual enrolment (active, with an
     * end in the future) on the course's manual instance, to see when the import folds into it.
     *
     * @param \stdClass $seed
     * @return array<string, array{legacy: int, manual: int, end: int}> Case => legacy enrolment id, manual enrolment id, manual end.
     */
    private function seed_manual_ends(\stdClass $seed): array {
        $manualend = time() + 100 * DAYSECS;
        // Case => [legacy id, legacy status, legacy end].
        $cases = [
            'noend' => [7041, 0, 0],
            'laterend' => [7042, 0, $manualend + DAYSECS],
            'earlierend' => [7043, 0, $manualend - DAYSECS],
            'sameend' => [7044, 0, $manualend],
            'legacyinactive' => [7045, 1, 0],
        ];
        $ids = [];
        foreach ($cases as $case => [$legacy, $status, $end]) {
            $learner = (int) $this->getDataGenerator()->create_user()->id;
            $manual = $this->add_enrolment(0, $seed->manual['c1'], $learner, 0, self::T0, $manualend);
            $this->add_enrolment($legacy, $seed->inst['lp1'], $learner, $status, self::T0, $end);
            $ids[$case] = ['legacy' => $legacy, 'manual' => $manual, 'end' => $manualend];
        }
        return $ids;
    }

    /**
     * Put the seed into core enrol and user_enrolments.
     *
     * @return \stdClass course (1..4), missingcourse, user (a..h), inst (BizLMS instances), manual, native, studentrole.
     */
    private function seed(): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $seed = (object) ['course' => [], 'user' => [], 'inst' => [], 'manual' => [], 'native' => []];
        $this->studentrole = (int) $DB->get_field('role', 'id', ['archetype' => 'student'], IGNORE_MULTIPLE);
        $seed->studentrole = $this->studentrole;

        foreach ([1, 2, 3, 4] as $n) {
            $seed->course[$n] = (int) $gen->create_course()->id;
        }
        $seed->missingcourse = max($seed->course) + 1000;
        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'h'] as $key) {
            $seed->user[$key] = (int) $gen->create_user()->id;
        }
        $seed->user['g'] = max($seed->user) + 1000;
        $DB->set_field('user', 'deleted', 1, ['id' => $seed->user['f']]);

        // Manual instances: courses 1 and 2 one enabled, course 3 none, course 4 only a disabled one.
        $seed->manual['c1'] = $this->ensure_manual($seed->course[1]);
        $seed->manual['c2'] = $this->ensure_manual($seed->course[2]);
        $DB->delete_records('enrol', ['courseid' => $seed->course[3], 'enrol' => 'manual']);
        $seed->manual['c4'] = $this->ensure_manual($seed->course[4]);
        $DB->set_field('enrol', 'status', 1, ['id' => $seed->manual['c4']]);

        // The BizLMS instances, in this order so a group's lowest id is predictable.
        $seed->inst['lp1'] = $this->add_instance('learningplan', $seed->course[1], 1);
        $seed->inst['lp2'] = $this->add_instance('learningplan', $seed->course[1], 2);
        $seed->inst['lp5'] = $this->add_instance('learningplan', $seed->course[1], 5);
        $seed->inst['pr1'] = $this->add_instance('program', $seed->course[2], 10);
        $seed->inst['cl1'] = $this->add_instance('classroom', $seed->course[3], 20);
        $seed->inst['lp3'] = $this->add_instance('learningplan', $seed->course[4], 3);
        $seed->inst['lp4'] = $this->add_instance('learningplan', $seed->missingcourse, 4);
        $seed->inst['self1'] = $this->add_instance('self', $seed->course[1], 0);

        // Enrolments that exist already and are not the import's business.
        $seed->native['c'] = $this->add_enrolment(0, $seed->manual['c2'], $seed->user['c']);
        $seed->native['h'] = $this->add_enrolment(0, $seed->manual['c1'], $seed->user['h'], 1);
        $seed->native['d'] = $this->add_enrolment(0, $seed->manual['c1'], $seed->user['d']);
        $seed->native['b'] = $this->add_enrolment(0, $seed->inst['self1'], $seed->user['b']);

        // The legacy enrolments.
        $this->add_enrolment(7001, $seed->inst['lp1'], $seed->user['a']);
        $this->add_enrolment(7002, $seed->inst['lp2'], $seed->user['a']);
        $this->add_enrolment(7003, $seed->inst['lp1'], $seed->user['b']);
        $this->add_enrolment(7004, $seed->inst['pr1'], $seed->user['c']);
        $this->add_enrolment(7005, $seed->inst['cl1'], $seed->user['d'], 1, self::T0 + 5);
        $this->add_enrolment(7006, $seed->inst['lp3'], $seed->user['e'], 0, self::T0, self::T0 + 1000);
        $this->add_enrolment(7007, $seed->inst['lp1'], $seed->user['f']);
        $this->add_enrolment(7008, $seed->inst['lp1'], $seed->user['g']);
        $this->add_enrolment(7009, $seed->inst['lp1'], $seed->user['h']);
        $this->add_enrolment(7010, $seed->inst['lp2'], $seed->user['h']);
        $this->add_enrolment(7011, $seed->inst['lp4'], $seed->user['b']);
        $this->add_enrolment(7012, $seed->inst['pr1'], $seed->user['a']);

        // BizLMS wrote role assignments with component empty and itemid 0, and never touched them again.
        role_assign($this->studentrole, $seed->user['a'], \context_course::instance($seed->course[1])->id);

        $this->seeded = $seed;
        return $seed;
    }

    /**
     * An enabled manual instance of a course: the one the course has, or a new one.
     *
     * @param int $courseid
     * @return int
     */
    private function ensure_manual(int $courseid): int {
        global $DB;
        $id = $DB->get_field_select('enrol', 'id', "courseid = :c AND enrol = 'manual'", ['c' => $courseid], IGNORE_MULTIPLE);
        if ($id) {
            $DB->set_field('enrol', 'status', 0, ['id' => $id]);
            return (int) $id;
        }
        return (int) $DB->insert_record('enrol', (object) ['enrol' => 'manual', 'status' => 0, 'courseid' => $courseid,
            'sortorder' => 0, 'roleid' => $this->studentrole, 'timecreated' => self::T0, 'timemodified' => self::T0]);
    }

    /**
     * An enrol instance of a given method, as BizLMS left it (customint1 = the classroom, program or plan).
     *
     * @param string $method
     * @param int $courseid
     * @param int $plan
     * @return int
     */
    private function add_instance(string $method, int $courseid, int $plan): int {
        global $DB;
        return (int) $DB->insert_record('enrol', (object) ['enrol' => $method, 'status' => 0, 'courseid' => $courseid,
            'sortorder' => 10 + $plan, 'roleid' => $this->studentrole, 'customint1' => $plan,
            'timecreated' => self::T0, 'timemodified' => self::T0]);
    }

    /**
     * A row of user_enrolments. A positive id is explicit (the legacy ids), 0 takes the next one.
     *
     * @param int $id
     * @param int $enrolid
     * @param int $userid
     * @param int $status
     * @param int $start
     * @param int $end
     * @return int The id.
     */
    private function add_enrolment(int $id, int $enrolid, int $userid, int $status = 0, int $start = self::T0, int $end = 0): int {
        global $DB;
        $row = (object) ['status' => $status, 'enrolid' => $enrolid, 'userid' => $userid, 'timestart' => $start,
            'timeend' => $end, 'modifierid' => 0, 'timecreated' => self::T0 + $id, 'timemodified' => self::T0 + 100 + $id];
        if ($id > 0) {
            $row->id = $id;
            $DB->import_record('user_enrolments', $row);
            return $id;
        }
        $row->timecreated = self::T0;
        $row->timemodified = self::T0;
        return (int) $DB->insert_record('user_enrolments', $row);
    }
}
