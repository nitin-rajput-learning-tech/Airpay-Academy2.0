<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for brand_manager — Phase T.0.
 *
 * The brand-name preservation tests are the headline coverage here:
 * given a customer override "Airpay" -> the Kannada-script form, every
 * occurrence in translated text must be substituted; and without an
 * override the brand must be preserved verbatim.
 *
 * @package    local_sentientia_translate
 * @covers     \local_sentientia_translate\brand_manager
 */
final class brand_manager_test extends \advanced_testcase {

    /** Kannada-script rendering of "Airpay" used across the brand tests. */
    private const AIRPAY_KN = 'ಏರ್‌ಪೇ';

    public function test_is_supported_lang(): void {
        $this->assertTrue(brand_manager::is_supported_lang('hi'));
        $this->assertTrue(brand_manager::is_supported_lang('mr'));
        $this->assertTrue(brand_manager::is_supported_lang('kn'));
        $this->assertTrue(brand_manager::is_supported_lang('sw'));
        $this->assertFalse(brand_manager::is_supported_lang('fr'));
        $this->assertFalse(brand_manager::is_supported_lang(''));
    }

    public function test_default_protected_terms_present_without_db(): void {
        $this->resetAfterTest();
        $terms = brand_manager::get_protected_terms(1);
        $this->assertContains('Airpay', $terms);
        $this->assertContains('UPI', $terms);
        $this->assertContains('Aadhaar', $terms);
    }

    public function test_get_protected_terms_merges_db_rows(): void {
        $this->resetAfterTest();
        brand_manager::set_override(1, 'PhonePe', 'hi', 'फ़ोनपे');
        $terms = brand_manager::get_protected_terms(1);
        $this->assertContains('Airpay', $terms);     // default
        $this->assertContains('PhonePe', $terms);    // from DB
        // De-duped.
        $this->assertSame(count($terms), count(array_unique($terms)));
    }

    // ── Brand-name preservation: WITH override ─────────────────────────

    public function test_apply_overrides_substitutes_brand_in_target_script(): void {
        $this->resetAfterTest();
        $map = ['Airpay' => self::AIRPAY_KN];
        $text = '[MOCK kn] Welcome to Airpay. Airpay keeps you safe.';
        [$out, $count] = brand_manager::apply_overrides($text, $map);
        $this->assertSame(2, $count);
        $this->assertStringContainsString(self::AIRPAY_KN, $out);
        $this->assertStringNotContainsString('Airpay', $out);
    }

    public function test_apply_for_loads_and_applies_db_override(): void {
        $this->resetAfterTest();
        brand_manager::set_override(1, 'Airpay', 'kn', self::AIRPAY_KN);
        $text = 'Airpay is a payment company. Use Airpay daily.';
        [$out, $count] = brand_manager::apply_for($text, 1, 'kn');
        $this->assertSame(2, $count);
        $this->assertStringContainsString(self::AIRPAY_KN, $out);
        $this->assertStringNotContainsString('Airpay', $out);
    }

    // ── Brand-name preservation: WITHOUT override ──────────────────────

    public function test_apply_for_preserves_brand_when_no_override(): void {
        $this->resetAfterTest();
        // No override configured for kn — brand must stay verbatim.
        $text = 'Airpay is a payment company.';
        [$out, $count] = brand_manager::apply_for($text, 1, 'kn');
        $this->assertSame(0, $count);
        $this->assertStringContainsString('Airpay', $out);
        $this->assertSame($text, $out);
    }

    public function test_override_is_language_scoped(): void {
        $this->resetAfterTest();
        // Override only for kn.
        brand_manager::set_override(1, 'Airpay', 'kn', self::AIRPAY_KN);
        // hi has no override — brand preserved.
        [$outhi, $counthi] = brand_manager::apply_for('Use Airpay now', 1, 'hi');
        $this->assertSame(0, $counthi);
        $this->assertStringContainsString('Airpay', $outhi);
        // kn applies.
        [$outkn, $countkn] = brand_manager::apply_for('Use Airpay now', 1, 'kn');
        $this->assertSame(1, $countkn);
        $this->assertStringContainsString(self::AIRPAY_KN, $outkn);
    }

