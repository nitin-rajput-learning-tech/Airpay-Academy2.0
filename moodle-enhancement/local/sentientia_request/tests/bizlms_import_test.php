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
            // 2026-10-07 (COMMS-R1): a pending request whose requester has left, or whose item is gone, is history.
            'request.pending_stale' => 'history_only',
            // The seed holds comment rows, so preflight blocks for the owner unless the owner has reviewed them: the
            // signed value is fold_into_decision_note, and test_comment_rows_block_until_the_owner_has_reviewed_them
            // runs with it.
            'request.comments' => 'fold_reviewed',
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

        // A deleted requester is history and is imported. Its request is still pending, but nobody can decide it
        // (COMMS-R1): route admin, no approver, so Approve could not enrol or message an account that has left.
        $this->assert_columns($this->imported($records, 12), [
            'userid' => $this->u['dead']->id, 'item_type' => 'path', 'itemid' => 12, 'status' => 'pending',
            'route' => 'admin', 'approver_userid' => null,
        ], 'deleted requester');
        $this->assert_columns($this->imported($records, 13), ['userid' => $this->u['susp']->id, 'status' => 'approved'],
            'suspended requester');

        // A plan that no longer exists gets itemid 0, not its legacy id (COMMS-R2: paths keep their ids and a later
        // path could be given 13), and its pending request is history only (COMMS-R1), not routed to the supervisor.
        $this->assert_columns($this->imported($records, 15), [
            'item_type' => 'path', 'itemid' => 0, 'status' => 'pending', 'route' => 'admin', 'approver_userid' => null,
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
        // A live requester asking for a learning plan that exists (record 15's plan is gone, so since COMMS-R1 it is
        // history and is no longer routed).
        $this->record(17, $this->u['u1'], 'learningplan', 12, 'PENDING');
        $this->apply();
        $records = 'local_request_records';

        // The same rule, from the same class the manager delegates to, for the FULL user row.
        $u1 = $DB->get_record('user', ['id' => $this->u['u1']->id], '*', MUST_EXIST);
        [$route, $approver] = request_manager::route_approver($u1, $this->c['c1']);
        $row = $this->imported($records, 1);
        $this->assertSame($route, $row->route);
        $this->assertSame($approver, (int) $row->approver_userid);

        [$route, $approver] = request_manager::route_approver_for_path($u1, 12);
        $row = $this->imported($records, 17);
        $this->assertSame($route, $row->route);
        $this->assertSame('manager', $row->route, 'the live supervisor');
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
        $DB->set_field('local_sentientia_request', 'status', 'bogus', ['id' => $ids[3]]);
        $failures = implode(' ', $importer->verify($ctx));
        $this->assertStringContainsString('imported_rows_with_a_deadline:1', $failures);
        $this->assertStringContainsString('imported_rows_with_an_unregistered_tenant:5', $failures);
        $this->assertStringContainsString('imported_rows_without_the_marker:1', $failures);
        $this->assertStringContainsString('imported_rows_with_an_unknown_status:1', $failures);
    }

    public function test_a_requester_cancelling_an_imported_pending_row_is_not_a_verify_failure(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->apply();
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));

        // F-77: after go-live the requester may cancel their own pending request; a later verify must still pass.
        $row = $this->imported('local_request_records', 1);
        request_manager::cancel((int) $row->id, (int) $this->u['u1']->id);
        $this->assertSame('cancelled', $DB->get_field('local_sentientia_request', 'status', ['id' => $row->id]));
        $this->assertSame([], $importer->verify($ctx), 'cancelled is a state a request can be in');
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

    // The 2026-10-07 owner decisions: COMMS-R1, R2 and R4, and the follow-ups F-77, F-78 and F-80.

    /**
     * Summed warnings of the request steps of a run.
     *
     * @param \local_sentientia_platform\bizlms\report $report
     * @return array<string, int>
     */
    private function warnings(\local_sentientia_platform\bizlms\report $report): array {
        $out = [];
        foreach ($report->to_array()['features']['request']['steps'] ?? [] as $step) {
            foreach ($step['warnings'] ?? [] as $code => $n) {
                $out[$code] = ($out[$code] ?? 0) + $n;
            }
        }
        return $out;
    }

    public function test_a_pending_request_whose_requester_or_item_is_gone_is_history_only(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $sup = (int) $this->u['sup']->id;
        $this->record(17, $this->u['u1'], 'learningplan', 12, 'PENDING');          // live requester, the plan exists
        $this->record(18, $this->u['susp'], 'elearning', $this->c['c1'], 'PENDING'); // a suspended requester
        $this->record(19, $this->u['u1'], 'elearning', 999999, 'PENDING');          // the course is gone
        $this->approval(7, $this->u['susp'], 11, 0);                                // a pending approval of a suspended user
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $records = 'local_request_records';

        // Routed like any new request: a live requester, an item that exists.
        $this->assert_columns($this->imported($records, 17), [
            'status' => 'pending', 'route' => 'manager', 'approver_userid' => $sup,
        ], 'live requester, existing plan');

        // History only: still pending (the status is exact), route admin, no approver.
        foreach ([12 => 'deleted requester', 15 => 'plan gone', 18 => 'suspended requester', 19 => 'course gone'] as $id => $why) {
            $this->assert_columns($this->imported($records, $id), [
                'status' => 'pending', 'route' => 'admin', 'approver_userid' => null, 'timedue' => null,
            ], $why);
        }
        $this->assertEquals(999999, $this->imported($records, 19)->itemid, 'a course keeps its id: core ids are never reused');
        $this->assert_columns($this->imported('local_learningplan_approval', 7), [
            'status' => 'pending', 'route' => 'admin', 'approver_userid' => null,
        ], 'an approval of a suspended requester');

        // The row is in All requests (request.hidden_rows = show) but in nobody's inbox.
        $this->assertSame(0, $DB->count_records_select('local_sentientia_request',
            "legacy_source = 'bizlms' AND status = 'pending' AND approver_userid IS NOT NULL AND id IN ("
            . implode(',', array_map(fn($id) => (int) $this->imported($records, $id)->id, [12, 15, 18, 19])) . ')'));
        $this->assertSame(5, $this->warnings($report)['pending_history_only'] ?? 0, 'records 12, 15, 18 and 19, approval 7');

        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));

        // verify() fails a pending row with an approver whose requester or item is gone.
        $DB->set_field('local_sentientia_request', 'approver_userid', $sup, ['id' => $this->imported($records, 12)->id]);
        $DB->set_field('local_sentientia_request', 'approver_userid', $sup, ['id' => $this->imported($records, 19)->id]);
        $DB->set_field('local_sentientia_request', 'approver_userid', $sup, ['id' => $this->imported($records, 15)->id]);
        $this->assertStringContainsString('pending_rows_with_an_approver_whose_requester_or_item_is_gone:3',
            implode(' ', $importer->verify($ctx)));
    }

    public function test_verify_does_not_blame_the_import_for_what_happened_after_go_live(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));

        // Record 1 is a live requester's pending request for course c1, routed to the supervisor.
        $records = 'local_request_records';
        $row = $this->imported($records, 1);
        $this->assertNotNull($row->approver_userid, 'an actionable row: a person decides it');
        $later = (int) $this->map($records, 1)->timecreated + 3600;
        $failure = 'pending_rows_with_an_approver_whose_requester_or_item_is_gone';

        // The requester is suspended, then deleted, AFTER the import (Moodle stamps timemodified when it does that).
        $requester = (int) $row->userid;
        $DB->set_field('user', 'suspended', 1, ['id' => $requester]);
        $DB->set_field('user', 'timemodified', $later, ['id' => $requester]);
        $this->assertSame([], $importer->verify($ctx), 'a requester suspended after the import is not an import defect');
        $DB->set_field('user', 'deleted', 1, ['id' => $requester]);
        $this->assertSame([], $importer->verify($ctx), 'nor one deleted after it');

        // The same account, already suspended when the row was imported, is a defect: the importer must not route it.
        $DB->set_field('user', 'timemodified', (int) $this->map($records, 1)->timecreated - 60, ['id' => $requester]);
        $this->assertStringContainsString($failure, implode(' ', $importer->verify($ctx)));
        $DB->set_field('user', 'timemodified', $later, ['id' => $requester]);
        $this->assertSame([], $importer->verify($ctx));

        // The course is deleted after the import: the row is not an import defect while a course_deleted event says so.
        $course = (int) $row->courseid;
        $DB->delete_records('course', ['id' => $course]);
        $this->assertStringContainsString($failure, implode(' ', $importer->verify($ctx)),
            'a course that is missing with no deletion event after the import is what the import saw');
        $logid = (int) $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\\core\\event\\course_deleted', 'component' => 'core', 'action' => 'deleted', 'target' => 'course',
            'objecttable' => 'course', 'objectid' => $course, 'crud' => 'd', 'edulevel' => 1,
            'contextid' => \context_system::instance()->id, 'contextlevel' => CONTEXT_COURSE, 'contextinstanceid' => $course,
            'userid' => 2, 'courseid' => $course, 'relateduserid' => null, 'anonymous' => 0, 'other' => 'N;',
            'timecreated' => $later, 'origin' => 'cli', 'ip' => null, 'realuserid' => null,
        ]);
        $this->assertSame([], $importer->verify($ctx), 'a course deleted after the import is not an import defect');
        // A deletion event from before the import does not excuse it.
        $DB->set_field('logstore_standard_log', 'timecreated', (int) $this->map($records, 1)->timecreated - 60, ['id' => $logid]);
        $this->assertStringContainsString($failure, implode(' ', $importer->verify($ctx)));
    }

    public function test_the_owner_may_keep_stale_pending_requests_actionable(): void {
        $this->overrides = ['request.pending_stale' => 'actionable'];
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // As the code behaved before the decision: routed whatever the state of the requester or the item.
        $this->assertEquals(get_admin()->id, $this->imported('local_request_records', 12)->approver_userid);
        $this->assertEquals($this->u['sup']->id, $this->imported('local_request_records', 15)->approver_userid);
        $this->assertArrayNotHasKey('pending_history_only', $this->warnings($report));
        // The gone plan still gets itemid 0: that is COMMS-R2, not COMMS-R1.
        $this->assertEquals(0, $this->imported('local_request_records', 15)->itemid);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_a_request_for_a_gone_path_classroom_or_program_gets_itemid_zero(): void {
        $this->contract_begin();
        $this->contract_seed();
        $r = (int) $this->u['resp']->id;
        $t0 = self::T0;
        // None of 23, 33 and 14 is in the map of its feature: the item was deleted in BizLMS.
        $this->record(20, $this->u['u1'], 'classroom', 23, 'APPROVED', ['responder' => $r, 'respondeddate' => $t0 + 9100]);
        $this->record(21, $this->u['u1'], 'program', 33, 'REJECTED', ['responder' => $r, 'respondeddate' => $t0 + 9200]);
        $this->record(22, $this->u['u1'], 'learningplan', 14, 'APPROVED', ['responder' => $r, 'respondeddate' => $t0 + 9300]);
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $records = 'local_request_records';

        foreach ([20 => ['classroom', 'approved'], 21 => ['program', 'rejected'], 22 => ['path', 'approved']] as $id => [$type, $status]) {
            $row = $this->imported($records, $id);
            $this->assertSame($type, $row->item_type, "record {$id}");
            $this->assertEquals(0, $row->itemid, "record {$id}: the legacy id could be given to a later item");
            $this->assertSame($status, $row->status, "record {$id}: the status is exact");
        }
        // The ones that exist keep their ids; a course and a certification keep theirs too.
        $this->assertEquals(31, $this->imported($records, 5)->itemid, 'program 31 exists');
        $this->assertEquals(21, $this->imported($records, 4)->itemid, 'classroom 21 exists');
        $this->assertEquals(11, $this->imported($records, 3)->itemid, 'plan 11 exists');
        $this->assertEquals(999999, $this->imported($records, 9)->itemid, 'a deleted course keeps its core id');
        $this->assertEquals(77, $this->imported($records, 11)->itemid, 'a certification has no entity: legacy id kept');
        $this->assertGreaterThanOrEqual(4, $this->warnings($report)['item_deleted'] ?? 0, '15, 20, 21, 22 and the deleted course');
    }

    public function test_comment_rows_block_until_the_owner_has_reviewed_them(): void {
        global $DB;
        // The signed value. The seed holds five comment rows.
        $this->overrides = ['request.comments' => 'fold_into_decision_note'];
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('needs_owner:request_comments_present=5', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_request'), 'a blocked preflight writes nothing');

        // With no comment row there is nothing to review: the signed value does not block.
        $DB->delete_records('local_request_comments');
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
    }

    public function test_a_value_the_importer_does_not_carry_out_blocks_the_comments_decision(): void {
        $this->overrides = ['request.comments' => 'ignore'];
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:request.comments', implode(' ', $result['blockers']));
    }

    public function test_deciding_an_imported_request_appends_to_its_folded_comments(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $resp = (int) $this->u['resp']->id;
        // Record 1 is pending and supervised; record 8 too. Each gets one comment, which becomes its note.
        $this->comment(6, '1', $resp, '2026-03-07 08:00:00', 'Waiting for budget sign-off');
        $this->comment(7, '8', $resp, '2026-03-07 09:00:00', 'Needs a second opinion');
        $this->apply();
        $supervisor = (int) $this->u['sup']->id;
        $this->setUser($this->u['sup']);
        $sink = $this->redirectMessages();

        $approved = $this->imported('local_request_records', 1);
        $thread = (string) $approved->decision_note;
        $this->assertStringContainsString('Waiting for budget sign-off', $thread);
        request_manager::decide((int) $approved->id, $supervisor, 'approved', 'Go ahead');
        $this->assertSame($thread . "\n" . 'Go ahead',
            $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $approved->id]),
            'the decider\'s note goes below the thread, and the thread stays');

        $rejected = $this->imported('local_request_records', 8);
        $thread = (string) $rejected->decision_note;
        request_manager::decide((int) $rejected->id, $supervisor, 'rejected', 'Not this quarter');
        $this->assertSame($thread . "\n" . 'Not this quarter',
            $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $rejected->id]));
        $sink->close();
    }

    public function test_the_note_after_a_decision_replaces_a_native_note_and_keeps_an_imported_thread(): void {
        $method = new \ReflectionMethod(request_manager::class, 'note_after_decision');
        $thread = '[2026-03-04 09:00] Asha Rao: Attached.';
        $native = (object) ['legacy_source' => null, 'decision_note' => 'old note'];
        $imported = (object) ['legacy_source' => 'bizlms', 'decision_note' => $thread];
        $bare = (object) ['legacy_source' => 'bizlms', 'decision_note' => null];

        $this->assertSame('new note', $method->invoke(null, $native, 'new note'), 'a native note is replaced, as it always was');
        $this->assertSame($thread . "\n" . 'new note', $method->invoke(null, $imported, 'new note'));
        $this->assertSame($thread, $method->invoke(null, $imported, ''), 'no decider note: the thread is left as it is');
        $this->assertSame($thread, $method->invoke(null, $imported, "  \n"), 'a blank note counts as none');
        $this->assertSame('new note', $method->invoke(null, $bare, 'new note'), 'an imported row with no thread');
    }

    public function test_a_comment_with_the_zero_date_has_no_date_not_year_zero(): void {
        $this->contract_begin();
        $this->contract_seed();
        // F-80: a datetime BizLMS never set reads back from MySQL as the zero date.
        $this->comment(6, '2', (int) $this->u['resp']->id, '0000-00-00 00:00:00', 'Undated remark');
        $this->apply();
        $note = (string) $this->imported('local_request_records', 2)->decision_note;
        $this->assertStringContainsString('[unknown date] ' . fullname($this->u['resp']) . ': Undated remark', $note);
        $this->assertStringNotContainsString('0000-00-00', $note);
    }

    public function test_the_real_registry_knows_the_three_features_request_depends_on(): void {
        // F-78: classroom, program and learningplan are merged, so the dependency is no longer a stand-in. The other
        // tests keep the stand-ins (the legacy_schema_fixture trait takes one fixture file, F-79, which the real
        // importers' own legacy tables would need); this one pins that the real registry resolves the dependency.
        registry::set_testing_importers(null);
        $importers = registry::load();
        foreach ((new importer())->depends() as $feature) {
            $this->assertArrayHasKey($feature, $importers, "{$feature} is registered by its own plugin");
            $this->assertNotInstanceOf(dependency_stub::class, $importers[$feature]);
        }
        $order = registry::sorted($importers, ['request']);
        $position = array_flip($order);
        foreach ((new importer())->depends() as $feature) {
            $this->assertLessThan($position['request'], $position[$feature], "{$feature} runs before request");
        }
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
