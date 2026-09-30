<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_org\privacy\provider;

/**
 * The privacy provider of local_sentientia_org names the one person column it holds, and never erases a scope row.
 *
 * local_sentientia_cohort_scope.usermodified is the administrator who last changed a cohort's scope (ADR-032). It is
 * an actor reference: a request exports it and clears it, and the row - which tenant a cohort belongs to - stays.
 *
 * @package    local_sentientia_org
 * @category   test
 * @covers     \local_sentientia_org\privacy\provider
 *
 * @group local_sentientia_org
 * @group bizlms_import
 */
final class privacy_cohort_scope_test extends \advanced_testcase {

    /** The table. */
    private const TABLE = 'local_sentientia_cohort_scope';

    /**
     * @param int $cohortid
     * @param int $usermodified
     * @return int The row id.
     */
    private function scope(int $cohortid, int $usermodified): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'cohortid' => $cohortid, 'open_path' => '/1/5', 'departmentids' => '5,12', 'usermodified' => $usermodified,
            'timemodified' => 1700000000,
        ]);
    }

    /**
     * @param \stdClass $user
     * @return approved_contextlist
     */
    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_org', [\context_system::instance()->id]);
    }

    public function test_the_provider_does_not_claim_the_plugin_holds_no_personal_data(): void {
        $this->assertNotContains(\core_privacy\local\metadata\null_provider::class, class_implements(provider::class));
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_sentientia_org'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertArrayHasKey(self::TABLE, $declared);
        $this->assertContains('usermodified', $declared[self::TABLE]);
    }

    public function test_a_user_is_found_in_the_system_context_only_when_they_changed_a_scope(): void {
        $this->resetAfterTest();
        $admin = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $this->scope(11, (int) $admin->id);

        $this->assertEquals([\context_system::instance()->id],
            array_values(provider::get_contexts_for_userid($admin->id)->get_contextids()));
        $this->assertEquals([], array_values(provider::get_contexts_for_userid($bystander->id)->get_contextids()));

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_org');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$admin->id], $userlist->get_userids());
    }

    public function test_export_gives_the_user_the_scopes_they_changed(): void {
        $this->resetAfterTest();
        $admin = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->scope(11, (int) $admin->id);
        $this->scope(12, (int) $other->id);

        writer::reset();
        provider::export_user_data($this->approved($admin));
        $data = writer::with_context(\context_system::instance())
            ->get_data([get_string('privacy:subcontext:cohort_scope', 'local_sentientia_org')]);
        $this->assertCount(1, $data->cohort_scope);
        $this->assertSame(11, $data->cohort_scope[0]->cohortid);
        $this->assertSame('/1/5', $data->cohort_scope[0]->open_path);
    }

    public function test_erasure_clears_the_reference_and_keeps_the_scope_row(): void {
        global $DB;
        $this->resetAfterTest();
        $admin = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->scope(11, (int) $admin->id);
        $theirs = $this->scope(12, (int) $other->id);

        provider::delete_data_for_user($this->approved($admin));

        $row = $DB->get_record(self::TABLE, ['id' => $mine], '*', MUST_EXIST);
        $this->assertEquals(0, $row->usermodified, 'the person is gone from the record');
        $this->assertSame('/1/5', $row->open_path, 'the record stays: it says which tenant the cohort belongs to');
        $this->assertEquals($other->id, $DB->get_field(self::TABLE, 'usermodified', ['id' => $theirs]));
    }

    public function test_the_dpdp_flow_takes_the_same_path(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertTrue(method_exists(provider::class, 'anonymise_data_for_user'));
        $admin = $this->getDataGenerator()->create_user();
        $mine = $this->scope(11, (int) $admin->id);

        provider::anonymise_data_for_user($this->approved($admin));

        $this->assertEquals(0, $DB->get_field(self::TABLE, 'usermodified', ['id' => $mine]));
        $this->assertTrue($DB->record_exists(self::TABLE, ['id' => $mine]));
    }

    public function test_erasing_a_list_of_users_and_a_whole_context(): void {
        global $DB;
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $three = $this->getDataGenerator()->create_user();
        $this->scope(11, (int) $one->id);
        $this->scope(12, (int) $two->id);
        $kept = $this->scope(13, (int) $three->id);

        provider::delete_data_for_users(new approved_userlist(\context_system::instance(), 'local_sentientia_org',
            [$one->id, $two->id]));
        $this->assertEquals(0, $DB->count_records_select(self::TABLE, 'usermodified IN (:a, :b)',
            ['a' => $one->id, 'b' => $two->id]));
        $this->assertEquals($three->id, $DB->get_field(self::TABLE, 'usermodified', ['id' => $kept]));

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(0, $DB->count_records_select(self::TABLE, 'usermodified > 0'));
        $this->assertEquals(3, $DB->count_records(self::TABLE), 'no scope row is ever deleted');
    }
}