    public function test_apply_overrides_whole_token_only(): void {
        // "Airpayment" must NOT be substituted (it's a different token).
        $map = ['Airpay' => self::AIRPAY_KN];
        $text = 'Airpayment is not Airpay';
        [$out, $count] = brand_manager::apply_overrides($text, $map);
        $this->assertSame(1, $count);
        $this->assertStringContainsString('Airpayment', $out);
        $this->assertStringContainsString(self::AIRPAY_KN, $out);
    }

    public function test_apply_overrides_longest_first(): void {
        // Multi-word brand wins over its prefix.
        $map = [
            'Airpay'                  => 'X',
            'Airpay Payment Services' => 'Y',
        ];
        $text = 'Airpay Payment Services and Airpay';
        [$out, $count] = brand_manager::apply_overrides($text, $map);
        $this->assertSame(2, $count);
        $this->assertStringContainsString('Y', $out);
        $this->assertStringContainsString('X', $out);
        $this->assertStringNotContainsString('Airpay Payment Services', $out);
    }

    public function test_apply_overrides_empty_map_noop(): void {
        [$out, $count] = brand_manager::apply_overrides('Airpay text', []);
        $this->assertSame(0, $count);
        $this->assertSame('Airpay text', $out);
    }

    public function test_apply_overrides_skips_identity_mapping(): void {
        // Source === target should not be counted as a substitution.
        $map = ['Airpay' => 'Airpay'];
        [$out, $count] = brand_manager::apply_overrides('Use Airpay', $map);
        $this->assertSame(0, $count);
        $this->assertSame('Use Airpay', $out);
    }

    // ── CRUD ───────────────────────────────────────────────────────────

    public function test_set_override_inserts_then_updates(): void {
        $this->resetAfterTest();
        $id1 = brand_manager::set_override(1, 'Airpay', 'kn', self::AIRPAY_KN);
        $this->assertGreaterThan(0, $id1);
        // Same triple updates in place (no duplicate row).
        $id2 = brand_manager::set_override(1, 'Airpay', 'kn', 'ಬೇರೆ');
        $this->assertSame($id1, $id2);

        $map = brand_manager::get_overrides(1, 'kn');
        $this->assertSame('ಬೇರೆ', $map['Airpay']);
    }

    public function test_set_override_rejects_unsupported_lang(): void {
        $this->resetAfterTest();
        $this->expectException(\coding_exception::class);
        brand_manager::set_override(1, 'Airpay', 'fr', 'Airpay');
    }

    public function test_set_override_rejects_empty(): void {
        $this->resetAfterTest();
        $this->expectException(\coding_exception::class);
        brand_manager::set_override(1, '', 'kn', '');
    }

    public function test_delete_override_respects_customer(): void {
        $this->resetAfterTest();
        $id = brand_manager::set_override(1, 'Airpay', 'kn', self::AIRPAY_KN);

        // Wrong customer cannot delete.
        $this->assertFalse(brand_manager::delete_override($id, 999));
        $this->assertNotEmpty(brand_manager::get_overrides(1, 'kn'));

        // Correct customer can.
        $this->assertTrue(brand_manager::delete_override($id, 1));
        $this->assertEmpty(brand_manager::get_overrides(1, 'kn'));
    }

    public function test_list_for_customer(): void {
        $this->resetAfterTest();
        brand_manager::set_override(1, 'Airpay', 'kn', self::AIRPAY_KN);
        brand_manager::set_override(1, 'UPI', 'hi', 'यूपीआई');
        $rows = brand_manager::list_for_customer(1);
        $this->assertCount(2, $rows);
    }
}
