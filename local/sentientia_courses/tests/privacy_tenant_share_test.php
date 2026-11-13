<?php
// This file is part of Sentientia LMS.

/**
 * Privacy for local_sentientia_courses_tenant_share (declared 2026-11-13).
 *
 * A share row is a tenant's catalog configuration. The admin who created or
 * last changed it is an ACTOR on it, so erasure anonymises shared_by to 0 and
 * keeps the share; export gives the admin every share they are named on.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_courses\privacy\provider;

/**
 * @covers \local_sentientia_courses\privacy\provider
 */
final class privacy_tenant_share_test extends \core_privacy\tests\provider_testcase {

    /** @var string */
    private const TABLE = 'local_sentientia_courses_tenant_share';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    private function share(int $courseid, int $tenantid, int $sharedby): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'courseid'     => $courseid,
            'tenant_id'    => $tenantid,
            'shared_by'    => $sharedby,
            'status'       => 'active',
            'timeshared'   => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_metadata_declares_the_share_table(): void {
        $names = array_map(fn($item) => $item->get_name(),
            provider::get_metadata(new collection('local_sentientia_courses'))->get_collection());
        $this->assertContains(self::TABLE, $names);
    }

    /**
     * The sharer is found, listed and exported; another admin's share is not theirs.
     */
    public function test_sharer_is_found_and_exported(): void {
        $gen = $this->getDataGenerator();
        $admin = $gen->create_user();
        $other = $gen->create_user();
        $course = $gen->create_course();
        $this->share($course->id, 77, $admin->id);
        $this->share($course->id, 177, $other->id);

        $sys = \context_system::instance();
        $this->assertSame([(int) $sys->id],
            array_map('intval', provider::get_contexts_for_userid($admin->id)->get_contextids()));

        $userlist = new userlist($sys, 'local_sentientia_courses');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $admin->id, array_map('intval', $userlist->get_userids()));
        $this->assertContains((int) $other->id, array_map('intval', $userlist->get_userids()));

        provider::export_user_data(new approved_contextlist($admin, 'local_sentientia_courses', [$sys->id]));
        $data = writer::with_context($sys)->get_data([
            get_string('pluginname', 'local_sentientia_courses'),
            get_string('privacy:metadata:courses_tenant_share', 'local_sentientia_courses'),
        ]);
        $this->assertCount(1, $data->courseshares);
        $this->assertEquals(77, $data->courseshares[0]['tenant_id']);
    }

    /**
     * Erasure keeps the share (the tenant's catalog) and zeroes only the sharer.
     */
    public function test_erasure_anonymises_the_sharer_and_keeps_the_share(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $admin = $gen->create_user();
        $other = $gen->create_user();
        $course = $gen->create_course();
        $mine = $this->share($course->id, 77, $admin->id);
        $theirs = $this->share($course->id, 177, $other->id);
        $sys = \context_system::instance();

        provider::delete_data_for_user(new approved_contextlist($admin, 'local_sentientia_courses', [$sys->id]));

        $this->assertSame(2, $DB->count_records(self::TABLE));
        $this->assertSame(0, (int) $DB->get_field(self::TABLE, 'shared_by', ['id' => $mine]));
        $this->assertSame((int) $other->id, (int) $DB->get_field(self::TABLE, 'shared_by', ['id' => $theirs]));
        $this->assertSame('active', $DB->get_field(self::TABLE, 'status', ['id' => $mine]));
    }

    public function test_userlist_and_context_wide_erasure(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $course = $gen->create_course();
        $this->share($course->id, 77, $a->id);
        $this->share($course->id, 177, $b->id);
        $sys = \context_system::instance();

        provider::delete_data_for_users(new approved_userlist($sys, 'local_sentientia_courses', [$a->id]));
        $this->assertSame(0, $DB->count_records(self::TABLE, ['shared_by' => $a->id]));
        $this->assertSame(1, $DB->count_records(self::TABLE, ['shared_by' => $b->id]));

        provider::delete_data_for_all_users_in_context($sys);
        $this->assertSame(2, $DB->count_records(self::TABLE), 'the shares stay');
        $this->assertSame(2, $DB->count_records(self::TABLE, ['shared_by' => 0]));
    }
}
