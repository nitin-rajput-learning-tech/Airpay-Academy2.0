<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_sentientia_core\privacy\provider;
use local_sentientia_platform\feature_flags;

/**
 * The imported BizLMS admin log, read side (ADR-032 legacy_logs, mapping doc section 8): the tenant scope of the
 * report, the filters, the erasure treatment the owner signed (keep the row, scrub the first name), the privacy
 * provider's export and erase paths, the default-OFF flag and the capability that no role holds by default.
 *
 * The rows are inserted directly, so these tests do not need the BizLMS tables or the importer.
 *
 * @package    local_sentientia_core
 * @category   test
 * @covers     \local_sentientia_core\admin_log
 * @covers     \local_sentientia_core\privacy\provider
 *
 * @group local_sentientia_core
 * @group bizlms_import
 */
final class admin_log_test extends provider_testcase {
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var string */
    private const COMPONENT = 'local_sentientia_core';

    /** @var int Timestamp base of the seed. */
    private const T0 = 1600000000;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    /**
     * One row of the table.
     *
     * @param array<string, mixed> $values
     * @return int
     */
    private function row(array $values = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_admin_log', (object) ($values + [
            'source' => 'local_logs', 'event' => 'insert', 'module' => 'course',
            'description' => 'User with Username "Asha"  created the course  "Safety 101"',
            'itemref' => '101', 'userid' => 0, 'usermodified' => 0, 'actor_path' => null,
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]));
    }

    /**
     * A user, optionally with a tenant path.
     *
     * @param string|null $path
     * @return \stdClass
     */
    private function user_at(?string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        if ($path !== null) {
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        }
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * A tenant admin as UAT has them: a manager-archetype role at system context.
     *
     * @param string $path
     * @return \stdClass
     */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $user = $this->user_at($path);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    // The description scrub.

    public function test_scrub_replaces_only_the_first_name_in_every_bizlms_shape(): void {
        $shapes = [
            // As BizLMS wrote them: the create string has two spaces before the verb.
            'User with Username "Asha"  created the course  "Safety 101"'
                => 'User with Username "[erased]"  created the course  "Safety 101"',
            'User with Username "Asha" has updated the course  "Safety 101"'
                => 'User with Username "[erased]" has updated the course  "Safety 101"',
            'User with Username "Asha" has deleted the course with courseid  "102"'
                => 'User with Username "[erased]" has deleted the course with courseid  "102"',
            'User with Username "Ravi Kumar" has deleted the forum with forumid  "9"'
                => 'User with Username "[erased]" has deleted the forum with forumid  "9"',
            'User with Username "आशा" has deleted the onlineexam with onlineexamid  "5"'
                => 'User with Username "[erased]" has deleted the onlineexam with onlineexamid  "5"',
        ];
        foreach ($shapes as $before => $after) {
            $this->assertSame($after, admin_log::scrub_description(admin_log::SOURCE_LOGS, $before));
            $this->assertSame($after, admin_log::scrub_description(admin_log::SOURCE_LOGS, $after), 'scrubbing twice changes nothing');
        }
    }

    public function test_scrub_replaces_a_description_it_cannot_read_and_keeps_an_upload_error(): void {
        $this->assertSame(admin_log::DESCRIPTION_PLACEHOLDER,
            admin_log::scrub_description(admin_log::SOURCE_LOGS, 'Asha deleted something'),
            'a name that cannot be located with certainty is not left behind');
        $this->assertSame('Category "Missing" not found',
            admin_log::scrub_description(admin_log::SOURCE_ERRORS, 'Category "Missing" not found'),
            'an upload error holds no first name');
    }

    // Erasure.

    public function test_erasing_a_user_keeps_the_rows_and_removes_the_person(): void {
        global $DB;
        $asha = $this->user_at('/1/5');
        $other = $this->user_at('/1/5');
        $acted = $this->row(['userid' => $asha->id, 'usermodified' => $asha->id]);
        $onlymodified = $this->row(['userid' => $other->id, 'usermodified' => $asha->id,
            'description' => 'User with Username "Ravi" has updated the course  "X"']);
        $untouched = $this->row(['userid' => $other->id, 'usermodified' => $other->id,
            'description' => 'User with Username "Ravi" has updated the course  "Y"']);

        // The id as a string, the way a user record from the database carries it.
        $changed = admin_log::anonymise_users([(string) $asha->id, 0, -5]);
        $this->assertGreaterThanOrEqual(2, $changed);

        $row = $DB->get_record('local_sentientia_admin_log', ['id' => $acted], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->userid);
        $this->assertSame(0, (int) $row->usermodified);
        $this->assertSame('User with Username "[erased]"  created the course  "Safety 101"', $row->description,
            'the row and the rest of its text are history, only the name goes');
        $this->assertSame('101', $row->itemref);
        $this->assertSame(self::T0, (int) $row->timecreated);

        $row = $DB->get_record('local_sentientia_admin_log', ['id' => $onlymodified], '*', MUST_EXIST);
        $this->assertSame((int) $other->id, (int) $row->userid, 'the actor is somebody else');
        $this->assertSame(0, (int) $row->usermodified, 'the erased user is removed as the modifier');
        $this->assertSame('User with Username "Ravi" has updated the course  "X"', $row->description,
            'the description names the actor, not the modifier');

        $row = $DB->get_record('local_sentientia_admin_log', ['id' => $untouched], '*', MUST_EXIST);
        $this->assertSame((int) $other->id, (int) $row->userid);
        $this->assertSame((int) $other->id, (int) $row->usermodified);
        $this->assertSame(3, $DB->count_records('local_sentientia_admin_log'), 'nothing was deleted');
    }

    public function test_erasing_every_user_keeps_every_row(): void {
        global $DB;
        $a = $this->user_at('/1');
        $b = $this->user_at('/77');
        $this->row(['userid' => $a->id, 'usermodified' => $a->id]);
        $this->row(['userid' => $b->id, 'usermodified' => $b->id, 'description' => 'User with Username "Ravi" has updated the course  "X"']);
        $this->row(['source' => 'local_courseerrors', 'event' => 'upload_error', 'description' => 'Invalid date', 'userid' => $b->id]);
        $this->row(['userid' => 0, 'usermodified' => 0, 'description' => 'Nightly sync']);

        $this->assertSame(3, admin_log::anonymise_all(), 'the row that holds no person is left alone');
        $this->assertSame(4, $DB->count_records('local_sentientia_admin_log'));
        $this->assertSame(0, $DB->count_records_select('local_sentientia_admin_log', 'userid <> 0 OR usermodified <> 0'));
        $this->assertFalse($DB->record_exists_select('local_sentientia_admin_log', $DB->sql_like('description', ':n'),
            ['n' => '%Asha%']), 'no first name is left');
        $this->assertFalse($DB->record_exists_select('local_sentientia_admin_log', $DB->sql_like('description', ':n'),
            ['n' => '%Ravi%']));
        $this->assertSame(0, admin_log::anonymise_all(), 'a second pass has nothing to do');
    }

    // The privacy provider.

    public function test_the_provider_lists_the_actor_and_the_modifier(): void {
        $actor = $this->user_at('/1');
        $modifier = $this->user_at('/1');
        $nobody = $this->user_at('/1');
        $this->row(['userid' => $actor->id, 'usermodified' => $modifier->id]);

        $userlist = new userlist(\context_system::instance(), self::COMPONENT);
        provider::get_users_in_context($userlist);
        $ids = array_map('intval', $userlist->get_userids());
        $this->assertContains((int) $actor->id, $ids);
        $this->assertContains((int) $modifier->id, $ids);
        $this->assertNotContains((int) $nobody->id, $ids);
    }

    public function test_the_provider_exports_the_entries_a_user_acted_in_or_modified(): void {
        $asha = $this->user_at('/1');
        $other = $this->user_at('/1');
        $this->row(['userid' => $asha->id, 'usermodified' => $asha->id, 'timecreated' => self::T0, 'timemodified' => self::T0 + 5]);
        $this->row(['userid' => $other->id, 'usermodified' => $asha->id,
            'description' => 'User with Username "Ravi" has updated the course  "X"', 'timecreated' => self::T0 + 10]);
        $this->row(['userid' => $other->id, 'usermodified' => $other->id,
            'description' => 'User with Username "Ravi" has updated the course  "Y"', 'timecreated' => self::T0 + 20]);

        $sys = \context_system::instance();
        $this->export_context_data_for_user((int) $asha->id, $sys, self::COMPONENT);
        $writer = writer::with_context($sys);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', self::COMPONENT), 'admin_log']);
        $this->assertCount(2, $data->entries, 'the entry only Ravi touched is Ravi\'s');
        $this->assertTrue($data->entries[0]['actor']);
        $this->assertTrue($data->entries[0]['modifier']);
        $this->assertSame('User with Username "Asha"  created the course  "Safety 101"', $data->entries[0]['description']);
        $this->assertFalse($data->entries[1]['actor'], 'the second entry lists Asha as the modifier only');
        $this->assertTrue($data->entries[1]['modifier']);
    }

    public function test_an_export_for_a_user_with_no_entries_writes_nothing(): void {
        $nobody = $this->user_at('/1');
        $this->row(['userid' => $this->user_at('/1')->id]);
        $sys = \context_system::instance();
        $this->export_context_data_for_user((int) $nobody->id, $sys, self::COMPONENT);
        $this->assertFalse(writer::with_context($sys)->has_any_data());
    }

    public function test_the_provider_erases_one_user_and_keeps_the_history(): void {
        global $DB;
        $asha = $this->user_at('/1');
        $other = $this->user_at('/1');
        $id = $this->row(['userid' => $asha->id, 'usermodified' => $asha->id]);
        $otherid = $this->row(['userid' => $other->id, 'usermodified' => $other->id,
            'description' => 'User with Username "Ravi" has updated the course  "X"']);

        provider::delete_data_for_user(new approved_contextlist($asha, self::COMPONENT, [\context_system::instance()->id]));

        $row = $DB->get_record('local_sentientia_admin_log', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->userid);
        $this->assertSame(0, (int) $row->usermodified);
        $this->assertStringNotContainsString('Asha', $row->description);
        $this->assertSame((int) $other->id, (int) $DB->get_field('local_sentientia_admin_log', 'userid', ['id' => $otherid]));
    }

    public function test_the_provider_erases_a_userlist_and_keeps_the_history(): void {
        global $DB;
        $asha = $this->user_at('/1');
        $other = $this->user_at('/1');
        $id = $this->row(['userid' => $asha->id, 'usermodified' => $asha->id]);
        $otherid = $this->row(['userid' => $other->id, 'usermodified' => $other->id,
            'description' => 'User with Username "Ravi" has updated the course  "X"']);

        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), self::COMPONENT, [(int) $asha->id]));

        $this->assertSame(0, (int) $DB->get_field('local_sentientia_admin_log', 'userid', ['id' => $id]));
        $this->assertSame((int) $other->id, (int) $DB->get_field('local_sentientia_admin_log', 'userid', ['id' => $otherid]));
        $this->assertSame(2, $DB->count_records('local_sentientia_admin_log'));
    }

    public function test_erasing_in_another_context_touches_nothing(): void {
        global $DB;
        $asha = $this->user_at('/1');
        $id = $this->row(['userid' => $asha->id, 'usermodified' => $asha->id]);
        provider::delete_data_for_user(new approved_contextlist($asha, self::COMPONENT,
            [\context_user::instance($asha->id)->id]));
        $this->assertSame((int) $asha->id, (int) $DB->get_field('local_sentientia_admin_log', 'userid', ['id' => $id]));
    }

    public function test_the_provider_erases_every_user_in_the_system_context(): void {
        global $DB;
        $asha = $this->user_at('/1');
        $this->row(['userid' => $asha->id, 'usermodified' => $asha->id]);
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(1, $DB->count_records('local_sentientia_admin_log'));
        $this->assertSame(0, $DB->count_records_select('local_sentientia_admin_log', 'userid <> 0 OR usermodified <> 0'));
    }

    public function test_the_provider_declares_the_admin_log_and_is_not_a_null_provider(): void {
        $this->assertFalse(in_array(\core_privacy\local\metadata\null_provider::class,
            class_implements(provider::class) ?: [], true), 'a table with a userid column is personal data');
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection(self::COMPONENT));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertArrayHasKey('local_sentientia_admin_log', $declared);
        foreach (['userid', 'usermodified', 'description', 'actor_path'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_admin_log']);
        }
        // Every declared privacy string exists (en/hi parity is checked by tools/check-lang-parity.php).
        foreach ($collection->get_collection() as $item) {
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), self::COMPONENT), $item->get_summary());
            foreach ($item->get_privacy_fields() as $field => $key) {
                $this->assertTrue(get_string_manager()->string_exists($key, self::COMPONENT), $key);
            }
        }
    }

    // The flag and the capability.

    public function test_the_report_flag_is_registered_and_off_until_someone_turns_it_on(): void {
        $this->setAdminUser();
        $this->assertFalse(admin_log::report_enabled(), 'default OFF');
        $this->assertFalse(feature_flags::is_enabled_for_tenant(admin_log::FLAG, 0));

        // set() throws for a key that is absent from every db/feature_flags.php, so this proves the registration.
        feature_flags::set(admin_log::FLAG, 0, true, (int) get_admin()->id, 'test');
        feature_flags::invalidate_caches();
        $this->assertTrue(admin_log::report_enabled());

        feature_flags::set(admin_log::FLAG, 0, null, (int) get_admin()->id, 'test');
        feature_flags::invalidate_caches();
        $this->assertFalse(admin_log::report_enabled(), 'unsetting returns to the default');
    }

    public function test_no_role_holds_the_capability_by_default(): void {
        global $DB;
        $this->assertNotFalse(get_capability_info('local/sentientia_core:viewadminlog'));
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => 'local/sentientia_core:viewadminlog']),
            'the log was never on screen in BizLMS, so nobody sees it until a role is given the capability');
    }

    // Tenant isolation.

    /**
     * Rows at /1/5, /1, /77 and /177/3, and two with no tenant.
     *
     * @return array<string, int> name => row id
     */
    private function tenant_rows(): array {
        return [
            'airpay_dept' => $this->row(['actor_path' => '/1/5', 'event' => 'insert']),
            'airpay_root' => $this->row(['actor_path' => '/1', 'event' => 'update']),
            'public' => $this->row(['actor_path' => '/77', 'event' => 'delete']),
            'zeea' => $this->row(['actor_path' => '/177/3', 'event' => 'insert']),
            'pathless' => $this->row(['actor_path' => null, 'event' => 'delete']),
            'error' => $this->row(['actor_path' => null, 'source' => 'local_courseerrors', 'event' => 'upload_error',
                'description' => 'Invalid date']),
        ];
    }

    /**
     * @param \stdClass[] $rows
     * @return int[] Row ids, sorted.
     */
    private function ids(array $rows): array {
        $ids = array_map(fn($r) => (int) $r->id, $rows);
        sort($ids);
        return $ids;
    }

    /**
     * @group tenant_isolation
     */
    public function test_a_tenant_admin_sees_their_own_tenant_and_nothing_else(): void {
        $rows = $this->tenant_rows();

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame([$rows['airpay_dept'], $rows['airpay_root']], $this->ids(admin_log::page([], 0, 50)));
        $this->assertSame(2, admin_log::count());

        $this->setUser($this->tenant_admin('/77'));
        $this->assertSame([$rows['public']], $this->ids(admin_log::page([], 0, 50)));

        $this->setUser($this->tenant_admin('/177'));
        $this->assertSame([$rows['zeea']], $this->ids(admin_log::page([], 0, 50)), 'a department below the tenant root is inside it');
    }

    /**
     * @group tenant_isolation
     */
    public function test_the_path_boundary_is_a_whole_segment(): void {
        $this->tenant_rows();
        // /7 must not see /77, /1 must not see /177, /17 must not see /177 or /1.
        foreach (['/7', '/17', '/17/1', '/2'] as $path) {
            $this->setUser($this->tenant_admin($path));
            $this->assertSame(0, admin_log::count(), $path . ' shares a prefix with a tenant, not a tenant');
            $this->assertSame([], admin_log::page([], 0, 50));
        }
    }

    /**
     * @group tenant_isolation
     */
    public function test_rows_with_no_tenant_are_for_cross_tenant_callers_only(): void {
        $rows = $this->tenant_rows();

        $this->setUser($this->tenant_admin('/1'));
        $this->assertNotContains($rows['pathless'], $this->ids(admin_log::page([], 0, 50)));
        $this->assertNotContains($rows['error'], $this->ids(admin_log::page([], 0, 50)));

        $this->setAdminUser();
        $this->assertSame(6, admin_log::count(), 'the site admin sees every tenant and the rows with none');
        $this->assertContains($rows['pathless'], $this->ids(admin_log::page([], 0, 50)));
    }

    /**
     * @group tenant_isolation
     */
    public function test_a_caller_with_no_tenant_sees_nothing_and_is_told_so(): void {
        $this->tenant_rows();
        $this->setUser($this->tenant_admin(''));
        $this->assertNull(admin_log::scope(), 'the page shows "no tenant" for this caller');
        $this->assertSame(0, admin_log::count());
        $this->assertSame([], admin_log::page([], 0, 50));

        $this->setUser($this->user_at(null));
        $this->assertNull(admin_log::scope());
    }

    /**
     * @group tenant_isolation
     */
    public function test_holding_the_capability_does_not_lift_the_tenant_scope(): void {
        global $DB;
        $rows = $this->tenant_rows();
        $roleid = $this->getDataGenerator()->create_role();
        $sys = \context_system::instance();
        assign_capability('local/sentientia_core:viewadminlog', CAP_ALLOW, $roleid, $sys->id, true);
        $auditor = $this->user_at('/77');
        role_assign($roleid, $auditor->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($DB->get_record('user', ['id' => $auditor->id], '*', MUST_EXIST));
        $this->assertTrue(has_capability('local/sentientia_core:viewadminlog', $sys));
        $this->assertSame([$rows['public']], $this->ids(admin_log::page([], 0, 50)),
            'the capability says what, the tenant scope says where (ADR-031)');
    }

    // Filters and paging.

    public function test_filters_paging_and_the_actor_join(): void {
        global $DB;
        $live = $this->user_at('/1');
        $gone = $this->user_at('/1');
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $this->row(['userid' => $live->id, 'event' => 'insert', 'timecreated' => self::T0 + 1]);
        $this->row(['userid' => $gone->id, 'event' => 'delete', 'timecreated' => self::T0 + 2]);
        $this->row(['userid' => 987654, 'event' => 'update', 'timecreated' => self::T0 + 3]);
        $this->row(['userid' => 0, 'source' => 'local_courseerrors', 'event' => 'upload_error', 'timecreated' => self::T0 + 4]);
        for ($i = 0; $i < 10; $i++) {
            $this->row(['event' => 'update', 'module' => 'forum', 'timecreated' => self::T0 + 10 + $i]);
        }
        $this->setAdminUser();

        $this->assertSame(14, admin_log::count());
        $this->assertSame(1, admin_log::count(['source' => 'local_courseerrors']));
        $this->assertSame(1, admin_log::count(['event' => 'delete']));
        $this->assertSame(10, admin_log::count(['module' => 'forum']));
        $this->assertSame(0, admin_log::count(['event' => 'nothing_like_this']));
        $this->assertSame(1, admin_log::count(['source' => 'local_logs', 'event' => 'delete']));

        $first = admin_log::page([], 0, 10);
        $this->assertCount(10, $first, 'a page holds at most what was asked');
        $this->assertSame(self::T0 + 19, (int) $first[0]->timecreated, 'newest first');
        $this->assertCount(4, admin_log::page([], 1, 10), 'the rest is on page 2');
        $this->assertCount(10, admin_log::page([], 0, 1), 'a page is never smaller than 10');

        $byevent = [];
        foreach (admin_log::page(['source' => 'local_logs'], 0, 100) as $row) {
            if ($row->event === 'delete') {
                $byevent['delete'] = $row;
            } else if ($row->event === 'update' && (int) $row->userid === 987654) {
                $byevent['ghost'] = $row;
            } else if ($row->event === 'insert') {
                $byevent['insert'] = $row;
            }
        }
        $this->assertSame((string) $live->firstname, $byevent['insert']->firstname);
        $this->assertEquals(1, $byevent['delete']->actor_deleted, 'the page can mark a deleted actor');
        $this->assertNull($byevent['ghost']->firstname, 'an actor with no user row has no name');

        $options = admin_log::filter_options();
        $this->assertEquals(['local_courseerrors' => 1, 'local_logs' => 13], $options['source']);
        $this->assertEquals(['delete' => 1, 'insert' => 1, 'update' => 11, 'upload_error' => 1], $options['event']);
        $this->assertEquals(['course' => 4, 'forum' => 10], $options['module']);
    }
}
