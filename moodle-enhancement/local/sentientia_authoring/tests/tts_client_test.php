<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * tts_client tests — mock-mode only (NEVER a live ElevenLabs call).
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\tts_client
 */
final class tts_client_test extends \advanced_testcase {

    public function test_mock_returns_placeholder_marker(): void {
        $r = tts_client::call_mock('Read this aloud.', 'en');
        $this->assertSame('mock', $r['mode']);
        $this->assertSame('mock', $r['voice_id']);
        $this->assertStringStartsWith('mock://', $r['audio_ref']);
        $this->assertNull($r['error']);
    }

    public function test_mock_counts_unicode_characters(): void {
        $r = tts_client::call_mock('अनुपालन', 'hi');
        // 7 Devanagari characters — mb_strlen, not byte length.
        $this->assertSame(mb_strlen('अनुपालन'), $r['charcount']);
    }

    public function test_estimate_cost_scales_with_length(): void {
        $short = tts_client::estimate_cost('abc');
        $long = tts_client::estimate_cost(str_repeat('a', 2000));
        $this->assertGreaterThan($short, $long);
    }

    public function test_mock_cost_is_zero_via_estimate_on_empty(): void {
        $this->assertSame(0.0, tts_client::estimate_cost(''));
    }
}
