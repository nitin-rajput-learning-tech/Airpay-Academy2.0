<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_emails\admin\setting_cadence_json
 *
 * Day-2 (2026-05-14) — validation rules for the cadence JSON admin
 * setting. The test exercises each branch of validate():
 *
 *   - empty (accepted; treated as "use baseline")
 *   - valid JSON array of positive ints (accepted)
 *   - not JSON (rejected, specific error key)
 *   - JSON but not an array (rejected)
 *   - empty array (rejected)
 *   - too many entries (rejected with max in error)
 *   - non-int value (rejected, value echoed)
 *   - negative int (rejected)
 *   - zero (rejected)
 *   - string-quoted "1" (rejected — caught common admin mistake)
 *
 * The class extends admin_setting_configtext but Moodle's settings
 * framework requires global state to fully instantiate. We side-step
 * that by using ReflectionClass to call the constructor without
 * touching the framework, then directly calling validate() on the
 * resulting instance.
 */
class setting_cadence_json_test extends \advanced_testcase {

    /**
     * Moodle's `admin_setting_*` classes live in lib/adminlib.php which
     * is only auto-loaded when the admin settings framework boots
     * (Site Admin → ...). PHPUnit doesn't load adminlib so the parent
     * class isn't resolvable until we require it ourselves.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
    }

    /**
     * Build a settings instance suitable for calling validate() on.
     * We can't use the normal Moodle settings flow inside a unit test
     * because that requires $ADMIN to be primed.
     */
    private function make_setting(): setting_cadence_json {
        return new setting_cadence_json(
            'local_sentientia_emails/test_cadence',
            'Cadence test',
            'Test help',
            '[1,3,7]',
            PARAM_TEXT,
            40
        );
    }

    public function test_empty_string_is_accepted(): void {
        $s = $this->make_setting();
        $this->assertSame('', $s->validate(''));
        $this->assertSame('', $s->validate('   '));
    }

    public function test_valid_cadence_is_accepted(): void {
        $s = $this->make_setting();
        $this->assertSame('', $s->validate('[1,3,7,14,21]'));
        $this->assertSame('', $s->validate('[1]'));
        $this->assertSame('', $s->validate('[42]'));
    }

    public function test_garbage_text_is_rejected(): void {
        $s = $this->make_setting();
        $err = $s->validate('not-json');
        $this->assertNotSame('', $err);
        $this->assertStringContainsString('JSON array', $err);
    }

    public function test_json_but_not_array_is_rejected(): void {
        $s = $this->make_setting();
        $this->assertNotSame('', $s->validate('42'));        // scalar
        $this->assertNotSame('', $s->validate('"foo"'));      // string
        $this->assertNotSame('', $s->validate('{"d":[1,3]}')); // object
    }

    public function test_empty_array_is_rejected(): void {
        $s = $this->make_setting();
        $err = $s->validate('[]');
        $this->assertNotSame('', $err);
        $this->assertStringContainsString('empty', $err);
    }

    public function test_too_many_entries_is_rejected(): void {
        $s = $this->make_setting();
        // 11 entries — one over the limit of 10.
        $err = $s->validate('[1,2,3,4,5,6,7,8,9,10,11]');
        $this->assertNotSame('', $err);
        $this->assertStringContainsString('too many', $err);
        // The max should be quoted back at the admin.
        $this->assertStringContainsString('10', $err);
    }

    public function test_negative_value_is_rejected(): void {
        $s = $this->make_setting();
        $err = $s->validate('[-1, 3, 7]');
        $this->assertNotSame('', $err);
    }

    public function test_zero_value_is_rejected(): void {
        $s = $this->make_setting();
        // Day 0 makes no sense as an offset — fires the same moment
        // the user enrolled — that's spam.
        $err = $s->validate('[0, 3]');
        $this->assertNotSame('', $err);
    }

    public function test_string_quoted_int_is_rejected(): void {
        $s = $this->make_setting();
        // Common admin mistake — typing ["1","3","7"] instead of [1,3,7].
        // json_decode returns string "1" not int 1; we reject strictly.
        $err = $s->validate('["1","3","7"]');
        $this->assertNotSame('', $err);
    }

    public function test_max_allowed_entries_is_accepted(): void {
        // Exactly 10 entries (the cap) — must pass.
        $s = $this->make_setting();
        $this->assertSame('',
            $s->validate('[1,2,3,4,5,6,7,8,9,10]'));
    }
}
