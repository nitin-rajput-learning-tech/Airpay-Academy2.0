<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_integrations;

defined('MOODLE_INTERNAL') || die();

/**
 * Schema lock-in: confirms the install.xml fixed in commit
 * 2026050700 actually creates the table that webhook.php inserts into.
 *
 * Pre-fix state (before this commit): webhook.php inserted into
 * local_sentientia_integration_log, but no install.xml existed for the
 * plugin → first KeKa POST threw a fatal SQL error.
 *
 * @package    local_sentientia_integrations
 * @category   test
 */
final class schema_test extends \advanced_testcase {

    public function test_integration_log_table_exists(): void {
        global $DB;
        $this->resetAfterTest();
        $manager = $DB->get_manager();
        $this->assertTrue(
            $manager->table_exists(new \xmldb_table('local_sentientia_integration_log')),
            'INTEGRATIONS-AUDIT.md §4.1 fix — table must be present after install.xml ships');
    }

    public function test_integration_log_columns(): void {
        global $DB;
        $this->resetAfterTest();
        $cols = array_keys($DB->get_columns('local_sentientia_integration_log'));
        // Webhook.php and any future audit consumer relies on these fields.
        foreach (['id', 'source', 'event_type', 'payload', 'status',
                  'errormsg', 'timecreated'] as $f) {
            $this->assertContains($f, $cols, "missing column: $f");
        }
    }

    public function test_webhook_can_insert_log_row(): void {
        global $DB;
        $this->resetAfterTest();
        // This is the EXACT shape webhook.php:41 uses.
        $id = $DB->insert_record('local_sentientia_integration_log', (object) [
            'source'      => 'keka_webhook',
            'event_type'  => 'joiner',
            'payload'     => '{"employeeNumber":"EMP-001","email":"x@y"}',
            'status'      => 'received',
            'timecreated' => time(),
        ]);
        $this->assertGreaterThan(0, $id);

        $row = $DB->get_record('local_sentientia_integration_log', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('keka_webhook', $row->source);
        $this->assertSame('joiner',       $row->event_type);
        $this->assertSame('received',     $row->status);
    }

    public function test_status_can_be_updated_post_processing(): void {
        global $DB;
        $this->resetAfterTest();
        // webhook.php:54-58 transitions status from received → processed/failed
        // after handle_webhook returns.
        $id = $DB->insert_record('local_sentientia_integration_log', (object) [
            'source' => 'keka_webhook', 'status' => 'received', 'timecreated' => time(),
        ]);
        $DB->set_field('local_sentientia_integration_log', 'status', 'processed', ['id' => $id]);
        $this->assertSame('processed',
            $DB->get_field('local_sentientia_integration_log', 'status', ['id' => $id]));
    }

    /**
     * The duplicate hrms_sync task stays removed, and the one task the plugin does register ships switched off.
     *
     * INTEGRATIONS-AUDIT.md §3.2 removed the hrms_sync task (a second implementation of keka_client::sync_employees,
     * the duplicate-user hazard). ADR-029 (2026-08-07, 708a823fb) then registered task\keka_reconcile, a thin wrapper
     * around keka_client::sync_employees() (the code path the webhook uses), DISABLED. This test used to assert that
     * the plugin registers no task at all, so it failed from that commit on, on every Moodle version. What it has to
     * protect is the two points above: no hrms_sync, and nothing that pulls from KeKa unless an admin turns it on.
     */
    public function test_only_the_disabled_reconcile_task_is_registered(): void {
        global $DB;
        $this->resetAfterTest();
        $tasks = $DB->get_records('task_scheduled',
            ['component' => 'local_sentientia_integrations']);

        $classes = array_map(fn($t) => ltrim($t->classname, '\\'), $tasks);
        foreach ($classes as $class) {
            $this->assertStringNotContainsString('hrms_sync', $class,
                'duplicate hrms_sync task should be removed; the webhook path and keka_reconcile are the only sync code');
        }
        $this->assertSame(['local_sentientia_integrations\task\keka_reconcile'], array_values($classes),
            'keka_reconcile is the only scheduled task of this plugin');
        foreach ($tasks as $task) {
            $this->assertSame(1, (int) $task->disabled,
                'the reconcile task ships disabled (flag + admin setting + task enable are a triple opt-in)');
        }
    }
}
