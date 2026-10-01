<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as importer_contract_type;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_request\bizlms\importer;
use local_sentientia_request\tests\bizlms\dependency_stub;

/**
 * The request feature of the BizLMS import (ADR-032; mapping doc, section 19).
 *
 * The contract every importer passes (importer_contract: dry run writes nothing, apply reconciles, a second
 * apply is a no-op, resume after a crash, a changed source is detected, no side effects, privacy) plus the
 * request-specific shapes: item and status mapping, routing of legacy pending requests, tenant, derived
 * timestamps, the approval rows that fold into a request row, the comment thread that becomes the decision
 * note, and every reason and blocker.
 *
 * The seed is realistic: the two BizLMS plugins' tables come from checked-in copies of their install.xml
 * (tests/fixtures/bizlms/), and the classroom, program and learning-plan features the importer depends on
 * are represented by stand-ins plus the map rows their importers would have written.
 *
 * @package    local_sentientia_request
 * @category   test
 * @covers     \local_sentientia_request\bizlms\importer
 * @covers     \local_sentientia_request\bizlms\records_step
 * @covers     \local_sentientia_request\bizlms\approvals_step
 * @covers     \local_sentientia_request\bizlms\comments_step
 *
 * @group local_sentientia_request
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract {
        contract_begin as private trait_contract_begin;
        contract_clear_import as private trait_contract_clear_import;
    }
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var array<string, mixed> Decision values a test overrides. */
    private array $overrides = [];

    /** @var array<string, \stdClass> The users of the seed, by role. */
    private array $u = [];

    /** @var array<string, int> The courses of the seed. */
    private array $c = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/request.install.xml'];
    }

    // The contract.

    protected function contract_importer(): importer_contract_type {
        return new importer();
    }

    /**
     * Register the importer and the stand-ins of the three features it depends on.
     *
     * @return importer_contract_type
     */
    protected function contract_begin(): importer_contract_type {
        $importer = $this->trait_contract_begin();
        $this->ensure_bizlms_schema();
        registry::set_testing_importers([
            new dependency_stub('learningplan', ['local_learningplan']),
            new dependency_stub('classroom', ['local_classroom']),
            new dependency_stub('program', ['local_program']),
            $importer,
        ]);
        return $importer;
    }

    /**
     * The clear the resume tests do wipes the map, which also holds the stand-ins' rows: put them back.
     *
     * @param importer_contract_type $importer
     * @return void
     */
    protected function contract_clear_import(importer_contract_type $importer): void {
        $this->trait_contract_clear_import($importer);
        $this->seed_dependency_map();
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array(array_merge([
            'request.pending' => 'actionable',
            'request.pending_classroom_program' => 'history_only',
            'request.certification' => 'unmapped',
            'request.decided_route' => 'admin',
            'request.hidden_rows' => 'show',
            'request.tenant_basis' => 'requester_current_root',
            'request.pending_approver' => 'sentientia_routing',
            'request.comments' => 'fold_into_decision_note',
            'tenant.unresolved.request' => 'pathless',
        ], $this->overrides));
    }

    protected function contract_requires_direct_org_dependency(): bool {
        // The target stores a tenant root, not a path, so the importer declares no tenant column; the
        // organisation data it reads for tenant roots is reached through the three features it depends on.
        return false;
    }

    protected function contract_user_columns(): array {
        return ['local_sentientia_request' => ['userid', 'approver_userid', 'decided_by_userid']];
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the highest id on every engine, so detection does not rest on the CRC.
        $this->record(500, $this->u['u1'], 'elearning', $this->c['c1'], 'PENDING');
    }

    protected function contract_seed(): void {
        global $DB;
        $this->ensure_approval_table();
        $t0 = self::T0;

        // The approver a request falls back to when the requester has no supervisor and the course no owner.
        set_config('default_approver', (int) get_admin()->id, 'local_sentientia_request');

        $sup = $this->user('/1/5');
        $this->u = [
            'sup' => $sup,
            'u1' => $this->user('/1/5', ['open_supervisorid' => $sup->id]),
            'u2' => $this->user('/77'),
            'u3' => $this->user(''),
            'u4' => $this->user('/5'),
            'resp' => $this->user('/1/5'),
            'dead' => $this->user('/1', ['deleted' => 1]),
            'susp' => $this->user('/1', ['suspended' => 1]),
        ];
        $this->c = [
            'c1' => (int) $this->getDataGenerator()->create_course()->id,
            'c2' => (int) $this->getDataGenerator()->create_course()->id,
        ];
        $this->seed_dependency_map();

        [$u1, $u2, $u3, $u4, $resp, $dead, $susp] = [
            $this->u['u1'], $this->u['u2'], $this->u['u3'], $this->u['u4'], $this->u['resp'], $this->u['dead'],
            $this->u['susp'],
        ];
        $r = (int) $resp->id;

        // local_request_records.
        $this->record(1, $u1, 'elearning', $this->c['c1'], 'PENDING');
        $this->record(2, $u2, 'elearning', $this->c['c2'], 'APPROVED', ['responder' => $r, 'respondeddate' => $t0 + 5000]);
        $this->record(3, $u1, 'learningplan', 11, 'REJECTED', ['responder' => $r, 'respondeddate' => $t0 + 6000]);
        $this->record(4, $u2, 'classroom', 21, 'PENDING');
        $this->record(5, $u1, 'program', 31, 'APPROVED', ['responder' => $r, 'respondeddate' => $t0 + 7000]);
        // A duplicate pair: BizLMS let the same learner request the same course twice.
        $this->record(6, $u3, 'elearning', $this->c['c1'], 'PENDING');
        $this->record(7, $u3, 'elearning', $this->c['c1'], 'PENDING');
        // No timecreated and no timemodified: the decision time stands in for both.
        $this->record(8, $u1, 'elearning', $this->c['c2'], 'PENDING',
            ['timecreated' => null, 'timemodified' => null, 'respondeddate' => $t0 + 8000]);
        // The course was deleted since.
        $this->record(9, $u2, 'elearning', 999999, 'APPROVED', ['responder' => $r, 'respondeddate' => $t0 + 8500]);
        // The requester has no user row.
        $this->record(10, 987654, 'elearning', $this->c['c1'], 'PENDING');
        // A certification: no Sentientia entity exists for it, and the requester's root is not a registered tenant.
        $this->record(11, $u4, 'certification', 77, 'PENDING');
        // A deleted requester, a suspended one.
        $this->record(12, $dead, 'learningplan', 12, 'PENDING');
        $this->record(13, $susp, 'elearning', $this->c['c1'], 'APPROVED', ['responder' => $r, 'respondeddate' => $t0 + 8800]);
        // A classroom the classroom import archived: there is nothing to point at.
        $this->record(14, $u1, 'classroom', 22, 'PENDING');
        // A learning plan that no longer exists in BizLMS.
        $this->record(15, $u1, 'learningplan', 13, 'PENDING');
        // The request with a comment thread.
        $this->record(16, $u2, 'elearning', $this->c['c1'], 'REJECTED', ['responder' => $r, 'respondeddate' => $t0 + 9000]);

        // local_learningplan_approval.
        $this->approval(1, $u1, 11, 2, ['approvedby' => $r, 'usermodified' => $r]);
        $this->approval(2, $u2, 12, 0, ['usermodified' => (int) $u2->id]);
        $this->approval(3, $u3, 12, 1, ['usermodified' => $r]);
        $this->approval(4, $u1, 99, 0);
        $this->approval(5, 876543, 11, 0);
        $this->approval(6, $u3, 11, 2, ['approvedby' => $r, 'usermodified' => $r,
            'reject_msg' => '<p>Not eligible this quarter</p>']);

        // local_request_comments.
        $this->comment(1, '16', $r, '2026-03-05 14:22:10', '<p>Please attach the manager approval</p>');
        $this->comment(2, '16', (int) $u2->id, '2026-03-04 09:00:00', 'Attached.');
        $this->comment(3, '999', $r, '2026-03-06 10:00:00', 'Nobody asked for this request.');
        $this->comment(4, 'abc', $r, '2026-03-06 10:05:00', 'Not a request id.');
        $this->comment(5, '10', $r, '2026-03-06 10:10:00', 'Its request was not imported.');
    }

    // Seed helpers.

    /**
     * A user with a tenant path.
     *
     * @param string $path open_path ('' for none).
     * @param array $fields Other columns to set.
     * @return \stdClass
     */
    private function user(string $path, array $fields = []): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        foreach ($fields as $name => $value) {
            $DB->set_field('user', $name, $value, ['id' => $user->id]);
        }
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * The map rows the classroom, program and learning-plan importers would have written, for the ids the seed
     * uses. They are stand-ins, so they are recorded as folded (a row that became part of another): the contract
     * checks that every imported map row points at a target row that exists, and these targets are not built.
     *
     * Learning plans 11 and 12, classroom 21 and program 31 resolve to themselves (the ids are preserved).
     * Classroom 22 was archived (no target). Learning plan 13 is not in the map at all: it never existed.
     *
     * @return void
     */
    private function seed_dependency_map(): void {
        global $DB;
        $rows = [
            ['learningplan', 'local_learningplan', 'local_sentientia_learningpath', 11, 'folded'],
            ['learningplan', 'local_learningplan', 'local_sentientia_learningpath', 12, 'folded'],
            ['classroom', 'local_classroom', 'local_sentientia_classroom', 21, 'folded'],
            ['classroom', 'local_classroom', '', 22, 'archived'],
            ['program', 'local_program', 'local_sentientia_programs', 31, 'folded'],
        ];
        foreach ($rows as [$feature, $source, $target, $id, $outcome]) {
            if ($DB->record_exists(legacymap::TABLE, ['sourcetable' => $source, 'sourceid' => $id, 'subkey' => ''])) {
                continue;
            }
            $DB->insert_record(legacymap::TABLE, (object) [
                'feature' => $feature, 'sourcetable' => $source, 'sourceid' => $id, 'subkey' => '',
                'targettable' => $target, 'targetid' => $target === '' ? null : $id, 'outcome' => $outcome,
                'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => self::T0,
            ]);
        }
    }

    /**
     * Create local_learningplan_approval, which lives in the learning-plan plugin's install.xml and so is a
     * fixture of its own. The class fixture removes it after each test (reset_database).
     *
     * @return void
     */
    private function ensure_approval_table(): void {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_learningplan_approval')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/fixtures/bizlms/learningplan.install.xml',
                'local_learningplan_approval');
        }
    }

    /**
     * One local_request_records row.
     *
     * @param int $id
     * @param \stdClass|int $user The requester, or a user id.
     * @param string $compname
     * @param int $componentid
     * @param string $status
     * @param array $extra Columns to override.
     * @return void
     */
    private function record(int $id, \stdClass|int $user, string $compname, int $componentid, string $status,
                            array $extra = []): void {
        global $DB;
        $userid = is_object($user) ? (int) $user->id : $user;
        $DB->import_record('local_request_records', (object) ($extra + [
            'id' => $id, 'createdbyid' => $userid, 'compname' => $compname, 'compcode' => null, 'compkey' => null,
            'module_id' => $componentid, 'componentid' => $componentid, 'status' => $status, 'req_type' => null,
            'req_values' => null, 'responder' => null, 'respondeddate' => null, 'usermodified' => $userid,
            'timecreated' => self::T0 + $id, 'timemodified' => self::T0 + 100 + $id,
            'c1' => null, 'c2' => null, 'c3' => null,
        ]));
    }

    /**
     * One local_learningplan_approval row.
     *
     * @param int $id
     * @param \stdClass|int $user
     * @param int $planid
     * @param int $approvestatus 0 pending, 1 approved, 2 rejected.
     * @param array $extra Columns to override.
     * @return void
     */
    private function approval(int $id, \stdClass|int $user, int $planid, int $approvestatus, array $extra = []): void {
        global $DB;
        $userid = is_object($user) ? (int) $user->id : $user;
        $DB->import_record('local_learningplan_approval', (object) ($extra + [
            'id' => $id, 'planid' => $planid, 'userid' => $userid, 'approvedby' => 0,
            'approvestatus' => $approvestatus, 'reject_msg' => null,
            'timecreated' => self::T0 + 200 + $id, 'timemodified' => self::T0 + 300 + $id,
            'usermodified' => $userid,
        ]));
    }

    /**
     * One local_request_comments row.
     *
     * @param int $id
     * @param string $instanceid The request id, as BizLMS stored it (a char column).
     * @param int $userid
     * @param string $dt
     * @param string $message
     * @return void
     */
    private function comment(int $id, string $instanceid, int $userid, string $dt, string $message): void {
        global $DB;
        $DB->import_record('local_request_comments', (object) [
            'id' => $id, 'instanceid' => $instanceid, 'createdbyid' => $userid, 'dt' => $dt, 'message' => $message,
        ]);
    }

    /**
     * The target row a source row became.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass
     */
    private function imported(string $sourcetable, int $sourceid): \stdClass {
        global $DB;
        $map = $this->map($sourcetable, $sourceid);
        $this->assertSame('imported', $map->outcome, "{$sourcetable} {$sourceid}");
        return $DB->get_record($map->targettable, ['id' => $map->targetid], '*', MUST_EXIST);
    }

    /**
     * The primary map row of a source row.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass
     */
    private function map(string $sourcetable, int $sourceid): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE,
            ['sourcetable' => $sourcetable, 'sourceid' => $sourceid, 'subkey' => ''], '*', MUST_EXIST);
    }

    /**
     * Assert the listed columns of a row.
     *
     * @param \stdClass $row
     * @param array<string, mixed> $expected column => value (null means SQL NULL)
     * @param string $label
     * @return void
     */
    private function assert_columns(\stdClass $row, array $expected, string $label): void {
        foreach ($expected as $column => $value) {
            if ($value === null) {
                $this->assertNull($row->{$column}, "{$label}: {$column} is NULL");
            } else {
                $this->assertEquals($value, $row->{$column}, "{$label}: {$column}");
            }
        }
    }

    /**
     * Apply the import and require it to finish.
     *
     * @param array $options Runner options.
     * @return array The run result.
     */
    private function apply(array $options = []): array {
        [$result] = $this->contract_run(true, $options);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        return $result;
    }

    // The mapping.

    public function test_depends_exactly_as_the_map_says(): void {
        $this->assertSame(['classroom', 'program', 'learningplan'], (new importer())->depends());
    }

    public function test_the_signed_decisions_satisfy_the_importer(): void {
        global $CFG;
        // The owner-signed file, through the byte-identical copy the platform plugin ships for its tests.
        $signed = decisions::load($CFG->dirroot
            . '/local/sentientia_platform/tests/fixtures/bizlms/bizlms-import-decisions.copy.json');
        foreach ((new importer())->decisions() as $decision) {
            $this->assertTrue($signed->has($decision->key), "{$decision->key} is accepted in the signed file");
            if ($decision->allowed !== null) {
                $this->assertContains($signed->get($decision->key), $decision->allowed, $decision->key);
            }
            $this->assertStringNotContainsString('cart.', $decision->key, 'the finance-confirm cart keys are not declared');
        }
    }

    public function test_every_request_is_mapped_item_status_tenant_and_times(): void {
        $this->contract_begin();
        $this->contract_seed();
        $admin = (int) get_admin()->id;
        $t0 = self::T0;
        $r = (int) $this->u['resp']->id;
        $sup = (int) $this->u['sup']->id;
        $this->apply();

        $records = 'local_request_records';
        $this->assert_columns($this->imported($records, 1), [
            'userid' => $this->u['u1']->id, 'item_type' => 'course', 'itemid' => $this->c['c1'],
            'courseid' => $this->c['c1'], 'costcenterid' => 1, 'reason' => '', 'status' => 'pending',
            'route' => 'manager', 'approver_userid' => $sup, 'decided_by_userid' => null, 'decision_note' => null,
            'timecreated' => $t0 + 1, 'timemodified' => $t0 + 101, 'timedue' => null, 'timedecided' => null,
            'timeescalated' => null, 'legacy_source' => 'bizlms',
        ], 'pending course with a live supervisor');

        $this->assert_columns($this->imported($records, 2), [
            'item_type' => 'course', 'itemid' => $this->c['c2'], 'costcenterid' => 77, 'status' => 'approved',
            'route' => 'admin', 'approver_userid' => $r, 'decided_by_userid' => $r, 'timedecided' => $t0 + 5000,
            'timecreated' => $t0 + 2, 'timemodified' => $t0 + 102, 'timedue' => null,
        ], 'approved course');

        $this->assert_columns($this->imported($records, 3), [
            'item_type' => 'path', 'itemid' => 11, 'courseid' => 0, 'costcenterid' => 1, 'status' => 'rejected',
            'route' => 'admin', 'approver_userid' => $r, 'decided_by_userid' => $r, 'timedecided' => $t0 + 6000,
        ], 'rejected learning plan');

        // History only: no approver, so it sits in nobody's inbox.
        $this->assert_columns($this->imported($records, 4), [
            'item_type' => 'classroom', 'itemid' => 21, 'courseid' => 0, 'costcenterid' => 77, 'status' => 'pending',
            'route' => 'admin', 'approver_userid' => null, 'decided_by_userid' => null, 'timedue' => null,
        ], 'pending classroom');

        $this->assert_columns($this->imported($records, 5), [
            'item_type' => 'program', 'itemid' => 31, 'status' => 'approved', 'approver_userid' => $r,
            'decided_by_userid' => $r, 'timedecided' => $t0 + 7000,
        ], 'approved program');

        // The duplicate pair stays two rows; a requester with no supervisor falls back to the default approver,
        // and no tenant means costcenterid 0 (visible to cross-tenant callers only).
        foreach ([6, 7] as $id) {
            $this->assert_columns($this->imported($records, $id), [
                'userid' => $this->u['u3']->id, 'item_type' => 'course', 'itemid' => $this->c['c1'],
                'costcenterid' => 0, 'status' => 'pending', 'route' => 'admin', 'approver_userid' => $admin,
            ], "duplicate {$id}");
        }
        $this->assertNotEquals($this->imported($records, 6)->id, $this->imported($records, 7)->id);

        // No timecreated or timemodified: the decision time stands in for both.
        $this->assert_columns($this->imported($records, 8), [
            'timecreated' => $t0 + 8000, 'timemodified' => $t0 + 8000, 'status' => 'pending',
        ], 'derived timestamps');

        // BizLMS hid a row whose course was deleted; the owner chose to show it (request.hidden_rows).
        $this->assert_columns($this->imported($records, 9), [
            'item_type' => 'course', 'itemid' => 999999, 'courseid' => 999999, 'status' => 'approved',
        ], 'deleted course');

        // A certification has no Sentientia entity: history only, legacy id kept, and the requester's root (5)
        // is not a registered tenant.
        $this->assert_columns($this->imported($records, 11), [
            'item_type' => 'certification', 'itemid' => 77, 'courseid' => 0, 'costcenterid' => 0,
            'status' => 'pending', 'approver_userid' => null,
        ], 'certification');

        // A deleted requester is history and is imported; a pending learning plan routes like a new one would.
        $this->assert_columns($this->imported($records, 12), [
            'userid' => $this->u['dead']->id, 'item_type' => 'path', 'itemid' => 12, 'route' => 'admin',
            'approver_userid' => $admin,
        ], 'deleted requester');
        $this->assert_columns($this->imported($records, 13), ['userid' => $this->u['susp']->id, 'status' => 'approved'],
            'suspended requester');

        // A plan that no longer exists keeps its legacy id; the path request still routes to the supervisor.
        $this->assert_columns($this->imported($records, 15), [
            'item_type' => 'path', 'itemid' => 13, 'route' => 'manager', 'approver_userid' => $sup,
        ], 'deleted plan');

        // Rows that cannot be imported are skipped, with a reason.
        $orphan = $this->map($records, 10);
        $this->assertSame('skipped', $orphan->outcome);
        $this->assertSame('orphan_user', $orphan->reason);
        $this->assertSame('user_not_found', $orphan->detail);
        $this->assertNull($orphan->targetid);
        $unmapped = $this->map($records, 14);
        $this->assertSame('skipped', $unmapped->outcome);
        $this->assertSame('orphan_item', $unmapped->reason);
        $this->assertSame('item_not_imported', $unmapped->detail);

        $this->assertSame(14, $this->count_outcome($records, 'imported'));
        $this->assertSame(2, $this->count_outcome($records, 'skipped'));
    }

    public function test_learning_plan_approvals_fold_into_their_request_or_stand_alone(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $admin = (int) get_admin()->id;
        $r = (int) $this->u['resp']->id;
        $t0 = self::T0;
        $this->apply();
        $approvals = 'local_learningplan_approval';

        // 1: the same (user, plan) as request row 3, which was imported: it adds nothing.
        $folded = $this->map($approvals, 1);
        $this->assertSame('folded', $folded->outcome);
        $this->assertSame('dup_of_request', $folded->reason);
        $this->assertSame('local_sentientia_request', $folded->targettable);
        $this->assertSame((int) $this->map('local_request_records', 3)->targetid, (int) $folded->targetid);

        // 2: pending, and no request row for the pair: a request of its own, routed like a new one.
        $this->assert_columns($this->imported($approvals, 2), [
            'userid' => $this->u['u2']->id, 'item_type' => 'path', 'itemid' => 12, 'courseid' => 0,
            'costcenterid' => 77, 'reason' => '', 'status' => 'pending', 'route' => 'admin',
            'approver_userid' => $admin, 'decided_by_userid' => null, 'decision_note' => null,
            'timecreated' => $t0 + 202, 'timemodified' => $t0 + 302, 'timedue' => null, 'timedecided' => null,
            'legacy_source' => 'bizlms',
        ], 'pending approval');

        // 3: approved, and approvedby is 0, so the decider is usermodified.
        $this->assert_columns($this->imported($approvals, 3), [
            'status' => 'approved', 'route' => 'admin', 'approver_userid' => $r, 'decided_by_userid' => $r,
            'timedecided' => $t0 + 303, 'costcenterid' => 0,
        ], 'approved approval');

        // 6: rejected with a message, which is the decision note, as text.
        $this->assert_columns($this->imported($approvals, 6), [
            'status' => 'rejected', 'decided_by_userid' => $r, 'decision_note' => 'Not eligible this quarter',
            'timedecided' => $t0 + 306,
        ], 'rejected approval');

        $this->assertSame('orphan_item', $this->map($approvals, 4)->reason);
        $this->assertSame('item_not_found', $this->map($approvals, 4)->detail);
        $this->assertSame('orphan_user', $this->map($approvals, 5)->reason);
        $this->assertSame(1, $this->count_outcome($approvals, 'folded'));
        $this->assertSame(3, $this->count_outcome($approvals, 'imported'));
        $this->assertSame(2, $this->count_outcome($approvals, 'skipped'));
    }

    public function test_the_comment_thread_becomes_the_decision_note(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();

        // Oldest first, "[date time] full name: text", markup removed.
        $expected = '[2026-03-04 09:00] ' . fullname($this->u['u2']) . ': Attached.' . "\n"
            . '[2026-03-05 14:22] ' . fullname($this->u['resp']) . ': Please attach the manager approval';
        $this->assertSame($expected, $this->imported('local_request_records', 16)->decision_note);

        $comments = 'local_request_comments';
        foreach ([1, 2] as $id) {
            $fold = $this->map($comments, $id);
            $this->assertSame('folded', $fold->outcome);
            $this->assertSame('folded_into_note', $fold->reason);
            $this->assertSame((int) $this->map('local_request_records', 16)->targetid, (int) $fold->targetid);
        }
        $this->assertSame('request_not_found', $this->map($comments, 3)->detail);
        $this->assertSame('instance_not_a_request', $this->map($comments, 4)->detail);
        $this->assertSame('request_not_imported', $this->map($comments, 5)->detail);
        foreach ([3, 4, 5] as $id) {
            $this->assertSame('skipped', $this->map($comments, $id)->outcome);
            $this->assertSame('orphan_request', $this->map($comments, $id)->reason);
        }
    }

    public function test_routing_is_the_routing_a_new_request_gets(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $records = 'local_request_records';

        // The same rule, from the same class the manager delegates to, for the FULL user row.
        $u1 = $DB->get_record('user', ['id' => $this->u['u1']->id], '*', MUST_EXIST);
        [$route, $approver] = request_manager::route_approver($u1, $this->c['c1']);
        $row = $this->imported($records, 1);
        $this->assertSame($route, $row->route);
        $this->assertSame($approver, (int) $row->approver_userid);

        [$route, $approver] = request_manager::route_approver_for_path($u1, 13);
        $row = $this->imported($records, 15);
        $this->assertSame($route, $row->route);
        $this->assertSame($approver, (int) $row->approver_userid);

        // A suspended supervisor does not count: the request falls back to the default approver.
        $DB->set_field('user', 'suspended', 1, ['id' => $this->u['sup']->id]);
        $this->assertSame([approver_routing::ROUTE_ADMIN, (int) get_admin()->id],
            approver_routing::for_path($u1, 13));
    }

    public function test_nothing_is_enrolled_sent_or_scheduled(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $enrolments = $DB->count_records('user_enrolments');
        $roles = $DB->count_records('role_assignments');
        $messages = $DB->count_records('messages');
        $tasks = $DB->count_records('task_adhoc');
        $this->apply();

        $this->assertSame($enrolments, $DB->count_records('user_enrolments'), 'approved requests enrol nobody');
        $this->assertSame($roles, $DB->count_records('role_assignments'));
        $this->assertSame($messages, $DB->count_records('messages'));
        $this->assertSame($tasks, $DB->count_records('task_adhoc'));
        $this->assertSame(0, $DB->count_records_select('local_sentientia_request',
            'legacy_source = :ls AND (timedue IS NOT NULL OR timeescalated IS NOT NULL)', ['ls' => 'bizlms']),
            'no imported row has an SLA clock');
    }

    // Decisions.

    public function test_readonly_leaves_every_pending_request_out_of_every_inbox(): void {
        global $DB;
        $this->overrides = ['request.pending' => 'readonly'];
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();

        $this->assertSame(0, $DB->count_records_select('local_sentientia_request',
            "legacy_source = 'bizlms' AND status = 'pending' AND approver_userid IS NOT NULL"));
        $this->assertGreaterThan(0, $DB->count_records('local_sentientia_request', ['status' => 'pending']));
        // Decided rows keep who decided.
        $this->assertEquals($this->u['resp']->id, $this->imported('local_request_records', 2)->approver_userid);
    }

    public function test_expired_closes_the_pending_requests(): void {
        global $DB;
        $this->overrides = ['request.pending' => 'expired'];
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();

        $this->assertSame(0, $DB->count_records('local_sentientia_request', ['status' => 'pending']));
        $row = $this->imported('local_request_records', 1);
        $this->assertSame('expired', $row->status);
        $this->assertNull($row->approver_userid);
        $this->assertSame('approved', $this->imported('local_request_records', 2)->status);
    }

    public function test_a_different_decided_route_is_carried(): void {
        $this->overrides = ['request.decided_route' => 'legacy'];
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $this->assertSame('legacy', $this->imported('local_request_records', 2)->route);
        $this->assertSame('legacy', $this->imported('local_learningplan_approval', 6)->route);
        // A pending row keeps its routing label.
        $this->assertSame('manager', $this->imported('local_request_records', 1)->route);
    }

    public function test_filtering_hidden_rows_archives_what_bizlms_hid(): void {
        $this->overrides = ['request.hidden_rows' => 'filter'];
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();

        // The deleted course (9), the deleted requester (12), the suspended one (13), the deleted plan (15).
        foreach ([9, 12, 13, 15] as $id) {
            $map = $this->map('local_request_records', $id);
            $this->assertSame('archived', $map->outcome, "record {$id}");
            $this->assertSame('hidden_in_bizlms', $map->reason);
            $this->assertNull($map->targetid);
        }
        $this->assertSame('imported', $this->map('local_request_records', 1)->outcome);
    }

    public function test_unresolved_tenants_can_be_skipped_instead(): void {
        $this->overrides = ['tenant.unresolved.request' => 'skip'];
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();

        // The two duplicates (no tenant), the certification (root 5), and the approval of the same requester.
        foreach ([6, 7, 11] as $id) {
            $map = $this->map('local_request_records', $id);
            $this->assertSame('skipped', $map->outcome, "record {$id}");
            $this->assertSame('no_tenant', $map->reason);
        }
        $this->assertSame('no_tenant', $this->map('local_learningplan_approval', 3)->reason);
        $this->assertSame('imported', $this->map('local_request_records', 1)->outcome);
    }

    public function test_needs_owner_reasons_are_unproven_until_accepted(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertSame(2, $result['exit'], 'skipped rows the owner has not accepted leave the import unproven');
        $lines = implode(' ', $result['unproven']);
        foreach (['request:orphan_user', 'request:orphan_item', 'request:orphan_request'] as $reason) {
            $this->assertStringContainsString($reason, $lines);
        }
        // Folds and archives are not losses.
        $this->assertStringNotContainsString('dup_of_request', $lines);
        $this->assertStringNotContainsString('folded_into_note', $lines);
    }

    public function test_accepted_reasons_make_the_import_proven(): void {
        $this->overrides = ['accepted_reasons' => [
            'request:orphan_user', 'request:orphan_item', 'request:orphan_request',
        ]];
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
    }

    // Blockers.

    public function test_a_status_nobody_writes_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_request_records', 'status', 'COMPLETE', ['id' => 7]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_request_records.status=COMPLETE',
            implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_request'));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE, ['feature' => 'request']));
    }

    public function test_an_unknown_component_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_request_records', 'compname', 'survey', ['id' => 7]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_request_records.compname=survey',
            implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_request'));
    }

    public function test_an_unknown_approval_status_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_learningplan_approval', 'approvestatus', 3, ['id' => 2]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_learningplan_approval.approvestatus=3',
            implode(' ', $result['blockers']));
    }

    public function test_a_column_nothing_wrote_blocks_when_it_holds_a_value(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_request_records', 'c1', 'something', ['id' => 3]);
        $DB->set_field('local_request_records', 'req_type', 'custom', ['id' => 4]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $blockers = implode(' ', $result['blockers']);
        $this->assertStringContainsString('needs_owner:unmapped_column:local_request_records.c1=1', $blockers);
        $this->assertStringContainsString('needs_owner:unmapped_column:local_request_records.req_type=1', $blockers);
        $this->assertStringNotContainsString('.compcode', $blockers);
    }

    public function test_a_table_with_no_map_blocks_while_it_holds_rows(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('block_request_records');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);

        // Empty: nothing to lose.
        $this->apply();
        $this->assertSame(0, $DB->count_records('block_request_records'));
        $this->contract_clear_import($this->contract_importer());

        $DB->import_record('block_request_records', (object) ['id' => 1]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('needs_owner:unmapped_table_has_rows:block_request_records=1',
            implode(' ', $result['blockers']));
    }

    public function test_the_config_table_is_declined_not_imported(): void {
        $importer = new importer();
        $this->assertArrayHasKey('local_request_config', $importer->declined_tables());
        $this->assertArrayNotHasKey('local_request_config', $importer->sources());
        foreach (['block_request_records', 'block_request_comments', 'block_request_config',
                'local_request_formfields', 'local_request_form_data'] as $table) {
            $this->assertArrayHasKey($table, $importer->declined_tables());
        }
    }

    // verify().

    public function test_verify_reports_what_the_import_must_never_leave(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx), 'a clean import verifies');

        $ids = array_values(array_map(fn($row) => (int) $row->id,
            $DB->get_records('local_sentientia_request', ['legacy_source' => 'bizlms'], 'id')));
        $DB->set_field('local_sentientia_request', 'timedue', time() + 3600, ['id' => $ids[0]]);
        $DB->set_field('local_sentientia_request', 'costcenterid', 5, ['id' => $ids[1]]);
        $DB->set_field('local_sentientia_request', 'legacy_source', null, ['id' => $ids[2]]);
        $DB->set_field('local_sentientia_request', 'status', 'cancelled', ['id' => $ids[3]]);
        $failures = implode(' ', $importer->verify($ctx));
        $this->assertStringContainsString('imported_rows_with_a_deadline:1', $failures);
        $this->assertStringContainsString('imported_rows_with_an_unregistered_tenant:5', $failures);
        $this->assertStringContainsString('imported_rows_without_the_marker:1', $failures);
        $this->assertStringContainsString('imported_rows_with_an_unknown_status:1', $failures);
    }

    // Imported rows under the engines.

    public function test_cron_and_decide_treat_imported_rows_as_history(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        set_config('auto_expire_days', 1, 'local_sentientia_request');
        $pending = $this->imported('local_request_records', 1);

        // Old enough to expire, and (forced) overdue: the cron jobs still leave an imported row alone.
        $DB->set_field('local_sentientia_request', 'timedue', time() - 3600, ['id' => $pending->id]);
        $this->assertSame(0, request_manager::escalate_overdue());
        $this->assertSame(0, request_manager::auto_expire());
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $pending->id]));
        $this->assertSame('manager', $DB->get_field('local_sentientia_request', 'route', ['id' => $pending->id]));

        // Nobody can decide a classroom request: it is history only.
        $classroom = $this->imported('local_request_records', 4);
        $this->setAdminUser();
        try {
            request_manager::decide((int) $classroom->id, (int) get_admin()->id, 'approved', 'ok');
            $this->fail('decide() must refuse a classroom request');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_invalidstate', $e->errorcode);
        }
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $classroom->id]));
    }

    public function test_an_imported_pending_course_request_is_decided_like_any_other(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $row = $this->imported('local_request_records', 1);
        $supervisor = (int) $this->u['sup']->id;
        $this->assertEquals($supervisor, $row->approver_userid);
        $enrolments = $DB->count_records('user_enrolments');

        // The routed approver decides it: a person still decides, and an approval enrols the requester.
        $this->setUser($this->u['sup']);
        $sink = $this->redirectMessages();
        request_manager::decide((int) $row->id, $supervisor, 'approved', 'Go ahead');
        $sink->close();
        $this->assertSame('approved', $DB->get_field('local_sentientia_request', 'status', ['id' => $row->id]));
        $this->assertGreaterThan($enrolments, $DB->count_records('user_enrolments'));
    }

    // The contract, for the parts that need the seed and the extra tables.

    public function test_the_stand_ins_are_not_applicable_and_the_feature_is(): void {
        $this->contract_begin();
        $this->contract_seed();
        $result = $this->apply();
        $this->assertSame('not_applicable', $result['features']['classroom']);
        $this->assertSame('not_applicable', $result['features']['program']);
        $this->assertSame('not_applicable', $result['features']['learningplan']);
        $this->assertSame('complete', $result['features']['request']);
        $this->assertTrue(legacymap::feature_complete('request'));
    }

    /**
     * Count the primary map rows of a source table with an outcome.
     *
     * @param string $sourcetable
     * @param string $outcome
     * @return int
     */
    private function count_outcome(string $sourcetable, string $outcome): int {
        global $DB;
        return $DB->count_records(legacymap::TABLE,
            ['sourcetable' => $sourcetable, 'subkey' => '', 'outcome' => $outcome]);
    }
}
