<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_sentientia_platform\feature_flags;
use local_sentientia_request\external\list_all;
use local_sentientia_request\external\list_mine;
use local_sentientia_request\external\list_pending;
use local_sentientia_request\privacy\provider;

/**
 * The reader and engine changes that ship with the BizLMS request import (ADR-032; mapping doc, section 19,
 * "Code fixes").
 *
 * None of these tests needs a BizLMS table: they insert rows the way the importer leaves them
 * (legacy_source = 'bizlms', no deadline, no reason) and read them back through the real lists, the real
 * request_manager and the real privacy provider. The importer itself is tested in bizlms_import_test.php.
 *
 * @package    local_sentientia_request
 * @category   test
 * @covers     \local_sentientia_request\imported_history
 * @covers     \local_sentientia_request\item_label
 * @covers     \local_sentientia_request\request_manager
 * @covers     \local_sentientia_request\external\list_mine
 * @covers     \local_sentientia_request\external\list_pending
 * @covers     \local_sentientia_request\external\list_all
 * @covers     \local_sentientia_request\privacy\provider
 * @covers     ::local_sentientia_request_ensure_legacy_source
 *
 * @group local_sentientia_request
 * @group bizlms_import
 * @group tenant_isolation
 */
final class imported_history_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var string */
    private const FLAG = 'sentientia.request.imported_history';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    protected function tearDown(): void {
        feature_flags::invalidate_caches();
        parent::tearDown();
    }

    // Helpers.

    /** A user at $path, with the manager role at system context when $manager (a tenant admin / approver). */
    private function user(string $path, bool $manager = false): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        if ($manager) {
            $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
            role_assign($managerid, $user->id, \context_system::instance()->id);
            accesslib_clear_all_caches_for_unit_testing();
        }
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * A request row, native unless told otherwise.
     *
     * @param array $fields Columns to override.
     * @return int The new id.
     */
    private function request(array $fields = []): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_request', (object) ($fields + [
            'userid' => 2, 'item_type' => 'course', 'itemid' => 0, 'courseid' => 0, 'costcenterid' => 1,
            'reason' => 'A reason that is long enough to be a real one.', 'status' => 'pending', 'route' => 'admin',
            'approver_userid' => null, 'decision_note' => null, 'decided_by_userid' => null,
            'timecreated' => $now, 'timedue' => null, 'timedecided' => null, 'timeescalated' => null,
            'timemodified' => $now, 'legacy_source' => null,
        ]));
    }

    /** A request as the importer leaves it. */
    private function imported(array $fields = []): int {
        return $this->request($fields + ['legacy_source' => 'bizlms', 'reason' => '']);
    }

    private function flag(bool $on): void {
        feature_flags::set(self::FLAG, 0, $on, (int) get_admin()->id, 'test');
        feature_flags::invalidate_caches();
    }

    /** @return int[] */
    private function ids(array $result): array {
        return array_map('intval', array_column($result['rows'], 'id'));
    }

    // The flag.

    public function test_the_flag_exists_and_is_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(self::FLAG, $registry);
        $this->assertFalse($registry[self::FLAG]['default']);
        $this->assertFalse(imported_history::visible());
    }

    public function test_imported_rows_are_left_out_of_every_list_until_the_flag_is_on(): void {
        $learner = $this->user('/1');
        $approver = $this->user('/1', true);
        $native = $this->request(['userid' => $learner->id, 'approver_userid' => $approver->id,
            'timedue' => time() + 3600]);
        $imported = $this->imported(['userid' => $learner->id, 'approver_userid' => $approver->id]);

        // OFF: exactly what the lists showed before the import existed.
        $this->setUser($learner);
        $this->assertSame([$native], $this->ids(list_mine::execute()));
        $this->setUser($approver);
        $this->assertSame([$native], $this->ids(list_pending::execute()));
        $this->assertSame([$native], $this->ids(list_all::execute()));
        $this->assertSame(1, request_manager::pending_count_for_approver((int) $approver->id));

        // ON: the history is there for the requester, the approver and the tenant admin.
        $this->flag(true);
        $this->setUser($learner);
        $this->assertEqualsCanonicalizing([$native, $imported], $this->ids(list_mine::execute()));
        $this->setUser($approver);
        $this->assertEqualsCanonicalizing([$native, $imported], $this->ids(list_pending::execute()));
        $this->assertEqualsCanonicalizing([$native, $imported], $this->ids(list_all::execute()));
        $this->assertSame(2, request_manager::pending_count_for_approver((int) $approver->id));

        // And OFF again.
        $this->flag(false);
        $this->setUser($learner);
        $this->assertSame([$native], $this->ids(list_mine::execute()));
    }

    public function test_a_tenant_admin_sees_only_their_tenants_imported_rows(): void {
        $this->flag(true);
        $own = $this->imported(['costcenterid' => 1]);
        $foreign = $this->imported(['costcenterid' => 177]);
        $tenantless = $this->imported(['costcenterid' => 0]);

        $this->setUser($this->user('/1', true));
        $ids = $this->ids(list_all::execute('', 'timecreated', 'desc', 0, 100, '{"tenant":177}'));
        $this->assertContains($own, $ids);
        $this->assertNotContains($foreign, $ids, 'another tenant\'s history stays out, whatever the filter says');
        $this->assertNotContains($tenantless, $ids, 'a row with no tenant is cross-tenant only');

        $this->setAdminUser();
        $ids = $this->ids(list_all::execute('', 'timecreated', 'desc', 0, 100));
        $this->assertEqualsCanonicalizing([$own, $foreign, $tenantless], $ids);
    }

    // Item names.

    public function test_every_item_type_reads_by_its_own_name(): void {
        global $DB;
        $this->flag(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Payments 101']);
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Onboarding Journey', 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
            'status' => 1, 'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $ofcourse = $this->request(['itemid' => $course->id, 'courseid' => $course->id]);
        $gonecourse = $this->request(['itemid' => 987654, 'courseid' => 987654]);
        // Native path requests read "(deleted course)" before this fix, because only {course} was joined.
        $ofpath = $this->request(['item_type' => 'path', 'itemid' => $pathid]);
        $importedpath = $this->imported(['item_type' => 'path', 'itemid' => $pathid, 'status' => 'approved']);
        $gonepath = $this->imported(['item_type' => 'path', 'itemid' => 424242]);
        // Classroom and program tables belong to other plugins; a row whose item is not there must still list.
        $classroom = $this->imported(['item_type' => 'classroom', 'itemid' => 424242]);
        $program = $this->imported(['item_type' => 'program', 'itemid' => 424242]);
        $certification = $this->imported(['item_type' => 'certification', 'itemid' => 77]);

        $rows = [];
        foreach (list_all::execute('', 'timecreated', 'desc', 0, 100)['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $component = 'local_sentientia_request';
        $this->assertSame('Payments 101', $rows[$ofcourse]['course_name']);
        $this->assertSame(get_string('item_deleted_course', $component), $rows[$gonecourse]['course_name']);
        $this->assertSame('Onboarding Journey', $rows[$ofpath]['course_name']);
        $this->assertSame('Onboarding Journey', $rows[$importedpath]['course_name']);
        $this->assertSame(get_string('item_deleted', $component), $rows[$gonepath]['course_name']);
        $this->assertSame(get_string('item_deleted', $component), $rows[$classroom]['course_name']);
        $this->assertSame(get_string('item_deleted', $component), $rows[$program]['course_name']);
        $this->assertSame(get_string('item_certification', $component, 77), $rows[$certification]['course_name']);
        $this->assertSame('certification', $rows[$certification]['item_type']);
    }

    public function test_search_reaches_the_name_of_a_path(): void {
        global $DB;
        $this->flag(true);
        $this->setAdminUser();
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Quokka Safety Programme', 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
            'status' => 1, 'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $hit = $this->imported(['item_type' => 'path', 'itemid' => $pathid]);
        $this->imported(['item_type' => 'path', 'itemid' => 424242]);

        $this->assertSame([$hit], $this->ids(list_all::execute('quokka')));
        $learner = $this->user('/1');
        $mine = $this->imported(['userid' => $learner->id, 'item_type' => 'path', 'itemid' => $pathid]);
        $this->setUser($learner);
        $this->assertSame([$mine], $this->ids(list_mine::execute('Quokka')));
    }

    // Row shape.

    public function test_an_imported_row_says_it_has_no_reason_and_the_route_reads_in_words(): void {
        $component = 'local_sentientia_request';
        $imported = list_mine::shape((object) ['id' => 1, 'status' => 'approved', 'item_type' => 'course',
            'reason' => '', 'legacy_source' => 'bizlms', 'route' => 'legacy']);
        $this->assertSame(get_string('reason_imported', $component), $imported['reason']);
        $this->assertSame(get_string('route_legacy', $component), $imported['route_label']);
        $this->assertSame('legacy', $imported['route']);

        $native = list_mine::shape((object) ['id' => 2, 'status' => 'pending', 'item_type' => 'course',
            'reason' => '', 'legacy_source' => null, 'route' => 'manager']);
        $this->assertSame('', $native['reason'], 'only an imported row gets the placeholder');
        $this->assertSame(get_string('route_manager', $component), $native['route_label']);

        $this->assertSame('something_new', list_mine::route_label('something_new'), 'no string: the stored code');
        $this->assertSame('', list_mine::route_label(''));
    }

    // The approvals inbox.

    public function test_the_inbox_sorts_rows_with_no_deadline_last_in_either_direction(): void {
        $this->flag(true);
        $approver = $this->user('/1', true);
        $history = $this->imported(['approver_userid' => $approver->id, 'costcenterid' => 1]);
        $soon = $this->request(['approver_userid' => $approver->id, 'timedue' => time() + 3600]);
        $later = $this->request(['approver_userid' => $approver->id, 'timedue' => time() + 7200]);
        $this->setUser($approver);

        $this->assertSame([$soon, $later, $history], $this->ids(list_pending::execute()),
            'NULL sorts first in ascending order on MySQL; the imported history must not');
        $this->assertSame([$later, $soon, $history],
            $this->ids(list_pending::execute('', 'timedue', 'desc')));
    }

    public function test_the_inbox_returns_the_sla_badge_its_column_declares(): void {
        $component = 'local_sentientia_request';
        $this->flag(true);
        $approver = $this->user('/1', true);
        $overdue = $this->request(['approver_userid' => $approver->id, 'timedue' => time() - 7200]);
        $open = $this->request(['approver_userid' => $approver->id, 'timedue' => time() + 7200]);
        $history = $this->imported(['approver_userid' => $approver->id]);
        $this->setUser($approver);

        $rows = [];
        foreach (list_pending::execute()['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $this->assertSame(get_string('sla_overdue', $component), $rows[$overdue]['due_badge']);
        $this->assertSame('bg-danger', $rows[$overdue]['due_badge_class']);
        $this->assertStringStartsWith('Due in', $rows[$open]['due_badge']);
        $this->assertSame(get_string('sla_none', $component), $rows[$history]['due_badge']);
        $this->assertSame('bg-secondary', $rows[$history]['due_badge_class']);
    }

    public function test_only_a_course_or_path_request_gets_approve_and_reject_buttons(): void {
        $this->flag(true);
        $approver = $this->user('/1', true);
        $course = $this->imported(['approver_userid' => $approver->id, 'itemid' => 5, 'courseid' => 5]);
        $path = $this->imported(['approver_userid' => $approver->id, 'item_type' => 'path', 'itemid' => 6]);
        // The importer never gives these an approver; force one to prove the buttons are gated by type too.
        $classroom = $this->imported(['approver_userid' => $approver->id, 'item_type' => 'classroom', 'itemid' => 7]);
        $this->setUser($approver);

        $rows = [];
        foreach (list_pending::execute()['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $this->assertStringContainsString('decide-request', $rows[$course]['actions']);
        $this->assertStringContainsString('decide-request', $rows[$path]['actions']);
        $this->assertSame('', $rows[$classroom]['actions']);
    }

    // The engines.

    public function test_decide_refuses_every_item_type_it_cannot_act_on(): void {
        global $DB;
        $approver = $this->user('/1', true);
        $this->setUser($approver);
        foreach (['classroom', 'program', 'certification'] as $type) {
            $id = $this->imported(['approver_userid' => $approver->id, 'item_type' => $type, 'itemid' => 9]);
            foreach (['approved', 'rejected'] as $decision) {
                try {
                    request_manager::decide($id, (int) $approver->id, $decision, 'note');
                    $this->fail("decide({$decision}) must refuse a {$type} request");
                } catch (\moodle_exception $e) {
                    $this->assertSame('error_invalidstate', $e->errorcode);
                }
            }
            $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $id]));
        }
    }

    public function test_cron_skips_imported_rows_and_still_handles_native_ones(): void {
        global $DB;
        set_config('auto_expire_days', 1, 'local_sentientia_request');
        set_config('default_approver', (int) get_admin()->id, 'local_sentientia_request');
        $old = time() - 10 * 86400;
        $sink = $this->redirectMessages();

        $nativeoverdue = $this->request(['route' => 'manager', 'timedue' => $old, 'timecreated' => time()]);
        $importedoverdue = $this->imported(['route' => 'manager', 'timedue' => $old, 'timecreated' => time()]);
        $this->assertSame(1, request_manager::escalate_overdue());
        $this->assertSame('admin', $DB->get_field('local_sentientia_request', 'route', ['id' => $nativeoverdue]));
        $this->assertSame('manager', $DB->get_field('local_sentientia_request', 'route', ['id' => $importedoverdue]));

        $nativeold = $this->request(['timecreated' => $old]);
        $importedold = $this->imported(['timecreated' => $old]);
        $this->assertGreaterThanOrEqual(1, request_manager::auto_expire());
        $this->assertSame('expired', $DB->get_field('local_sentientia_request', 'status', ['id' => $nativeold]));
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $importedold]),
            'the first cron run after the import must not flip imported history to expired');
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $importedoverdue]));
        $sink->close();
    }

    public function test_routing_reads_the_supervisor_then_the_default(): void {
        global $DB;
        set_config('default_approver', (int) get_admin()->id, 'local_sentientia_request');
        $supervisor = $this->user('/1');
        $learner = $this->user('/1');
        $DB->set_field('user', 'open_supervisorid', $supervisor->id, ['id' => $learner->id]);
        $learner = $DB->get_record('user', ['id' => $learner->id], '*', MUST_EXIST);

        $this->assertSame([approver_routing::ROUTE_MANAGER, (int) $supervisor->id],
            approver_routing::for_course($learner, 5));
        $this->assertSame([approver_routing::ROUTE_MANAGER, (int) $supervisor->id],
            approver_routing::for_path($learner, 5));
        // The manager delegates to it: one copy of the rules.
        $this->assertSame(approver_routing::for_course($learner, 5), request_manager::route_approver($learner, 5));
        $this->assertSame(approver_routing::for_path($learner, 5), request_manager::route_approver_for_path($learner, 5));

        // A partial record (no supervisor column) routes to the default: why the importer passes the full row.
        $partial = (object) ['id' => $learner->id];
        $this->assertSame([approver_routing::ROUTE_ADMIN, (int) get_admin()->id],
            approver_routing::for_course($partial, 5));
        // A suspended supervisor does not count.
        $DB->set_field('user', 'suspended', 1, ['id' => $supervisor->id]);
        $this->assertSame([approver_routing::ROUTE_ADMIN, (int) get_admin()->id],
            approver_routing::for_path($learner, 5));
    }

    public function test_the_duplicate_guard_counts_imported_rows_only_where_the_lists_show_them(): void {
        global $DB;
        $learner = $this->user('/1');
        $this->setUser($learner);
        $sink = $this->redirectMessages();
        $reason = 'I need this for my certification cycle this quarter.';

        // A learner must not be refused for a pending request they cannot see in My requests.
        $hidden = $this->getDataGenerator()->create_course();
        $this->imported(['userid' => $learner->id, 'itemid' => $hidden->id, 'courseid' => $hidden->id]);
        $rec = request_manager::submit((int) $learner->id, (int) $hidden->id, $reason);
        $this->assertSame('pending', $rec->status, 'flag OFF: the imported row is invisible, so it is no duplicate');
        $this->assertNull($DB->get_field('local_sentientia_request', 'legacy_source', ['id' => $rec->id]),
            'a native submission is not marked');

        // Once the history is shown, the pending imported request is a real one.
        $this->flag(true);
        $shown = $this->getDataGenerator()->create_course();
        $this->imported(['userid' => $learner->id, 'itemid' => $shown->id, 'courseid' => $shown->id]);
        try {
            request_manager::submit((int) $learner->id, (int) $shown->id, $reason);
            $this->fail('flag ON: a pending imported request for the same course is a duplicate');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_alreadyrequested', $e->errorcode);
        }
        $this->assertSame(1, $DB->count_records('local_sentientia_request',
            ['userid' => $learner->id, 'courseid' => $shown->id]));
        $sink->close();
    }

    public function test_the_path_duplicate_guard_follows_the_same_rule(): void {
        global $DB;
        $learner = $this->user('/1');
        $this->setUser($learner);
        $sink = $this->redirectMessages();
        $reason = 'I need this path for my certification cycle this quarter.';
        $paths = [];
        foreach (['hidden', 'shown'] as $name) {
            $paths[$name] = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
                'name' => 'Path ' . $name, 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
                'status' => \local_sentientia_learningpath\path_manager::STATUS_ACTIVE, 'visible' => 1,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $this->imported(['userid' => $learner->id, 'item_type' => 'path', 'itemid' => $paths[$name]]);
        }

        $rec = request_manager::submit_path((int) $learner->id, $paths['hidden'], $reason);
        $this->assertSame('pending', $rec->status, 'flag OFF: the imported row is invisible, so it is no duplicate');

        $this->flag(true);
        try {
            request_manager::submit_path((int) $learner->id, $paths['shown'], $reason);
            $this->fail('flag ON: a pending imported request for the same path is a duplicate');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_alreadyrequested', $e->errorcode);
        }
        $sink->close();
    }

    // Privacy.

    public function test_the_provider_declares_the_columns_and_exports_the_item(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_sentientia_request'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        foreach (['userid', 'approver_userid', 'decided_by_userid', 'item_type', 'itemid', 'decision_note'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_request'], $column);
        }

        $user = $this->user('/1');
        $this->imported(['userid' => $user->id, 'item_type' => 'program', 'itemid' => 31, 'status' => 'approved']);
        $context = \context_system::instance();
        writer::reset();
        provider::export_user_data(new approved_contextlist($user, 'local_sentientia_request', [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_sentientia_request')]);
        $this->assertCount(1, $data->requests);
        $this->assertSame('program', $data->requests[0]['item_type']);
        $this->assertEquals(31, $data->requests[0]['item_id']);
    }

    public function test_erasing_a_user_redacts_the_names_in_imported_notes(): void {
        global $DB;
        $requester = $this->user('/1');
        $approver = $this->user('/1');
        $other = $this->user('/1');
        $note = '[2026-03-05 14:22] Some Person: please attach the approval';

        $imported = $this->imported(['userid' => $requester->id, 'approver_userid' => $approver->id,
            'decision_note' => $note]);
        $native = $this->request(['userid' => $requester->id, 'decided_by_userid' => $other->id,
            'decision_note' => 'a native note']);
        $unrelated = $this->imported(['userid' => $other->id, 'decision_note' => $note]);
        $context = \context_system::instance();

        // The approver is erased: the imported note that names them goes, the requester's row stays readable.
        provider::delete_data_for_user(new approved_contextlist($approver, 'local_sentientia_request', [$context->id]));
        $this->assertSame('(redacted)', $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $imported]));
        $this->assertSame($note, $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $unrelated]));

        // The requester is erased: their reason goes, and so does the imported note; the native note, decided by
        // someone else, stays as it always did.
        $DB->set_field('local_sentientia_request', 'decision_note', $note, ['id' => $imported]);
        provider::delete_data_for_user(new approved_contextlist($requester, 'local_sentientia_request', [$context->id]));
        $this->assertSame('(redacted)', $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $imported]));
        $this->assertSame('(redacted)', $DB->get_field('local_sentientia_request', 'reason', ['id' => $native]));
        $this->assertSame('a native note', $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $native]));
        $this->assertSame($note, $DB->get_field('local_sentientia_request', 'decision_note', ['id' => $unrelated]));
    }

    // The schema.

    public function test_the_upgrade_adds_legacy_source_once(): void {
        global $CFG, $DB;
        // DDL below: no rollback-based reset for this test.
        $this->preventResetByRollback();
        require_once($CFG->dirroot . '/local/sentientia_request/db/upgradelib.php');
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_sentientia_request');
        $field = new \xmldb_field('legacy_source', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'timemodified');

        // install.xml declares it: a fresh site has it, and the helper leaves it alone.
        $this->assertTrue($dbman->field_exists($table, $field));
        $this->assertFalse(local_sentientia_request_ensure_legacy_source($dbman));

        // A site upgraded from 1.4.0 does not: the helper adds it, once, and keeps the rows.
        $id = $this->request();
        $dbman->drop_field($table, $field);
        try {
            $this->assertFalse($dbman->field_exists($table, $field));
            $this->assertTrue(local_sentientia_request_ensure_legacy_source($dbman));
            $this->assertFalse(local_sentientia_request_ensure_legacy_source($dbman));
        } finally {
            // F-80: if an assertion above fails the column must not stay dropped for the tests that follow.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $columns = $DB->get_columns('local_sentientia_request', false);
        $this->assertArrayHasKey('legacy_source', $columns);
        $this->assertFalse((bool) $columns['legacy_source']->not_null, 'NULL on every native row');
        $this->assertEquals(40, $columns['legacy_source']->max_length);
        $this->assertNull($DB->get_field('local_sentientia_request', 'legacy_source', ['id' => $id]));
    }
}
