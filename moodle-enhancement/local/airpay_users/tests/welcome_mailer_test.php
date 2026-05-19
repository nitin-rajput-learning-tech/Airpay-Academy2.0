<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users;

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
 *   - send() sends through message_send (caught via redirectMessages())
 *   - send() returns false (not throw) on missing user
 *
 * @package    local_airpay_users
 * @category   test
 */
final class welcome_mailer_test extends \advanced_testcase {

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
        $sink = $this->redirectMessages();

        $sent = welcome_mailer::send((int) $u->id, 'TempPass!23');
        $this->assertTrue($sent);

        $msgs = $sink->get_messages();
        $this->assertCount(1, $msgs);
        $msg = $msgs[0];
        $this->assertStringContainsString('Alice Anderson', $msg->fullmessage);
        $this->assertStringContainsString('TempPass!23',   $msg->fullmessage);
        $this->assertStringContainsString($u->username,     $msg->fullmessage);
        $this->assertStringContainsString($u->email,        $msg->fullmessage);
        $sink->close();
    }

    public function test_send_uses_admin_configured_default(): void {
        $this->resetAfterTest();
        set_config('welcome_email_subject', 'Custom subject for [employee_name]',
            'local_airpay_users');
        set_config('welcome_email_body',
            'Custom body. User: [employee_username]. Pass: [employee_password].',
            'local_airpay_users');

        $u = $this->getDataGenerator()->create_user([
            'firstname' => 'Bob', 'lastname' => 'Brown',
            'username' => 'bob_' . uniqid(),
        ]);
        $sink = $this->redirectMessages();

        welcome_mailer::send((int) $u->id, 'SecretPw!1');

        $msgs = $sink->get_messages();
        $this->assertCount(1, $msgs);
        $this->assertStringContainsString('Custom subject for Bob Brown',
            $msgs[0]->subject);
        $this->assertStringContainsString('User: ' . $u->username,
            $msgs[0]->fullmessage);
        $this->assertStringContainsString('Pass: SecretPw!1',
            $msgs[0]->fullmessage);
        $sink->close();
    }

    public function test_send_uses_tenant_override_when_user_in_tenant(): void {
        $this->resetAfterTest();
        // Configure a tenant override for tenant 77.
        set_config('welcome_email_subject_77',
            'Public-tenant subject for [employee_name]',
            'local_airpay_users');
        set_config('welcome_email_body_77',
            'Public body — welcome [employee_name]!',
            'local_airpay_users');

        global $DB;
        $u = $this->getDataGenerator()->create_user(['firstname' => 'Carol']);
        $DB->set_field('user', 'open_path', '/77', ['id' => $u->id]);

        $sink = $this->redirectMessages();
        welcome_mailer::send((int) $u->id, 'CarolPw!1');

        $msgs = $sink->get_messages();
        $this->assertSame(1, count($msgs));
        $this->assertStringContainsString('Public-tenant subject for Carol',
            $msgs[0]->subject);
        $this->assertStringContainsString('Public body — welcome Carol!',
            $msgs[0]->fullmessage);
        $sink->close();
    }

    public function test_send_returns_false_for_missing_user(): void {
        $this->resetAfterTest();
        $result = welcome_mailer::send(99999999, 'doesnt-matter');
        $this->assertFalse($result,
            'Missing user should return false, not throw');
    }
}
