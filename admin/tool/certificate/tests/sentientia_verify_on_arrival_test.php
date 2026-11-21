<?php
// This file is part of the tool_certificate plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// SENTIENTIA-CORE-MOD: Sentientia-owned test for the verify-on-arrival vendor patch in index.php. Not an upstream
// file: keep it when a tool_certificate release is imported. See
// moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md and ADR-041.

namespace tool_certificate;

use tool_certificate_generator;

/**
 * Tests for the verify-on-arrival vendor patch in admin/tool/certificate/index.php (2026-11-19).
 *
 * Certificate QR codes, the Sentientia certificate gallery's "Verify" button and the LinkedIn link all open
 * index.php?code=XXXX. Upstream showed the code in the field but no result until the visitor pressed Verify,
 * and the form's action URL kept the ?code=, so formslib printed a second input named "code".
 *
 * index.php itself is a page script and is not run here. These tests cover what the patch relies on: the bare
 * action URL gives the form one "code" input; verifying is read-only, also for a visitor who is not logged in;
 * and the patch is still in index.php (a tripwire for the next vendor import).
 *
 * PHPUnit reads no doc-comment metadata from a class that has attributes, so the group is an attribute too
 * (a "@group" line here was ignored, and --group tool_certificate skipped this file; fixed 2026-11-19).
 *
 * @package    tool_certificate
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('tool_certificate')]
#[\PHPUnit\Framework\Attributes\CoversClass(certificate::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(verify_certificate_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(permission::class)]
final class sentientia_verify_on_arrival_test extends \advanced_testcase {

    /**
     * Get the certificate generator.
     *
     * @return tool_certificate_generator
     */
    protected function get_generator(): tool_certificate_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certificate');
    }

    /**
     * Count the inputs named "code" in rendered form HTML.
     *
     * @param string $html
     * @return int
     */
    private function count_code_inputs(string $html): int {
        return preg_match_all('/<input\b[^>]*\bname="code"/', $html);
    }

    /**
     * A form posted to the verification link URL carries two "code" inputs; posted to the bare URL, one.
     */
    public function test_verification_link_form_has_one_code_field(): void {
        global $PAGE;
        $this->resetAfterTest();
        $code = 'ABCDEF123456';

        // The page set-up of index.php, so that rendering a form finds a page context.
        $PAGE->set_url(template::verification_url($code));
        $PAGE->set_context(\context_system::instance());

        // Before the patch: the action URL is the verification link itself, so formslib adds ?code= as a hidden input.
        $linkform = new verify_certificate_form(template::verification_url($code));
        $this->assertSame(2, $this->count_code_inputs($linkform->render()));

        // After the patch: the bare URL, with the code from the link set as the field's value.
        $bareform = new verify_certificate_form(new \moodle_url('/admin/tool/certificate/index.php'));
        $bareform->set_data(['code' => $code]);
        $html = $bareform->render();
        $this->assertSame(1, $this->count_code_inputs($html));
        $this->assertStringContainsString('value="' . $code . '"', $html);

        // Arriving by link is not a submission: index.php takes the verify-on-arrival branch.
        $this->assertFalse($bareform->is_submitted());
    }

    /**
     * Verifying a code, as the arrival branch does on GET, changes no certificate data, also when not logged in.
     */
    public function test_verify_on_arrival_is_read_only(): void {
        global $DB;
        $this->resetAfterTest();

        $this->setAdminUser();
        $template = $this->get_generator()->create_template((object)['name' => 'Certificate 1']);
        $user = $this->getDataGenerator()->create_user();
        $issueid = $template->issue_certificate($user->id);
        $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $issueid]);

        $issuesbefore = $DB->get_records('tool_certificate_issues');
        $templatesbefore = $DB->get_records('tool_certificate_templates');

        // Someone who scans a QR code is usually not logged in. index.php checks this before verifying.
        $this->setUser(null);
        $this->assertTrue(permission::can_verify());

        $sink = $this->redirectEvents();
        $valid = certificate::verify($code);
        $invalid = certificate::verify('NOTAVALIDCODE');
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($valid->success);
        $this->assertEquals($issueid, $valid->issue->id);
        $this->assertFalse($invalid->success);

        // The one side effect is the audit event for the valid code, which the plugin declares a read.
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(event\certificate_verified::class, $event);
        $this->assertSame('r', $event->crud);
        $this->assertEquals(0, $event->userid);

        // No certificate data changed.
        $this->assertEquals($issuesbefore, $DB->get_records('tool_certificate_issues'));
        $this->assertEquals($templatesbefore, $DB->get_records('tool_certificate_templates'));
    }

    /**
     * Tripwire: the two tagged hunks are still in index.php.
     *
     * A tool_certificate release that replaces index.php drops them silently. This test then fails, so whoever
     * imports the release re-applies the patch from the core-mod record, or drops it if upstream fixed the bug.
     */
    public function test_index_php_carries_the_patch(): void {
        $source = file_get_contents(dirname(__DIR__) . '/index.php');
        $this->assertNotFalse($source);

        $this->assertSame(2, substr_count($source, '// SENTIENTIA-CORE-MOD:'));
        // Any layout of the call: 4.5.7 had the arguments on one line, 5.0.12 puts one argument per line.
        $this->assertMatchesRegularExpression(
            '~verify_certificate_form\(\s*new moodle_url\(\'/admin/tool/certificate/index\.php\'\),~', $source);
        $this->assertStringContainsString('$form->get_data() || ($code && !$form->is_submitted())', $source);
    }
}
