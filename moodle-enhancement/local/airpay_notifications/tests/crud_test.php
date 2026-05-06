<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_notifications;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for airpay_notifications.
 *
 * @package    local_airpay_notifications
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    private function seed_rule(string $name = 'Test Rule', int $enabled = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_notif_rules')) {
            $this->markTestSkipped('local_airpay_notif_rules table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_notif_rules', (object) [
            'name'         => $name,
            'rule_type'    => 'deadline_warning',
            'channel'      => 'inapp',
            'trigger_days' => 3,
            'audience'     => 'learner',
            'enabled'      => $enabled,
            'template'     => '',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_toggle_enabled_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $rid = $this->seed_rule('Foo', 1);

        rule_manager::toggle_enabled($rid, false);
        $this->assertEquals(0, (int) $DB->get_field('local_airpay_notif_rules', 'enabled', ['id' => $rid]));

        rule_manager::toggle_enabled($rid, true);
        $this->assertEquals(1, (int) $DB->get_field('local_airpay_notif_rules', 'enabled', ['id' => $rid]));
    }

    public function test_toggle_enabled_no_arg_inverts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $rid = $this->seed_rule('Foo', 1);
        rule_manager::toggle_enabled($rid);
        $this->assertEquals(0, (int) $DB->get_field('local_airpay_notif_rules', 'enabled', ['id' => $rid]));
    }

    public function test_delete_removes_rule(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $rid = $this->seed_rule();
        rule_manager::delete($rid);
        $this->assertFalse($DB->record_exists('local_airpay_notif_rules', ['id' => $rid]));
    }

    public function test_external_delete_rule_capability_required(): void {
        $this->resetAfterTest();
        $rid = $this->seed_rule();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_rule::execute($rid);
    }

    public function test_external_toggle_rule_capability_required(): void {
        $this->resetAfterTest();
        $rid = $this->seed_rule();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\toggle_rule::execute($rid, false);
    }
}
