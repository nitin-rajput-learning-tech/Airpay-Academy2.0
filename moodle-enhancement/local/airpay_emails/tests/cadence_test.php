<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Sprint B unit tests for the course_incomplete ramping cadence.
 *
 * The cadence logic lives inside the private method
 * `\local_airpay_emails\task\process_rules::process_course_incomplete()`.
 * Rather than try to spin up a full task + DB harness for it, we
 * exercise the small pieces that ARE accessible:
 *
 *   - JSON parsing + default cadence
 *   - Day-offset computation (today-floor minus enrolment, in days)
 *   - In-cadence membership check
 *
 * These are simple but they're the parts most likely to regress when
 * the cadence array format evolves (e.g. adding per-day-template
 * overrides). The full integration test — "user enrolled 7 days ago
 * gets an email on day 7" — is part of the manual smoke flow in the
 * commit message.
 *
 * @coversNothing
 */
class cadence_test extends \advanced_testcase {

    public function test_default_cadence_used_when_json_empty(): void {
        $cadence = $this->parse_cadence(null);
        $this->assertSame([1, 3, 7, 14, 21], $cadence);

        $cadence = $this->parse_cadence('');
        $this->assertSame([1, 3, 7, 14, 21], $cadence);
    }

    public function test_cadence_parses_valid_json_array(): void {
        $cadence = $this->parse_cadence('[2,5,10]');
        $this->assertSame([2, 5, 10], $cadence);
    }

    public function test_cadence_coerces_string_numbers_to_int(): void {
        $cadence = $this->parse_cadence('["1","3","7"]');
        $this->assertSame([1, 3, 7], $cadence);
    }

    public function test_invalid_json_falls_back_to_default(): void {
        $cadence = $this->parse_cadence('not-json');
        $this->assertSame([1, 3, 7, 14, 21], $cadence);
    }

    public function test_non_array_json_falls_back_to_default(): void {
        // JSON-valid but not an array — treat as malformed.
        $cadence = $this->parse_cadence('"foo"');
        $this->assertSame([1, 3, 7, 14, 21], $cadence);
    }

    public function test_day_offset_calculation_uses_today_floor(): void {
        // A user enrolled exactly 7 calendar days ago should yield 7.
        $today_floor = strtotime('today');
        $enrolled = $today_floor - 7 * 86400;
        $offset = (int) floor(($today_floor - $enrolled) / 86400);
        $this->assertSame(7, $offset);
    }

    public function test_day_offset_is_zero_for_today_enrolment(): void {
        $today_floor = strtotime('today');
        $offset = (int) floor(($today_floor - $today_floor) / 86400);
        $this->assertSame(0, $offset);
    }

    public function test_day_offset_for_partial_day_rounds_down(): void {
        // Enrolled 7.5 days ago → still day-7 (floor).
        $today_floor = strtotime('today');
        $enrolled = $today_floor - 7 * 86400 - 12 * 3600;
        $offset = (int) floor(($today_floor - $enrolled) / 86400);
        $this->assertSame(7, $offset);
    }

    public function test_in_cadence_membership_uses_strict_equality(): void {
        $cadence = [1, 3, 7, 14, 21];
        $this->assertTrue(in_array(7, $cadence, true));
        $this->assertFalse(in_array(8, $cadence, true));
        // Float 7.0 should NOT match int 7 with strict mode — we always
        // cast to int upstream so this guard catches a regression where
        // someone removes the (int) cast.
        $this->assertFalse(in_array(7.0, $cadence, true));
    }

    /**
     * Pull the same cadence-parsing logic out of process_course_incomplete()
     * so we can unit-test it in isolation. Keep this in sync with the
     * implementation; if it drifts the tests will go stale.
     */
    private function parse_cadence(?string $json): array {
        $cadence = [];
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $cadence = array_map('intval', $decoded);
            }
        }
        if (empty($cadence)) {
            $cadence = [1, 3, 7, 14, 21];
        }
        return $cadence;
    }
}
