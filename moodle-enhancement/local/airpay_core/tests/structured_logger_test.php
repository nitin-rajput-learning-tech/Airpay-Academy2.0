<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\structured_logger
 */
class structured_logger_test extends \advanced_testcase {

    /**
     * Smoke: emit() doesn't throw on a normal call.
     */
    public function test_info_emit_does_not_throw(): void {
        $this->resetAfterTest(true);
        // Just call and assert no exception.
        structured_logger::info('cart', 'checkout_completed',
            ['orderid' => 42, 'duration_ms' => 245]);
        $this->assertTrue(true);
    }

    public function test_warn_emit_does_not_throw(): void {
        $this->resetAfterTest(true);
        structured_logger::warn('proctoring', 'aws_unreachable',
            ['attempt' => 2, 'http' => 503]);
        $this->assertTrue(true);
    }

    public function test_error_emit_does_not_throw(): void {
        $this->resetAfterTest(true);
        structured_logger::error('cart', 'gateway_callback_failed',
            ['orderid' => 99]);
        $this->assertTrue(true);
    }

    /**
     * Reflection helper: invoke the private static `scrub_extra()`
     * directly so we can assert its behaviour without inspecting
     * stdout/log output (which is environment-dependent).
     */
    private function scrub_extra(array $extra): array {
        $r = new \ReflectionClass(structured_logger::class);
        $method = $r->getMethod('scrub_extra');
        $method->setAccessible(true);
        return $method->invoke(null, $extra);
    }

    public function test_scrub_redacts_password_field(): void {
        $cleaned = $this->scrub_extra([
            'orderid'  => 42,
            'password' => 'hunter2',
        ]);
        $this->assertSame(42, $cleaned['orderid']);
        $this->assertSame('(redacted)', $cleaned['password']);
    }

    public function test_scrub_redacts_email_field(): void {
        $cleaned = $this->scrub_extra([
            'orderid' => 42,
            'email'   => 'user@example.com',
        ]);
        $this->assertSame('(redacted)', $cleaned['email']);
    }

    public function test_scrub_redacts_partial_field_name_match(): void {
        $cleaned = $this->scrub_extra([
            'user_email' => 'a@b.in',
            'api_key'    => 'sk-abc',
            'card_number' => '4111111111111111',
        ]);
        $this->assertSame('(redacted)', $cleaned['user_email']);
        $this->assertSame('(redacted)', $cleaned['api_key']);
        $this->assertSame('(redacted)', $cleaned['card_number']);
    }

    public function test_scrub_preserves_innocuous_fields(): void {
        $cleaned = $this->scrub_extra([
            'count'      => 3,
            'duration_ms' => 245,
            'status'     => 'completed',
        ]);
        $this->assertSame(3, $cleaned['count']);
        $this->assertSame(245, $cleaned['duration_ms']);
        $this->assertSame('completed', $cleaned['status']);
    }

    /**
     * Reflection helper: get the per-request id via private method.
     */
    private function request_id_method(): \ReflectionMethod {
        $r = new \ReflectionClass(structured_logger::class);
        $method = $r->getMethod('request_id');
        $method->setAccessible(true);
        return $method;
    }

    public function test_request_id_is_stable_within_request(): void {
        $this->resetAfterTest(true);
        $m = $this->request_id_method();
        $first  = $m->invoke(null);
        $second = $m->invoke(null);
        $this->assertSame($first, $second,
            'request_id should cache after first call');
        $this->assertNotEmpty($first);
    }
}
