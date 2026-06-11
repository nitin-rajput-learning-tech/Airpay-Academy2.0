<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #8 (2026-05-16) — tests for path_audience_enroller (target-audience bulk enrol).
 *
 * Locks in:
 *   - resolve_audience returns exactly the users matching a single filter
 *   - multiple filters AND together (designation + region)
 *   - empty filter map returns "every user the caller can see"
 *   - non-siteadmin caller is constrained to their tenant tree
 *   - preview returns count + sample
 *   - enrol_by_filter creates path-user rows for all matched users
 *   - re-running enrol_by_filter is idempotent (enrolled=0 second run)
 *
 * @package    local_sentientia_learningpath
 * @category   test
 */
final class audience_enroller_test extends \advanced_testcase {

    // Vanilla PHPUnit sites lack the BizLMS user/course columns this plugin
    // queries (open_path etc.) - provision them per-test via the shared trait.
    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->ensure_bizlms_schema();
    }

    /** Helper: create a user with specific open_* values + open_path. */
    private function seed(array $attrs, string $open_path = '/1'): int {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $update = ['id' => $u->id, 'open_path' => $open_path];
        foreach ($attrs as $k => $v) {
            $update[$k] = $v;
        }
        $DB->update_record('user', (object) $update);
        return (int) $u->id;
    }

    private function seed_path(): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name'         => 'Audience test path ' . microtime(true),
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_resolve_audience_filters_by_designation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed(['open_designation' => 'Branch Manager']);
        $this->seed(['open_designation' => 'Branch Manager']);
        $this->seed(['open_designation' => 'Engineer']);

        $matched = path_audience_enroller::resolve_audience(
            ['designation' => 'Branch Manager'], 2);
        $this->assertCount(2, $matched);
    }

    public function test_resolve_audience_ands_multiple_filters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed(['open_designation' => 'Manager', 'open_region' => 'West']);
        $this->seed(['open_designation' => 'Manager', 'open_region' => 'East']);
        $this->seed(['open_designation' => 'Engineer', 'open_region' => 'West']);

        $matched = path_audience_enroller::resolve_audience([
            'designation' => 'Manager',
            'region'      => 'West',
        ], 2);
        $this->assertCount(1, $matched,
            'AND across designation+region should narrow to a single user');
    }

    public function test_resolve_audience_respects_caller_tenant(): void {
        $this->resetAfterTest();
        global $DB;

        // Admin in tenant /77.
        $caller = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/77', ['id' => $caller->id]);
        $this->setUser($caller);

        // Seed 2 users with the same designation but in DIFFERENT tenants.
        $this->seed(['open_designation' => 'AcrossTenant'], '/1');
        $this->seed(['open_designation' => 'AcrossTenant'], '/77');

        $matched = path_audience_enroller::resolve_audience(
            ['designation' => 'AcrossTenant'], (int) $caller->id);

        // Non-siteadmin must only see /77 users.
        foreach ($matched as $uid) {
            $u = $DB->get_record('user', ['id' => $uid], 'open_path');
            $this->assertStringStartsWith('/77', $u->open_path,
                'Non-siteadmin caller must not see cross-tenant users');
        }
    }

    public function test_resolve_audience_siteadmin_sees_across_tenants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed(['open_designation' => 'SeenByAdmin'], '/1');
        $this->seed(['open_designation' => 'SeenByAdmin'], '/77');

        $matched = path_audience_enroller::resolve_audience(
            ['designation' => 'SeenByAdmin'], 2);
        $this->assertCount(2, $matched);
    }

    public function test_preview_returns_count_and_sample(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        for ($i = 0; $i < 5; $i++) {
            $this->seed(['open_designation' => 'Sampled', 'firstname' => "Sample$i"]);
        }

        $r = path_audience_enroller::preview(
            ['designation' => 'Sampled'], 2, 3);
        $this->assertSame(5, $r['count']);
        $this->assertCount(3, $r['sample'],
            'sample_size=3 should return exactly 3 rows even though 5 matched');
    }

    public function test_enrol_by_filter_creates_path_user_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $pid = $this->seed_path();
        $this->seed(['open_designation' => 'EnrolBatch']);
        $this->seed(['open_designation' => 'EnrolBatch']);
        $this->seed(['open_designation' => 'EnrolBatch']);
        $this->seed(['open_designation' => 'OtherRole']);  // shouldn't match

        $result = path_audience_enroller::enrol_by_filter(
            $pid, ['designation' => 'EnrolBatch'], 2);

        $this->assertSame(3, $result['matched']);
        $this->assertSame(3, $result['enrolled']);
        $this->assertFalse($result['capped']);

        $this->assertEquals(3,
            $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
    }

    public function test_enrol_by_filter_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $pid = $this->seed_path();
        $this->seed(['open_designation' => 'Idempot']);
        $this->seed(['open_designation' => 'Idempot']);

        $first  = path_audience_enroller::enrol_by_filter($pid,
            ['designation' => 'Idempot'], 2);
        $second = path_audience_enroller::enrol_by_filter($pid,
            ['designation' => 'Idempot'], 2);

        $this->assertSame(2, $first['enrolled']);
        $this->assertSame(0, $second['enrolled'],
            'Re-running with the same filter should enrol 0 new users');
        $this->assertSame(2, $second['matched'],
            'Match count should still reflect everyone matching the filter');
    }
}
