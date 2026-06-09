<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_cron_health;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \block_sentientia_cron_health
 *
 * Unit tests for the cron-health dashboard block. Parallel to
 * block_sentientia_cert_health/tests/block_test.php — same shape so
 * the regression-coverage guarantees stay consistent across both
 * dashboard blocks.
 *
 * Coverage:
 *   - get_content() returns null for non-site-admins (silent hide)
 *   - get_content() returns the rendered widget for admins
 *   - widget HTML includes the 3 KPI labels in English
 *   - widget wraps in <section role="region" aria-label> for landmark
 *     navigation (a11y regression guard)
 *   - widget KPI numbers reflect actual {task_scheduled} state
 */
class block_test extends \advanced_testcase {

    /**
     * Build a fresh block instance. PHPUnit's bootstrap doesn't
     * autoload block_base; we require blocks/moodleblock.class.php
     * before instantiating.
     */
    private function make_block(): \block_sentientia_cron_health {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once(__DIR__ . '/../block_sentientia_cron_health.php');
        $b = new \block_sentientia_cron_health();
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

        // The three KPI labels (from lang/en strings) should appear
        // in the rendered HTML.
        $this->assertStringContainsString('Airpay tasks stuck', $content->text);
        $this->assertStringContainsString('Other tasks stuck',  $content->text);
        $this->assertStringContainsString('In failure backoff', $content->text);
    }

    public function test_widget_wraps_in_region_landmark(): void {
        // A11y regression guard. Engineering 21 added the region
        // landmark + aria-label — future refactor must keep them.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $block = $this->make_block();
        $content = $block->get_content();

        $this->assertStringContainsString('role="region"',       $content->text);
        $this->assertStringContainsString('aria-label=',         $content->text);
        $this->assertStringContainsString('airpay-cron-health',  $content->text);
    }

    public function test_widget_reflects_seeded_stuck_task(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Clear any Moodle-shipped scheduled tasks so the test starts
        // from a known baseline.
        $DB->delete_records('task_scheduled');

        // Seed one stuck Airpay task: nextruntime 12 hours in the past.
        $now = time();
        $DB->insert_record('task_scheduled', (object) [
            'classname'    => '\\local_sentientia_recompletion\\task\\run_rules',
            'component'    => 'local_sentientia_recompletion',
            'lastruntime'  => $now - 86400,
            'nextruntime'  => $now - 43200,
            'blocking'     => 0,
            'minute'       => '*',
            'hour'         => '*',
            'day'          => '*',
            'month'        => '*',
            'dayofweek'    => '*',
            'disabled'     => 0,
            'faildelay'    => 0,
            'timestarted'  => null,
            'hostname'     => null,
            'pid'          => null,
        ]);

        $block = $this->make_block();
        $content = $block->get_content();
        $html = $content->text;

        // The stuck-airpay KPI should show "1" alongside its label.
        $this->assertMatchesRegularExpression(
            '/>1<.*Airpay tasks stuck/s',
            $html,
            'Stuck-airpay KPI should reflect the seeded stuck task');

        // The "Stuck Airpay tasks" sub-heading should appear (Sprint
        // B Engineering 21 changed h5 → h3; we don't pin the level
        // here, just the text presence).
        $this->assertStringContainsString('Stuck Airpay tasks', $html);
    }
}
