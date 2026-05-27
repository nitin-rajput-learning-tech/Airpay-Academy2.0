<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\customer::get_customer_config
 * @covers \local_airpay_core\customer::set_customer_config
 *
 * Phase G.1 (2026-05-25) — regression suite for the per-customer
 * scoped configuration registry introduced for AI quiz prompt
 * templates (local_sentientia_aiquiz). The helper abstracts where the
 * value physically lives (config_plugins today, a real customer-config
 * table tomorrow) behind a stable get/set signature.
 *
 * Each test uses resetAfterTest() so config writes never leak.
 */
class customer_config_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_get_returns_default_when_unset(): void {
        $this->assertNull(
            customer::get_customer_config('aiquiz_prompt_template', 1));
        $this->assertSame('FALLBACK',
            customer::get_customer_config('aiquiz_prompt_template', 1, 'FALLBACK'));
    }

    public function test_set_then_get_round_trips(): void {
        customer::set_customer_config('aiquiz_prompt_template', 1, 'AIRPAY PROMPT');
        $this->assertSame('AIRPAY PROMPT',
            customer::get_customer_config('aiquiz_prompt_template', 1));
    }

    public function test_get_reads_canonical_config_key(): void {
        // The canonical storage key is
        // local_airpay_core/customer_<id>_<key>. A value written there
        // directly (as settings.php does) must be visible to the getter.
        set_config('customer_1_aiquiz_prompt_template', 'DIRECT WRITE', 'local_airpay_core');
        $this->assertSame('DIRECT WRITE',
            customer::get_customer_config('aiquiz_prompt_template', 1));
    }

    public function test_get_returns_default_on_blank_value(): void {
        set_config('customer_1_aiquiz_prompt_template', '   ', 'local_airpay_core');
        // Whitespace-only stored value is treated as "no override".
        $this->assertSame('DEF',
            customer::get_customer_config('aiquiz_prompt_template', 1, 'DEF'));
    }

    public function test_config_is_scoped_per_customer(): void {
        customer::set_customer_config('aiquiz_prompt_template', 1, 'CUSTOMER ONE');
        // Customer 2 must not see customer 1's value.
        $this->assertNull(
            customer::get_customer_config('aiquiz_prompt_template', 2));
        // Customer 1 still resolves.
        $this->assertSame('CUSTOMER ONE',
            customer::get_customer_config('aiquiz_prompt_template', 1));
    }

    public function test_config_is_scoped_per_key(): void {
        customer::set_customer_config('aiquiz_prompt_template', 1, 'PROMPT');
        customer::set_customer_config('some_other_key', 1, 'OTHER');
        $this->assertSame('PROMPT',
            customer::get_customer_config('aiquiz_prompt_template', 1));
        $this->assertSame('OTHER',
            customer::get_customer_config('some_other_key', 1));
    }

    public function test_set_null_reverts_to_default(): void {
        customer::set_customer_config('aiquiz_prompt_template', 1, 'TO REMOVE');
        $this->assertSame('TO REMOVE',
            customer::get_customer_config('aiquiz_prompt_template', 1));
        customer::set_customer_config('aiquiz_prompt_template', 1, null);
        $this->assertNull(
            customer::get_customer_config('aiquiz_prompt_template', 1));
    }

    public function test_get_with_zero_or_negative_customer_returns_default(): void {
        // Defensive: customer id <= 0 short-circuits to the default.
        $this->assertSame('D', customer::get_customer_config('k', 0, 'D'));
        $this->assertSame('D', customer::get_customer_config('k', -5, 'D'));
    }

    public function test_get_with_empty_key_returns_default(): void {
        $this->assertSame('D', customer::get_customer_config('', 1, 'D'));
    }

    public function test_set_with_invalid_customer_is_noop(): void {
        // Must not throw, must not write.
        customer::set_customer_config('aiquiz_prompt_template', 0, 'X');
        $this->assertNull(customer::get_customer_config('aiquiz_prompt_template', 0));
    }

    public function test_get_preserves_devanagari_value(): void {
        // The registry must round-trip a Devanagari template verbatim —
        // it stores prompt bodies that may be authored in Hindi.
        $hindi = 'आप एक विशेषज्ञ हिन्दी क्विज़-लेखक हैं। केवल JSON लौटाएँ।';
        customer::set_customer_config('aiquiz_prompt_template', 1, $hindi);
        $this->assertSame($hindi,
            customer::get_customer_config('aiquiz_prompt_template', 1));
    }
}
