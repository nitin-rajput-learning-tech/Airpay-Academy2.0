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
            // COMMS-N1 (2026-10-07): shapes the first scrub let through, found by re-running it on synthetic text.
            'label and value in table cells' => ['<td>Password</td><td>Xy9!kLm2</td>', 'Xy9!kLm2'],
            'label and value in table rows' => ["<tr><td>Password</td>\n<td>Xy9!kLm2</td></tr>", 'Xy9!kLm2'],
            'line break between label and value' => ['Password<br>Xy9!kLm2', 'Xy9!kLm2'],
            'newline between label and value' => ["Password\nXy9!kLm2", 'Xy9!kLm2'],
            'windows newline between label and value' => ["Password\r\nXy9!kLm2", 'Xy9!kLm2'],
            'bold label then bold value' => ['<b>Password</b>&nbsp;<b>Xy9!kLm2</b>', 'Xy9!kLm2'],
            'semicolon in the value' => ['Password: Ab;xYz9', 'xYz9'],
            'semicolon in the value, head' => ['Password: Ab;xYz9', 'Ab;'],
            'ampersand in the value' => ['Password: Abc&1234', '1234'],
            'comma in the value' => ['Password: Abc,1234 then', '1234'],
            'full welcome body' => ['Username: jdoe<br>Password: Xy9!kLm2<br>Log in at the link', 'Xy9!kLm2'],
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

    /**
     * importer::verify() runs scrub() over what was imported, so scrub() must be idempotent: scrubbing a scrubbed text
     * changes nothing, or a clean import would fail its own verify.
     *
     * @dataProvider secrets_provider
     * @param string $text
     * @param string $secret
     */
    public function test_the_scrub_is_idempotent(string $text, string $secret): void {
        $once = redactor::scrub($text);
        $this->assertNotNull($once);
        $this->assertSame($once, redactor::scrub($once), 'scrubbing the output again changes nothing');
    }

    public function test_a_link_keeps_its_other_parameters_when_the_value_may_contain_an_ampersand(): void {
        // The value of a secret may contain '&', but an '&' that opens the next parameter ends it.
        $this->assertSame('https://x.example/login?token=' . redactor::MASK . '&user=5&lang=en',
            redactor::scrub('https://x.example/login?token=abcdef123456&user=5&lang=en'));
    }

    public function test_a_very_long_secret_is_blanked_whole_and_never_leaves_a_tail(): void {
        $value = str_repeat('A', 100000);
        $this->assertSame('password: ' . redactor::MASK, redactor::scrub('password: ' . $value));
        $this->assertSame('password: ' . redactor::MASK, redactor::scrub(redactor::scrub('password: ' . $value)));
    }

    public function test_a_long_run_of_tags_or_newlines_between_label_and_value_does_not_make_the_scrub_fail(): void {
        foreach ([str_repeat('<br>', 100000), str_repeat("\n", 100000), str_repeat(' ', 100000) . '<br>'] as $gap) {
            $this->assertNotNull(redactor::scrub('password' . $gap . 'x'));
        }
    }

    public function test_ordinary_text_is_left_alone(): void {
        foreach ([
            'Dear learner, your course is due on 5 Oct.',
            'password island is nice',
            '<p>Hello, you have been enrolled.</p>',
            'Enrolled in Safety 101',
            // A secret word with nothing after it that could be a value, or only text on the same line.
            'Your password has been reset.',
            'Reset your password and log in again.',
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
        // COMMS-N1: a real welcome subject names the account, not a secret.
        $this->assertTrue(redactor::subject_suggests_credentials('Your Airpay Academy account'));
        $this->assertTrue(redactor::subject_suggests_credentials('Your login details'));
        $this->assertFalse(redactor::subject_suggests_credentials('Course reminder'));
        $this->assertFalse(redactor::subject_suggests_credentials('Enrolled in Safety 101'));
        $this->assertFalse(redactor::subject_suggests_credentials('Congratulations on completing Anti Money Laundering'));
        $this->assertFalse(redactor::subject_suggests_credentials(''));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function mentions_provider(): array {
        return [
            'password' => ['Your password', true],
            'passwd' => ['Temp passwd issued', true],
            'pwd' => ['pwd reset', true],
            'passcode' => ['Your passcode', true],
            'otp' => ['OTP for login', true],
            'pin' => ['Your PIN', true],
            'secret' => ['A secret', true],
            'token' => ['Access token', true],
            'credentials' => ['Your credentials', true],
            'credential' => ['credential', true],
            'bare pass is not a mention' => ['Exam pass certificate', false],
            'inside a word: pin' => ['Spinning up', false],
            'inside a word: otp' => ['Optional course', false],
            'ordinary subject' => ['Enrolled in Safety 101', false],
            'empty' => ['', false],
        ];
    }

    /**
     * @dataProvider mentions_provider
     * @param string $text
     * @param bool $expected
     */
    public function test_a_text_that_names_a_secret_word_is_recognised_without_a_separator(string $text, bool $expected): void {
        $this->assertSame($expected, redactor::text_mentions_secret($text));
        $this->assertFalse(redactor::text_mentions_secret(null));
    }

    public function test_the_mask_and_the_subject_placeholder_are_not_themselves_a_secret(): void {
        // verify() looks for the placeholder and the subject mask in the target; neither may trip the scrub.
        $this->assertSame(redactor::SUBJECT_MASK, redactor::scrub(redactor::SUBJECT_MASK));
        $this->assertSame(redactor::MASK, redactor::scrub(redactor::MASK));
        // The dev masking script (local_sentientia_platform/cli/mask_pii_for_dev.php) keeps this exact subject: it names no one.
        $this->assertSame('[withheld: account credentials]', redactor::SUBJECT_MASK);
    }
}
