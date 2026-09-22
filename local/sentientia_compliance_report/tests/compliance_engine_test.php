<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_compliance_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for compliance_engine.
 *
 * Locks in:
 * - F5 (commit 3f0142320): get_compliance_matrix safely handles users
 *   whose snapshot row is missing for a mandatory course. Previously
 *   the code dereferenced bool false → PHP warning + 4 per page-load.
 * - Cross-tenant LIKE fix (commit ac22501e8): orgpath filtering uses
 *   exact match OR /-bounded prefix; '/1' must not match '/100' or '/177'.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 *
 * @group tenant_isolation
 */
final class compliance_engine_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * Place a user at a path with optional employee_id.
     */
    private function user_at_path(string $path, string $empid = '', string $designation = ''): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        if ($empid) {
            $DB->set_field('user', 'open_employeeid', $empid, ['id' => $u->id]);
        }
        if ($designation) {
            $DB->set_field('user', 'open_designation', $designation, ['id' => $u->id]);
        }
        $u->open_path = $path;
        return $u;
    }

    /**
     * Insert a row into local_compliance_courses (mandatory course list).
     */
    private function add_mandatory_course(int $courseid, string $name, int $sort = 1, int $costcenterid = 0): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_compliance_courses')) {
            $this->markTestSkipped('local_compliance_courses table not present.');
        }
        $DB->insert_record_raw('local_compliance_courses', (object)[
            'courseid' => $courseid,
            'coursename' => $name,
            'costcenterid' => $costcenterid,
            'is_active' => 1,
            'sort_order' => $sort,
            'timecreated' => time(),
            'timemodified' => time(),
        ], false, false, false);
    }

    /**
     * Insert a snapshot row for a (user, course) pair.
     */
    private function add_snapshot(int $userid, int $courseid, string $deptpath,
                                  string $status = 'open', ?int $deadline = null): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_compliance_snapshot')) {
            $this->markTestSkipped('local_compliance_snapshot table not present.');
        }
        $DB->insert_record_raw('local_compliance_snapshot', (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'department_path' => $deptpath,
            'status' => $status,
            'progress_percent' => 0,
            'days_overdue' => 0,
            'deadline_date' => $deadline,
            'matched_by' => 'userid',
            'snapshot_date' => time(),
        ], false, false, false);
    }

    /**
     * F5 REGRESSION: matrix with a user missing a snapshot row for one of
     * the mandatory courses must NOT emit a PHP warning.
     */
    public function test_matrix_handles_missing_snapshot_without_warning(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // Create 2 mandatory courses but only seed snapshot for course 1.
        // Course 2 will have $snap === false in the matrix loop (the F5 path).
        $u = $this->user_at_path('/1/2/3', 'EMP001', 'Analyst');
        $this->add_mandatory_course(1001, 'POSH', 1);
        $this->add_mandatory_course(1002, 'InfoSec', 2);
        $this->add_snapshot($u->id, 1001, '/1/2/3', 'completed');
        // NO snapshot for ($u->id, 1002) — this is what triggered F5.

        // Capture PHP errors during the call.
        $errors = [];
        set_error_handler(function ($severity, $message, $file, $line) use (&$errors) {
            if (str_contains($message, 'deadline_date') || str_contains($message, 'days_overdue')) {
                $errors[] = "$message at $file:$line";
            }
            return false;
        });

        try {
            $matrix = compliance_engine::get_compliance_matrix('/1/2/3');
        } finally {
            restore_error_handler();
        }

        $this->assertEmpty($errors,
            'F5 regression: matrix dereferenced \$snap on a missing-row case. Errors: '
            . implode("\n", $errors));

        // Sanity: the user appears with both courses, second one as 'not_enrolled'.
        $this->assertCount(1, $matrix['rows']);
        $userrow = $matrix['rows'][0];
        $this->assertCount(2, $userrow['courses']);
        $statuses = array_column($userrow['courses'], 'status');
        $this->assertContains('completed', $statuses);
        $this->assertContains('not_enrolled', $statuses);
    }

    /**
     * Cross-tenant LIKE: orgpath '/1' must NOT match users whose
     * department_path is '/100' or '/177'.
     */
    public function test_matrix_does_not_leak_across_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u1   = $this->user_at_path('/1', 'A1');
        $u100 = $this->user_at_path('/100', 'A100');
        $u177 = $this->user_at_path('/177', 'A177');

        $this->add_mandatory_course(2001, 'GDPR', 1);
        $this->add_snapshot($u1->id,   2001, '/1',   'completed');
        $this->add_snapshot($u100->id, 2001, '/100', 'completed');
        $this->add_snapshot($u177->id, 2001, '/177', 'completed');

        $matrix = compliance_engine::get_compliance_matrix('/1');

        $this->assertSame(1, $matrix['total'],
            'orgpath=/1 leaked rows from /100 or /177 (pre-fix LIKE /1% bug)');
        $this->assertCount(1, $matrix['rows']);
        $this->assertSame((int)$u1->id, (int)$matrix['rows'][0]['userid']);
    }

    /**
     * orgpath '/1/2' should match users at '/1/2', '/1/2/3', '/1/2/anything'
     * but NOT '/1/20' or '/1/2x'.
     */
    public function test_matrix_prefix_boundary_strict(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u_in1 = $this->user_at_path('/1/2');     // exact match
        $u_in2 = $this->user_at_path('/1/2/3');   // descendant
        $u_out = $this->user_at_path('/1/20');    // sibling — not a descendant of /1/2

        $this->add_mandatory_course(3001, 'Compliance', 1);
        foreach ([$u_in1, $u_in2, $u_out] as $u) {
            $this->add_snapshot($u->id, 3001, $u->open_path, 'completed');
        }

        $matrix = compliance_engine::get_compliance_matrix('/1/2');

        $this->assertSame(2, $matrix['total']);
        $userids = array_map('intval', array_column($matrix['rows'], 'userid'));
        sort($userids);
        $expected = [(int)$u_in1->id, (int)$u_in2->id];
        sort($expected);
        $this->assertSame($expected, $userids,
            '/-boundary prefix should match /1/2 + /1/2/3 but not /1/20');
    }

    /**
     * F5 deadline: snapshot WITH a deadline_date renders correctly
     * (regression: should still work, not broken by the fix).
     */
    public function test_matrix_preserves_deadline_when_snapshot_present(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path('/1', 'EMP001');
        $this->add_mandatory_course(4001, 'Annual Refresher', 1);

        $deadline = strtotime('+30 days');
        $this->add_snapshot($u->id, 4001, '/1', 'open', $deadline);

        $matrix = compliance_engine::get_compliance_matrix('/1');

        $this->assertSame(1, $matrix['total']);
        $course = $matrix['rows'][0]['courses'][0];
        $this->assertSame('open', $course['status']);
        $this->assertNotEmpty($course['deadline'],
            'snapshot with deadline_date should render formatted date');
    }

    /**
     * 1.0.2 (UAT 2026-09-16): course columns follow the scope's tenant —
     * global courses (costcenterid 0) plus the tenant's own pinned courses.
     * Another tenant's mandatory course must NOT appear as an empty
     * "Not Enrolled" column (ZEEA's Tanzania course showed up for Airpay).
     */
    public function test_matrix_columns_are_tenant_scoped(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u1 = $this->user_at_path('/1', 'A1');
        $this->add_mandatory_course(5001, 'Global policy', 1, 0);
        $this->add_mandatory_course(5002, 'Airpay POSH', 2, 1);
        $this->add_mandatory_course(5003, 'ZEEA conduct (TZ)', 3, 177);
        $this->add_snapshot($u1->id, 5001, '/1', 'completed');

        $scoped = compliance_engine::get_compliance_matrix('/1');
        $this->assertSame([5001, 5002],
            array_map('intval', array_column($scoped['courses'], 'courseid')),
            'Airpay scope must list global + Airpay-pinned courses only');
        $this->assertCount(2, $scoped['rows'][0]['courses']);

        $zeea = compliance_engine::get_compliance_matrix('/177');
        $this->assertSame([5001, 5003],
            array_map('intval', array_column($zeea['courses'], 'courseid')));

        $global = compliance_engine::get_compliance_matrix('');
        $this->assertSame([5001, 5002, 5003],
            array_map('intval', array_column($global['courses'], 'courseid')),
            'site admins (empty scope) keep every active course');
    }

    /**
     * 1.0.2: tenant id parsing + scope-aware course list on their own.
     */
    public function test_tenant_id_from_path(): void {
        $this->assertSame(0, compliance_engine::tenant_id_from_path(''));
        $this->assertSame(1, compliance_engine::tenant_id_from_path('/1'));
        $this->assertSame(1, compliance_engine::tenant_id_from_path('/1/2/3'));
        $this->assertSame(177, compliance_engine::tenant_id_from_path('/177/178'));
    }

    /**
     * 1.0.2: a scoped admin's ?bu= drill-down is clamped to their own tenant;
     * a foreign BU resets the whole drill-down. Site admins are untouched.
     */
    public function test_clamp_filter_to_tenant(): void {
        // Own tenant — kept, including deeper levels.
        $this->assertSame([1, 5, 9], compliance_engine::clamp_filter_to_tenant('/1', 1, 5, 9));
        // No BU chosen — kept.
        $this->assertSame([0, 0, 0], compliance_engine::clamp_filter_to_tenant('/1', 0, 0, 0));
        // Foreign BU (hand-edited URL) — reset to "all of my tenant".
        $this->assertSame([0, 0, 0], compliance_engine::clamp_filter_to_tenant('/1', 177, 178, 0));
        // Prefix look-alike is still foreign.
        $this->assertSame([0, 0, 0], compliance_engine::clamp_filter_to_tenant('/1', 10, 0, 0));
        // Site admin — anything goes.
        $this->assertSame([177, 178, 0], compliance_engine::clamp_filter_to_tenant('', 177, 178, 0));
    }

    /**
     * 1.0.2: status labels come from the lang pack (Hindi parity), unknown
     * statuses fall back to ucfirst.
     */
    public function test_status_label_is_localised(): void {
        $this->assertSame(get_string('status_completed', 'local_sentientia_compliance_report'),
            compliance_engine::status_label('completed'));
        $this->assertSame(get_string('status_not_enrolled', 'local_sentientia_compliance_report'),
            compliance_engine::status_label('not_enrolled'));
        $this->assertSame('Open', compliance_engine::status_label('open'));
    }

    /**
     * Insert a root org row with a fixed id (fills every NOT NULL column the
     * schema may carry so the test survives install.xml changes).
     */
    private function add_root_org(int $id, string $name): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_org')) {
            $this->markTestSkipped('local_sentientia_org table not present.');
        }
        $rec = ['id' => $id, 'fullname' => $name, 'shortname' => 'ORG' . $id,
                'parentid' => 0, 'path' => '/' . $id, 'depth' => 1, 'visible' => 1];
        foreach ($DB->get_columns('local_sentientia_org') as $col) {
            if (isset($rec[$col->name]) || !$col->not_null || $col->has_default) {
                continue;
            }
            $rec[$col->name] = ($col->meta_type === 'C' || $col->meta_type === 'X') ? '' : 0;
        }
        $DB->insert_record_raw('local_sentientia_org', (object) $rec, true, false, true);
    }

    /**
     * 1.0.2 (UAT 2026-09-16): the Business Unit list for a scoped admin is
     * /-bounded. `LIKE '/1%'` also matched /10 and /177, which is how ZEEA
     * appeared in the Airpay admin's filter (and its people in the counts).
     */
    public function test_hierarchy_level_one_is_slash_bounded(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->add_root_org(1, 'Airpay');
        $this->add_root_org(10, 'Ten');
        $this->add_root_org(177, 'ZEEA');
        $this->user_at_path('/1');
        $this->user_at_path('/1/2');
        $this->user_at_path('/10/3');
        $this->user_at_path('/177/178');

        $scoped = compliance_engine::get_org_hierarchy_level(1, '/1');
        $this->assertSame([1], array_column($scoped, 'id'),
            'scope /1 must list only BU 1 (not /10 or /177)');
        $this->assertSame(2, $scoped[0]['user_count']);

        $all = compliance_engine::get_org_hierarchy_level(1, '');
        $ids = array_column($all, 'id');
        sort($ids);
        $this->assertSame([1, 10, 177], $ids, 'site admins (empty scope) see every BU');
    }
}
