<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\cron_health
 */
class cron_health_test extends \advanced_testcase {

    /**
     * Seed a task_scheduled row. Moodle's task framework usually
     * manages this table itself; we manipulate directly for the test.
     */
    private function seed_task(string $classname, int $lastruntime,
                                int $nextruntime,
                                int $disabled = 0,
                                int $faildelay = 0): int {
        global $DB;
        return (int) $DB->insert_record('task_scheduled', (object) [
            'classname'    => $classname,
            'component'    => 'local_airpay_core',
            'lastruntime'  => $lastruntime,
            'nextruntime'  => $nextruntime,
            'blocking'     => 0,
            'minute'       => '*',
            'hour'         => '*',
            'day'          => '*',
            'month'        => '*',
            'dayofweek'    => '*',
            'disabled'     => $disabled,
            'faildelay'    => $faildelay,
            'timestarted'  => null,
            'hostname'     => null,
            'pid'          => null,
        ]);
    }

    public function test_summary_returns_zero_when_no_tasks(): void {
        $this->resetAfterTest(true);
        global $DB;
        // Clear any tasks Moodle pre-seeded so this test starts clean.
        $DB->delete_records('task_scheduled');
        $s = cron_health::summary();
        $this->assertSame(0, $s['stuck_airpay']);
        $this->assertSame(0, $s['stuck_other']);
        $this->assertSame(0, $s['in_backoff']);
    }

    public function test_stuck_airpay_task_surfaces_in_summary(): void {
        $this->resetAfterTest(true);
        global $DB;
        $DB->delete_records('task_scheduled');

        $now = time();
        // Seed an Airpay task whose nextruntime is 12 hours in the past.
        $this->seed_task(
            '\\local_sentientia_recompletion\\task\\run_rules',
            lastruntime: $now - 86400,
            nextruntime: $now - 43200,
        );

        $s = cron_health::summary();
        $this->assertSame(1, $s['stuck_airpay']);
        $this->assertSame(0, $s['stuck_other']);

        $stuck = cron_health::get_stuck_airpay_tasks();
        $this->assertCount(1, $stuck);
        $this->assertGreaterThan(0, $stuck[0]->overdue_seconds);
    }

    public function test_disabled_task_does_not_count_as_stuck(): void {
        $this->resetAfterTest(true);
        global $DB;
        $DB->delete_records('task_scheduled');

        $now = time();
        $this->seed_task(
            '\\local_sentientia_recompletion\\task\\run_rules',
            lastruntime: $now - 86400,
            nextruntime: $now - 43200,
            disabled: 1,
        );

        $s = cron_health::summary();
        $this->assertSame(0, $s['stuck_airpay']);
    }

    public function test_non_airpay_task_classified_separately(): void {
        $this->resetAfterTest(true);
        global $DB;
        $DB->delete_records('task_scheduled');

        $now = time();
        $this->seed_task(
            '\\core\\task\\some_other_task',
            lastruntime: $now - 86400,
            nextruntime: $now - 43200,
        );

        $s = cron_health::summary();
        $this->assertSame(0, $s['stuck_airpay']);
        $this->assertSame(1, $s['stuck_other']);
    }

    public function test_faildelay_surfaces_in_backoff_list(): void {
        $this->resetAfterTest(true);
        global $DB;
        $DB->delete_records('task_scheduled');

        $now = time();
        $this->seed_task(
            '\\local_sentientia_recompletion\\task\\run_rules',
            lastruntime: $now - 600,
            nextruntime: $now + 3600,        // not stuck yet
            faildelay: 1200,                  // but in backoff
        );

        $s = cron_health::summary();
        $this->assertSame(0, $s['stuck_airpay']);
        $this->assertSame(1, $s['in_backoff']);

        $backoff = cron_health::get_tasks_in_failure_backoff();
        $this->assertCount(1, $backoff);
        $this->assertSame(1200, (int) $backoff[0]->faildelay);
    }

    public function test_format_overdue_humanises_seconds(): void {
        $this->assertSame('45s', cron_health::format_overdue(45));
        $this->assertSame('5m', cron_health::format_overdue(5 * 60));
        $this->assertSame('1h 30m', cron_health::format_overdue(90 * 60));
        $this->assertSame('2d 3h', cron_health::format_overdue(
            2 * 86400 + 3 * 3600));
    }
}
