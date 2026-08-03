<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_sentientia_learningpath\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests — proves export + erasure work against the REAL userid-keyed
 * tables (local_sentientia_learningpath_users + local_sentientia_lp_adaptive_log).
 *
 * Regression guard for the 2026-06 fix where the provider pointed at the non-existent
 * table 'local_airpay_lp_users', silently no-opping every export/erase path.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @covers     \local_sentientia_learningpath\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    /** Seed one path-assignment row + one adaptive-decision row for a user. */
    private function seed(int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid'        => 7,
            'userid'        => $userid,
            'status'        => 1,
            'timecreated'   => $now,
            'timecompleted' => 0,
        ]);
        $DB->insert_record('local_sentientia_lp_adaptive_log', (object) [
            'pathid'          => 7,
            'userid'          => $userid,
            'costcenterid'    => 1,
            'pivot_type'      => 'remediate',
            'trigger_type'    => 'quiz',
            'source_courseid' => 0,
            'target_courseid' => 0,
            'quiz_score'      => 42.5,
            'velocity_score'  => 0,
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);
    }

    public function test_metadata_declares_real_tables(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_sentientia_learningpath'));
        $this->assertGreaterThanOrEqual(2, count($collection->get_collection()));
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        // assertContainsEquals (loose ==) — Moodle returns context ids as strings from some
        // code paths, so a strict assertContains(int, [...]) can spuriously fail.
        $this->assertContainsEquals(\context_system::instance()->id, $contextlist->get_contextids());
    }

    public function test_export_writes_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $systemcontext = \context_system::instance();
        $this->export_context_data_for_user((int) $user->id, $systemcontext, 'local_sentientia_learningpath');
        $this->assertTrue(writer::with_context($systemcontext)->has_any_data());
    }

    public function test_delete_for_user_targets_only_that_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $this->seed((int) $other->id);

        $approved = new approved_contextlist($user, 'local_sentientia_learningpath',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath_users', ['userid' => $user->id]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_lp_adaptive_log', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_users', ['userid' => $other->id]));
        $this->assertEquals(1, $DB->count_records('local_sentientia_lp_adaptive_log', ['userid' => $other->id]));
    }
}
