<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider lock-in tests for local_airpay_skills.
 *
 * @package    local_airpay_skills
 * @category   test
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    private function seed_user_skill(int $userid): array {
        global $DB;
        $catid = $DB->insert_record('local_airpay_skill_cats', (object) [
            'name' => 'Tech', 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time(),
        ]);
        $skillid = (int) $DB->insert_record('local_airpay_skills', (object) [
            'categoryid' => $catid, 'name' => 'Python', 'description' => '',
            'max_level' => 5, 'sort_order' => 1, 'timecreated' => time(),
        ]);
        $usid = (int) $DB->insert_record('local_airpay_user_skills', (object) [
            'userid' => $userid, 'skillid' => $skillid,
            'current_level' => 3, 'source' => 'manual',
            'source_id' => 0, 'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return ['skillid' => $skillid, 'usid' => $usid];
    }

    public function test_get_metadata_declares_user_skills_table(): void {
        $collection = new \core_privacy\local\metadata\collection('local_airpay_skills');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('local_airpay_user_skills', $items[0]->get_name());
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->seed_user_skill((int) $u->id);

        $userlist = new \core_privacy\local\request\userlist(
            \context_system::instance(), 'local_airpay_skills');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $u->id, $userlist->get_userids());
    }

    public function test_export_user_data_includes_skill_levels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->seed_user_skill((int) $u->id);

        $sysctx = \context_system::instance();
        $cl = new approved_contextlist($u, 'local_airpay_skills', [$sysctx->id]);
        provider::export_user_data($cl);

        $this->assertTrue(writer::with_context($sysctx)->has_any_data());
    }

    public function test_delete_data_for_user_removes_their_skills(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->seed_user_skill((int) $u->id);

        $this->assertTrue($DB->record_exists('local_airpay_user_skills',
            ['userid' => $u->id]));

        $cl = new approved_contextlist($u, 'local_airpay_skills',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($cl);

        $this->assertFalse($DB->record_exists('local_airpay_user_skills',
            ['userid' => $u->id]));
    }

    public function test_delete_for_user_does_not_touch_reference_tables(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $seeded = $this->seed_user_skill((int) $u->id);

        $cl = new approved_contextlist($u, 'local_airpay_skills',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($cl);

        // Skills table + categories must still be intact.
        $this->assertTrue($DB->record_exists('local_airpay_skills',
            ['id' => $seeded['skillid']]),
            'skills reference data must NOT be deleted by GDPR delete');
    }
}
