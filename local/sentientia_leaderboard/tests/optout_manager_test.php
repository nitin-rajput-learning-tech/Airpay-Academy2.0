<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see optout_manager}.
 *
 * Locks in:
 *   - opt-out is per (user, customer) — different customers don't share
 *   - opt-out is idempotent (calling twice doesn't insert duplicates)
 *   - reversal is a real delete (privacy export must not see stale rows)
 *   - bulk `opted_out_userids()` returns the right shape
 *
 * @package    local_sentientia_leaderboard
 * @category   test
 * @covers     \local_sentientia_leaderboard\optout_manager
 */
final class optout_manager_test extends \advanced_testcase {

    public function test_default_state_is_opted_in(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->assertFalse(optout_manager::is_opted_out((int) $u->id));
    }

    public function test_opt_out_then_opt_in_is_reversible(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        optout_manager::opt_out((int) $u->id);
        $this->assertTrue(optout_manager::is_opted_out((int) $u->id));

        optout_manager::opt_in((int) $u->id);
        $this->assertFalse(optout_manager::is_opted_out((int) $u->id),
            'opt_in must reverse opt_out');
    }

    public function test_opt_out_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        optout_manager::opt_out((int) $u->id);
        $count1 = $DB->count_records('local_sentientia_lb_optouts',
            ['userid' => $u->id]);
        optout_manager::opt_out((int) $u->id);
        $count2 = $DB->count_records('local_sentientia_lb_optouts',
            ['userid' => $u->id]);
        $this->assertSame(1, $count1);
        $this->assertSame(1, $count2,
            'opting out twice must not create a duplicate row');
    }

    public function test_opt_in_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        // No opt-out exists. opt_in should be a no-op.
        optout_manager::opt_in((int) $u->id);
        $count = $DB->count_records('local_sentientia_lb_optouts',
            ['userid' => $u->id]);
        $this->assertSame(0, $count);
    }

    public function test_opt_out_is_per_customer(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        // Opt out in customer 1.
        optout_manager::opt_out((int) $u->id, 1);
        $this->assertTrue(optout_manager::is_opted_out((int) $u->id, 1));

        // Should still be opted-in in customer 99.
        $this->assertFalse(optout_manager::is_opted_out((int) $u->id, 99));
    }

    public function test_opted_out_userids_returns_indexed_map(): void {
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        optout_manager::opt_out((int) $u1->id);
        optout_manager::opt_out((int) $u3->id);

        $map = optout_manager::opted_out_userids();
        $this->assertArrayHasKey((int) $u1->id, $map);
        $this->assertArrayNotHasKey((int) $u2->id, $map);
        $this->assertArrayHasKey((int) $u3->id, $map);
        $this->assertCount(2, $map);
    }

    public function test_set_preference_value(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        optout_manager::set_preference_value((int) $u->id, '1');
        $this->assertTrue(optout_manager::is_opted_out((int) $u->id));

        optout_manager::set_preference_value((int) $u->id, '0');
        $this->assertFalse(optout_manager::is_opted_out((int) $u->id));

        optout_manager::set_preference_value((int) $u->id, true);
        $this->assertTrue(optout_manager::is_opted_out((int) $u->id));

        optout_manager::set_preference_value((int) $u->id, false);
        $this->assertFalse(optout_manager::is_opted_out((int) $u->id));
    }
}
