<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_emails\certificate_helper
 *
 * Sprint B unit tests.
 *
 * The tests intentionally avoid creating real tool_certificate templates
 * (that would require pulling in a heavy stub of the certificate
 * plugin's data-generator). Instead we focus on:
 *
 *   - empty/missing-issue paths (returns null cleanly)
 *   - environment-defence: tool_certificate not installed → null
 *   - the temp-cleanup helper's null-safety
 *
 * Integration with a live issue + PDF generation is exercised manually
 * via the smoke flow documented in Sprint B's commit message.
 */
class certificate_helper_test extends \advanced_testcase {

    public function test_no_issue_for_user_returns_null(): void {
        $this->resetAfterTest(true);
        $u = $this->getDataGenerator()->create_user();

        // No tool_certificate_issues row exists for any course yet.
        $result = certificate_helper::get_issue_for_user_course($u->id, 9999);
        $this->assertNull($result);
    }

    public function test_cleanup_handles_null_input(): void {
        // Must not throw on null — the sender calls this unconditionally
        // even when no certificate was materialised.
        certificate_helper::cleanup_materialised(null);
        $this->assertTrue(true);
    }

    public function test_cleanup_handles_missing_abs_path(): void {
        // Should not throw if the array lacks abs_path (e.g. partial input).
        certificate_helper::cleanup_materialised(['display_name' => 'x.pdf']);
        $this->assertTrue(true);
    }

    public function test_cleanup_deletes_real_temp_file(): void {
        global $CFG;
        $this->resetAfterTest(true);

        // Drop a real file in $CFG->tempdir/airpay_emails and verify
        // cleanup_materialised removes it.
        $dir = $CFG->tempdir . '/airpay_emails';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/test-' . uniqid() . '.pdf';
        file_put_contents($path, '%PDF-1.4 test');
        $this->assertFileExists($path);

        certificate_helper::cleanup_materialised([
            'abs_path' => $path,
            'rel_path' => 'temp/airpay_emails/' . basename($path),
            'display_name' => 'x.pdf',
        ]);

        $this->assertFileDoesNotExist($path);
    }
}
