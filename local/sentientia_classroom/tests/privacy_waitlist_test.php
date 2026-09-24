<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/provider_without_waitlist.php');

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_sentientia_classroom\privacy\provider;
use local_sentientia_classroom\privacy\provider_without_waitlist;

/**
 * Privacy provider tests for the classroom waiting list.
 *
 * Written 2026-09-24. local_sentientia_classroom_waitlist holds a userid and,
 * after an admin removal, the admin's free-text reason. The provider never
 * declared, reported, exported or erased it, so a DPDP erasure read
 * 'completed' with every waiting-list row intact. The table was also absent
 * from db/install.xml (only upgrade step 2026051130 created it), so a fresh
 * install - and the PHPUnit database - had no waiting list at all.
 *
 * Nothing here has been run yet (the shared PHPUnit database is being
 * rebuilt); it was checked by reading against db/install.xml.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class privacy_waitlist_test extends provider_testcase {

    private const COMPONENT = 'local_sentientia_classroom';
    private const WAITLIST  = 'local_sentientia_classroom_waitlist';

    private function classroom(): int {
        global $DB;
        // name is the only NOT NULL column without a default.
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Full classroom', 'capacity' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * One waiting-list row. status is CHAR(20): 'waiting', 'promoted' or
     * 'removed'. No unique index on this table, so rows can repeat freely.
     */
    private function wait(int $classroomid, int $userid, int $position,
            string $status = 'waiting', ?string $reason = null): int {
        global $DB;
        return (int) $DB->insert_record(self::WAITLIST, (object) [
            'classroomid'  => $classroomid,
            'userid'       => $userid,
            'position'     => $position,
            'status'       => $status,
            'reason'       => $reason,
            'removed_at'   => $status === 'removed' ? time() : null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, self::COMPONENT, [\context_system::instance()->id]);
    }

    public function test_a_fresh_install_creates_the_waitlist_table(): void {
        global $DB;
        // The PHPUnit database is built from db/install.xml alone, never from
        // upgrade.php: this is exactly the schema a fresh install gets.
        $this->assertTrue($DB->get_manager()->table_exists(self::WAITLIST));
        $this->assertEqualsCanonicalizing(
            ['id', 'classroomid', 'userid', 'position', 'status', 'reason',
             'promoted_at', 'removed_at', 'timecreated', 'timemodified'],
            array_keys($DB->get_columns(self::WAITLIST)));
    }

    public function test_metadata_declares_the_waitlist_and_its_strings_exist(): void {
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $table = null;
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof database_table && $item->get_name() === self::WAITLIST) {
                $table = $item;
            }
        }
        $this->assertNotNull($table, 'The waiting list holds userid and must be declared.');

        $fields = $table->get_privacy_fields();
        $this->assertArrayHasKey('userid', $fields);
        $this->assertArrayHasKey('reason', $fields, 'reason can hold an admin\'s free text about the person.');

        $strings = get_string_manager();
        foreach (array_merge([$table->get_summary()], array_values($fields)) as $identifier) {
            $this->assertTrue($strings->string_exists($identifier, self::COMPONENT), $identifier);
        }
    }

    public function test_a_waitlist_only_user_is_reported(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $this->wait($this->classroom(), (int) $subject->id, 1);
        $syscontext = \context_system::instance();

        $contextids = provider::get_contexts_for_userid((int) $subject->id)->get_contextids();
        $this->assertContains((int) $syscontext->id, array_map('intval', $contextids),
            'A waiting-list place with no roster or attendance row is still data about this person.');

        $userlist = new userlist($syscontext, self::COMPONENT);
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $subject->id, array_map('intval', $userlist->get_userids()));
    }

    public function test_export_includes_waitlist_rows_and_the_removal_reason(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $this->wait($this->classroom(), (int) $subject->id, 1);
        $this->wait($this->classroom(), (int) $subject->id, 1, 'removed', 'Removed at the line manager\'s request');
        $syscontext = \context_system::instance();

        $this->export_context_data_for_user((int) $subject->id, $syscontext, self::COMPONENT);

        $writer = writer::with_context($syscontext);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data(['sentientia_classroom']);
        $this->assertEquals(2, $data->waitlist_count);
        $this->assertCount(2, $data->waitlist);
        $reasons = array_column(array_map(fn($row) => (array) $row, $data->waitlist), 'reason');
        $this->assertContains('Removed at the line manager\'s request', $reasons);
    }

    public function test_erasure_removes_every_waitlist_row_and_moves_the_queue_up(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();

        // The subject heads a queue, the other person is behind them...
        $cid = $this->classroom();
        $this->wait($cid, (int) $subject->id, 1);
        $behind = $this->wait($cid, (int) $other->id, 2);
        // ...and an older, admin-removed place elsewhere carries free text.
        $this->wait($this->classroom(), (int) $subject->id, 1, 'removed', 'Free text about the subject');

        provider::delete_data_for_user($this->approved($subject));

        $this->assertEquals(0, $DB->count_records(self::WAITLIST, ['userid' => $subject->id]));
        // The other person's place survives and is now the head of the queue.
        $row = $DB->get_record(self::WAITLIST, ['id' => $behind], '*', MUST_EXIST);
        $this->assertEquals('waiting', $row->status);
        $this->assertEquals(1, (int) $row->position);
    }

    public function test_bulk_erasure_removes_only_the_listed_users(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $keeper = $gen->create_user();
        $cid = $this->classroom();
        $this->wait($cid, (int) $a->id, 1);
        $kept = $this->wait($cid, (int) $keeper->id, 2);
        $this->wait($cid, (int) $b->id, 3);

        provider::delete_data_for_users(new approved_userlist(
            \context_system::instance(), self::COMPONENT, [(int) $a->id, (int) $b->id]));

        $this->assertEquals(0, $DB->count_records(self::WAITLIST, ['userid' => $a->id]));
        $this->assertEquals(0, $DB->count_records(self::WAITLIST, ['userid' => $b->id]));
        $this->assertEquals(1, (int) $DB->get_field(self::WAITLIST, 'position', ['id' => $kept], MUST_EXIST));
    }

    public function test_dpdp_anonymise_drops_the_waitlist_but_keeps_attendance(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $cid = $this->classroom();
        $now = time();
        $sessionid = (int) $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid' => $cid, 'sessiondate' => $now, 'starttime' => $now,
            'endtime' => $now + HOURSECS, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        // status is INT(2) here: 1 = present. UNIQUE(sessionid, userid).
        $attendance = (int) $DB->insert_record('local_sentientia_classroom_attendance', (object) [
            'sessionid' => $sessionid, 'userid' => (int) $subject->id, 'status' => 1,
            'notes' => 'Personal note', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->wait($cid, (int) $subject->id, 1, 'removed', 'Free text about the subject');

        provider::anonymise_data_for_user($this->approved($subject));

        // A waiting-list place is not a learning record: it goes.
        $this->assertEquals(0, $DB->count_records(self::WAITLIST, ['userid' => $subject->id]));
        // Attendance is, and stays, without its note.
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_attendance', ['id' => $attendance]));
        $this->assertNull($DB->get_field('local_sentientia_classroom_attendance', 'notes', ['id' => $attendance]));
    }

    public function test_erasing_the_whole_context_clears_the_waitlist(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cid = $this->classroom();
        $this->wait($cid, (int) $gen->create_user()->id, 1);
        $this->wait($cid, (int) $gen->create_user()->id, 2);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(0, $DB->count_records(self::WAITLIST));
    }

    /**
     * A site installed fresh before 2026-09-24 has no waiting-list table. The
     * double reports it absent while the real one still holds a row, so any
     * unguarded access shows up as that row being read or changed.
     */
    public function test_without_the_table_no_path_touches_it_and_erasure_still_runs(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        $cid = $this->classroom();
        $row = $this->wait($cid, (int) $subject->id, 1, 'removed', 'Free text about the subject');

        // Discovery: the waiting list is never consulted.
        $this->assertEmpty(provider_without_waitlist::get_contexts_for_userid((int) $subject->id)->get_contextids());
        $userlist = new userlist($syscontext, self::COMPONENT);
        provider_without_waitlist::get_users_in_context($userlist);
        $this->assertNotContains((int) $subject->id, array_map('intval', $userlist->get_userids()));

        // A roster row, so there is something for the rest of erasure to do.
        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid' => $cid, 'userid' => (int) $subject->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        provider_without_waitlist::export_user_data($this->approved($subject));
        $data = writer::with_context($syscontext)->get_data(['sentientia_classroom']);
        $this->assertEquals(1, $data->roster_count);
        $this->assertEquals(0, $data->waitlist_count);

        provider_without_waitlist::delete_data_for_user($this->approved($subject));
        $this->assertEquals(0, $DB->count_records('local_sentientia_classroom_users', ['userid' => $subject->id]));

        provider_without_waitlist::anonymise_data_for_user($this->approved($subject));
        provider_without_waitlist::delete_data_for_users(
            new approved_userlist($syscontext, self::COMPONENT, [(int) $subject->id]));
        provider_without_waitlist::delete_data_for_all_users_in_context($syscontext);

        $this->assertTrue($DB->record_exists(self::WAITLIST, ['id' => $row]),
            'No path may read or write a table the guard reports absent.');
    }
}
