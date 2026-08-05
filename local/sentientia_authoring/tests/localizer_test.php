<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * localizer tests — translate routing + graceful degradation.
 *
 * The translate plugin is an OPTIONAL dependency: these tests pin the
 * degradation contract so the studio never hard-fails when translate is absent
 * or its flag is off.
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\localizer
 */
final class localizer_test extends \advanced_testcase {

    public function test_native_languages_need_no_translation(): void {
        $this->assertFalse(localizer::needs_translation('en'));
        $this->assertFalse(localizer::needs_translation('hi'));
        $this->assertFalse(localizer::needs_translation('hi_IN'));
    }

    public function test_localize_text_is_noop_for_native_language(): void {
        $src = 'Compliance is mandatory.';
        $this->assertSame($src, localizer::localize_text($src, 'en'));
        $this->assertSame($src, localizer::localize_text($src, 'hi'));
    }

    public function test_localize_text_degrades_to_source_when_translate_absent(): void {
        // In the PHPUnit env the translate engine may be unavailable; either
        // way, a non-native language must NEVER hard-fail — it returns source.
        $src = 'Compliance is mandatory.';
        $out = localizer::localize_text($src, 'fr');
        $this->assertSame($src, $out);
    }

    public function test_empty_text_returns_empty(): void {
        $this->assertSame('', localizer::localize_text('', 'fr'));
    }

    public function test_strategy_key_for_native_language(): void {
        $this->assertSame('localize_native', localizer::strategy_key('en'));
        $this->assertSame('localize_native', localizer::strategy_key('hi'));
    }

    public function test_strategy_key_for_non_native_reflects_availability(): void {
        // When translate is unavailable, a non-native language degrades.
        $key = localizer::strategy_key('fr');
        $this->assertContains($key, ['localize_translate', 'localize_degraded']);
    }

    public function test_normalise_lang_truncates_to_two_letters(): void {
        $this->assertSame('en', localizer::normalise_lang('en-GB'));
        $this->assertSame('hi', localizer::normalise_lang('HI_in'));
    }
}
