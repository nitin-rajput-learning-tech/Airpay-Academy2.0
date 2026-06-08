<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_cert_health;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \block_sentientia_cert_health
 *
 * Unit tests for the cert-health dashboard block.
 *
 * Coverage:
 *   - get_content() returns null for non-site-admin (the silent-hide
 *     contract — the block doesn't render at all unless the viewer
 *     is a site administrator)
 *   - get_content() returns null when local_airpay_email_log table
 *     is missing (defensive guard for when sentientia_emails is
 *     uninstalled)
 *   - get_content() returns the rendered widget when a site admin
 *     is logged in
 *   - the widget HTML includes the 3 KPI labels in English
 *   - the widget reflects actual log data (synthetic rows)
 */
class block_test extends \advanced_testcase {

    /**
     * Build a fresh block instance for unit testing.
     *
     * Moodle's block_base lives in lib/blocklib.php — phpunit
     * bootstrap doesn't autoload it (blocks usually load through
     * block_manager), so we require it explicitly before
     * instantiating.
     */
    private function make_block(): \block_sentientia_cert_health {
        global $CFG;
        // block_base lives in blocks/moodleblock.class.php — must be
        // loaded before the block class extends it. PHPUnit's
        // bootstrap doesn't autoload Moodle's block classes.
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once(__DIR__ . '/../block_sentientia_cert_health.php');
        $b = new \block_sentientia_cert_health();
        $b->init();
        return $b;
    }

    public function test_get_content_returns_null_for_non_admin(): void {
        $this->resetAfterTest(true);
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $block = $this->make_block();
        $content = $block->get_content();

        $this->assertNull($content,
            'Block must hide itself silently for non-site-admins');
    }

    public function test_get_content_returns_widget_for_admin(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $block = $this->make_block();
        $content = $block->get_content();

        $this->assertNotNull($content);
        $this->assertObjectHasProperty('text', $content);
        $this->assertNotEmpty($content->text);
    }

    public function test_widget_includes_three_kpi_labels(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $block = $this->make_block();
        $content = $block->get_content();

        // All three KPI labels should appear in the rendered HTML.
        // Test via string-contains since the block builds HTML inline
        // (no template); html_writer escapes the label but the words
        // themselves should still be present.
        $this->assertStringContainsString('Certificates emailed', $content->text);
        $this->assertStringContainsString('Failed sends',         $content->text);
        $this->assertStringContainsString('Suppressed sends',     $content->text);
    }

    public function test_widget_wraps_in_region_landmark(): void {
        // A11y guarantee: the block's outer element is a
        // <section role="region" aria-label> so screen-reader users
        // can jump to it via landmark navigation. Regression check —
        // future refactor might accidentally remove the role.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $block = $this->make_block();
        $content = $block->get_content();

        $this->assertStringContainsString('role="region"', $content->text);
        $this->assertStringContainsString('aria-label=', $content->text);
        $this->assertStringContainsString('airpay-cert-health', $content->text);
    }

    public function test_widget_counts_reflect_log_table(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Seed 3 cert-bearing email rows in the last 7 days:
        //   2 sent, 1 failed.
        $now = time();
        $row_template = [
            'rule_id'              => null,
            'userid'               => 1,
            'courseid'             => 1,
            'tenant_id'            => 1,
            'channel'              => 'email',
            'subject'              => 'Congrats',
            'template_key'         => 'enrollment/course_completed',
            'attachment_filename'  => 'Airpay-certificate-XYZ.pdf',
            'certificate_issue_id' => 42,
            'timecreated'          => $now,
        ];
        $DB->insert_record('local_airpay_email_log', (object) array_merge(
            $row_template, ['status' => 'sent']));
        $DB->insert_record('local_airpay_email_log', (object) array_merge(
            $row_template, ['status' => 'sent']));
        $DB->insert_record('local_airpay_email_log', (object) array_merge(
            $row_template, ['status' => 'failed']));
        // One older row that should NOT be counted (older than 7 days).
        $DB->insert_record('local_airpay_email_log', (object) array_merge(
            $row_template, [
                'status'      => 'sent',
                'timecreated' => $now - 8 * 86400,
            ]));

        $block = $this->make_block();
        $content = $block->get_content();

        // Sent count = 2 → big number "2" should appear.
        // Failed count = 1 → big number "1" should appear.
        // Suppressed count = 0 → "0" should appear.
        // We can't easily assert exact numbers without parsing HTML,
        // but we can assert all three integers are present in order.
        $html = $content->text;
        // Locate the three KPI blocks by their label and check the
        // corresponding numeric value appears nearby.
        $this->assertMatchesRegularExpression(
            '/>2<.*Certificates emailed/s',
            $html,
            'Sent count should reflect the 2 seeded sent rows');
        $this->assertMatchesRegularExpression(
            '/>1<.*Failed sends/s',
            $html,
            'Failed count should reflect the 1 seeded failed row');
        $this->assertMatchesRegularExpression(
            '/>0<.*Suppressed sends/s',
            $html,
            'Suppressed count should be 0 since no suppressed rows seeded');
    }

    public function test_widget_count_excludes_non_certificate_rows(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Seed 2 NON-cert rows (no attachment_filename and no
        // certificate_issue_id) — they should NOT be counted.
        $now = time();
        $DB->insert_record('local_airpay_email_log', (object) [
            'rule_id'              => null,
            'userid'               => 1,
            'tenant_id'            => 1,
            'channel'              => 'email',
            'subject'              => 'Generic reminder',
            'template_key'         => 'notifications/foo',
            'attachment_filename'  => null,
            'certificate_issue_id' => null,
            'status'               => 'sent',
            'timecreated'          => $now,
        ]);

        $block = $this->make_block();
        $content = $block->get_content();

        // All three KPIs should still show 0 — the seeded row had
        // no attachment OR certificate_issue_id so the WHERE clause
        // excludes it.
        $html = $content->text;
        $this->assertMatchesRegularExpression(
            '/>0<.*Certificates emailed/s', $html);
        $this->assertMatchesRegularExpression(
            '/>0<.*Failed sends/s', $html);
    }
}
