<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_users\bizlms\users_importer;
use local_sentientia_users\tests\bizlms\stub_org_importer;
use local_sentientia_users\tests\bizlms\users_seed;

/**
 * The users feature of the BizLMS import (ADR-032, mapping doc section 10): the HRMS sync runs and errors, the
 * earlier training records, the login days and the position and domain lookups of BizLMS's local_users plugin.
 *
 * The importer contract (tests/bizlms of local_sentientia_platform, "Test approach" 4) runs against the seed in
 * tests/classes/bizlms/users_seed.php, which has every kind of row the feature can meet. The tests after it check
 * the column maps, the run matching, the tenant attribution, the owner's decisions, the reasons, verify,
 * preflight, that the legacy tables and {user} are never written, and that the lookups keep their ids.
 *
 * The org feature is registered as a stub (tests/classes/bizlms/stub_org_importer.php): the real org importer is
 * another plugin's, and the registry refuses a tenant importer that does not depend on it.
 *
 * The production-shaped variant of local_syncerrors (type, sync_file_name, firstname, lastname) is in
 * bizlms_import_prodshape_test.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\bizlms\users_importer
 * @covers     \local_sentientia_users\bizlms\sync_index
 * @covers     \local_sentientia_users\bizlms\sync_run_step
 * @covers     \local_sentientia_users\bizlms\synthetic_run_step
 * @covers     \local_sentientia_users\bizlms\sync_error_step
 * @covers     \local_sentientia_users\bizlms\transcript_step
 * @covers     \local_sentientia_users\bizlms\login_day_step
 * @covers     \local_sentientia_users\bizlms\lookup_step
 *
 * @group local_sentientia_users
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use users_seed;
    use \local_sentientia_org\test\bizlms_fixture;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => self::users_fixture_xml()];
    }

    protected function contract_importer(): framework_importer {
        return new users_importer();
    }

    /**
     * The importer depends on org, which is not this plugin's, so the org feature is registered as a stub next to
     * it. A class method overrides the trait's, and every contract test calls it through $this.
     *
     * @return framework_importer
     */
    protected function contract_begin(): framework_importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new stub_org_importer(), $importer]);
        return $importer;
    }

    protected function contract_decisions(): decisions {
        return $this->users_decisions();
    }

    protected function contract_seed(): void {
        $this->seed_users_world();
    }

    protected function contract_mutate_source(): void {
        $this->put_run(99, ['usercreated' => $this->uid['ua'], 'timecreated' => $this->at('2026-03-13 10:00:00')]);
    }

    protected function contract_collision(): ?array {
        // A row at a domain's legacy id that is not (and cannot be) a copy of it: the target is a new table.
        return ['table' => 'local_sentientia_users_domain', 'row' => (object) [
            'id' => 3, 'name' => 'Somebody else', 'code' => 'SE', 'costcenterid' => 0, 'timecreated' => 1, 'timemodified' => 1]];
    }

    protected function contract_user_columns(): array {
        return [
            'local_sentientia_users_sync_runs' => ['usercreated'],
            'local_sentientia_users_sync_errors' => ['email', 'employee_code', 'username', 'firstname', 'lastname',
                'modified_by'],
            'local_sentientia_users_transcript' => ['userid', 'employee_id', 'learner_name', 'usercreated', 'usermodified'],
            'local_sentientia_users_logindays' => ['userid'],
        ];
    }

    // The signed decisions.

    public function test_the_signed_decisions_file_satisfies_the_importer(): void {
        $file = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        $signed = decisions::load($file);
        foreach ((new users_importer())->decisions() as $decision) {
            $this->assertTrue($signed->has($decision->key), $decision->key . ' is accepted in the signed file');
            if ($decision->allowed !== null) {
                $this->assertContains($signed->get($decision->key), $decision->allowed,
                    $decision->key . ': the signed value is one the importer supports');
            }
        }
    }

    // sync_runs.

    public function test_sync_run_columns_are_mapped_and_the_source_times_are_kept(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->users_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $run = $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 1);
        $this->assertSame('', $run->filename, 'BizLMS never stored a file name');
        $this->assertSame('bizlms', $run->source);
        $this->assertSame('completed', $run->status);
        $this->assertNull($run->error_summary);
        $this->assertSame(5, (int) $run->insertedcount);
        $this->assertSame(3, (int) $run->updatedcount);
        $this->assertSame(2, (int) $run->errorcount, 'the legacy error count is copied as BizLMS showed it');
        $this->assertSame(3, (int) $run->warningcount, 'warnings and supervisor warnings are added');
        $this->assertSame(0, (int) $run->skippedcount);
        $this->assertSame(0, (int) $run->suspendedcount);
        $this->assertSame($this->uid['ua'], (int) $run->usercreated);
        $this->assertSame($this->at('2026-03-10 10:00:00'), (int) $run->timecreated);
        $this->assertSame($this->at('2026-03-10 10:05:00'), (int) $run->timemodified);
        // Total rows: inserted + updated + the error rows (not warnings) matched to the run: errors 1 and 10.
        $this->assertSame(5 + 3 + 2, (int) $run->totalrows);

        $second = $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 2);
        foreach (['insertedcount', 'updatedcount', 'errorcount', 'warningcount'] as $counter) {
            $this->assertSame(0, (int) $second->{$counter}, 'a NULL counter is 0: ' . $counter);
        }
        $this->assertSame(1, (int) $second->totalrows, 'error 5 is the only row matched to run 2');
        $this->assertSame($second->timecreated, $second->timemodified, 'no modified time: the created time');

        $overflow = $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 4);
        $this->assertSame(2147483647, (int) $overflow->insertedcount, 'a count no INT holds is clamped, not refused');
        $this->assertSame(0, (int) $overflow->usercreated);
        $this->assertSame(0, (int) $overflow->timecreated, 'nothing invents a time');
        $warnings = $this->step_section($report, 'users.sync_runs', 'warnings');
        $this->assertArrayHasKey('count_clamped', $warnings);
        $this->assertArrayHasKey('no_uploader', $warnings);
        $this->assertArrayHasKey('derived_timestamp', $warnings);
    }

    public function test_a_run_whose_legacy_counters_differ_from_its_matched_rows_is_reported_not_corrected(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);
        $warnings = $this->step_section($report, 'users.sync_runs', 'warnings');

        // errorscount against the error rows matched to the run: run 1 says 2 and has errors 1 and 10 (equal); runs
        // 2 and 5 say nothing (NULL) and have error 5 and error 11 matched. Runs 3 and 4 say 0 and have none.
        $this->assertSame(2, $warnings['legacy_error_count_differs']);
        // warningscount + supervisorwarningscount against the warning rows matched: run 1 says 1 + 2 and has one
        // (error 2, a midnight row); no other run claims a warning and none has one.
        $this->assertSame(1, $warnings['legacy_warning_count_differs']);

        // Reported, never corrected: the counters stay as BizLMS showed them.
        $one = $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 1);
        $five = $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 5);
        $this->assertSame(2, (int) $one->errorcount);
        $this->assertSame(3, (int) $one->warningcount);
        $this->assertSame(0, (int) $five->errorcount, 'it says nothing, although a matched row exists');
        $this->assertSame(1, (int) $five->totalrows, 'the derived total does count the matched row');
    }

    public function test_the_run_tenant_is_the_uploaders_tenant_now_never_the_legacy_costcenterid(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);

        // Run 1 was uploaded by ua (/1/5) and the legacy row says costcenterid 77: the legacy value is not used.
        $this->assertSame(1, (int) $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 1)->costcenterid);
        $this->assertSame(1, (int) $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 3)->costcenterid);
        // Run 2 was uploaded by the site admin, who is cross-tenant: tenant 0 (the signed decision), although the
        // legacy row says 1. Run 4 has no uploader.
        $this->assertSame(0, (int) $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 2)->costcenterid);
        $this->assertSame(0, (int) $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 4)->costcenterid);
        $methods = $this->step_section($report, 'users.sync_runs', 'tenant_methods');
        $this->assertEquals(['exact' => 3, 'fallback:crosstenant' => 1, 'unresolved' => 1], $methods);
    }

    public function test_a_cross_tenant_uploader_can_keep_their_own_root_when_the_owner_decides_so(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('user', 'open_path', '/177/9', ['id' => $this->uid['admin']]);
        $this->users_run(true, $this->users_decisions(['users.admin_runs_tenant' => 'uploader_root']));
        $this->assertSame(177, (int) $this->target_of('local_sentientia_users_sync_runs', 'local_userssyncdata', 2)->costcenterid);
    }

    // Run matching and the synthetic runs.

    public function test_every_error_is_matched_to_a_run_by_the_signed_window_rules(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->users_run(true);
        $this->assertContains($result['exit'], [0, 2]);
        $run1 = (int) $DB->get_field(legacymap::TABLE, 'targetid', ['sourcetable' => 'local_userssyncdata', 'sourceid' => 1]);
        $run2 = (int) $DB->get_field(legacymap::TABLE, 'targetid', ['sourcetable' => 'local_userssyncdata', 'sourceid' => 2]);
        $run5 = (int) $DB->get_field(legacymap::TABLE, 'targetid', ['sourcetable' => 'local_userssyncdata', 'sourceid' => 5]);

        $err = fn(int $id) => $this->target_of('local_sentientia_users_sync_errors', 'local_syncerrors', $id);
        $this->assertSame($run1, (int) $err(1)->runid, 'an error 10 seconds before the run belongs to it');
        $this->assertSame($run1, (int) $err(10)->runid);
        $this->assertSame($run1, (int) $err(2)->runid, 'a warning (midnight) belongs to the uploader\'s first run that day');
        $this->assertSame($run2, (int) $err(5)->runid);
        $this->assertSame($run5, (int) $err(11)->runid);

        // 07:00 and 07:30 are more than an hour before run 1 (10:00): they match no run.
        foreach ([3, 4, 6, 7, 8, 9] as $orphan) {
            $this->assertNotContains((int) $err($orphan)->runid, [$run1, $run2, $run5], "error {$orphan} is an orphan");
        }
        $this->assertSame('error', $err(1)->severity);
        $this->assertSame('warning', $err(2)->severity, 'an exact midnight in the server timezone is a warning');
        $this->assertSame('warning', $err(7)->severity);
        $this->assertSame(11, $DB->count_records('local_sentientia_users_sync_errors'));
        $this->assertSame(11, $DB->count_records(legacymap::TABLE, ['sourcetable' => 'local_syncerrors', 'subkey' => '']));
    }

    public function test_synthetic_runs_are_made_per_uploader_and_day_with_their_own_counts(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);

        $orphan = '#local_syncerrors.orphan_day';
        $uamin = 1;
        // ua: two orphan days. The first (D1) is the group's primary row, D3 is a sub-row named by its day.
        $d1 = $this->target_of('local_sentientia_users_sync_runs', $orphan, $uamin);
        $d3 = $this->target_of('local_sentientia_users_sync_runs', $orphan, $uamin, 'day:20260312');
        $this->assertSame(2, (int) $d1->errorcount);
        $this->assertSame(0, (int) $d1->warningcount);
        $this->assertSame(2, (int) $d1->totalrows);
        $this->assertSame(0, (int) $d1->insertedcount);
        $this->assertSame('bizlms', $d1->source);
        $this->assertSame('completed', $d1->status);
        $this->assertSame($this->uid['ua'], (int) $d1->usercreated);
        $this->assertSame($this->at('2026-03-10 07:30:00'), (int) $d1->timecreated, 'the time of its last error');
        $this->assertSame(1, (int) $d1->costcenterid, 'the uploader\'s tenant');
        $this->assertSame(0, (int) $d3->errorcount);
        $this->assertSame(1, (int) $d3->warningcount, 'error 7 is a warning');
        $this->assertSame($this->at('2026-03-12 00:00:00'), (int) $d3->timecreated);

        $ub = $this->target_of('local_sentientia_users_sync_runs', $orphan, 6);
        $this->assertSame(77, (int) $ub->costcenterid);
        $admin = $this->target_of('local_sentientia_users_sync_runs', $orphan, 5);
        $this->assertSame(0, (int) $admin->costcenterid, 'a cross-tenant uploader: tenant 0');
        $none = $this->target_of('local_sentientia_users_sync_runs', $orphan, 8);
        $this->assertSame(0, (int) $none->usercreated);
        $this->assertSame(0, (int) $none->timecreated, 'errors with no time make a run with no time');

        // 5 legacy runs + (ua x2, admin, ub, none) = 10; the errors' runs are all in the table.
        $this->assertSame(10, $DB->count_records('local_sentientia_users_sync_runs'));
        // dup's only error is matched to its run: nothing orphan, so the group is archived.
        $archived = $DB->get_record(legacymap::TABLE, ['sourcetable' => $orphan, 'sourceid' => 11, 'subkey' => '']);
        $this->assertSame('archived', $archived->outcome);
        $this->assertSame('no_unattached_errors', $archived->reason);
        $this->assertSame(1, $this->step_section($report, 'users.orphan_runs', 'counters')['archived']);
    }

    public function test_exact_shape_has_no_service_runs_and_says_how_severity_was_found(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $warnings = $this->users_preflight()->warnings();
        $this->assertContains('severity_inferred_from_midnight', $warnings);
        $this->assertContains('no_sync_file_name_column', $warnings);

        $this->users_run(true);
        // Without sync_file_name nothing can be told from the web service: every group of the service step is empty.
        $this->assertSame(0, $DB->count_records(legacymap::TABLE,
            ['sourcetable' => '#local_syncerrors.service_day', 'outcome' => 'imported']));
        $this->assertSame(5, $DB->count_records(legacymap::TABLE,
            ['sourcetable' => '#local_syncerrors.service_day', 'reason' => 'no_service_errors']));
    }

    /**
     * The preflight of the users feature on the current tables.
     *
     * @return \local_sentientia_platform\bizlms\preflight
     */
    private function users_preflight(): \local_sentientia_platform\bizlms\preflight {
        registry::set_testing_importers([new stub_org_importer(), new users_importer()]);
        $runner = new \local_sentientia_platform\bizlms\runner(['decisions' => $this->users_decisions()]);
        return $runner->preflight([])['preflights']['users'];
    }

    // sync_errors.

    public function test_sync_error_columns_are_mapped(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);

        $first = $this->target_of('local_sentientia_users_sync_errors', 'local_syncerrors', 1);
        $this->assertSame('a@x.com', $first->email);
        $this->assertSame('E100', $first->employee_code);
        $this->assertSame('-', $first->username, 'BizLMS never stored the username');
        $this->assertSame('', $first->firstname, 'the exact-shape table has no name columns');
        $this->assertSame('', $first->lastname);
        $this->assertSame(0, (int) $first->csv_line_number, 'BizLMS never stored the CSV line');
        $this->assertSame('Invalid designation,Missing manager', $first->error_message, 'verbatim, commas and all');
        $this->assertSame('designation', $first->mandatory_fields);
        $this->assertSame($this->uid['ua'], (int) $first->modified_by);
        $this->assertSame($this->at('2026-03-10 09:59:50'), (int) $first->timecreated);

        $warning = $this->target_of('local_sentientia_users_sync_errors', 'local_syncerrors', 2);
        $this->assertNull($warning->mandatory_fields, 'NULL stays NULL');

        $long = $this->target_of('local_sentientia_users_sync_errors', 'local_syncerrors', 10);
        $this->assertSame('-', $long->email, 'no e-mail: the dash BizLMS wrote');
        $this->assertSame(100, \core_text::strlen($long->employee_code), 'a code over 100 characters is truncated');
        $this->assertArrayHasKey('truncated:employee_code', $this->step_section($report, 'users.sync_errors', 'warnings'));

        $none = $this->target_of('local_sentientia_users_sync_errors', 'local_syncerrors', 8);
        $this->assertSame(0, (int) $none->modified_by);
        $this->assertSame(0, (int) $none->timecreated);
        $this->assertSame('-', $none->employee_code);
    }

    // Transcript.

    public function test_the_transcript_is_matched_to_one_live_account_and_parsed(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);
        $t = fn(int $id) => $this->target_of('local_sentientia_users_transcript', 'local_transcript_history', $id);

        $one = $t(1);
        $this->assertSame($this->uid['la'], (int) $one->userid, 'employee id E1 names exactly one live account');
        $this->assertSame('Safety 101', $one->title);
        $this->assertSame('Classroom', $one->training_type);
        $this->assertSame('OBJ-1', $one->objectref);
        $this->assertSame('Mumbai', $one->location);
        $this->assertSame(0, (int) $one->courseid, 'a course that does not exist is 0');
        $this->assertSame('completed', $one->status);
        $this->assertSame('Completed', $one->status_raw, 'the raw text is always kept');
        $this->assertSame('15/06/2016', $one->completion_date_raw);
        $this->assertSame($this->at('2016-06-15 00:00:00'), (int) $one->timecompleted, 'd/m/Y in the server timezone');
        $this->assertEqualsWithDelta(85.0, (float) $one->score, 0.001);
        $this->assertEqualsWithDelta(1.5, (float) $one->hours, 0.001, '1:30 is an hour and a half');
        $this->assertSame('85%', $one->score_raw);
        $this->assertSame('1:30', $one->hours_raw);
        $this->assertSame('bizlms', $one->source);
        $this->assertSame($this->uid['admin'], (int) $one->usercreated);
        $this->assertSame($this->at('2026-03-10 08:00:00'), (int) $one->timecreated);
        $this->assertSame(0, (int) $one->timemodified, 'NULL is 0');

        $dup = $t(2);
        $this->assertSame($this->uid['dup'], (int) $dup->userid, 'the deleted twin is not a candidate');
        $this->assertSame('inprogress', $dup->status);
        $this->assertSame($this->at('2016-07-01 00:00:00'), (int) $dup->timecompleted, 'Y-m-d');
        $this->assertNull($dup->score, 'n/a does not parse');
        $this->assertEqualsWithDelta(2.5, (float) $dup->hours, 0.001);

        $weird = $t(3);
        $this->assertSame($this->uid['la'], (int) $weird->userid, 'a userid that names an account is kept');
        $this->assertSame('unknown', $weird->status);
        $this->assertSame('Weird', $weird->status_raw);
        $this->assertNull($weird->timecompleted, '31/02/2016 is not a date');
        $this->assertNull($weird->hours);

        $nobody = $t(4);
        $this->assertSame(0, (int) $nobody->userid);
        $this->assertSame('(untitled)', $nobody->title);
        $this->assertSame('unknown', $nobody->status);
        $this->assertSame('', $nobody->status_raw);
        $this->assertSame($this->at('2016-06-15 00:00:00'), (int) $nobody->timecompleted, 'an Excel serial');

        $padded = $t(5);
        $this->assertSame($this->uid['la'], (int) $padded->userid, 'trimmed and case-insensitive');
        $this->assertSame('completed', $padded->status, 'PASSED is on the completed list');
        $this->assertGreaterThan(1, (int) $padded->courseid, 'a real course is kept');
        $this->assertSame($this->at('2016-06-15 00:00:00'), (int) $padded->timecompleted, 'd-M-Y');

        $gone = $t(6);
        $this->assertSame($this->uid['dupgone'], (int) $gone->userid, 'an account that exists, even deleted, keeps its rows');
        $warnings = $this->step_section($report, 'users.transcript', 'warnings');
        foreach (['user_matched_by_employee_id', 'no_user', 'unparsed_date', 'unparsed_score', 'unparsed_hours'] as $code) {
            $this->assertArrayHasKey($code, $warnings, $code);
        }
    }

    public function test_the_transcript_tenant_is_the_matched_learners_current_path(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);
        $t = fn(int $id) => $this->target_of('local_sentientia_users_transcript', 'local_transcript_history', $id);

        $this->assertSame('/1/5', $t(1)->open_path);
        $this->assertSame(1, (int) $t(1)->costcenterid);
        $this->assertSame('/1', $t(2)->open_path);
        // No learner: no path, visible to cross-tenant callers only (the signed decision).
        $this->assertNull($t(4)->open_path);
        $this->assertSame(0, (int) $t(4)->costcenterid);
        // Rows 1, 2, 3, 5 and 6 have a learner with a plain path; row 4 has none.
        $this->assertEquals(['exact' => 5, 'unresolved' => 1],
            $this->step_section($report, 'users.transcript', 'tenant_methods'));
    }

    public function test_transcript_rows_never_reach_completions_the_log_or_the_xapi_store(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $completions = $DB->count_records('course_completions');
        $logs = $DB->count_records('logstore_standard_log');
        $this->users_run(true);
        $this->assertSame($completions, $DB->count_records('course_completions'));
        $this->assertSame($logs, $DB->count_records('logstore_standard_log'));
        if ($DB->get_manager()->table_exists('local_sentientia_xapi_stmts')) {
            $this->assertSame(0, $DB->count_records('local_sentientia_xapi_stmts'));
        }
    }

    // Login days.

    public function test_login_days_collapse_duplicates_and_the_unkeyable_are_skipped(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->users_run(true);
        $this->assertSame(2, $result['exit'], 'two rows are not imported for a reason that needs the owner');
        $this->assertContains('users:invalid_login_row=2', $result['unproven']);

        $this->assertSame(4, $DB->count_records('local_sentientia_users_logindays'));
        $first = $this->target_of('local_sentientia_users_logindays', 'local_uniquelogins', 1);
        $this->assertSame($this->uid['la'], (int) $first->userid);
        $this->assertSame($this->at('2026-03-10 00:00:00'), (int) $first->logindate);
        $this->assertSame('web', $first->source);
        $this->assertSame($this->at('2026-03-10 08:00:00'), (int) $first->timecreated, 'the first login of the day');
        foreach ([2, 5, 6] as $duplicate) {
            $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_uniquelogins', 'sourceid' => $duplicate, 'subkey' => '']);
            $this->assertSame('merged', $map->outcome);
            $this->assertSame('duplicate_login_day', $map->reason);
        }
        $second = $this->target_of('local_sentientia_users_logindays', 'local_uniquelogins', 3);
        $this->assertSame($this->at('2026-03-11 00:00:00'), (int) $second->timecreated, 'no time: the day itself');
        $this->assertArrayHasKey('derived_timestamp', $this->step_section($report, 'users.logindays', 'warnings'));
        $skipped = $DB->get_records(legacymap::TABLE, ['sourcetable' => 'local_uniquelogins', 'outcome' => 'skipped'],
            'sourceid', 'sourceid, reason, detail');
        $this->assertSame([7, 8], array_map('intval', array_keys($skipped)));
        $this->assertSame('no_user', $skipped[7]->detail);
        $this->assertSame('no_day', $skipped[8]->detail);
        $this->assertArrayHasKey('user_not_found', $this->step_section($report, 'users.logindays', 'warnings'));

        // The owner accepts the two rows: the run is proven.
        $this->contract_clear_import(new users_importer());
        [$accepted] = $this->users_run(true, $this->users_decisions(['accepted_reasons' => ['users:invalid_login_row']]));
        $this->assertSame(0, $accepted['exit']);
    }

    public function test_the_owner_can_decline_the_login_days_and_the_lookups(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->users_run(true, $this->users_decisions([
            'users.uniquelogins' => 'skip', 'users.positions_domains_lookup_import' => false]));
        $this->assertSame(0, $result['exit'], 'archiving by decision needs nobody\'s acceptance');
        $this->assertSame(0, $DB->count_records('local_sentientia_users_logindays'));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_domain'));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_position'));
        $this->assertSame(9, $DB->count_records(legacymap::TABLE,
            ['sourcetable' => 'local_uniquelogins', 'reason' => 'declined_by_decision']));
        $this->assertSame(14, $DB->count_records(legacymap::TABLE, ['reason' => 'declined_by_decision',
            'outcome' => 'archived']), 'nine login rows, two domains and three positions');
    }

    // Lookups.

    public function test_the_lookups_keep_their_ids_and_the_sequences_are_reset(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->users_run(true);

        $domain = $DB->get_record('local_sentientia_users_domain', ['id' => 3], '*', MUST_EXIST);
        $this->assertSame('Engineering', $domain->name);
        $this->assertSame('ENG', $domain->code);
        $this->assertSame(1, (int) $domain->costcenterid);
        $this->assertSame(0, (int) $domain->timecreated, 'the table has no times: none is invented');
        $this->assertArrayHasKey('no_source_time', $this->step_section($report, 'users.domains', 'warnings'));
        $this->assertTrue($DB->record_exists('local_sentientia_users_domain', ['id' => 7]));

        $position = $DB->get_record('local_sentientia_users_position', ['id' => 2], '*', MUST_EXIST);
        $this->assertSame('Analyst', $position->name);
        $this->assertSame(3, (int) $position->domainid);
        $this->assertSame(4, (int) $position->sortorder);
        $this->assertSame($this->at('2026-03-10 08:00:00'), (int) $position->timecreated);
        $this->assertSame((int) $position->timecreated, (int) $position->timemodified, 'no modified time: the created time');
        $long = $DB->get_record('local_sentientia_users_position', ['id' => 5], '*', MUST_EXIST);
        $this->assertSame(255, \core_text::strlen($long->name), 'a name over 255 characters is truncated');
        $this->assertArrayHasKey('truncated:name', $this->step_section($report, 'users.positions', 'warnings'));
        $this->assertSame(0, (int) $DB->get_record('local_sentientia_users_position', ['id' => 9])->timecreated);

        // After finalise a native insert gets an id above the legacy maximum, not 1.
        $new = $DB->insert_record('local_sentientia_users_position', (object) [
            'name' => 'Native', 'code' => 'N', 'domainid' => 0, 'costcenterid' => 0, 'sortorder' => 0,
            'timecreated' => 1, 'timemodified' => 1]);
        $this->assertGreaterThan(9, $new);
        $newdomain = $DB->insert_record('local_sentientia_users_domain', (object) [
            'name' => 'Native', 'code' => 'N', 'costcenterid' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        $this->assertGreaterThan(7, $newdomain);
    }

    // Preflight and the declined table.

    public function test_preflight_reports_counts_the_mirror_mismatch_and_the_status_effect(): void {
        $this->contract_begin();
        $this->contract_seed();
        $preflight = $this->users_preflight();
        $this->assertSame([], $preflight->blockers());
        $counts = $preflight->counts();
        $this->assertSame(1, $counts['employee_code_over_100']);
        $this->assertSame(1, $counts['userdata_path_mismatch'],
            'ua is /1/5 now and local_userdata says /77; the agreeing row and the empty row are not counted');
        $this->assertSame(3, $counts['transcript_status_unknown'], 'Weird, and the two rows with no status');
        $histogram = $preflight->histograms()['local_sentientia_users_transcript.status'];
        $this->assertSame(2, $histogram['completed'], 'Completed and PASSED');
        $this->assertSame(1, $histogram['inprogress']);
        $this->assertSame(3, $histogram['unknown']);
    }

    public function test_local_userdata_is_never_written_back_to_the_users(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $before = $DB->get_records('user', null, 'id', 'id, open_path, open_employeeid, timemodified');
        $userdata = fingerprint::table('local_userdata');
        $this->users_run(true);
        $this->assertEquals($before, $DB->get_records('user', null, 'id', 'id, open_path, open_employeeid, timemodified'),
            'the import writes nothing to {user}');
        $this->assertSame($userdata, fingerprint::table('local_userdata'), 'a declined table is untouched');
        $this->assertSame('/77', $DB->get_field('local_userdata', 'costcenterpath', ['id' => 2]));
    }

    public function test_no_legacy_table_is_ever_written(): void {
        $this->contract_begin();
        $this->contract_seed();
        $before = [];
        foreach (array_keys((new users_importer())->sources()) as $table) {
            $before[$table] = fingerprint::table($table);
        }
        $this->users_run(true);
        foreach ($before as $table => $fingerprint) {
            $this->assertSame($fingerprint, fingerprint::table($table), $table . ' is the archive and must not change');
        }
    }

    // Verify.

    public function test_verify_notices_a_row_that_is_not_what_the_import_wrote(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->users_run(true);

        // The importer's verify() on a healthy import.
        $ctx = $this->verify_context();
        $this->assertSame([], (new users_importer())->verify($ctx));

        $errorid = (int) $DB->get_field('local_sentientia_users_sync_errors', 'id', [], IGNORE_MULTIPLE);
        $DB->set_field('local_sentientia_users_sync_errors', 'severity', 'fatal', ['id' => $errorid]);
        $DB->delete_records('local_sentientia_users_domain', ['id' => 7]);
        $DB->set_field('local_sentientia_users_transcript', 'status', 'invented', ['id' => (int) $DB->get_field(
            'local_sentientia_users_transcript', 'id', [], IGNORE_MULTIPLE)]);
        $DB->set_field('local_sentientia_users_sync_runs', 'costcenterid', 4242, ['id' => (int) $DB->get_field(
            'local_sentientia_users_sync_runs', 'id', [], IGNORE_MULTIPLE)]);
        $failures = implode(' ', (new users_importer())->verify($ctx));
        foreach (['unknown_severity', 'target_rows_missing:local_sentientia_users_domain',
                'transcript_status_outside_the_signed_list', 'invalid_run_tenant:4242'] as $expected) {
            $this->assertStringContainsString($expected, $failures);
        }
    }

    /**
     * A context the importer's verify() can read from, built the way the runner builds one.
     *
     * @return \local_sentientia_platform\bizlms\context
     */
    private function verify_context(): \local_sentientia_platform\bizlms\context {
        return \local_sentientia_platform\bizlms\context::build(new users_importer(), false, 0, $this->users_decisions());
    }

    // The importer's own declaration.

    public function test_the_importer_declares_what_the_registry_and_the_map_require(): void {
        $importer = new users_importer();
        $this->assertSame('users', $importer->feature());
        $this->assertSame(['org'], $importer->depends());
        $this->assertSame([], $importer->core_writes(), 'no core table is written');
        $this->assertFalse($importer->atomic());
        $this->assertSame(['local_sentientia_users_transcript' => 'open_path'], $importer->tenant_columns());
        $this->assertArrayHasKey('local_userdata', $importer->declined_tables());
        $this->assertEqualsCanonicalizing([
            'local_userssyncdata', 'local_syncerrors', 'local_transcript_history', 'local_uniquelogins', 'local_domains',
            'local_positions'], array_keys($importer->sources()));
        $preserve = [];
        foreach ($importer->steps() as $step) {
            if ($step instanceof \local_sentientia_platform\bizlms\step
                    && $step->idpolicy() === \local_sentientia_platform\bizlms\idpolicy::PRESERVE) {
                $preserve[] = $step->targettable();
            }
        }
        sort($preserve);
        $this->assertSame(['local_sentientia_users_domain', 'local_sentientia_users_position'], $preserve);
    }
}
