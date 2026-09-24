<?php
// This file is part of Sentientia LMS.
//
// Sentientia LMS is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. Distributed WITHOUT ANY WARRANTY. See the GNU GPL for
// more details. <http://www.gnu.org/licenses/>.

/**
 * Privacy provider tests for local_sentientia_platform - the ADR-017 user-type tables.
 *
 * Written 2026-09-24. The provider covered the two feature-flag tables only.
 * The five user-type tables (classification + employee / consumer /
 * partner-employee / operator profile) are created at runtime by
 * user_type_tables::ensure(), not by install.xml in every tree, so the
 * install.xml-based privacy_coverage_test could not see them and nothing
 * erased them: a public-signup learner's consumer profile and classification
 * row survived a DPDP erasure that local_sentientia_privacy reported as
 * 'completed'. These tests seed every one of those tables, and a second and
 * third person whose profile names the subject as manager, and assert what
 * erasure does to each.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_sentientia_platform\privacy\provider;
use local_sentientia_platform\schema\user_type_tables;

/**
 * @covers \local_sentientia_platform\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {

    /** @var string */
    private const COMPONENT = 'local_sentientia_platform';

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // The same code path db/install.php and upgrade step 2026052801 use.
        // Idempotent: a no-op where install.xml or install.php already
        // created the tables, which is the normal PHPUnit case.
        user_type_tables::ensure($DB->get_manager());
    }

    // ---------------------------------------------------------------------
    // Seed helpers. Every NOT NULL column without a default is set
    // (user_type.user_type, partner_employee_profile.customer_id); each table
    // has a UNIQUE key on userid, so each helper is called at most once per
    // user per table.
    // ---------------------------------------------------------------------

    private function seed_user_type(int $userid, string $type): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_user_type', (object) [
            'userid' => $userid, 'user_type' => $type,
            'provisioning_source' => 'signup_public', 'provisioned_at' => time(),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function seed_employee(int $userid, ?int $managerid): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_employee_profile', (object) [
            'userid' => $userid, 'employee_id' => 'E' . $userid,
            'department' => 'Operations', 'job_title' => 'Analyst',
            'manager_userid' => $managerid, 'hire_date' => time(),
            'cost_center_path' => '/1', 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function seed_consumer(int $userid): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_consumer_profile', (object) [
            'userid' => $userid, 'interests_json' => '["payments","compliance"]',
            'weekly_goal' => 3, 'referral_source' => 'friend',
            'consent_marketing' => 1, 'consent_leaderboard' => 0,
            'payment_history_url' => 'https://example.invalid/payments/' . $userid,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function seed_partner(int $userid, ?int $managerid): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_partner_employee_profile', (object) [
            'userid' => $userid, 'customer_id' => 1,
            'partner_employee_id' => 'P' . $userid, 'partner_department' => 'Sales',
            'partner_job_title' => 'Representative', 'partner_manager_userid' => $managerid,
            'partner_hire_date' => time(), 'cost_center_path' => '/77',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function seed_operator(int $userid): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_operator_profile', (object) [
            'userid' => $userid, 'operator_role' => 'support',
            'contact_phone' => '+91 00000 00000', 'oncall_for_customer_id' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * The shared scenario.
     *
     *   subject    - a row in every user-type table; manager of report and
     *                partnerreport.
     *   report     - an employee whose manager is the subject.
     *   partnerreport - a partner employee whose partner manager is the subject.
     *   bystander  - their own classification, consumer profile, and an
     *                employee profile whose manager is someone else.
     *   othermgr   - the bystander's manager; no rows of their own.
     *
     * @return array<string,\stdClass> users by role, plus 'ids' => row ids
     */
    private function scenario(): array {
        $gen = $this->getDataGenerator();
        $u = [];
        foreach (['subject', 'report', 'partnerreport', 'bystander', 'othermgr'] as $role) {
            $u[$role] = $gen->create_user();
        }
        $s = (int) $u['subject']->id;

        $this->seed_user_type($s, 'employee');
        $this->seed_employee($s, (int) $u['othermgr']->id);
        $this->seed_consumer($s);
        $this->seed_partner($s, null);
        $this->seed_operator($s);

        $ids = (object) [
            'report'        => $this->seed_employee((int) $u['report']->id, $s),
            'partnerreport' => $this->seed_partner((int) $u['partnerreport']->id, $s),
            'bystanderemp'  => $this->seed_employee((int) $u['bystander']->id, (int) $u['othermgr']->id),
        ];
        $this->seed_user_type((int) $u['report']->id, 'employee');
        $this->seed_user_type((int) $u['bystander']->id, 'consumer');
        $this->seed_consumer((int) $u['bystander']->id);

        $u['ids'] = $ids;
        return $u;
    }

    /**
     * Assert the subject has no row left in any user-type table and that the
     * other people's rows survived with only the manager link cleared.
     *
     * @param array $u scenario()
     */
    private function assert_subject_erased_others_kept(array $u): void {
        global $DB;
        $s = (int) $u['subject']->id;

        foreach (user_type_tables::TABLES as $table) {
            $this->assertSame(0, $DB->count_records($table, ['userid' => $s]),
                "{$table}: the subject's own row must be erased");
        }

        // The report's profile is theirs: it stays, only the link to the
        // erased manager goes.
        $report = $DB->get_record('local_sentientia_employee_profile',
            ['id' => $u['ids']->report], '*', MUST_EXIST);
        $this->assertNull($report->manager_userid);
        $this->assertSame((int) $u['report']->id, (int) $report->userid);
        $this->assertSame('E' . $u['report']->id, $report->employee_id);

        $partner = $DB->get_record('local_sentientia_partner_employee_profile',
            ['id' => $u['ids']->partnerreport], '*', MUST_EXIST);
        $this->assertNull($partner->partner_manager_userid);
        $this->assertSame('P' . $u['partnerreport']->id, $partner->partner_employee_id);

        // A manager link to somebody else is untouched.
        $this->assertEquals((int) $u['othermgr']->id, $DB->get_field(
            'local_sentientia_employee_profile', 'manager_userid', ['id' => $u['ids']->bystanderemp]));

        // Everybody else's own rows survive.
        $this->assertTrue($DB->record_exists('local_sentientia_user_type',
            ['userid' => $u['report']->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_user_type',
            ['userid' => $u['bystander']->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_consumer_profile',
            ['userid' => $u['bystander']->id]));
    }

    // ---------------------------------------------------------------------
    // Metadata.
    // ---------------------------------------------------------------------

    public function test_metadata_names_every_user_type_table_and_its_user_columns(): void {
        global $DB;
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = $item;
        }

        foreach (user_type_tables::TABLES as $table) {
            $this->assertArrayHasKey($table, $declared,
                "{$table} is created by user_type_tables::ensure() and holds personal data, "
                . 'so the provider must declare it');
            $fields = $declared[$table]->get_privacy_fields();
            $this->assertArrayHasKey('userid', $fields, "{$table}.userid must be declared");

            // Every declared field is a real column (catches a typo that
            // would otherwise read as coverage) and every string exists.
            $columns = array_keys($DB->get_columns($table));
            foreach ($fields as $field => $stringid) {
                $this->assertContains($field, $columns, "{$table}.{$field} is declared but is not a column");
                $this->assertTrue(get_string_manager()->string_exists($stringid, self::COMPONENT),
                    "missing lang string {$stringid}");
            }
            $this->assertTrue(get_string_manager()->string_exists(
                $declared[$table]->get_summary(), self::COMPONENT));
        }

        $this->assertArrayHasKey('manager_userid',
            $declared['local_sentientia_employee_profile']->get_privacy_fields());
        $this->assertArrayHasKey('partner_manager_userid',
            $declared['local_sentientia_partner_employee_profile']->get_privacy_fields());
    }

    // ---------------------------------------------------------------------
    // Discovery.
    // ---------------------------------------------------------------------

    public function test_contexts_are_reported_for_owners_and_for_managers_only(): void {
        $gen = $this->getDataGenerator();
        $consumer = $gen->create_user();
        $manager = $gen->create_user();
        $report = $gen->create_user();
        $nobody = $gen->create_user();
        $this->seed_consumer((int) $consumer->id);
        $this->seed_employee((int) $report->id, (int) $manager->id);

        $sys = [(int) \context_system::instance()->id];
        $contexts = function (\stdClass $user): array {
            return array_values(array_map('intval',
                provider::get_contexts_for_userid((int) $user->id)->get_contextids()));
        };

        $this->assertSame($sys, $contexts($consumer), 'a consumer profile is data about its owner');
        $this->assertSame($sys, $contexts($report));
        $this->assertSame($sys, $contexts($manager),
            'being named as somebody\'s manager is data about the manager');
        $this->assertSame([], $contexts($nobody));
    }

    public function test_users_in_context_lists_owners_and_managers(): void {
        $u = $this->scenario();
        $userlist = new userlist(\context_system::instance(), self::COMPONENT);
        provider::get_users_in_context($userlist);
        $ids = array_map('intval', $userlist->get_userids());

        foreach (['subject', 'report', 'partnerreport', 'bystander', 'othermgr'] as $role) {
            $this->assertContains((int) $u[$role]->id, $ids, "{$role} must be listed");
        }

        // Nothing for a non-system context.
        $course = $this->getDataGenerator()->create_course();
        $courselist = new userlist(\context_course::instance($course->id), self::COMPONENT);
        provider::get_users_in_context($courselist);
        $this->assertSame([], $courselist->get_userids());
    }

    // ---------------------------------------------------------------------
    // Erasure.
    // ---------------------------------------------------------------------

    public function test_erasing_a_user_removes_their_rows_and_unlinks_them_as_manager(): void {
        $u = $this->scenario();
        provider::delete_data_for_user(new approved_contextlist(
            $u['subject'], self::COMPONENT, [\context_system::instance()->id]));

        $this->assert_subject_erased_others_kept($u);
    }

    public function test_erasing_a_userlist_removes_their_rows_and_unlinks_them_as_manager(): void {
        $u = $this->scenario();
        provider::delete_data_for_users(new approved_userlist(
            \context_system::instance(), self::COMPONENT, [(int) $u['subject']->id]));

        $this->assert_subject_erased_others_kept($u);
    }

    public function test_erasure_in_another_context_touches_nothing(): void {
        global $DB;
        $u = $this->scenario();
        $usercontext = \context_user::instance($u['subject']->id);
        provider::delete_data_for_user(new approved_contextlist(
            $u['subject'], self::COMPONENT, [$usercontext->id]));

        $this->assertTrue($DB->record_exists('local_sentientia_consumer_profile',
            ['userid' => $u['subject']->id]));
        $this->assertEquals((int) $u['subject']->id, $DB->get_field(
            'local_sentientia_employee_profile', 'manager_userid', ['id' => $u['ids']->report]));
    }

    public function test_erasing_everyone_in_the_system_context_empties_the_tables(): void {
        global $DB;
        $this->scenario();
        provider::delete_data_for_all_users_in_context(\context_system::instance());

        foreach (user_type_tables::TABLES as $table) {
            $this->assertSame(0, $DB->count_records($table), "{$table} must be empty");
        }
    }

    /**
     * The tables are created at runtime, so a site can lack one. A missing
     * table must never throw: local_sentientia_privacy marks the whole
     * erasure 'partial' when a provider throws. The rest must still be erased.
     */
    public function test_a_missing_user_type_table_never_throws(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $report = $gen->create_user();
        $this->seed_user_type((int) $subject->id, 'consumer');
        $this->seed_consumer((int) $subject->id);
        $reportid = $this->seed_employee((int) $report->id, (int) $subject->id);

        // The operator table is empty in this test (nothing seeded it), so
        // dropping and re-creating it loses nothing.
        $dbman->drop_table(new \xmldb_table('local_sentientia_operator_profile'));
        try {
            $this->assertFalse($dbman->table_exists('local_sentientia_operator_profile'));
            $sys = \context_system::instance();

            $this->assertSame([(int) $sys->id], array_values(array_map('intval',
                provider::get_contexts_for_userid((int) $subject->id)->get_contextids())));
            $userlist = new userlist($sys, self::COMPONENT);
            provider::get_users_in_context($userlist);
            $this->assertContains((int) $subject->id, array_map('intval', $userlist->get_userids()));
            $this->export_context_data_for_user((int) $subject->id, $sys, self::COMPONENT);

            provider::delete_data_for_user(new approved_contextlist(
                $subject, self::COMPONENT, [$sys->id]));

            $this->assertFalse($DB->record_exists('local_sentientia_user_type',
                ['userid' => $subject->id]));
            $this->assertFalse($DB->record_exists('local_sentientia_consumer_profile',
                ['userid' => $subject->id]));
            $this->assertNull($DB->get_field('local_sentientia_employee_profile',
                'manager_userid', ['id' => $reportid]));

            provider::delete_data_for_users(new approved_userlist($sys, self::COMPONENT,
                [(int) $report->id]));
            provider::delete_data_for_all_users_in_context($sys);
        } finally {
            // Restore the schema for every later test, through the same path
            // the installer uses.
            user_type_tables::ensure($dbman);
        }
        $this->assertTrue($dbman->table_exists('local_sentientia_operator_profile'));
    }

    // ---------------------------------------------------------------------
    // Export.
    // ---------------------------------------------------------------------

    public function test_export_includes_profiles_and_manager_links_but_not_reports_data(): void {
        $u = $this->scenario();
        $sys = \context_system::instance();
        $this->export_context_data_for_user((int) $u['subject']->id, $sys, self::COMPONENT);

        $writer = writer::with_context($sys);
        $this->assertTrue($writer->has_any_data());
        $base = get_string('pluginname', self::COMPONENT);

        $type = $writer->get_data([$base, 'user_type']);
        $this->assertSame('employee', $type->user_type);

        $employee = $writer->get_data([$base, 'employee_profile']);
        $this->assertSame('E' . $u['subject']->id, $employee->employee_id);
        $this->assertEquals((int) $u['othermgr']->id, $employee->manager_userid);

        $consumer = $writer->get_data([$base, 'consumer_profile']);
        $this->assertSame('friend', $consumer->referral_source);
        $this->assertSame('https://example.invalid/payments/' . $u['subject']->id,
            $consumer->payment_history_url);

        $partner = $writer->get_data([$base, 'partner_employee_profile']);
        $this->assertSame('P' . $u['subject']->id, $partner->partner_employee_id);

        $operator = $writer->get_data([$base, 'operator_profile']);
        $this->assertSame('support', $operator->operator_role);

        // The subject manages one employee and one partner employee. They are
        // told the links exist - counts only; the reports' own profile fields
        // are the reports' data and are not exported to their manager.
        $managed = $writer->get_data([$base, 'recorded_as_manager']);
        $this->assertEquals(['employee_profiles' => 1, 'partner_employee_profiles' => 1],
            (array) $managed);
    }

    public function test_export_for_a_user_with_no_rows_writes_nothing(): void {
        $nobody = $this->getDataGenerator()->create_user();
        $sys = \context_system::instance();
        $this->export_context_data_for_user((int) $nobody->id, $sys, self::COMPONENT);

        $this->assertFalse(writer::with_context($sys)->has_any_data());
    }
}
