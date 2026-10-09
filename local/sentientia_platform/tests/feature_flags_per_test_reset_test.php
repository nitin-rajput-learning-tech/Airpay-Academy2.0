<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * A flag one test switches ON is back to the database's answer in the next
 * test, with no feature_flags::invalidate_caches() anywhere in this class.
 *
 * Written 2026-10-09. feature_flags kept its override snapshot in private
 * statics, which phpunit_util::reset_all_data() does not reach: the table
 * was rolled back after a test but the snapshot was not, so the next test
 * read the old flag state for as long as the snapshot was inside
 * SNAPSHOT_TTL. Eight or more suites worked around it in setUp(). The state
 * now lives in the core DI container, which reset_all_data() resets after
 * every test.
 *
 * The two tests must run in this order, in one process. The first leaves a
 * snapshot that holds the flag ON; the second asserts it is gone. Run alone,
 * the second proves nothing, so it is skipped (by #[Depends] on PHPUnit 10+,
 * by the static marker on older PHPUnit, which ignores attributes).
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(feature_flags::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(local\feature_flags_state::class)]
final class feature_flags_per_test_reset_test extends \advanced_testcase {

    /** Registered with default OFF in db/feature_flags.php. */
    private const KEY = 'ai.sentientia.enabled';

    /** Both tests freeze the clock here; see the second test for why. */
    private const T0 = 1900000000;

    /** @var bool Set by the first test once its snapshot holds the flag ON. */
    private static bool $snapshotleftwithflagon = false;

    public function test_a_test_switches_a_flag_on(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::T0);

        $this->assertFalse(feature_flags::is_enabled_for(self::KEY, 0, 0), 'registered default');
        feature_flags::set(self::KEY, 0, true, get_admin()->id, 'per-test reset');
        // set() dropped the snapshot; this lookup reloads it at T0, flag ON.
        $this->assertTrue(feature_flags::is_enabled_for(self::KEY, 0, 0));

        self::$snapshotleftwithflagon = true;
    }

    #[\PHPUnit\Framework\Attributes\Depends('test_a_test_switches_a_flag_on')]
    public function test_the_next_test_reads_the_flag_from_the_database_again(): void {
        global $DB;
        $this->resetAfterTest();
        if (!self::$snapshotleftwithflagon) {
            $this->markTestSkipped('Proves something only after test_a_test_switches_a_flag_on, in the same process.');
        }

        // resetAfterTest() rolled the override row back...
        $this->assertFalse($DB->record_exists('local_sentientia_feature_flags', ['flag_key' => self::KEY]));

        // ...and the clock is frozen at the instant the previous test loaded
        // its snapshot, so that snapshot would be 0 s old: inside SNAPSHOT_TTL,
        // and not from the future (a negative age forces a reload by itself,
        // which would let this pass for the wrong reason). Only the per-test
        // reset can make this lookup read the table.
        $this->mock_clock_with_frozen(self::T0);
        $this->assertFalse(feature_flags::is_enabled_for(self::KEY, 0, 0),
            'the snapshot the previous test left behind must not survive the per-test reset');
    }
}
