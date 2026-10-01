<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_users\bizlms\users_importer;

/**
 * The seed and the helpers the users import tests share (ADR-032, mapping doc section 10, "Fixture").
 *
 * A test class that uses this trait also uses \local_sentientia_org\test\bizlms_fixture (the open_* columns of
 * {user}) and \local_sentientia_platform\phpunit\legacy_schema_fixture (the BizLMS tables).
 *
 * THE WORLD
 *
 *   users      admin (site admin, id 2); ua (tenant /1/5, uploads HRMS files); ub (/77); la (/1/5, employee id E1);
 *              dup and dupgone (both idnumber DUP, the second one deleted); ghost (987654, has no row)
 *   days       D1 = 2026-03-10, D2 = 2026-03-11, D3 = 2026-03-12, in Moodle's server timezone
 *
 *   local_userssyncdata (runs)
 *     1  by ua, D1 10:00 (modified 10:05), 5 new, 3 updated, 2 errors, 1+2 warnings, legacy costcenterid 77
 *     2  by admin, D1 15:00, every counter NULL, no modified time, legacy costcenterid 1
 *     3  by ua, D2 09:00
 *     4  no uploader, no times, 5 000 000 000 new users (more than an INT holds)
 *     5  by dup, D2 12:00
 *
 *   local_syncerrors (rows; "attach" = the run it is matched to)
 *     1   ua D1 09:59:50  error    attaches run 1            e-mail a@x.com, code E100
 *     2   ua D1 00:00:00  warning  attaches run 1 (first run that day)
 *     3   ua D1 07:00:00  error    too early for run 1: orphan run (ua, D1)
 *     4   ua D1 07:30:00  error    orphan run (ua, D1)
 *     5   admin D1 14:30  error    attaches run 2
 *     6   ub D1 12:00     error    ub has no run: orphan run (ub, D1)
 *     7   ua D3 00:00:00  warning  ua has no run on D3: orphan run (ua, D3)
 *     8   no uploader, no time     orphan run (null uploader, "none")
 *     9   admin D1 03:00  error    orphan run (admin, D1), tenant 0
 *     10  ua D1 09:59:55  error    attaches run 1; no e-mail; a 150-character code
 *     11  dup D2 11:59    error    attaches run 5 (so dup has no orphan run)
 *
 *   local_transcript_history
 *     1  no user, employee id E1                        -> la; "Completed", 15/06/2016, 85%, 1:30, course 999999
 *     2  no user, employee id DUP                       -> dup (the deleted twin is ignored); "in progress"
 *     3  la, wrong employee id; "Weird", 31/02/2016, "abc" hours
 *     4  no user, employee id NOBODY; no title, no status, Excel serial date 42536
 *     5  no user, employee id " e1 " (padded, other case); a real course; 15-Jun-2016
 *     6  dupgone (a deleted account)
 *
 *   local_uniquelogins: la D1 twice (1, 2), la D2 with no type or time (3), admin D1 three times (4, 5, 6),
 *     no user (7), la with no day (8), ghost D1 (9)
 *   local_domains 3 and 7 (no time columns); local_positions 2, 5 (name 300 characters) and 9
 *   local_userdata: la '/1/5' (agrees), ua '/77' (disagrees), no user
 *
 * @package    local_sentientia_users
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait users_seed {

    /** @var array<string, int> The seed's users by name. */
    protected array $uid = [];

    /**
     * The BizLMS tables as install.xml gives them.
     *
     * @return string
     */
    protected static function users_fixture_xml(): string {
        return __DIR__ . '/../../fixtures/bizlms/users.install.xml';
    }

    /**
     * A timestamp in Moodle's server timezone.
     *
     * @param string $datetime 'Y-m-d H:i:s'
     * @return int
     */
    protected function at(string $datetime): int {
        return (new \DateTimeImmutable($datetime, \core_date::get_server_timezone_object()))->getTimestamp();
    }

    /**
     * A user with the open_path the test gives them.
     *
     * @param string|null $path
     * @param array<string, mixed> $record Extra user fields.
     * @param bool $deleted
     * @return int
     */
    protected function make_user(?string $path, array $record = [], bool $deleted = false): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user($record);
        if ($path !== null) {
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        }
        if ($deleted) {
            $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
        }
        return (int) $user->id;
    }

    /**
     * @param string $table
     * @param int $id
     * @param array<string, mixed> $values Overrides of the defaults (every column NULL).
     * @param string[] $columns The table's columns (without id).
     * @return void
     */
    private function put_row(string $table, int $id, array $values, array $columns): void {
        global $DB;
        $row = ['id' => $id];
        foreach ($columns as $column) {
            $row[$column] = null;
        }
        $DB->import_record($table, (object) ($values + $row));
    }

    protected function put_run(int $id, array $values): void {
        $this->put_row('local_userssyncdata', $id, $values, ['newuserscount', 'updateduserscount', 'errorscount',
            'warningscount', 'supervisorwarningscount', 'usercreated', 'timecreated', 'usermodified', 'timemodified',
            'costcenterid']);
    }

    protected function put_error(int $id, array $values): void {
        $this->put_row('local_syncerrors', $id, $values, ['error', 'date_created', 'modified_by', 'mandatory_fields',
            'email', 'idnumber']);
    }

    protected function put_transcript(int $id, array $values): void {
        $this->put_row('local_transcript_history', $id, $values, ['employee_id', 'fullname', 'training_title',
            'completion_date', 'status', 'training_type', 'transcript_score', 'training_hours', 'training_object_id',
            'training_location', 'courseid', 'userid', 'usercreated', 'usermodified', 'timemodified', 'timecreated']);
    }

    protected function put_login(int $id, array $values): void {
        $this->put_row('local_uniquelogins', $id, $values + ['userid' => 0, 'day' => 0, 'month' => 0, 'year' => 0,
            'count_date' => 0, 'timemodified' => 0], ['type']);
    }

    protected function put_domain(int $id, array $values): void {
        $this->put_row('local_domains', $id, $values, ['name', 'code', 'costcenter']);
    }

    protected function put_position(int $id, array $values): void {
        $this->put_row('local_positions', $id, $values, ['name', 'code', 'domain', 'costcenter', 'sortorder',
            'usercreated', 'timecreated', 'usermodified', 'timemodified']);
    }

    protected function put_userdata(int $id, array $values): void {
        $this->put_row('local_userdata', $id, $values, ['userid', 'costcenterpath', 'categorypath', 'usercreated',
            'timecreated', 'usermodified', 'timemodified']);
    }

    /**
     * Create the seed's users and fill every legacy table (see the description of the trait).
     *
     * @return void
     */
    protected function seed_users_world(): void {
        $this->ensure_bizlms_schema();
        $admin = (int) get_admin()->id;
        $this->uid = [
            'admin' => $admin,
            'ua' => $this->make_user('/1/5'),
            'ub' => $this->make_user('/77'),
            'la' => $this->make_user('/1/5', ['open_employeeid' => 'E1', 'firstname' => 'Asha', 'lastname' => 'Rao',
                'email' => 'asha.rao@example.com']),
            'dup' => $this->make_user('/1', ['idnumber' => 'DUP']),
            'dupgone' => $this->make_user('/1', ['idnumber' => 'DUP'], true),
            'ghost' => 987654,
        ];
        $u = $this->uid;
        $d1 = '2026-03-10';
        $d2 = '2026-03-11';
        $d3 = '2026-03-12';

        $this->put_run(1, ['newuserscount' => 5, 'updateduserscount' => 3, 'errorscount' => 2, 'warningscount' => 1,
            'supervisorwarningscount' => 2, 'usercreated' => $u['ua'], 'usermodified' => $u['ua'],
            'timecreated' => $this->at("$d1 10:00:00"), 'timemodified' => $this->at("$d1 10:05:00"),
            'costcenterid' => 77]);
        $this->put_run(2, ['usercreated' => $u['admin'], 'timecreated' => $this->at("$d1 15:00:00"),
            'costcenterid' => 1]);
        $this->put_run(3, ['newuserscount' => 1, 'updateduserscount' => 0, 'usercreated' => $u['ua'],
            'timecreated' => $this->at("$d2 09:00:00"), 'timemodified' => $this->at("$d2 09:01:00")]);
        $this->put_run(4, ['newuserscount' => 5000000000]);
        $this->put_run(5, ['usercreated' => $u['dup'], 'timecreated' => $this->at("$d2 12:00:00")]);

        $this->put_error(1, ['error' => 'Invalid designation,Missing manager', 'date_created' => $this->at("$d1 09:59:50"),
            'modified_by' => $u['ua'], 'mandatory_fields' => 'designation', 'email' => 'a@x.com', 'idnumber' => 'E100']);
        $this->put_error(2, ['error' => 'Supervisor not found', 'date_created' => $this->at("$d1 00:00:00"),
            'modified_by' => $u['ua'], 'email' => 'b@x.com', 'idnumber' => 'E101']);
        $this->put_error(3, ['error' => 'Email invalid', 'date_created' => $this->at("$d1 07:00:00"),
            'modified_by' => $u['ua'], 'email' => 'c@x.com', 'idnumber' => 'E102']);
        $this->put_error(4, ['error' => 'Email invalid', 'date_created' => $this->at("$d1 07:30:00"),
            'modified_by' => $u['ua'], 'email' => 'd@x.com', 'idnumber' => 'E103']);
        $this->put_error(5, ['error' => 'Department missing', 'date_created' => $this->at("$d1 14:30:00"),
            'modified_by' => $u['admin'], 'email' => 'e@x.com', 'idnumber' => 'E104']);
        $this->put_error(6, ['error' => 'Grade missing', 'date_created' => $this->at("$d1 12:00:00"),
            'modified_by' => $u['ub'], 'email' => 'f@x.com', 'idnumber' => 'E105']);
        $this->put_error(7, ['error' => 'Supervisor not found', 'date_created' => $this->at("$d3 00:00:00"),
            'modified_by' => $u['ua'], 'email' => 'g@x.com', 'idnumber' => 'E106']);
        $this->put_error(8, ['error' => 'Unknown']);
        $this->put_error(9, ['error' => 'Location missing', 'date_created' => $this->at("$d1 03:00:00"),
            'modified_by' => $u['admin'], 'email' => 'h@x.com', 'idnumber' => 'E107']);
        $this->put_error(10, ['error' => 'Code too long', 'date_created' => $this->at("$d1 09:59:55"),
            'modified_by' => $u['ua'], 'idnumber' => str_repeat('9', 150)]);
        $this->put_error(11, ['error' => 'Email invalid', 'date_created' => $this->at("$d2 11:59:00"),
            'modified_by' => $u['dup'], 'email' => 'i@x.com', 'idnumber' => 'E108']);

        $t = $this->at("$d1 08:00:00");
        $this->put_transcript(1, ['employee_id' => 'E1', 'fullname' => 'Asha Rao', 'training_title' => 'Safety 101',
            'completion_date' => '15/06/2016', 'status' => 'Completed', 'training_type' => 'Classroom',
            'transcript_score' => '85%', 'training_hours' => '1:30', 'training_object_id' => 'OBJ-1',
            'training_location' => 'Mumbai', 'courseid' => 999999, 'usercreated' => $u['admin'], 'timecreated' => $t]);
        $this->put_transcript(2, ['employee_id' => 'DUP', 'fullname' => 'D Uplicate', 'training_title' => 'Fire drill',
            'completion_date' => '2016-07-01', 'status' => 'in progress', 'transcript_score' => 'n/a',
            'training_hours' => '2.5', 'timecreated' => $t]);
        $this->put_transcript(3, ['employee_id' => 'ZZ', 'fullname' => 'Asha Rao', 'training_title' => 'Ethics',
            'completion_date' => '31/02/2016', 'status' => 'Weird', 'training_hours' => 'abc', 'userid' => $u['la'],
            'timecreated' => $t]);
        $this->put_transcript(4, ['employee_id' => 'NOBODY', 'fullname' => 'N Obody', 'completion_date' => '42536',
            'timecreated' => $t]);
        $this->put_transcript(5, ['employee_id' => ' e1 ', 'training_title' => 'Onboarding',
            'completion_date' => '15-Jun-2016', 'status' => 'PASSED',
            'courseid' => (int) $this->getDataGenerator()->create_course()->id, 'timecreated' => $t]);
        $this->put_transcript(6, ['employee_id' => 'GONE', 'fullname' => 'GOne', 'training_title' => 'Old course',
            'userid' => $u['dupgone'], 'timecreated' => $t]);

        $day1 = $this->at("$d1 00:00:00");
        $day2 = $this->at("$d2 00:00:00");
        $this->put_login(1, ['userid' => $u['la'], 'count_date' => $day1, 'timemodified' => $this->at("$d1 08:00:00"),
            'type' => 'web']);
        $this->put_login(2, ['userid' => $u['la'], 'count_date' => $day1, 'timemodified' => $this->at("$d1 17:00:00"),
            'type' => 'web']);
        $this->put_login(3, ['userid' => $u['la'], 'count_date' => $day2, 'timemodified' => 0]);
        foreach ([4, 5, 6] as $id) {
            $this->put_login($id, ['userid' => $u['admin'], 'count_date' => $day1,
                'timemodified' => $this->at("$d1 0$id:00:00"), 'type' => 'web']);
        }
        $this->put_login(7, ['userid' => null, 'count_date' => $day1, 'type' => 'web']);
        $this->put_login(8, ['userid' => $u['la'], 'count_date' => null, 'type' => 'web']);
        $this->put_login(9, ['userid' => $u['ghost'], 'count_date' => $day1, 'timemodified' => $this->at("$d1 09:00:00"),
            'type' => 'web']);

        $this->put_domain(3, ['name' => 'Engineering', 'code' => 'ENG', 'costcenter' => 1]);
        $this->put_domain(7, ['name' => 'Finance', 'code' => 'FIN', 'costcenter' => 1]);
        $this->put_position(2, ['name' => 'Analyst', 'code' => 'AN1', 'domain' => 3, 'costcenter' => 1, 'sortorder' => 4,
            'timecreated' => $t, 'timemodified' => null]);
        $this->put_position(5, ['name' => str_repeat('P', 300), 'code' => 'LONG', 'domain' => 7, 'costcenter' => 1,
            'sortorder' => 1, 'timecreated' => $t, 'timemodified' => $t + 10]);
        $this->put_position(9, ['name' => 'Lead', 'code' => 'LD1', 'domain' => 3, 'costcenter' => 77, 'sortorder' => 9]);

        $this->put_userdata(1, ['userid' => $u['la'], 'costcenterpath' => '/1/5', 'timecreated' => $t]);
        $this->put_userdata(2, ['userid' => $u['ua'], 'costcenterpath' => '/77', 'timecreated' => $t]);
        $this->put_userdata(3, ['userid' => null, 'costcenterpath' => null]);
    }

    /**
     * The owner's choices as the signed file records them (all accepted).
     *
     * @param array<string, mixed> $override Replaces a value, for the tests of a different choice.
     * @return decisions
     */
    protected function users_decisions(array $override = []): decisions {
        return decisions::from_array($override + [
            'tenant.unresolved.users' => 'pathless',
            'users.admin_runs_tenant' => 'zero',
            'users.transcript_status_map' => [
                'normalise' => 'lower(trim(status_raw))',
                'completed' => ['completed', 'complete', 'passed', 'pass', 'attended', 'yes'],
                'inprogress' => ['in progress', 'inprogress', 'started', 'registered', 'enrolled'],
                'failed' => ['failed', 'fail', 'not passed'],
                'notstarted' => ['not started', 'pending', 'assigned'],
                'cancelled' => ['cancelled', 'withdrawn', 'no show', 'absent'],
                'otherwise' => 'unknown',
            ],
            'users.uniquelogins' => 'import',
            'users.transcript_counts_toward_totals' => false,
            'users.erasure_treatment' => 'anonymise',
            'users.userdata_reconciliation' => 'user_open_path_authoritative',
            'users.positions_domains_lookup_import' => true,
        ]);
    }

    /**
     * Run the importer (with the stub org importer registered next to it) and return the result and the report.
     *
     * @param bool $apply False for a dry run.
     * @param decisions|null $decisions
     * @param array $options Runner option overrides.
     * @return array{0: array, 1: report}
     */
    protected function users_run(bool $apply, ?decisions $decisions = null, array $options = []): array {
        registry::set_testing_importers([new stub_org_importer(), new users_importer()]);
        $report = new report();
        $runner = new runner($options + ($apply ? ['permit' => guard::test_permit()] : []) + [
            'apply' => $apply,
            'decisions' => $decisions ?? $this->users_decisions(),
            'report' => $report,
            'batch' => 2,
            'atomic_threshold' => 0,
        ]);
        return [$runner->run([]), $report];
    }

    /**
     * The row the importer made for a source row (a primary map row).
     *
     * @param string $table Target table.
     * @param string $source Source table (or derived source name).
     * @param int $sourceid
     * @param string $subkey
     * @return \stdClass
     */
    protected function target_of(string $table, string $source, int $sourceid, string $subkey = ''): \stdClass {
        global $DB;
        $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => $source, 'sourceid' => $sourceid, 'subkey' => $subkey],
            '*', MUST_EXIST);
        return $DB->get_record($table, ['id' => $map->targetid], '*', MUST_EXIST);
    }

    /**
     * One section of a step in a report.
     *
     * @param report $report
     * @param string $step Step key.
     * @param string $section warnings, skipped_by_reason, tenant_methods or counters.
     * @return array
     */
    protected function step_section(report $report, string $step, string $section): array {
        return $report->to_array()['features']['users']['steps'][$step][$section] ?? [];
    }
}
