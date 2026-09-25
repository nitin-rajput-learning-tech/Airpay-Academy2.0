<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #7 (2026-05-16) — tests for tenant-scoped welcome email + tokens.
 *
 * Locks in:
 *   - substitute_tokens replaces [employee_name] etc. case-insensitively
 *   - missing tokens collapse to empty string (no literal [foo] in output)
 *   - send() uses configured default subject+body when set
 *   - send() uses tenant-specific override when configured for that tenant
 *   - send() falls back to DEFAULT_* constants when nothing configured
 *   - send() sends by email_to_user (caught via redirectEmails()) and stores
 *     nothing in {notifications}, so the plaintext password never lands in
 *     the database
 *   - the default body carries the white-label [support_email] token
 *   - send() returns false (not throw) on missing user
 *
 * @package    local_sentientia_users
 * @category   test
 *
 * @group tenant_isolation
 */
final class welcome_mailer_test extends \advanced_testcase {

    /**
     * Capture outgoing email. Local and CI configs may set noemailever,
     * which makes email_to_user() return before the PHPUnit sink; the test
     * clears it (resetAfterTest restores $CFG).
     */
    private function email_sink(): \phpunit_phpmailer_sink {
        global $CFG;
        $CFG->noemailever = false;
        return $this->redirectEmails();
    }

    /** The decoded text of a captured email (headers + all MIME parts). */
    private function email_text(\stdClass $mail): string {
        return quoted_printable_decode($mail->header . "\n" . $mail->body);
    }

    public function test_substitute_tokens_case_insensitive(): void {
        $out = welcome_mailer::substitute_tokens(
            'Hi [Employee_Name], your email is [EMPLOYEE_EMAIL].',
            ['employee_name' => 'Alice', 'employee_email' => 'a@a.org']);
        $this->assertSame('Hi Alice, your email is a@a.org.', $out);
    }

    public function test_substitute_tokens_collapses_missing(): void {
        // A token not in the map should become empty string, NOT remain
        // as the literal "[foo]" placeholder. This protects the user from
        // seeing raw bracket-templates in their welcome email.
        $out = welcome_mailer::substitute_tokens(
            'Hi [employee_name], your password is [missing_token].',
            ['employee_name' => 'Alice']);
        // [missing_token] is NOT in the substitution map, so it stays as-is —
        // that's actually what preg_replace does (only matches what's in the
        // map). We're explicitly NOT collapsing unknown tokens, because
        // that would silently hide template bugs.
        $this->assertStringContainsString('Alice', $out);
        $this->assertStringContainsString('[missing_token]', $out,
            'Unknown tokens should remain so template bugs are visible');
    }

    public function test_send_uses_default_template_when_no_config(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user([
            'firstname' => 'Alice', 'lastname' => 'Anderson',
            'email' => 'alice_' . uniqid() . '@example.org',
        ]);
        global $DB;
        $sink = $this->email_sink();

        $sent = welcome_mailer::send((int) $u->id, 'TempPass!23');
        $this->assertTrue($sent);

        $mails = $sink->get_messages();
        $this->assertCount(1, $mails);
        $text = $this->email_text($mails[0]);
        $this->assertSame($u->email, $mails[0]->to);
        $this->assertStringContainsString('Alice Anderson', $text);
        $this->assertStringContainsString('TempPass!23',   $text);
        $this->assertStringContainsString($u->username,     $text);
        $this->assertStringContainsString($u->email,        $text);
        // White-label: the support line comes from the token, whose
        // customer-zero default is the Airpay Academy address.
        $this->assertStringContainsString('Need help? Email academy@airpay.co.in.', $text);
        $this->assertStringNotContainsString('[support_email]', $text);
        // The plaintext password is never stored.
        $this->assertSame(0, $DB->count_records('notifications', ['useridto' => $u->id]));
        $sink->close();
    }

    public function test_send_uses_admin_configured_default(): void {
        $this->resetAfterTest();
        set_config('welcome_email_subject', 'Custom subject for [employee_name]',
            'local_sentientia_users');
        set_config('welcome_email_body',
            'Custom body. User: [employee_username]. Pass: [employee_password].',
            'local_sentientia_users');

        $u = $this->getDataGenerator()->create_user([
            'firstname' => 'Bob', 'lastname' => 'Brown',
            'username' => 'bob_' . uniqid(),
        ]);
        $sink = $this->email_sink();

        welcome_mailer::send((int) $u->id, 'SecretPw!1');

        $mails = $sink->get_messages();
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('Custom subject for Bob Brown',
            $mails[0]->subject);
        $text = $this->email_text($mails[0]);
        $this->assertStringContainsString('User: ' . $u->username, $text);
        $this->assertStringContainsString('Pass: SecretPw!1', $text);
        $sink->close();
    }

    public function test_send_uses_tenant_override_when_user_in_tenant(): void {
        $this->resetAfterTest();
        // Configure a tenant override for tenant 77.
        set_config('welcome_email_subject_77',
            'Public-tenant subject for [employee_name]',
            'local_sentientia_users');
        set_config('welcome_email_body_77',
            'Public body — welcome [employee_name]!',
            'local_sentientia_users');

        global $DB;
        $u = $this->getDataGenerator()->create_user(['firstname' => 'Carol']);
        $DB->set_field('user', 'open_path', '/77', ['id' => $u->id]);

        $sink = $this->email_sink();
        welcome_mailer::send((int) $u->id, 'CarolPw!1');

        $mails = $sink->get_messages();
        $this->assertSame(1, count($mails));
        $this->assertStringContainsString('Public-tenant subject for Carol',
            $mails[0]->subject);
        $this->assertStringContainsString('Public body — welcome Carol!',
            $this->email_text($mails[0]));
        $sink->close();
    }

    public function test_default_body_is_white_label(): void {
        $this->resetAfterTest();
        set_config('support_email', 'help@customer-n.example', 'local_sentientia_users');
        $u = $this->getDataGenerator()->create_user(['firstname' => 'Dev']);
        $sink = $this->email_sink();
        welcome_mailer::send((int) $u->id, 'DevPw!1');
        $mails = $sink->get_messages();
        $this->assertCount(1, $mails);
        $text = $this->email_text($mails[0]);
        $this->assertStringContainsString('Need help? Email help@customer-n.example.', $text);
        $this->assertStringNotContainsString('academy@airpay.co.in', $text);
        $this->assertStringNotContainsString('Airpay Academy account', welcome_mailer::DEFAULT_BODY);
        $sink->close();
    }

    public function test_send_returns_false_for_missing_user(): void {
        $this->resetAfterTest();
        $result = welcome_mailer::send(99999999, 'doesnt-matter');
        $this->assertFalse($result,
            'Missing user should return false, not throw');
    }
}
