<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * The readers of the imported BizLMS history in this plugin (ADR-032, users): the earlier training records and
 * the position and domain labels on the profile. Both are default OFF (db/feature_flags.php), and the importer
 * does not turn either on.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\legacy_history
 * @covers     \local_sentientia_users\user_manager::build_profile_context
 *
 * @group local_sentientia_users
 * @group bizlms_import
 * @group tenant_isolation
 */
final class legacy_history_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        legacy_history::reset_labels();
    }

    private function user_at(string $path, array $record = []): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user($record);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    private function transcript_row(int $userid, string $title, ?int $completed, string $status = 'completed'): int {
        global $DB;
        return $DB->insert_record('local_sentientia_users_transcript', (object) [
            'userid' => $userid, 'employee_id' => 'E', 'learner_name' => 'Somebody', 'title' => $title,
            'training_type' => 'Classroom', 'objectref' => '', 'location' => 'Pune', 'courseid' => 0, 'status' => $status,
            'status_raw' => 'As loaded', 'completion_date_raw' => $completed === null ? '12 Jun' : '', 'score_raw' => '',
            'hours_raw' => '', 'timecompleted' => $completed, 'score' => 90.5, 'hours' => null, 'costcenterid' => 1,
            'open_path' => '/1', 'source' => 'bizlms', 'usercreated' => 0, 'usermodified' => 0,
            'timecreated' => 1700000000, 'timemodified' => 0]);
    }

    public function test_the_flags_are_registered_and_default_off(): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        foreach ([legacy_history::FLAG_TRANSCRIPT, legacy_history::FLAG_POSITION_LABELS] as $flag) {
            $this->assertArrayHasKey($flag, $registry, $flag . ' is registered in db/feature_flags.php');
            $this->assertFalse($registry[$flag]['default'], $flag . ' is default OFF');
        }
        $this->assertFalse(legacy_history::transcript_enabled());
        $this->assertFalse(legacy_history::position_labels_enabled());
    }

    public function test_the_transcript_of_a_learner_is_newest_first_with_unparsed_dates_last(): void {
        $learner = $this->user_at('/1/5');
        $stranger = $this->user_at('/1/5');
        $this->transcript_row((int) $learner->id, 'Newest', 1700000000);
        $this->transcript_row((int) $learner->id, 'Older', 1600000000, 'failed');
        $this->transcript_row((int) $learner->id, 'Undated', null, 'weird');
        $this->transcript_row((int) $stranger->id, 'Somebody else\'s', 1700000500);

        $rows = legacy_history::transcript_for_user((int) $learner->id);
        $this->assertSame(['Newest', 'Older', 'Undated'], array_column($rows, 'title'));
        $this->assertSame(get_string('transcript_status_completed', 'local_sentientia_users'), $rows[0]['status']);
        $this->assertSame(get_string('transcript_status_failed', 'local_sentientia_users'), $rows[1]['status']);
        $this->assertSame(get_string('transcript_status_unknown', 'local_sentientia_users'), $rows[2]['status'],
            'a status the profile has no label for is shown as unknown, the raw text beside it');
        $this->assertSame('As loaded', $rows[0]['statusraw']);
        $this->assertSame('12 Jun', $rows[2]['completed'], 'a date that did not parse shows the text as loaded');
        $this->assertSame('90.50', $rows[0]['score']);
        $this->assertSame('', $rows[0]['hours']);
    }

    public function test_a_deleted_learner_has_no_transcript_and_the_limit_is_honoured(): void {
        global $DB;
        $learner = $this->user_at('/1/5');
        for ($i = 0; $i < 5; $i++) {
            $this->transcript_row((int) $learner->id, 'Row ' . $i, 1700000000 + $i);
        }
        $this->assertCount(2, legacy_history::transcript_for_user((int) $learner->id, 2));
        $this->assertSame([], legacy_history::transcript_for_user(0));

        $DB->set_field('user', 'deleted', 1, ['id' => $learner->id]);
        $this->assertSame([], legacy_history::transcript_for_user((int) $learner->id));
    }

    public function test_the_profile_shows_the_transcript_only_while_the_flag_is_on(): void {
        $learner = $this->user_at('/1/5');
        $this->transcript_row((int) $learner->id, 'Safety 101', 1700000000);
        $this->setAdminUser();

        $off = user_manager::build_profile_context((int) $learner->id);
        $this->assertArrayNotHasKey('ap_has_transcript', $off, 'default OFF: the profile is what it was');
        $this->assertArrayNotHasKey('ap_position', $off);

        \local_sentientia_platform\feature_flags::set(legacy_history::FLAG_TRANSCRIPT, 0, true);
        $on = user_manager::build_profile_context((int) $learner->id);
        $this->assertTrue($on['ap_has_transcript']);
        $this->assertSame(['Safety 101'], array_column($on['ap_transcript'], 'title'));
    }

    public function test_the_profile_shows_no_transcript_section_for_a_learner_without_records(): void {
        $learner = $this->user_at('/1/5');
        $this->setAdminUser();
        \local_sentientia_platform\feature_flags::set(legacy_history::FLAG_TRANSCRIPT, 0, true);
        $this->assertArrayNotHasKey('ap_has_transcript', user_manager::build_profile_context((int) $learner->id));
    }

    public function test_position_and_domain_are_labelled_from_the_imported_lookups(): void {
        global $DB;
        $DB->import_record('local_sentientia_users_domain', (object) ['id' => 3, 'name' => 'Engineering', 'code' => 'ENG',
            'costcenterid' => 1, 'timecreated' => 0, 'timemodified' => 0]);
        $DB->import_record('local_sentientia_users_position', (object) ['id' => 2, 'name' => 'Analyst', 'code' => 'AN1',
            'domainid' => 3, 'costcenterid' => 1, 'sortorder' => 4, 'timecreated' => 0, 'timemodified' => 0]);
        $this->assertSame('Analyst', legacy_history::position_label(2));
        $this->assertSame('Engineering', legacy_history::domain_label(3));
        $this->assertSame('', legacy_history::position_label(99), 'an id with no row has no label');
        $this->assertSame('', legacy_history::domain_label(0));

        $learner = $this->user_at('/1/5');
        $this->setAdminUser();
        \local_sentientia_platform\feature_flags::set(legacy_history::FLAG_POSITION_LABELS, 0, true);
        $context = user_manager::build_profile_context((int) $learner->id);
        $this->assertArrayHasKey('ap_position', $context);
        $this->assertSame('', $context['ap_position'], 'the user has no position id: no line is shown');
        $this->assertSame('', $context['ap_domain']);
    }

    public function test_a_viewer_from_another_tenant_cannot_reach_the_profile_that_would_show_the_transcript(): void {
        $learner = $this->user_at('/1/5');
        $other = $this->user_at('/77');
        $colleague = $this->user_at('/1');
        $this->transcript_row((int) $learner->id, 'Safety 101', 1700000000);

        $this->assertTrue(profile_access::can_view((int) $learner->id, (int) $learner->id), 'her own profile');
        $this->assertTrue(profile_access::can_view((int) $colleague->id, (int) $learner->id), 'a colleague in the same tenant');
        $this->assertFalse(profile_access::can_view((int) $other->id, (int) $learner->id),
            'another tenant never reaches the profile, so never the transcript on it');
    }
}
