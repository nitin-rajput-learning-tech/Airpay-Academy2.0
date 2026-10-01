<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * evaluation_audience_assigner: how a filter becomes a list of people, and how assigning that list behaves.
 *
 * tenant_scope_test covers the TENANT scope of resolve_audience(); this covers the filters themselves: the six
 * exact-match columns, the org path (bounded at a '/', so /1/5 is not /1/50), the cohort, who is never matched, the
 * MAX_AUDIENCE_SIZE cap, and assign_by_filter()'s count of new against already-assigned people.
 *
 * Every user is made by the data generator or cloned from one; nothing depends on ids or on data in the site.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\evaluation_audience_assigner
 *
 * @group local_sentientia_evaluation
 */
final class audience_assigner_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int The caller: a site administrator, so no tenant narrows the audience. */
    private int $caller;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->caller = (int) get_admin()->id;
    }

    /**
     * A user, with some of its open_* columns set.
     *
     * @param array<string, string> $columns column => value
     * @return int
     */
    private function user(array $columns = []): int {
        global $DB;
        $id = (int) $this->getDataGenerator()->create_user()->id;
        foreach ($columns as $column => $value) {
            $DB->set_field('user', $column, $value, ['id' => $id]);
        }
        return $id;
    }

    /**
     * @param array $filters
     * @return int[] The matched user ids, sorted.
     */
    private function resolve(array $filters): array {
        $ids = evaluation_audience_assigner::resolve_audience($filters, $this->caller);
        sort($ids);
        return $ids;
    }

    public function test_each_exact_match_filter_finds_only_its_own_column(): void {
        $columns = [
            'designation' => 'open_designation', 'region' => 'open_region', 'location' => 'open_location',
            'employmenttype' => 'open_employmenttype', 'grade' => 'open_grade', 'hrmsrole' => 'open_hrmsrole',
        ];
        $wanted = [];
        $decoys = [];
        foreach ($columns as $filter => $column) {
            $wanted[$filter] = $this->user([$column => $filter . '-A']);
            // Same column, a value that merely starts with the filter value: an exact match is not a prefix.
            $decoys[$filter] = $this->user([$column => $filter . '-A2']);
        }
        $nothing = $this->user();

        foreach ($columns as $filter => $column) {
            $this->assertSame([$wanted[$filter]], $this->resolve([$filter => $filter . '-A']), $filter);
            $this->assertSame([$decoys[$filter]], $this->resolve([$filter => $filter . '-A2']), $filter);
        }
        // No filter: everybody active (and nobody twice).
        $everyone = $this->resolve([]);
        $this->assertContains($nothing, $everyone);
        $this->assertSame(count($everyone), count(array_unique($everyone)));
    }

    public function test_filters_are_anded_and_an_empty_value_is_no_constraint(): void {
        $analystwest = $this->user(['open_designation' => 'Analyst', 'open_region' => 'West']);
        $analysteast = $this->user(['open_designation' => 'Analyst', 'open_region' => 'East']);
        $managerwest = $this->user(['open_designation' => 'Manager', 'open_region' => 'West']);

        $this->assertSame([$analystwest, $analysteast], $this->resolve(['designation' => 'Analyst']));
        $this->assertSame([$analystwest, $managerwest], $this->resolve(['region' => 'West']));
        $this->assertSame([$analystwest], $this->resolve(['designation' => 'Analyst', 'region' => 'West']));
        $this->assertSame([], $this->resolve(['designation' => 'Manager', 'region' => 'East']));
        // An empty value, or a key nobody supports, constrains nothing.
        $this->assertSame([$analystwest, $analysteast], $this->resolve(['designation' => 'Analyst', 'region' => '']));
        $this->assertSame([$analystwest, $analysteast], $this->resolve(['designation' => 'Analyst', 'nonsense' => 'x']));
    }

    public function test_org_path_matches_the_node_and_its_children_but_not_a_longer_id(): void {
        $node = $this->user(['open_path' => '/1/5']);
        $child = $this->user(['open_path' => '/1/5/9']);
        $this->user(['open_path' => '/1/50']);     // shares the digits, not the node
        $this->user(['open_path' => '/1/6']);
        $this->user(['open_path' => '/15']);
        $this->user(['open_path' => '/2/1/5']);   // contains the path, does not start with it

        $this->assertSame([$node, $child], $this->resolve(['org_path' => '/1/5']));
        // The path is normalised: no leading slash, a trailing one.
        $this->assertSame([$node, $child], $this->resolve(['org_path' => '1/5/']));
        // A leaf matches itself only.
        $this->assertSame([$child], $this->resolve(['org_path' => '/1/5/9']));
    }

    public function test_cohort_filter_matches_members_only(): void {
        $generator = $this->getDataGenerator();
        $member = $this->user();
        $other = $this->user();
        $cohort = $generator->create_cohort();
        $generator->create_cohort_member(['cohortid' => $cohort->id, 'userid' => $member]);
        $otherCohort = $generator->create_cohort();
        $generator->create_cohort_member(['cohortid' => $otherCohort->id, 'userid' => $other]);

        $this->assertSame([$member], $this->resolve(['cohortid' => (int) $cohort->id]));
        // A cohort id that does not exist matches nobody; 0 is no constraint.
        $this->assertSame([], $this->resolve(['cohortid' => 987654321]));
        $this->assertContains($other, $this->resolve(['cohortid' => 0]));
    }

    public function test_suspended_and_deleted_users_and_the_built_in_accounts_are_never_matched(): void {
        global $DB;
        $active = $this->user(['open_designation' => 'Tag']);
        $suspended = $this->user(['open_designation' => 'Tag']);
        $deleted = $this->user(['open_designation' => 'Tag']);
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended]);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted]);

        $this->assertSame([$active], $this->resolve(['designation' => 'Tag']));
        $everyone = $this->resolve([]);
        $this->assertNotContains($this->caller, $everyone, 'the site administrator is not an audience');
        $this->assertNotContains(1, $everyone, 'nor is the guest');
    }

    public function test_the_audience_is_capped(): void {
        global $DB;
        $cap = evaluation_audience_assigner::MAX_AUDIENCE_SIZE;
        // Clone a real user row so every required column is there; only the identity and the tag change.
        $template = $DB->get_record('user', ['id' => $this->user()], '*', MUST_EXIST);
        $records = [];
        for ($i = 0; $i < $cap + 1; $i++) {
            $record = clone $template;
            unset($record->id);
            $record->username = 'captest' . $i;
            $record->email = 'captest' . $i . '@example.invalid';
            $record->open_designation = 'cap-test';
            $records[] = $record;
        }
        $DB->insert_records('user', $records);

        $this->assertCount($cap, $this->resolve(['designation' => 'cap-test']), 'one more matches than the cap allows');
        $preview = evaluation_audience_assigner::preview(['designation' => 'cap-test'], $this->caller);
        $this->assertSame($cap, $preview['count']);
        $this->assertSame($cap, $preview['capped_at']);
        $this->assertCount(10, $preview['sample']);
    }

    public function test_assign_by_filter_assigns_each_person_once(): void {
        global $DB;
        $evaluationid = evaluation_manager::create((object) ['name' => 'Assigned by audience']);
        $a = $this->user(['open_designation' => 'Auditor']);
        $b = $this->user(['open_designation' => 'Auditor']);
        $c = $this->user(['open_designation' => 'Auditor']);
        $this->user(['open_designation' => 'Somebody else']);
        $due = time() + 86400;

        $first = evaluation_audience_assigner::assign_by_filter($evaluationid, ['designation' => 'Auditor'], $this->caller, $due);
        $this->assertSame(['matched' => 3, 'assigned' => 3, 'capped' => false], $first);
        $rows = $DB->get_records('local_sentientia_evaluation_assign', ['evaluationid' => $evaluationid], 'userid ASC');
        $this->assertSame([$a, $b, $c], array_map('intval', array_column($rows, 'userid')));
        foreach ($rows as $row) {
            $this->assertSame('manual', $row->trigger_event);
            $this->assertSame(0, (int) $row->source_id);
            $this->assertSame('assigned', $row->status);
            $this->assertSame($this->caller, (int) $row->assigned_by_userid, 'the caller is on the audit trail');
            $this->assertSame($due, (int) $row->due_at);
        }

        // Again: everybody matches, nobody is new, nothing is inserted.
        $second = evaluation_audience_assigner::assign_by_filter($evaluationid, ['designation' => 'Auditor'], $this->caller);
        $this->assertSame(['matched' => 3, 'assigned' => 0, 'capped' => false], $second);
        $this->assertSame(3, $DB->count_records('local_sentientia_evaluation_assign', ['evaluationid' => $evaluationid]));

        // A person who joins the audience later is the only new row.
        $d = $this->user(['open_designation' => 'Auditor']);
        $third = evaluation_audience_assigner::assign_by_filter($evaluationid, ['designation' => 'Auditor'], $this->caller);
        $this->assertSame(['matched' => 4, 'assigned' => 1, 'capped' => false], $third);
        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_assign',
            ['evaluationid' => $evaluationid, 'userid' => $d]));

        // Nobody matches: nothing happens, and it says so.
        $none = evaluation_audience_assigner::assign_by_filter($evaluationid, ['designation' => 'Nobody holds this'], $this->caller);
        $this->assertSame(['matched' => 0, 'assigned' => 0, 'capped' => false], $none);
    }

    public function test_assign_by_filter_refuses_an_evaluation_that_does_not_exist(): void {
        $this->expectException(\dml_missing_record_exception::class);
        evaluation_audience_assigner::assign_by_filter(987654321, [], $this->caller);
    }
}
