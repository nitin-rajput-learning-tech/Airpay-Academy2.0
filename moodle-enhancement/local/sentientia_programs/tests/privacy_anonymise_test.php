<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use local_sentientia_programs\privacy\provider;

/**
 * The DPDP erasure keeps certification-program records; core's erasure does not.
 *
 * Regression guard for 2026-09-24. local_sentientia_privacy's
 * privacy_manager::process_deletion() calls each Sentientia provider's
 * anonymise_data_for_user() when it has one and delete_data_for_user()
 * otherwise. This provider had no anonymise hook, so a right-to-erasure
 * request deleted local_sentientia_programs_users - the enrolment and
 * COMPLETION record of a certification program - which that flow promises to
 * keep, anonymised, against the user row it anonymises in place.
 *
 * @package    local_sentientia_programs
 * @category   test
 * @covers     \local_sentientia_programs\privacy\provider
 */
final class privacy_anonymise_test extends \advanced_testcase {

    /** A fixed completion time, so the kept row can be compared exactly. */
    private const COMPLETED_AT = 1767225600;

    /**
     * Insert a program with one level. Only NOT NULL columns without a
     * default are named, plus the ones the test reads back.
     *
     * @return array{0:int,1:int} [programid, levelid]
     */
    private function seed_program(string $name): array {
        global $DB;
        $now = time();
        $programid = (int) $DB->insert_record('local_sentientia_programs', (object) [
            'name'         => $name,
            'costcenterid' => 1,
            'status'       => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $levelid = (int) $DB->insert_record('local_sentientia_programs_levels', (object) [
            'programid'   => $programid,
            'name'        => 'Level 1',
            'sortorder'   => 0,
            'timecreated' => $now,
        ]);
        return [$programid, $levelid];
    }

    /**
     * Enrol a user in a program. status: 0 enrolled, 1 in progress, 2 completed.
     */
    private function enrol(int $programid, int $userid, int $levelid, int $status,
            ?int $timecompleted = null): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs_users', (object) [
            'programid'      => $programid,
            'userid'         => $userid,
            'currentlevelid' => $levelid,
            'status'         => $status,
            'timecreated'    => time(),
            'timecompleted'  => $timecompleted,
        ]);
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_programs',
            [\context_system::instance()->id]);
    }

    public function test_the_dpdp_flow_takes_the_anonymise_path(): void {
        // privacy_manager duck-types on this method. If it is removed, the
        // DPDP flow silently falls back to delete_data_for_user().
        $this->assertTrue(method_exists(provider::class, 'anonymise_data_for_user'));
    }

    public function test_anonymise_keeps_the_certification_record(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        [$certified, $certlevel] = $this->seed_program('AML certification');
        [$ongoing, $ongoinglevel] = $this->seed_program('KYC certification');
        $completed = $this->enrol($certified, (int) $subject->id, $certlevel, 2, self::COMPLETED_AT);
        $inprogress = $this->enrol($ongoing, (int) $subject->id, $ongoinglevel, 1);
        $theirs = $this->enrol($certified, (int) $other->id, $certlevel, 0);

        provider::anonymise_data_for_user($this->approved($subject));

        // The certification survives, still keyed to the subject's user row
        // (which the DPDP flow anonymises in place), every field intact.
        $kept = $DB->get_record('local_sentientia_programs_users', ['id' => $completed], '*', MUST_EXIST);
        $this->assertEquals($subject->id, $kept->userid);
        $this->assertEquals($certified, $kept->programid);
        $this->assertEquals(2, $kept->status);
        $this->assertEquals(self::COMPLETED_AT, $kept->timecompleted);
        $this->assertEquals($certlevel, $kept->currentlevelid);

        // So does the enrolment that is still in progress: how far the person
        // got is part of the same record.
        $kept = $DB->get_record('local_sentientia_programs_users', ['id' => $inprogress], '*', MUST_EXIST);
        $this->assertEquals(1, $kept->status);
        $this->assertEquals($ongoinglevel, $kept->currentlevelid);

        $this->assertEquals(2, $DB->count_records('local_sentientia_programs_users',
            ['userid' => $subject->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_programs_users', ['id' => $theirs]));
    }

    public function test_delete_data_for_user_is_still_the_full_erasure(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        [$programid, $levelid] = $this->seed_program('AML certification');
        $this->enrol($programid, (int) $subject->id, $levelid, 2, self::COMPLETED_AT);
        $theirs = $this->enrol($programid, (int) $other->id, $levelid, 2, self::COMPLETED_AT);

        // Core's privacy API (tool_dataprivacy) calls this one, and it must
        // still erase: anonymise_data_for_user() is a Sentientia-only hook.
        provider::delete_data_for_user($this->approved($subject));

        $this->assertEquals(0, $DB->count_records('local_sentientia_programs_users',
            ['userid' => $subject->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_programs_users', ['id' => $theirs]),
            'Another person\'s certification must not be touched.');
    }
}
