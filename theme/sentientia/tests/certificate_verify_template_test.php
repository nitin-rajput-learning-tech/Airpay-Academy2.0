<?php
// This file is part of Moodle - http://moodle.org/
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

namespace theme_sentientia;

/**
 * Tests for the certificate verification page (QW-CERT-VERIFY, 2026-11-19).
 *
 * templates/tool_certificate/verify_certificate_results.mustache overrides tool_certificate's result
 * template: a code that matches no issued certificate says what that means and what to do next, and a
 * matching code still renders tool_certificate's own result partial. scss/moodle/partials/_legacy-admin.scss
 * hides Moodle's "(!) Required" legend on the verification page only, where it printed under the Verify
 * button and read as a red "Required" error.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_certificate\output\verify_certificate_results
 */
final class certificate_verify_template_test extends \advanced_testcase {

    /**
     * The tests need tool_certificate and the theme's override of its result template.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\tool_certificate\certificate::class)) {
            $this->markTestSkipped('tool_certificate is not installed');
        }
        if (!is_file(dirname(__DIR__) . '/templates/tool_certificate/verify_certificate_results.mustache')) {
            $this->markTestSkipped('theme_sentientia templates are not available');
        }
    }

    /**
     * Render a certificate::verify() result through the theme, as admin/tool/certificate/index.php does.
     *
     * @param \stdClass $result
     * @return string
     */
    private function render_result(\stdClass $result): string {
        global $PAGE;
        // Issuing a certificate e-mails the user, and email_to_user() sets up the theme on $PAGE,
        // after which force_theme() throws. Render on a fresh page, as a new request would.
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->force_theme('sentientia');
        $renderer = $PAGE->get_renderer('core');
        return $renderer->render(new \tool_certificate\output\verify_certificate_results($result));
    }

    /**
     * A code that matches no issued certificate: "Not verified", the explanation, announced as an alert.
     */
    public function test_unknown_code_explains_the_result(): void {
        $this->resetAfterTest();

        // The code from the UAT review screenshot; no certificate was issued with it.
        $result = \tool_certificate\certificate::verify('6342739542AU');
        $this->assertFalse($result->success);

        $html = $this->render_result($result);
        $this->assertStringContainsString(s(get_string('notverified', 'tool_certificate')), $html);
        $this->assertStringContainsString(s(get_string('certverify_notfound', 'theme_sentientia')), $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringNotContainsString(s(get_string('validcertificate', 'tool_certificate')), $html);
    }

    /**
     * An issued code still renders tool_certificate's own result: valid, with no not-found text.
     */
    public function test_issued_code_renders_the_tool_certificate_result(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_certificate');
        $template = $generator->create_template((object) ['name' => 'Certificate 1']);
        $user = $this->getDataGenerator()->create_user();
        $issueid = $template->issue_certificate($user->id);
        $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $issueid], MUST_EXIST);

        $result = \tool_certificate\certificate::verify($code);
        $this->assertTrue($result->success);

        $html = $this->render_result($result);
        $this->assertStringContainsString(s(get_string('validcertificate', 'tool_certificate')), $html);
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringNotContainsString(s(get_string('certverify_notfound', 'theme_sentientia')), $html);
    }

    /**
     * The required-fields legend is hidden on the verification page only; other forms keep it.
     */
    public function test_required_legend_hidden_on_the_verification_page_only(): void {
        $scss = (string) file_get_contents(dirname(__DIR__) . '/scss/moodle/partials/_legacy-admin.scss');
        $this->assertStringContainsString('#page-admin-tool-certificate-index .mform .fdescription.required {', $scss);
        $this->assertDoesNotMatchRegularExpression('/^\s*\.mform\s+\.fdescription\.required\s*\{/m', $scss);
        $this->assertDoesNotMatchRegularExpression('/^\s*\.fdescription\.required\s*\{/m', $scss);
    }
}
