<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_emails\bizlms\redactor;

/**
 * Credential redaction of the BizLMS e-mail import (ADR-032, mapping doc section 11).
 *
 * redactor is pure: no database, no Moodle state. These are the facts the importer leans on to keep an
 * account password out of a second table. The end-to-end check (a password row is imported with its subject
 * masked and its body NULL) is in bizlms_import_test.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\bizlms\redactor
 *
 * @group local_sentientia_emails
 * @group bizlms_import
 */
final class bizlms_redactor_test extends \basic_testcase {

    /**
     * @return array<string, array{0: string, 1: string}> Text => what must be gone from it.
     */
    public static function secrets_provider(): array {
        return [
            'plain' => ['Your password: Abc12345 please log in', 'Abc12345'],
            'html tag between label and value' => ['<p>Password:</b> Xy9!kLm2</p>', 'Xy9!kLm2'],
            'was' => ['the password was: s3cretValue', 's3cretValue'],
            'equals sign' => ['Pin = 4455', '4455'],
            'non-breaking spaces' => ['Password&nbsp;:&nbsp;Zebra99x', 'Zebra99x'],
            'otp' => ['OTP is 482913. Do not share.', '482913'],
            'api key' => ['api key: sk_live_abcdef', 'sk_live_abcdef'],
            'link token' => ['https://x.example/login?token=abcdef123456&user=5', 'abcdef123456'],
            'bearer' => ['Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.abc', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9'],
        ];
    }

    /**
     * @dataProvider secrets_provider
     * @param string $text
     * @param string $secret
     */
    public function test_a_secret_value_does_not_survive_the_scrub(string $text, string $secret): void {
        $clean = redactor::scrub($text);
        $this->assertNotNull($clean);
        $this->assertStringNotContainsString($secret, $clean);
        $this->assertStringContainsString(redactor::MASK, $clean);
    }

    public function test_ordinary_text_is_left_alone(): void {
        foreach ([
            'Dear learner, your course is due on 5 Oct.',
            'password island is nice',
            '<p>Hello, you have been enrolled.</p>',
            'Enrolled in Safety 101',
            '',
        ] as $text) {
            $this->assertSame($text, redactor::scrub($text));
        }
    }

    public function test_the_words_around_a_secret_are_kept(): void {
        $this->assertSame('Your password: ' . redactor::MASK . ' please log in',
            redactor::scrub('Your password: Abc12345 please log in'));
        $this->assertSame('https://x.example/login?token=' . redactor::MASK . '&user=5',
            redactor::scrub('https://x.example/login?token=abcdef123456&user=5'));
    }

    public function test_the_scrub_makes_text_safe_for_a_utf8_column(): void {
        $clean = redactor::scrub("Caf\xC3\xA9 \xFF broken\0 byte");
        $this->assertNotNull($clean);
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $clean);
        $this->assertStringContainsString("Caf\xC3\xA9", $clean, 'valid UTF-8 is kept');
    }

    public function test_a_very_long_run_of_blanks_does_not_make_the_scrub_fail(): void {
        // A pathological message must not turn into "withhold the text" (null) by exhausting the PCRE JIT stack.
        $text = 'password' . str_repeat(' ', 100000) . 'x';
        $this->assertNotNull(redactor::scrub($text));
    }

    public function test_the_users_module_is_recognised_by_its_type(): void {
        $this->assertTrue(redactor::is_users_module('users', 'anything', ''));
        $this->assertTrue(redactor::is_users_module('Users', null, null), 'case is ignored');
        $this->assertTrue(redactor::is_users_module('classroom', 'users_welcome_email', ''),
            'the shortname of the welcome type says users');
        $this->assertTrue(redactor::is_users_module(null, null, 'local_users'), 'the module type of the row says users');
        $this->assertFalse(redactor::is_users_module('classroom', 'ilt_reminder', 'classroom'));
        $this->assertFalse(redactor::is_users_module('courses', 'course_enrol', ''));
        $this->assertFalse(redactor::is_users_module(null, null, null));
    }

    public function test_the_password_placeholder_in_a_template_is_found_in_either_part(): void {
        $this->assertTrue(redactor::template_uses_password('Hi', 'Your password is [employee_password]'));
        $this->assertTrue(redactor::template_uses_password('[Employee_Password]', 'x'), 'case is ignored');
        $this->assertFalse(redactor::template_uses_password('Hi', 'Your username is [employee_username]'));
        $this->assertFalse(redactor::template_uses_password(null, null));
    }

    public function test_a_subject_that_reads_like_an_account_message_is_suspect(): void {
        $this->assertTrue(redactor::subject_suggests_credentials('Welcome to Airpay Academy'));
        $this->assertTrue(redactor::subject_suggests_credentials('Your account credentials'));
        $this->assertTrue(redactor::subject_suggests_credentials('Reset your PASSWORD'));
        $this->assertFalse(redactor::subject_suggests_credentials('Course reminder'));
        $this->assertFalse(redactor::subject_suggests_credentials(''));
    }

    public function test_the_mask_and_the_subject_placeholder_are_not_themselves_a_secret(): void {
        // verify() looks for the placeholder and the subject mask in the target; neither may trip the scrub.
        $this->assertSame(redactor::SUBJECT_MASK, redactor::scrub(redactor::SUBJECT_MASK));
        $this->assertSame(redactor::MASK, redactor::scrub(redactor::MASK));
    }
}
