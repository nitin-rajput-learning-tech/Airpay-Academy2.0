<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// SENTIENTIA-CORE-MOD (vendor): tests for the working privacy provider (2026-11-13).
/**
 * Privacy provider tests for block_learnerscript.
 *
 * The vendor provider declared one table and handled none; seven tables hold user data. These tests seed every
 * table and assert on the rows: the subject's own rows go, an actor column goes to 0, a recipient comes off a
 * schedule's list, and other users keep everything.
 *
 * @package    block_learnerscript
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnerscript;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use block_learnerscript\privacy\provider;

/**
 * @covers \block_learnerscript\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    /** @var string[] */
    private const SUBJECT_TABLES = ['block_devicels', 'block_ls_coursetimestats', 'block_ls_modtimestats',
        'block_ls_learningformats', 'block_ls_exams', 'block_ls_certificates'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * One row in each subject table for the user.
     */
    private function seed(int $userid, int $modifiedby = 0): void {
        global $DB;
        $org = ['user_costcenterid' => 1, 'user_departmentid' => 0, 'user_subdepartment' => 0,
            'costcenterid' => 1, 'departmentid' => 0, 'subdepartment' => 0];
        $DB->insert_record('block_devicels', (object) ['userid' => $userid, 'accessip' => '10.1.2.3',
            'city' => 'Mumbai', 'timemodified' => time()]);
        $DB->insert_record('block_ls_coursetimestats', (object) ['userid' => $userid, 'courseid' => 2,
            'timespent' => 600, 'timemodified' => time()]);
        $DB->insert_record('block_ls_modtimestats', (object) ['userid' => $userid, 'courseid' => 2,
            'instanceid' => 1, 'activityid' => 3, 'timespent' => 60, 'timemodified' => time()]);
        $DB->insert_record('block_ls_learningformats', (object) ($org + ['moduleid' => 1, 'moduletype' => 'course',
            'learningformatid' => 1, 'name' => 'AML', 'userid' => $userid, 'username' => 'Asha Rao',
            'enroldate' => time()]));
        $DB->insert_record('block_ls_exams', (object) ($org + ['examid' => 1, 'examname' => 'KYC', 'vendorid' => 1,
            'vendorname' => 'V', 'userid' => $userid, 'username' => 'Asha Rao', 'enroldate' => time(),
            'usermodified' => $modifiedby]));
        $DB->insert_record('block_ls_certificates', (object) ($org + ['certificateid' => 1,
            'certificatename' => 'POSH', 'vendorid' => 1, 'vendorname' => 'V', 'userid' => $userid,
            'username' => 'Asha Rao', 'courseid' => 2, 'enroldate' => time(), 'usermodified' => $modifiedby]));
    }

    private function schedule(int $creator, string $recipients): int {
        global $DB;
        return (int) $DB->insert_record('block_ls_schedule', (object) ['reportid' => 1, 'userid' => $creator,
            'exporttofilesystem' => 0, 'exportformat' => 'csv', 'frequency' => 1, 'schedule' => '0',
            'nextschedule' => '0', 'roleid' => '0', 'sendinguserid' => $recipients, 'timecreated' => time(),
            'timemodified' => time(), 'contextlevel' => CONTEXT_SYSTEM, 'costcenterid' => 1,
            'departmentid' => 0, 'subdepartment' => 0]);
    }

    private function rows(int $userid): int {
        global $DB;
        $n = 0;
        foreach (self::SUBJECT_TABLES as $table) {
            $n += $DB->count_records($table, ['userid' => $userid]);
        }
        return $n;
    }

    public function test_metadata_declares_every_user_table(): void {
        $names = array_map(fn($item) => $item->get_name(),
            provider::get_metadata(new collection('block_learnerscript'))->get_collection());
        foreach (array_merge(self::SUBJECT_TABLES, ['block_ls_schedule']) as $table) {
            $this->assertContains($table, $names);
        }
    }

    /**
     * Erasure: own rows deleted, actor column zeroed, recipient removed, the schedule kept, others untouched.
     */
    public function test_erasure(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $this->seed((int) $subject->id);
        $this->seed((int) $other->id, (int) $subject->id);
        $created = $this->schedule((int) $subject->id, "{$other->id}");
        $received = $this->schedule((int) $other->id, "{$other->id},{$subject->id}");
        $sys = \context_system::instance();

        $this->assertSame([(int) $sys->id],
            array_map('intval', provider::get_contexts_for_userid((int) $subject->id)->get_contextids()));

        provider::delete_data_for_user(new approved_contextlist($subject, 'block_learnerscript', [$sys->id]));

        $this->assertSame(0, $this->rows((int) $subject->id));
        $this->assertSame(6, $this->rows((int) $other->id));
        $this->assertSame(0, $DB->count_records('block_ls_exams', ['usermodified' => $subject->id]));
        $this->assertSame(0, $DB->count_records('block_ls_certificates', ['usermodified' => $subject->id]));
        $this->assertSame(0, (int) $DB->get_field('block_ls_schedule', 'userid', ['id' => $created]));
        $this->assertSame((string) $other->id, $DB->get_field('block_ls_schedule', 'sendinguserid', ['id' => $received]));
        $this->assertSame(2, $DB->count_records('block_ls_schedule'), 'schedules stay');
        $this->assertSame([], provider::get_contexts_for_userid((int) $subject->id)->get_contextids());
    }

    /**
     * Export: every own row, the IP among them, and the schedules the user created or receives.
     */
    public function test_export(): void {
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $this->seed((int) $subject->id);
        $this->schedule((int) $other->id, "{$subject->id}");
        $sys = \context_system::instance();

        provider::export_user_data(new approved_contextlist($subject, 'block_learnerscript', [$sys->id]));

        $root = get_string('pluginname', 'block_learnerscript');
        $writer = writer::with_context($sys);
        $this->assertSame('10.1.2.3', $writer->get_data([$root, 'block_devicels'])->rows[0]['accessip']);
        foreach (self::SUBJECT_TABLES as $table) {
            $this->assertNotEmpty($writer->get_data([$root, $table]), $table);
        }
        $this->assertSame('recipient', $writer->get_data([$root, 'block_ls_schedule'])->schedules[0]['role']);
    }

    public function test_userlist_and_context_wide_erasure(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $c = $gen->create_user();
        $this->seed((int) $a->id);
        $this->seed((int) $b->id);
        $this->schedule((int) $b->id, "{$c->id}");
        $sys = \context_system::instance();

        $userlist = new userlist($sys, 'block_learnerscript');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $this->assertSame([(int) $a->id, (int) $b->id, (int) $c->id], $found);

        provider::delete_data_for_users(new approved_userlist($sys, 'block_learnerscript', [$a->id, $c->id]));
        $this->assertSame(0, $this->rows((int) $a->id));
        $this->assertSame(6, $this->rows((int) $b->id));
        $this->assertSame('', $DB->get_field('block_ls_schedule', 'sendinguserid', ['userid' => $b->id]));

        provider::delete_data_for_all_users_in_context($sys);
        $this->assertSame(0, $this->rows((int) $b->id));
        $this->assertSame(1, $DB->count_records('block_ls_schedule'));
        $this->assertSame(0, $DB->count_records_select('block_ls_schedule', 'userid > 0'));
    }
}
// END SENTIENTIA-CORE-MOD
