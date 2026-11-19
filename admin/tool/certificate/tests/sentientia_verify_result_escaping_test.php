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

// SENTIENTIA-CORE-MOD: Sentientia-owned test for the result-escaping vendor patch in
// classes/output/verify_certificate_result.php. Not an upstream file: keep it when a tool_certificate release is
// imported. See moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md and ADR-041.

namespace tool_certificate;

use tool_certificate\output\verify_certificate_result;
use tool_certificate\output\verify_certificate_results;
use tool_certificate_generator;

/**
 * Tests for the result-escaping vendor patch in classes/output/verify_certificate_result.php (2026-11-19).
 *
 * The verification result table is built with html_writer::table(), which prints cell text as HTML. Upstream put
 * the holder's stored full name and the certificate (template) name in it unescaped. Since the verify-on-arrival
 * patch (D32) the table renders on a plain GET for anyone who holds a link, so the patch escapes both values:
 * s() for the name, format_string() in the template's context for the certificate name.
 *
 * PHPUnit reads no doc-comment metadata from a class that has attributes, so the group is an attribute too.
 *
 * @package    tool_certificate
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('tool_certificate')]
#[\PHPUnit\Framework\Attributes\CoversClass(verify_certificate_result::class)]
final class sentientia_verify_result_escaping_test extends \advanced_testcase {

    /** @var string A stored full name with markup, an apostrophe and an ampersand. */
    private const HOLDER = 'Ann <script>alert(1)</script> O\'Brien & Co';

    /**
     * Get the certificate generator.
     *
     * @return tool_certificate_generator
     */
    protected function get_generator(): tool_certificate_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certificate');
    }

    /**
     * Set up $PAGE as admin/tool/certificate/index.php does and return its core renderer.
     *
     * @return \renderer_base
     */
    private function page_renderer(): \renderer_base {
        global $PAGE;
        $PAGE->set_url(new \moodle_url('/admin/tool/certificate/index.php'));
        $PAGE->set_context(\context_system::instance());
        return $PAGE->get_renderer('core');
    }

    /**
     * Through the real lookup and renderer: the holder's name and the certificate name are escaped in the table.
     */
    public function test_names_are_escaped_in_the_result_table(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $template = $this->get_generator()->create_template((object)[
            'name' => 'Certificate 1',
            'categoryid' => $category->id,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $issueid = $template->issue_certificate($user->id);
        $code = $DB->get_field('tool_certificate_issues', 'code', ['id' => $issueid], MUST_EXIST);

        // Values that reach the table unchanged from the database: the full name stored in the issue at issue time
        // (BizLMS-era issues included) and the template name. Written directly, past any form cleaning.
        $certificatename = 'Safety & <b>Compliance</b>';
        $DB->set_field('tool_certificate_issues', 'data', json_encode(['userfullname' => self::HOLDER]), ['id' => $issueid]);
        $DB->set_field('tool_certificate_templates', 'name', $certificatename, ['id' => $template->get_id()]);

        $this->setUser(null);
        $result = certificate::verify($code);
        $this->assertTrue($result->success);
        $html = $this->page_renderer()->render(new verify_certificate_results($result));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Ann &lt;script&gt;alert(1)&lt;/script&gt; O&#039;Brien &amp; Co', $html);
        $this->assertStringNotContainsString('<b>Compliance</b>', $html);
        $this->assertStringContainsString(
            format_string($certificatename, true, ['context' => \context_coursecat::instance($category->id)]), $html);
        $this->assertStringContainsString(s(get_string('validcertificate', 'tool_certificate')), $html);
    }

    /**
     * A template whose context is gone still verifies: the certificate name is formatted in the system context.
     */
    public function test_missing_template_context_falls_back_to_system(): void {
        global $DB;
        $this->resetAfterTest();

        $missingcontextid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {context}') + 1000;
        $issue = (object)[
            'code' => 'ABCDEF123456',
            'userid' => 2,
            'data' => json_encode(['userfullname' => self::HOLDER]),
            'certificatename' => 'Fire & Safety',
            'contextid' => $missingcontextid,
            'timecreated' => time(),
            'expires' => 0,
        ];

        $table = (new verify_certificate_result($issue))->export_for_template($this->page_renderer())->table;

        $this->assertStringContainsString('Fire &amp; Safety', $table);
        $this->assertStringNotContainsString('<script>', $table);
    }

    /**
     * Tripwire: the tagged hunk is still in verify_certificate_result.php.
     *
     * The test above fails too when the hunk is dropped; this one names the cause. Whoever imports a
     * tool_certificate release re-applies the hunk from the core-mod record, or drops it if upstream escapes the
     * two cells itself.
     */
    public function test_verify_certificate_result_carries_the_patch(): void {
        $source = file_get_contents(dirname(__DIR__) . '/classes/output/verify_certificate_result.php');
        $this->assertNotFalse($source);

        $this->assertSame(2, substr_count($source, '// SENTIENTIA-CORE-MOD:'));
        $this->assertStringContainsString("\$this->userfullname = s(@json_decode(\$issue->data, true)['userfullname']);",
            $source);
        $this->assertStringContainsString('$this->certificatename = format_string($issue->certificatename, true,', $source);
    }
}
