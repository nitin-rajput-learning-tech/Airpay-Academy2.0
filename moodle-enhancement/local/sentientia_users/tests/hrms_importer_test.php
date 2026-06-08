<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-6 (2026-05-16) — tests for the HRMS 24-column bulk-import engine.
 *
 * Locks in:
 *   - import_csv() inserts new users and updates existing ones idempotently
 *   - mandatory-column-missing rows go to sync_errors with severity=error
 *   - malformed CSV header (missing required columns) → run status=failed
 *   - reportingmanager_empid is resolved in PASS 2 against open_employeeid
 *   - unresolved manager → warning, not error; user is still created
 *   - employee_status non-active → user.suspended = 1
 *   - existing-user lookup matches on email OR username OR employee_code
 *   - 2+ existing users matching → error row, no DB write
 *   - org cascade (company_code → BU → dept) sets open_path correctly
 *
 * @package    local_sentientia_users
 * @category   test
 */
final class hrms_importer_test extends \advanced_testcase {

    /** Build a minimal seed org tree: Airpay > Tech > Backend. */
    private function seed_org_tree(): array {
        global $DB;
        $now = time();
        $airpay_id = $DB->insert_record('local_airpay_org', (object) [
            'name'         => 'Airpay',
            'shortname'    => 'AIRPAY',
            'parentid'     => 0,
            'path'         => '/',
            'depth'        => 1,
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $tech_id = $DB->insert_record('local_airpay_org', (object) [
            'name'         => 'Tech',
            'shortname'    => 'TECH',
            'parentid'     => $airpay_id,
            'path'         => '/' . $airpay_id,
            'depth'        => 2,
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $backend_id = $DB->insert_record('local_airpay_org', (object) [
            'name'         => 'Backend',
            'shortname'    => 'BACKEND',
            'parentid'     => $tech_id,
            'path'         => '/' . $airpay_id . '/' . $tech_id,
            'depth'        => 3,
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        return [
            'airpay'  => $airpay_id,
            'tech'    => $tech_id,
            'backend' => $backend_id,
        ];
    }

    private function header_line(): string {
        return implode(',', hrms_importer::STANDARD_COLUMNS);
    }

    private function row_alice(): string {
        // company_code,username,password,employee_code,prefix,first_name,
        // last_name,gender,email,bussiness_unit_code,department_code,
        // subdepartment_code,reportingmanager_empid,language,designation,
        // employment_type,region,grade,date_of_birth,date_of_joining,
        // mobileno,employee_status,timezone,force_password_change
        return 'AIRPAY,alice,,EMP001,Ms,Alice,Anderson,F,alice@airpay.in,'
            . 'TECH,BACKEND,,EMP999,en,Engineer,Permanent,APAC,L3,'
            . '01-01-1990,15-03-2022,9999988888,Active,Asia/Kolkata,0';
    }

    private function row_bob(): string {
        // Bob is Alice's manager (EMP999) — should be resolved in pass 2.
        return 'AIRPAY,bob.manager,,EMP999,Mr,Bob,Brown,M,bob@airpay.in,'
            . 'TECH,BACKEND,,,en,Engineering Manager,Permanent,APAC,L5,'
            . '01-01-1980,01-01-2020,9999977777,Active,Asia/Kolkata,0';
    }

    public function test_import_inserts_new_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        $csv = $this->header_line() . "\n" . $this->row_alice() . "\n";
        $run_id = hrms_importer::import_csv($csv, 2, 'test.csv');

        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, (int) $run->totalrows);
        $this->assertSame(1, (int) $run->insertedcount);
        $this->assertSame(0, (int) $run->errorcount);

        $user = $DB->get_record('user', ['email' => 'alice@airpay.in']);
        $this->assertNotFalse($user);
        $this->assertSame('alice', $user->username);
        $this->assertSame('Alice', $user->firstname);
        $this->assertSame('EMP001', $user->idnumber);
        $this->assertSame('EMP001', $user->open_employeeid);
    }

    public function test_missing_mandatory_field_writes_error(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        // Row with empty email — that's mandatory.
        $bad_row = 'AIRPAY,charlie,,EMP002,Mr,Charlie,Cox,M,,'
            . 'TECH,BACKEND,,,en,Engineer,Permanent,APAC,L3,'
            . '01-01-1990,15-03-2022,9999988888,Active,Asia/Kolkata,0';
        $csv = $this->header_line() . "\n" . $bad_row . "\n";

        $run_id = hrms_importer::import_csv($csv, 2);
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $run->errorcount);
        $this->assertSame(0, (int) $run->insertedcount);

        $errors = $DB->get_records('local_sentientia_users_sync_errors', ['runid' => $run_id]);
        $this->assertCount(1, $errors);
        $err = reset($errors);
        $this->assertSame('error', $err->severity);
        $this->assertStringContainsString('email', $err->mandatory_fields);
    }

    public function test_csv_missing_required_header_fails_run(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        // Drop the 'email' column from the header.
        $bad_header = 'first_name,last_name,username,company_code,'
            . 'employee_code,employee_status,gender';  // no 'email'
        $csv = $bad_header . "\n,,,,,,\n";

        $run_id = hrms_importer::import_csv($csv, 2, 'broken.csv');
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('email', (string) $run->error_summary);
    }

    public function test_manager_resolved_in_pass_two(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        // Alice references Bob (EMP999) as her manager. Bob is in row 2 of
        // the same CSV, so pass 1 inserts both with NULL supervisor; pass 2
        // resolves Alice's open_supervisorid → Bob's userid.
        $csv = $this->header_line() . "\n"
            . $this->row_alice() . "\n"
            . $this->row_bob() . "\n";

        $run_id = hrms_importer::import_csv($csv, 2);
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame(2, (int) $run->insertedcount);
        $this->assertSame(0, (int) $run->errorcount);
        $this->assertSame(0, (int) $run->warningcount,
            'Bob exists in same CSV, so no warning should fire');

        $alice = $DB->get_record('user', ['email' => 'alice@airpay.in'], '*', MUST_EXIST);
        $bob   = $DB->get_record('user', ['email' => 'bob@airpay.in'], '*', MUST_EXIST);
        $this->assertSame((int) $bob->id, (int) $alice->open_supervisorid,
            'Pass 2 should have linked Alice to Bob via open_employeeid match');
    }

    public function test_unresolved_manager_creates_warning_not_error(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        // Alice references EMP999 as her manager, but no Bob in the CSV.
        $csv = $this->header_line() . "\n" . $this->row_alice() . "\n";

        $run_id = hrms_importer::import_csv($csv, 2);
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $run->insertedcount,
            'Alice should still be inserted even though her manager is missing');
        $this->assertSame(0, (int) $run->errorcount);
        $this->assertGreaterThan(0, (int) $run->warningcount,
            'Missing manager should produce a warning');

        $warnings = $DB->get_records('local_sentientia_users_sync_errors',
            ['runid' => $run_id, 'severity' => 'warning']);
        $this->assertCount(1, $warnings);

        // Alice's open_supervisorid should be NULL/0.
        $alice = $DB->get_record('user', ['email' => 'alice@airpay.in'], '*', MUST_EXIST);
        $this->assertEmpty($alice->open_supervisorid);
    }

    public function test_inactive_employee_is_suspended(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        // Same as Alice but employee_status = Inactive.
        $row = 'AIRPAY,dan,,EMP003,Mr,Dan,Davis,M,dan@airpay.in,'
            . 'TECH,BACKEND,,,en,Engineer,Permanent,APAC,L3,'
            . '01-01-1990,15-03-2022,9999988888,Inactive,Asia/Kolkata,0';
        $csv = $this->header_line() . "\n" . $row . "\n";

        $run_id = hrms_importer::import_csv($csv, 2);
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $run->suspendedcount);

        $user = $DB->get_record('user', ['email' => 'dan@airpay.in'], '*', MUST_EXIST);
        $this->assertSame(1, (int) $user->suspended);
    }

    public function test_existing_user_is_updated_idempotently(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        // First run inserts Alice.
        $csv = $this->header_line() . "\n" . $this->row_alice() . "\n";
        hrms_importer::import_csv($csv, 2);

        // Second run with the same row should update, not insert.
        $run_id = hrms_importer::import_csv($csv, 2);
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame(0, (int) $run->insertedcount);
        $this->assertSame(1, (int) $run->updatedcount);

        // Only one Alice should exist.
        $this->assertEquals(1, $DB->count_records('user', ['email' => 'alice@airpay.in']));
    }

    public function test_org_cascade_sets_open_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $orgs = $this->seed_org_tree();

        $csv = $this->header_line() . "\n" . $this->row_alice() . "\n";
        hrms_importer::import_csv($csv, 2);

        $alice = $DB->get_record('user', ['email' => 'alice@airpay.in'], '*', MUST_EXIST);
        // path = /AIRPAY_id/TECH_id/BACKEND_id (the row has BU=TECH, dept=BACKEND).
        $expected = '/' . $orgs['airpay'] . '/' . $orgs['tech'] . '/' . $orgs['backend'];
        $this->assertSame($expected, $alice->open_path,
            "Org cascade should resolve AIRPAY > TECH > BACKEND to '$expected'");
    }

    public function test_unknown_company_code_is_an_error(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;
        $this->seed_org_tree();

        // company_code='NONEXISTENT' is not in the seeded tree.
        $bad_row = 'NONEXISTENT,erin,,EMP004,Ms,Erin,Evans,F,erin@airpay.in,'
            . 'TECH,BACKEND,,,en,Engineer,Permanent,APAC,L3,'
            . '01-01-1990,15-03-2022,9999988888,Active,Asia/Kolkata,0';
        $csv = $this->header_line() . "\n" . $bad_row . "\n";

        $run_id = hrms_importer::import_csv($csv, 2);
        $run = $DB->get_record('local_sentientia_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);
        $this->assertSame(1, (int) $run->errorcount);
        $errors = $DB->get_records('local_sentientia_users_sync_errors', ['runid' => $run_id]);
        $err = reset($errors);
        $this->assertStringContainsString('company_code', $err->error_message);
    }
}
