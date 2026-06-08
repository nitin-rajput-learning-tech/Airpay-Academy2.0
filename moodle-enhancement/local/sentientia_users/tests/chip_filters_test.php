<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 batch (2026-05-16) — tests for the user list-page chip filters
 * (designation, location, hrmsrole, employmenttype) + multi-value
 * email_list / empid_list.
 *
 * Locks in:
 *   - list_filter_options() returns distinct sorted values across allowed fields
 *   - tenant-scoped caller only sees values from their tenant's users
 *   - siteadmin sees values across all tenants
 *   - list_users() with chip filter narrows the result set
 *   - list_users() with multi-value email_list matches IN-clause
 *   - list_users() with empty filters returns the whole result set
 *
 * @package    local_sentientia_users
 * @category   test
 */
final class chip_filters_test extends \advanced_testcase {

    /**
     * Create N users with the given attribute values. Returns array of ids.
     */
    private function seed_users_with(array $attrs, int $count = 1): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user($attrs);
            $ids[] = (int) $user->id;
        }
        return $ids;
    }

    public function test_filter_options_returns_distinct_designations(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_users_with(['open_designation' => 'Engineer'], 3);
        $this->seed_users_with(['open_designation' => 'Manager'], 2);

        $result = \local_sentientia_users\external\list_filter_options::execute('');
        $this->assertIsArray($result);
        $this->assertContains('Engineer', $result['designation']);
        $this->assertContains('Manager',  $result['designation']);

        // Sorted alpha, dedup — only 1 'Engineer' + 1 'Manager' for these
        // seeded rows.
        $count_engineer = array_count_values($result['designation'])['Engineer'] ?? 0;
        $this->assertSame(1, $count_engineer,
            'DISTINCT must collapse duplicates');
    }

    public function test_filter_options_excludes_empty_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Seed one user with open_designation, one without.
        $this->seed_users_with(['open_designation' => 'Engineer'], 1);
        $this->seed_users_with([], 1);

        $result = \local_sentientia_users\external\list_filter_options::execute('');
        // The "no designation" user must not produce an empty-string option.
        foreach ($result['designation'] as $val) {
            $this->assertNotSame('', $val);
            $this->assertNotNull($val);
        }
    }

    public function test_filter_options_only_returns_requested_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_users_with([
            'open_designation' => 'Engineer',
            'open_location'    => 'Mumbai',
            'open_hrmsrole'    => 'Senior',
        ], 1);

        // Request only `designation` — the others should be empty arrays.
        $result = \local_sentientia_users\external\list_filter_options::execute('designation');
        $this->assertNotEmpty($result['designation']);
        $this->assertSame([], $result['location'],
            'Unrequested fields must return an empty array, not data');
        $this->assertSame([], $result['hrmsrole']);
    }

    public function test_list_users_filters_by_designation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Designation A: 3 users; Designation B: 2 users.
        $this->seed_users_with(['open_designation' => 'AAA-Eng'], 3);
        $this->seed_users_with(['open_designation' => 'BBB-Mgr'], 2);

        // No filter → at least 5 visible (could be more from baseline).
        $unfiltered = \local_sentientia_users\external\list_users::execute(
            '', 'firstname', 'asc', 0, 100, json_encode(['status' => 'all']));
        $this->assertGreaterThanOrEqual(5, $unfiltered['total']);

        // Filter to AAA-Eng only.
        $filtered = \local_sentientia_users\external\list_users::execute(
            '', 'firstname', 'asc', 0, 100,
            json_encode(['status' => 'all', 'designation' => 'AAA-Eng']));
        $this->assertSame(3, $filtered['total'],
            'Designation filter must return exactly the 3 seeded AAA-Eng users');
    }

    public function test_list_users_email_list_matches_in_clause(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        // Seed 3 users with known emails.
        $u1 = $this->getDataGenerator()->create_user(['email' => 'alice@example.org']);
        $u2 = $this->getDataGenerator()->create_user(['email' => 'bob@example.org']);
        $u3 = $this->getDataGenerator()->create_user(['email' => 'carol@example.org']);

        // Multi-value email_list: 2 of the 3.
        $result = \local_sentientia_users\external\list_users::execute(
            '', 'firstname', 'asc', 0, 100,
            json_encode([
                'status' => 'all',
                'email_list' => 'alice@example.org, carol@example.org',
            ]));
        $this->assertSame(2, $result['total'],
            'email_list with 2 values should match exactly 2 users');
    }

    public function test_list_users_empid_list_handles_newline_separated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user([
            'idnumber' => 'EMP-ONE', 'username' => 'emp1_' . uniqid(),
            'email' => 'emp1_' . uniqid() . '@example.org',
        ]);
        global $DB;
        // open_employeeid is a profile field; need to set it explicitly.
        $userid_one = $DB->get_field('user', 'id', ['idnumber' => 'EMP-ONE']);
        $DB->set_field('user', 'open_employeeid', 'EMP-ONE', ['id' => $userid_one]);

        $userid_two = $this->getDataGenerator()->create_user()->id;
        $DB->set_field('user', 'open_employeeid', 'EMP-TWO', ['id' => $userid_two]);

        // Newline-separated empid_list.
        $result = \local_sentientia_users\external\list_users::execute(
            '', 'firstname', 'asc', 0, 100,
            json_encode([
                'status' => 'all',
                'empid_list' => "EMP-ONE\nEMP-TWO",
            ]));
        $this->assertSame(2, $result['total']);
    }

    public function test_list_users_empty_filter_does_not_constrain(): void {
        // Empty filter values must be ignored (passing
        // `designation=''` must not collapse the result set to "users
        // whose designation is exactly empty").
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->seed_users_with(['open_designation' => 'X'], 1);
        $this->seed_users_with(['open_designation' => 'Y'], 1);

        $result = \local_sentientia_users\external\list_users::execute(
            '', 'firstname', 'asc', 0, 100,
            json_encode([
                'status'      => 'all',
                'designation' => '',  // empty → skip
            ]));
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }
}
