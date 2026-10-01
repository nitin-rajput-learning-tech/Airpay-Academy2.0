<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_users\bizlms\users_importer;
use local_sentientia_users\tests\bizlms\stub_org_importer;
use local_sentientia_users\tests\bizlms\users_seed;

/**
 * The users import against the shape local_syncerrors is expected to have on production (ADR-032, mapping doc
 * section 10, "Verification corrections applied"): the snapshot's install file lacks four columns the writers
 * and the maps use, and the production table has them.
 *
 *   type            'Warning' or an error type: tells a warning from an error without guessing from the clock
 *   sync_file_name  'Employee' (upload or cron) or 'Service' (the HR web service): the service rows are never
 *                   matched to an upload
 *   firstname, lastname  the person on the rejected line
 *
 * The exact-shape variant, where severity is inferred and nothing can be told from the web service, is in
 * bizlms_import_test (with the importer contract).
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\bizlms\sync_index
 * @covers     \local_sentientia_users\bizlms\sync_error_step
 * @covers     \local_sentientia_users\bizlms\synthetic_run_step
 *
 * @group local_sentientia_users
 * @group bizlms_import
 */
final class bizlms_import_prodshape_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use users_seed;
    use \local_sentientia_org\test\bizlms_fixture;

    protected static function legacy_fixture_definition(): array {
        return [
            'xml' => self::users_fixture_xml(),
            'extrafields' => ['local_syncerrors' => [
                new \xmldb_field('firstname', XMLDB_TYPE_CHAR, '255', null, null, null, null),
                new \xmldb_field('lastname', XMLDB_TYPE_CHAR, '255', null, null, null, null),
                new \xmldb_field('type', XMLDB_TYPE_CHAR, '50', null, null, null, null),
                new \xmldb_field('sync_file_name', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            ]],
        ];
    }

    protected function tearDown(): void {
        registry::set_testing_importers(null);
        parent::tearDown();
    }

    /**
     * Two uploaders and five rows that exercise the four production-only columns.
     *
     *   run 1  ua, D1 10:00        run 2  admin, D1 15:00
     *   1  ua D1 09:59:50  type Warning, Employee, "Asha" "Asha"   a warning although it is not midnight
     *   2  ua D1 00:00:00  type Error, Employee, "Ravi" "Ravi"     an error although it IS midnight; too early for run 1
     *   3  admin D1 14:30  type Error, Service                      inside run 2's window, yet never matched to it
     *   4  admin D1 14:31  type Error, Employee                     attaches run 2
     *   5  admin D2 08:00  type Error, Service                      a second service day
     *
     * @return void
     */
    private function seed_prodshape(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $this->uid = ['admin' => (int) get_admin()->id, 'ua' => $this->make_user('/1/5')];
        $u = $this->uid;
        $this->put_run(1, ['usercreated' => $u['ua'], 'timecreated' => $this->at('2026-03-10 10:00:00')]);
        $this->put_run(2, ['usercreated' => $u['admin'], 'timecreated' => $this->at('2026-03-10 15:00:00')]);
        $rows = [
            [1, $u['ua'], '2026-03-10 09:59:50', 'Warning', 'Employee', 'Asha', 'Asha'],
            [2, $u['ua'], '2026-03-10 00:00:00', 'Error', 'Employee', 'Ravi', 'Ravi'],
            [3, $u['admin'], '2026-03-10 14:30:00', 'Error', 'Service', 'Meera', 'Iyer'],
            [4, $u['admin'], '2026-03-10 14:31:00', 'Error', 'Employee', 'Dev', 'Nair'],
            [5, $u['admin'], '2026-03-11 08:00:00', 'Error', 'Service', 'Zed', 'Khan'],
        ];
        foreach ($rows as [$id, $uploader, $when, $type, $file, $first, $last]) {
            $DB->import_record('local_syncerrors', (object) ['id' => $id, 'error' => 'Row problem ' . $id,
                'date_created' => $this->at($when), 'modified_by' => $uploader, 'mandatory_fields' => null,
                'email' => 'p' . $id . '@example.com', 'idnumber' => 'C' . $id, 'type' => $type,
                'sync_file_name' => $file, 'firstname' => $first, 'lastname' => $last]);
        }
    }

    public function test_the_type_column_decides_severity_and_the_preflight_says_nothing_is_inferred(): void {
        $this->resetAfterTest();
        $this->seed_prodshape();
        registry::set_testing_importers([new stub_org_importer(), new users_importer()]);
        $runner = new \local_sentientia_platform\bizlms\runner(['decisions' => $this->users_decisions()]);
        $warnings = $runner->preflight([])['preflights']['users']->warnings();
        $this->assertNotContains('severity_inferred_from_midnight', $warnings);
        $this->assertNotContains('no_sync_file_name_column', $warnings);

        [$result] = $this->users_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $errors = 'local_sentientia_users_sync_errors';
        $runs = 'local_sentientia_users_sync_runs';

        $warning = $this->target_of($errors, 'local_syncerrors', 1);
        $this->assertSame('warning', $warning->severity, 'type Warning is a warning at any time of day');
        $this->assertSame('Asha', $warning->firstname);
        $this->assertSame('Asha', $warning->lastname, 'only an error row loses a last name equal to the first');
        $run1 = $this->target_of($runs, 'local_userssyncdata', 1);
        $this->assertSame((int) $run1->id, (int) $warning->runid, 'a warning belongs to the uploader\'s first run that day');

        $error = $this->target_of($errors, 'local_syncerrors', 2);
        $this->assertSame('error', $error->severity, 'type Error is an error even at midnight (the clock is not used)');
        $this->assertSame('Ravi', $error->firstname);
        $this->assertSame('', $error->lastname, 'BizLMS wrote the first name in both fields of an error row');
        $this->assertNotSame((int) $run1->id, (int) $error->runid, 'midnight is more than an hour before the run: an orphan');
    }

    public function test_web_service_rows_are_never_matched_to_a_run_and_get_a_service_run_per_day(): void {
        $this->resetAfterTest();
        $this->seed_prodshape();
        [$result, $report] = $this->users_run(true);
        $this->assertSame(0, $result['exit']);
        $errors = 'local_sentientia_users_sync_errors';
        $runs = 'local_sentientia_users_sync_runs';
        $run2 = $this->target_of($runs, 'local_userssyncdata', 2);

        $service = '#local_syncerrors.service_day';
        $adminmin = 3;
        $d1 = $this->target_of($runs, $service, $adminmin);
        $d2 = $this->target_of($runs, $service, $adminmin, 'day:20260311');
        $this->assertSame((int) $d1->id, (int) $this->target_of($errors, 'local_syncerrors', 3)->runid,
            'a Service row inside run 2\'s window is still not run 2\'s');
        $this->assertSame((int) $d2->id, (int) $this->target_of($errors, 'local_syncerrors', 5)->runid);
        $this->assertSame((int) $run2->id, (int) $this->target_of($errors, 'local_syncerrors', 4)->runid);
        foreach ([$d1, $d2] as $synthetic) {
            $this->assertSame(0, (int) $synthetic->costcenterid, 'the web service has no tenant');
            $this->assertSame('bizlms', $synthetic->source);
            $this->assertSame(1, (int) $synthetic->errorcount);
            $this->assertSame(1, (int) $synthetic->totalrows);
            $this->assertSame($this->uid['admin'], (int) $synthetic->usercreated);
        }
        $this->assertSame($this->at('2026-03-10 14:30:00'), (int) $d1->timecreated);
        $this->assertSame($this->at('2026-03-11 08:00:00'), (int) $d2->timecreated);
        // Run 2 holds only the Employee row 4: the Service rows are not in its total.
        $this->assertSame(1, (int) $run2->totalrows);
        $this->assertSame(1, $this->step_section($report, 'users.service_runs', 'counters')['imported']);
    }

    public function test_every_source_row_has_one_primary_map_row_in_either_shape(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_prodshape();
        $this->users_run(true);
        $this->assertSame(5, $DB->count_records(legacymap::TABLE, ['sourcetable' => 'local_syncerrors', 'subkey' => '']));
        $this->assertSame(2, $DB->count_records(legacymap::TABLE, ['sourcetable' => 'local_userssyncdata', 'subkey' => '']));
        // Runs made: 2 legacy + ua orphan (D1) + admin service (D1, D2) = 5.
        $this->assertSame(5, $DB->count_records('local_sentientia_users_sync_runs'));
    }
}
