<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_reports;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-032 (users): the two report surfaces that read the history the BizLMS import copied into
 * local_sentientia_users_*: the "Training Transcript" report type and the "Login days (imported)" column of User
 * Activity. Both are default OFF and change nothing while OFF.
 *
 * @package    local_sentientia_reports
 * @category   test
 * @covers     \local_sentientia_reports\report_manager
 *
 * @group local_sentientia_reports
 * @group bizlms_import
 * @group tenant_isolation
 */
final class imported_history_report_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path, string $email, array $extra = []): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user(['email' => $email] + $extra);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path, 'admin' . random_string(6) . '@example.com');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function report(string $type, ?string $path): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_reports', (object) [
            'name' => 'Report', 'report_type' => $type, 'costcenterid' => 0, 'open_path' => $path, 'status' => 1,
            'created_by' => 2, 'runcount' => 0, 'timecreated' => time(), 'timemodified' => time()]);
    }

    private function transcript(int $userid, string $name, string $title, ?int $completed, string $status = 'completed',
                                ?float $hours = 1.5): void {
        global $DB;
        $DB->insert_record('local_sentientia_users_transcript', (object) [
            'userid' => $userid, 'employee_id' => 'E' . $userid, 'learner_name' => $name, 'title' => $title,
            'training_type' => 'Classroom', 'objectref' => '', 'location' => '', 'courseid' => 0, 'status' => $status,
            'status_raw' => 'As loaded', 'completion_date_raw' => '', 'score_raw' => '', 'hours_raw' => '',
            'timecompleted' => $completed, 'score' => null, 'hours' => $hours, 'costcenterid' => 0, 'open_path' => null,
            'source' => 'bizlms', 'usercreated' => 0, 'usermodified' => 0, 'timecreated' => 1700000000,
            'timemodified' => 0]);
    }

    private function turn_on(string $flag): void {
        \local_sentientia_platform\feature_flags::set($flag, 0, true);
    }

    // Training Transcript.

    public function test_the_report_type_does_not_exist_while_its_flag_is_off(): void {
        $this->setAdminUser();
        $this->assertArrayNotHasKey('training_transcript', report_manager::report_types());
        $this->assertSame(array_keys(report_manager::REPORT_TYPES), array_keys(report_manager::report_types()),
            'OFF: exactly the four built-in types');
        try {
            report_manager::create((object) ['name' => 'Sneaky', 'report_type' => 'training_transcript', 'costcenterid' => 0]);
            $this->fail('a type that is off must not be creatable');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidreporttype', $e->errorcode);
        }
        // A saved report of that type (made while the flag was on) runs to nothing while it is off.
        $this->transcript((int) $this->user_at('/1/5', 'a@example.com')->id, 'A', 'Safety', 1700000000);
        $result = report_manager::run_report($this->report('training_transcript', null));
        $this->assertSame([], $result['rows']);
    }

    public function test_the_report_lists_records_by_learner_with_history_only_totals(): void {
        $this->turn_on(report_manager::FLAG_TRAINING_TRANSCRIPT);
        $this->setAdminUser();
        $this->assertArrayHasKey('training_transcript', report_manager::report_types());
        $id = report_manager::create((object) ['name' => 'Transcript', 'report_type' => 'training_transcript',
            'costcenterid' => 0]);

        $airpay = $this->user_at('/1/5', 'asha@example.com', ['firstname' => 'Asha', 'lastname' => 'Rao']);
        $this->transcript((int) $airpay->id, 'Asha Rao as loaded', 'Safety 101', 1700000000, 'completed', 1.5);
        $this->transcript((int) $airpay->id, 'Asha Rao as loaded', 'Ethics', null, 'weird', null);
        $this->transcript(0, 'Off Platform', 'Fire drill', 1600000000, 'failed', 2.0);

        $result = report_manager::run_report($id);
        $this->assertCount(3, $result['rows']);
        $titles = array_column($result['rows'], 'title');
        $this->assertSame(['Safety 101', 'Fire drill', 'Ethics'], $titles, 'newest first, no completion date last');
        $names = array_column($result['rows'], 'fullname');
        $this->assertSame('Asha Rao', $names[0], 'a matched learner is shown by her account name');
        $this->assertSame('Off Platform', $names[1], 'an unmatched row by the name as loaded');
        $this->assertSame(get_string('report_status_unknown', 'local_sentientia_reports'), $result['rows'][2]['status']);
        $summary = array_column($result['summary'], 'value', 'label');
        $this->assertSame(3, $summary[get_string('report_sum_records', 'local_sentientia_reports')]);
        $this->assertSame(1, $summary[get_string('report_sum_completed', 'local_sentientia_reports')]);
        $this->assertSame('3.50', $summary[get_string('report_sum_hours', 'local_sentientia_reports')]);
        $this->assertCount(9, $result['columns']);
    }

    public function test_a_tenant_admin_sees_only_records_of_learners_in_their_tenant(): void {
        $this->turn_on('sentientia.reports.training_transcript');
        $own = $this->user_at('/1/5', 'own@example.com', ['firstname' => 'Own', 'lastname' => 'Learner']);
        $zeea = $this->user_at('/177/178', 'zeea@example.com', ['firstname' => 'Zeea', 'lastname' => 'Learner']);
        $gone = $this->user_at('/1/5', 'gone@example.com', ['firstname' => 'Gone', 'lastname' => 'Learner']);
        $this->transcript((int) $own->id, 'x', 'Own record', 1700000000);
        $this->transcript((int) $zeea->id, 'x', 'ZEEA record', 1700000001);
        $this->transcript((int) $gone->id, 'x', 'Deleted learner record', 1700000002);
        $this->transcript(0, 'Unmatched', 'Off-platform record', 1700000003);
        global $DB;
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);

        $airpayreport = $this->report('training_transcript', '/1');
        $allreport = $this->report('training_transcript', null);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame(['Own record'], array_column(report_manager::run_report($airpayreport)['rows'], 'title'),
            'their tenant\'s learner only: not ZEEA, not a deleted account, not an unmatched row');
        try {
            report_manager::run_report($allreport);
            $this->fail('an all-organisations report is cross-tenant only');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }

        $this->setAdminUser();
        $titles = array_column(report_manager::run_report($allreport)['rows'], 'title');
        sort($titles);
        $this->assertSame(['Off-platform record', 'Own record', 'ZEEA record'], $titles,
            'a cross-tenant caller sees every live learner and the unmatched row, but not the deleted account');
    }

    // Login days.

    public function test_user_activity_gains_the_login_days_column_only_while_its_flag_is_on(): void {
        global $DB;
        $learner = $this->user_at('/1/5', 'asha@example.com', ['firstname' => 'Asha', 'lastname' => 'Rao']);
        $other = $this->user_at('/1/5', 'other@example.com');
        foreach ([1, 2, 3] as $day) {
            $DB->insert_record('local_sentientia_users_logindays', (object) ['userid' => $learner->id,
                'logindate' => 1700000000 + $day * DAYSECS, 'source' => 'web', 'timecreated' => 1, 'timemodified' => 1]);
        }
        $report = $this->report('user_activity', null);
        $this->setAdminUser();

        $off = report_manager::run_report($report);
        $this->assertNotContains('logindays', array_column($off['columns'], 'key'), 'default OFF: the report is what it was');
        $this->assertArrayNotHasKey('logindays', $off['rows'][0]);

        $this->turn_on('sentientia.reports.login_days');
        $on = report_manager::run_report($report);
        $this->assertContains('logindays', array_column($on['columns'], 'key'));
        $byemail = array_column($on['rows'], 'logindays', 'email');
        $this->assertSame(3, $byemail['asha@example.com']);
        $this->assertSame(0, $byemail['other@example.com'], 'no imported day is 0, not blank');
        $this->assertNotNull($other);
    }
}
