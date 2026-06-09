<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for prompt_builder — Phase H.0.
 *
 * These tests run in the unit-test DB sandbox; they do NOT call Anthropic.
 *
 * @package    local_sentientia_recommendations
 * @covers     \local_sentientia_recommendations\prompt_builder
 */
final class prompt_builder_test extends \advanced_testcase {

    private function sample_profile(): \stdClass {
        return (object)[
            'role'      => 'learner',
            'tenant'    => '1',
            'skills'    => ['AML', 'KYC'],
            'completed' => [10, 11],
        ];
    }

    private function sample_candidates(): array {
        return [
            (object)['id' => 12, 'fullname' => 'Advanced AML', 'shortname' => 'AML2', 'summary' => 'Deep dive.'],
            (object)['id' => 13, 'fullname' => 'PEP Screening', 'shortname' => 'PEP', 'summary' => 'PEP.'],
        ];
    }

    public function test_build_system_prompt_returns_non_empty_string(): void {
        $prompt = prompt_builder::build_system_prompt();
        $this->assertIsString($prompt);
        $this->assertGreaterThan(100, strlen($prompt));
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('recommendations', $prompt);
    }

    public function test_build_system_prompt_mentions_PII_rule(): void {
        $prompt = prompt_builder::build_system_prompt();
        $this->assertStringContainsString('personally identifiable information', $prompt);
    }

    public function test_build_system_prompt_forbids_already_completed(): void {
        $prompt = prompt_builder::build_system_prompt();
        $this->assertStringContainsString('already completed', $prompt);
    }

    public function test_build_user_message_embeds_profile_and_candidates(): void {
        $msg = prompt_builder::build_user_message($this->sample_profile(), $this->sample_candidates(), 3);
        $this->assertStringContainsString('exactly 3', $msg);
        $this->assertStringContainsString('course_id=12', $msg);
        $this->assertStringContainsString('course_id=13', $msg);
        $this->assertStringContainsString('LEARNER PROFILE', $msg);
        $this->assertStringContainsString('CANDIDATE COURSES', $msg);
        // Completed ids appear in the profile section.
        $this->assertStringContainsString('10, 11', $msg);
    }

    public function test_build_user_message_clamps_count_to_max(): void {
        $msg = prompt_builder::build_user_message($this->sample_profile(),
            $this->sample_candidates(), prompt_builder::MAX_RECOMMENDATIONS + 50);
        $this->assertStringContainsString('exactly ' . prompt_builder::MAX_RECOMMENDATIONS, $msg);
    }

    public function test_build_user_message_clamps_count_to_min(): void {
        $msg = prompt_builder::build_user_message($this->sample_profile(),
            $this->sample_candidates(), 0);
        $this->assertStringContainsString('exactly ' . prompt_builder::MIN_RECOMMENDATIONS, $msg);
    }

    public function test_build_user_message_caps_candidate_list(): void {
        $many = [];
        for ($i = 1; $i <= prompt_builder::MAX_CANDIDATE_COURSES + 20; $i++) {
            $many[] = (object)['id' => $i, 'fullname' => "C{$i}", 'shortname' => "S{$i}", 'summary' => ''];
        }
        $msg = prompt_builder::build_user_message($this->sample_profile(), $many, 5);
        // The candidate just beyond the cap must NOT appear.
        $beyond = prompt_builder::MAX_CANDIDATE_COURSES + 1;
        $this->assertStringNotContainsString("course_id={$beyond} ", $msg);
    }

    public function test_validate_request_clean_profile_passes(): void {
        $errors = prompt_builder::validate_request($this->sample_profile(), $this->sample_candidates(), 3);
        $this->assertSame([], $errors);
    }

    public function test_validate_request_empty_candidates_errors(): void {
        $errors = prompt_builder::validate_request($this->sample_profile(), [], 3);
        $this->assertContains('err_candidates_empty', $errors);
    }

    public function test_validate_request_bad_count_errors(): void {
        $errors = prompt_builder::validate_request($this->sample_profile(),
            $this->sample_candidates(), prompt_builder::MAX_RECOMMENDATIONS + 1);
        $this->assertContains('err_invalid_count', $errors);
    }

    public function test_validate_request_detects_aadhaar_in_profile(): void {
        $profile = (object)['role' => 'learner', 'skills' => ['1234 5678 9012']];
        $errors = prompt_builder::validate_request($profile, $this->sample_candidates(), 3);
        $this->assertContains('err_profile_contains_pii', $errors);
    }

    public function test_validate_request_detects_pan_in_profile(): void {
        $profile = (object)['role' => 'ABCDE1234F', 'skills' => []];
        $errors = prompt_builder::validate_request($profile, $this->sample_candidates(), 3);
        $this->assertContains('err_profile_contains_pii', $errors);
    }

    public function test_profile_contains_pii_pattern_false_on_clean(): void {
        $profile = (object)['role' => 'manager', 'skills' => ['Leadership', 'Section 42 of policy 12']];
        $this->assertFalse(prompt_builder::profile_contains_pii_pattern($profile));
    }
}
