<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\backup_filename
 *
 * Exercises the backup-filename template helper that powers P0 borrow
 * #11. Verifies token substitution, sanitisation, length truncation,
 * and the don't-explode-on-bad-input contract — the helper is called
 * from the SENTIENTIA SCORM pipeline so a thrown exception there
 * silently stalls content generation.
 */
class backup_filename_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_default_template_substitutes_type_id_and_date(): void {
        $name = backup_filename::resolve([
            'type'      => 'course',
            'id'        => 42,
            'shortname' => 'pci-dss',
        ]);

        $this->assertStringStartsWith('backup-moodle2-course-42-pci-dss-', $name);
        $this->assertStringEndsWith('.mbz', $name);
        // {date} substitutes YYYYMMDD-HHMM, so the suffix before .mbz
        // matches that shape.
        $this->assertMatchesRegularExpression('/-\d{8}-\d{4}\.mbz$/', $name);
    }

    public function test_shortname_is_sanitised(): void {
        // Shortnames in production sometimes contain spaces, accents,
        // and special chars. The helper must produce a clean filename.
        $name = backup_filename::resolve([
            'type'      => 'course',
            'id'        => 1,
            'shortname' => 'Customer Onboarding (FY-26) — final!',
        ]);

        // After sanitisation: lowercase, no spaces, no parens, no em-dash.
        $this->assertMatchesRegularExpression('/customer-onboarding-fy-26-final/', $name);
        $this->assertStringNotContainsString(' ', $name);
        $this->assertStringNotContainsString('(', $name);
        $this->assertStringNotContainsString('!', $name);
    }

    public function test_path_traversal_is_blocked(): void {
        $name = backup_filename::resolve([
            'type'      => 'course',
            'id'        => 99,
            'shortname' => '../../etc/passwd',
        ]);

        // No /, \, or .. anywhere in the result.
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('\\', $name);
        $this->assertStringNotContainsString('..', $name);
    }

    public function test_custom_template_with_all_tokens(): void {
        $template = '{site}__{customer}__{tenant}__{type}__{id}__{shortname}__{iso}';
        $name = backup_filename::resolve([
            'template'  => $template,
            'type'      => 'scorm',
            'id'        => 7,
            'shortname' => 'kyc',
        ]);

        // Tenant token may be empty if the test user has no open_path,
        // which is fine — empty tokens collapse and underscores get
        // normalised down to dashes.
        $this->assertStringContainsString('scorm', $name);
        $this->assertStringContainsString('7', $name);
        $this->assertStringContainsString('kyc', $name);
        $this->assertStringContainsString('airpay', $name); // customer
        $this->assertStringEndsWith('.mbz', $name);
    }

    public function test_extension_can_be_overridden(): void {
        $name = backup_filename::resolve([
            'type'      => 'scorm',
            'id'        => 5,
            'shortname' => 'gdpr',
            'extension' => 'zip',
        ]);

        $this->assertStringEndsWith('.zip', $name);
        $this->assertStringNotContainsString('.mbz', $name);
    }

    public function test_empty_context_produces_fallback(): void {
        // Even with zero useful input the helper must not blow up — it
        // returns a deterministic fallback. The SENTIENTIA pipeline
        // depends on this so a CSV-driven export job with a malformed
        // row doesn't crash the whole batch.
        $name = backup_filename::resolve([]);

        $this->assertIsString($name);
        $this->assertStringEndsWith('.mbz', $name);
        $this->assertGreaterThan(4, strlen($name)); // at least "x.mbz"
    }

    public function test_max_length_is_enforced(): void {
        // Pad shortname to push the result past MAX_LEN.
        $longname = str_repeat('a', 500);
        $name = backup_filename::resolve([
            'type'      => 'course',
            'id'        => 1,
            'shortname' => $longname,
        ]);

        $this->assertLessThanOrEqual(backup_filename::MAX_LEN + 4, strlen($name));
        $this->assertStringEndsWith('.mbz', $name, 'Extension must never be truncated.');
    }

    public function test_token_help_returns_documented_tokens(): void {
        $help = backup_filename::token_help();
        $this->assertNotEmpty($help);
        $this->assertArrayHasKey('{site}', $help);
        $this->assertArrayHasKey('{customer}', $help);
        $this->assertArrayHasKey('{tenant}', $help);
        $this->assertArrayHasKey('{type}', $help);
        $this->assertArrayHasKey('{id}', $help);
        $this->assertArrayHasKey('{shortname}', $help);
        $this->assertArrayHasKey('{date}', $help);
        $this->assertArrayHasKey('{iso}', $help);
        // Each value should be a human-readable description, not empty.
        foreach ($help as $token => $desc) {
            $this->assertNotEmpty($desc, "Token $token has no description");
        }
    }

    public function test_configured_template_is_used_when_no_override(): void {
        set_config('backup_filename_template', '{type}-AUDIT-{id}', 'local_airpay_core');
        $name = backup_filename::resolve(['type' => 'course', 'id' => 99]);
        $this->assertStringContainsString('course-audit-99', $name);
        $this->assertStringEndsWith('.mbz', $name);
    }

    public function test_unrecognised_tokens_are_left_as_literal(): void {
        // A typo'd token stays in the filename rather than silently
        // expanding to empty — easier to spot during testing. The
        // resulting curly-braced literal is still safe (gets sanitised
        // to dashes).
        $name = backup_filename::resolve([
            'template' => 'export-{notatoken}-{id}',
            'type'     => 'course',
            'id'       => 11,
        ]);
        // Curly braces get stripped by sanitise — survives as "notatoken".
        $this->assertStringContainsString('notatoken', $name);
        $this->assertStringContainsString('11', $name);
    }
}
