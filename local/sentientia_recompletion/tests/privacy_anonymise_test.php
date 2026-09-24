<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use local_sentientia_recompletion\privacy\provider;

/**
 * The DPDP erasure keeps the reset audit log keyed to the person; core's
 * erasure still unlinks it.
 *
 * Regression guard for 2026-09-24. local_sentientia_privacy's
 * privacy_manager::process_deletion() calls each Sentientia provider's
 * anonymise_data_for_user() when it has one and delete_data_for_user()
 * otherwise. This provider had no anonymise hook, so a right-to-erasure
 * request redacted every local_sentientia_recompletion_history row to
 * userid 0. A reset deletes the course completion, so that row is the only
 * evidence of every earlier compliance cycle the person completed - and the
 * DPDP flow promises to keep such records against the anonymised user row.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @covers     \local_sentientia_recompletion\privacy\provider
 */
final class privacy_anonymise_test extends \advanced_testcase {

    /** When the subject last completed the course before the reset. */
    private const PREVIOUS_COMPLETION = 1735689600;

    /** @var \stdClass The person being erased (an admin who also resets others). */
    private $subject;
    /** @var \stdClass Someone else. */
    private $other;
    /** @var int The subject's own reset: the kept compliance record. */
    private $own;
    /** @var int A reset the subject performed on the other person's completion. */
    private $performed;
    /** @var int A scheduled-task reset of the other person (reset_by_userid NULL). */
    private $cron;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->subject = $gen->create_user();
        $this->other = $gen->create_user();
        $courseid = (int) $gen->create_course(['enablecompletion' => 1])->id;
        $now = time();

        $ruleid = (int) $DB->insert_record('local_sentientia_recompletion_rules', (object) [
            'name'         => 'Annual AML',
            'courseid'     => $courseid,
            'period_days'  => 365,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $this->own = $this->history($ruleid, (int) $this->subject->id, $courseid, 'cron', null);
        $this->performed = $this->history($ruleid, (int) $this->other->id, $courseid, 'manual',
            (int) $this->subject->id);
        $this->cron = $this->history($ruleid, (int) $this->other->id, $courseid, 'cron', null);
    }

    /**
     * Insert one reset-history row. Every NOT NULL column without a default
     * (ruleid, userid, courseid) is named.
     */
    private function history(int $ruleid, int $userid, int $courseid, string $reason,
            ?int $resetby): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_recompletion_history', (object) [
            'ruleid'                 => $ruleid,
            'userid'                 => $userid,
            'courseid'               => $courseid,
            'reason'                 => $reason,
            'reset_by_userid'        => $resetby,
            'previous_timecompleted' => self::PREVIOUS_COMPLETION,
            'reset_grades'           => 1,
            'reset_attempts'         => 1,
            'dryrun'                 => 0,
            'timecreated'            => time(),
        ]);
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_recompletion',
            [\context_system::instance()->id]);
    }

    public function test_the_dpdp_flow_takes_the_anonymise_path(): void {
        // privacy_manager duck-types on this method. If it is removed, the
        // DPDP flow silently falls back to delete_data_for_user().
        $this->assertTrue(method_exists(provider::class, 'anonymise_data_for_user'));
    }

    public function test_anonymise_keeps_the_subjects_reset_history(): void {
        global $DB;

        provider::anonymise_data_for_user($this->approved($this->subject));

        // The subject's compliance record survives, still keyed to their
        // (DPDP-anonymised) user row, with the earlier completion intact.
        $kept = $DB->get_record('local_sentientia_recompletion_history', ['id' => $this->own],
            '*', MUST_EXIST);
        $this->assertEquals($this->subject->id, $kept->userid);
        $this->assertEquals(self::PREVIOUS_COMPLETION, $kept->previous_timecompleted);
        $this->assertSame('cron', $kept->reason);

        // Where the subject was only the actor, the row is the other person's
        // record: kept, with the actor anonymised to 0.
        $theirs = $DB->get_record('local_sentientia_recompletion_history',
            ['id' => $this->performed], '*', MUST_EXIST);
        $this->assertEquals($this->other->id, $theirs->userid);
        $this->assertEquals(0, $theirs->reset_by_userid);

        // A scheduled-task row keeps NULL: "nobody", not "an erased admin".
        $this->assertNull($DB->get_field('local_sentientia_recompletion_history',
            'reset_by_userid', ['id' => $this->cron]));
        $this->assertEquals(3, $DB->count_records('local_sentientia_recompletion_history'));
    }

    public function test_delete_data_for_user_still_unlinks_the_subject(): void {
        global $DB;

        // Core's privacy API (tool_dataprivacy) calls this one. It has always
        // redacted rather than deleted this audit log, and still does.
        provider::delete_data_for_user($this->approved($this->subject));

        $this->assertEquals(0, $DB->count_records('local_sentientia_recompletion_history',
            ['userid' => $this->subject->id]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_recompletion_history',
            ['reset_by_userid' => $this->subject->id]));

        $redacted = $DB->get_record('local_sentientia_recompletion_history', ['id' => $this->own],
            '*', MUST_EXIST);
        $this->assertEquals(0, $redacted->userid);
        $this->assertEquals(self::PREVIOUS_COMPLETION, $redacted->previous_timecompleted,
            'The audit row itself survives, attributable to nobody.');

        $theirs = $DB->get_record('local_sentientia_recompletion_history',
            ['id' => $this->performed], '*', MUST_EXIST);
        $this->assertEquals($this->other->id, $theirs->userid,
            'Another person\'s reset record must not be redacted.');
        $this->assertEquals(0, $theirs->reset_by_userid);
        $this->assertNull($DB->get_field('local_sentientia_recompletion_history',
            'reset_by_userid', ['id' => $this->cron]));
    }

    public function test_an_admin_who_only_reset_others_is_reachable(): void {
        global $DB;
        $admin = $this->getDataGenerator()->create_user();
        $DB->set_field('local_sentientia_recompletion_history', 'reset_by_userid', $admin->id,
            ['id' => $this->cron]);
        // A row an earlier erasure already redacted.
        $DB->set_field('local_sentientia_recompletion_history', 'userid', 0,
            ['id' => $this->own]);

        $contexts = provider::get_contexts_for_userid((int) $admin->id)->get_contextids();
        $this->assertContainsEquals(\context_system::instance()->id, $contexts,
            'An erasure must reach the actor column, or it is never anonymised.');

        $userlist = new userlist(\context_system::instance(), 'local_sentientia_recompletion');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertContainsEquals($admin->id, $userids);
        $this->assertContainsEquals($this->subject->id, $userids, 'actor of the manual reset');
        $this->assertContainsEquals($this->other->id, $userids);
        $this->assertNotContainsEquals(0, $userids, 'userid 0 is a redacted row, not a user');
    }
}
