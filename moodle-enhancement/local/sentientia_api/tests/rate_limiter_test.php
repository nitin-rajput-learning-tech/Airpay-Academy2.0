<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the fixed-window rate limiter.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\rate_limiter
 */
final class rate_limiter_test extends \advanced_testcase {

    public function test_budget_enforced(): void {
        $this->resetAfterTest();
        set_config('rate_limit', 3, 'local_sentientia_api');
        set_config('rate_window', 60, 'local_sentientia_api');

        $u = $this->getDataGenerator()->create_user();

        // 3 hits allowed.
        rate_limiter::check_and_increment($u->id);
        rate_limiter::check_and_increment($u->id);
        rate_limiter::check_and_increment($u->id);

        // 4th should throw.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/rate/i');
        rate_limiter::check_and_increment($u->id);
    }

    public function test_headers_report_remaining(): void {
        $this->resetAfterTest();
        set_config('rate_limit', 10, 'local_sentientia_api');
        set_config('rate_window', 60, 'local_sentientia_api');

        $u = $this->getDataGenerator()->create_user();
        rate_limiter::check_and_increment($u->id);
        rate_limiter::check_and_increment($u->id);

        $h = rate_limiter::headers($u->id);
        $this->assertSame(10, $h['limit']);
        $this->assertSame(8, $h['remaining']);
        $this->assertGreaterThan(time(), $h['reset']);
    }

    public function test_anonymous_denied(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        rate_limiter::check_and_increment(0);
    }

    public function test_prune_removes_old_windows(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('rate_window', 60, 'local_sentientia_api');

        $u = $this->getDataGenerator()->create_user();
        // Insert an old counter row directly.
        $DB->insert_record('local_sentientia_api_rate', (object)[
            'userid' => $u->id, 'windowstart' => time() - 10000, 'hits' => 5, 'timemodified' => time() - 10000,
        ]);
        $deleted = rate_limiter::prune();
        $this->assertGreaterThanOrEqual(1, $deleted);
    }
}
