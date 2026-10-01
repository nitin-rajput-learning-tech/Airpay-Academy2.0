<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_users\privacy\provider;

/**
 * The privacy provider of local_sentientia_users (ADR-032, users).
 *
 * Until 2026-10-01 the plugin declared a null provider, a claim that it holds no personal data, while its sync
 * error log held the e-mail address, employee code and name of every rejected CSV line. These tests pin what the
 * real provider does: it declares the four tables, finds a person by id AND by the identity written on a rejected
 * line, exports what is theirs (and no other person's line), and on erasure keeps the history while removing the
 * person (signed decision users.erasure_treatment = anonymise); login days are deleted.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\privacy\provider
 * @covers     \local_sentientia_users\legacy_history
 *
 * @group local_sentientia_users
 * @group bizlms_import
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    // The open_* columns of {user}: the employee id an unmatched transcript row is claimed by is open_employeeid.
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var array<string, \stdClass> */
    private array $u = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $gen = $this->getDataGenerator();
        // The uploader, the learner (a rejected line and a transcript are about her) and a stranger.
        $this->u['uploader'] = $gen->create_user(['email' => 'uploader@example.com', 'username' => 'uploader1']);
        $this->u['learner'] = $gen->create_user(['email' => 'asha.rao@example.com', 'username' => 'asha.rao',
            'firstname' => 'Asha', 'lastname' => 'Rao', 'idnumber' => 'EMP100']);
        $this->u['stranger'] = $gen->create_user(['email' => 'stranger@example.com', 'username' => 'stranger1',
            'firstname' => 'Stan', 'lastname' => 'Ger', 'idnumber' => 'EMP999']);
        $this->seed();
    }

    private function seed(): void {
        global $DB;
        $uploader = (int) $this->u['uploader']->id;
        $learner = (int) $this->u['learner']->id;
        $stranger = (int) $this->u['stranger']->id;
        $now = 1700000000;

        $runid = $DB->insert_record('local_sentientia_users_sync_runs', (object) [
            'filename' => 'hrms-march.csv', 'source' => 'web', 'costcenterid' => 1, 'totalrows' => 2, 'insertedcount' => 0,
            'updatedcount' => 0, 'skippedcount' => 0, 'errorcount' => 2, 'warningcount' => 0, 'suspendedcount' => 0,
            'usercreated' => $uploader, 'status' => 'completed', 'error_summary' => null,
            'timecreated' => $now, 'timemodified' => $now]);
        $error = fn(string $email, string $code, string $first, string $last, string $message) => (object) [
            'runid' => $runid, 'csv_line_number' => 2, 'email' => $email, 'employee_code' => $code, 'username' => '-',
            'firstname' => $first, 'lastname' => $last, 'error_message' => $message, 'mandatory_fields' => 'designation',
            'severity' => 'error', 'modified_by' => $uploader, 'timecreated' => $now];
        // A line about the learner (the e-mail and the code are quoted in the message) and a line about somebody else.
        $DB->insert_record('local_sentientia_users_sync_errors', $error('asha.rao@example.com', 'EMP100', 'Asha', 'Rao',
            'Row for EMP100: email asha.rao@example.com is invalid; ASHA is not a known manager'));
        $DB->insert_record('local_sentientia_users_sync_errors', $error('other@example.com', 'EMP555', 'Other', 'Person',
            'Row for EMP555 failed'));

        $transcript = fn(int $userid, string $employee, string $name, int $creator) => (object) [
            'userid' => $userid, 'employee_id' => $employee, 'learner_name' => $name, 'title' => 'Safety 101',
            'training_type' => 'Classroom', 'objectref' => '', 'location' => 'Mumbai', 'courseid' => 0,
            'status' => 'completed', 'status_raw' => 'Completed', 'completion_date_raw' => '15/06/2016',
            'score_raw' => '85', 'hours_raw' => '1', 'timecompleted' => $now, 'score' => 85, 'hours' => 1,
            'costcenterid' => 1, 'open_path' => '/1', 'source' => 'bizlms', 'usercreated' => $creator, 'usermodified' => 0,
            'timecreated' => $now, 'timemodified' => 0];
        $DB->insert_record('local_sentientia_users_transcript', $transcript($learner, 'EMP100', 'Asha Rao', $uploader));
        // An off-platform row that carries the learner's employee code but was never matched to her account.
        $DB->insert_record('local_sentientia_users_transcript', $transcript(0, 'emp100', 'A. Rao', 0));
        $DB->insert_record('local_sentientia_users_transcript', $transcript($stranger, 'EMP999', 'Stan Ger', 0));

        foreach ([$learner => [1, 2], $stranger => [1]] as $userid => $days) {
            foreach ($days as $day) {
                $DB->insert_record('local_sentientia_users_logindays', (object) [
                    'userid' => $userid, 'logindate' => $now + $day * DAYSECS, 'source' => 'web',
                    'timecreated' => $now, 'timemodified' => $now]);
            }
        }
    }

    private function system_list(string $key): approved_contextlist {
        return new approved_contextlist($this->u[$key], 'local_sentientia_users', [\context_system::instance()->id]);
    }

    public function test_the_provider_does_not_claim_to_hold_no_personal_data(): void {
        $this->assertFalse(in_array(\core_privacy\local\metadata\null_provider::class,
            class_implements(provider::class) ?: [], true));
        $collection = provider::get_metadata(new collection('local_sentientia_users'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertEqualsCanonicalizing([
            'local_sentientia_users_sync_runs', 'local_sentientia_users_sync_errors',
            'local_sentientia_users_transcript', 'local_sentientia_users_logindays'], array_keys($declared));
        foreach (['email', 'employee_code', 'username', 'firstname', 'lastname', 'modified_by'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_users_sync_errors']);
        }
        foreach (['userid', 'employee_id', 'learner_name', 'objectref', 'usercreated', 'usermodified'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_users_transcript']);
        }
        $this->assertContains('usercreated', $declared['local_sentientia_users_sync_runs']);
        foreach (['userid', 'logindate', 'timecreated', 'timemodified'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_users_logindays']);
        }
    }

    public function test_every_privacy_string_exists(): void {
        $collection = provider::get_metadata(new collection('local_sentientia_users'));
        $manager = get_string_manager();
        foreach ($collection->get_collection() as $item) {
            $identifiers = array_merge([$item->get_summary()], array_values($item->get_privacy_fields()));
            foreach ($identifiers as $identifier) {
                $this->assertTrue($manager->string_exists($identifier, 'local_sentientia_users'), $identifier);
            }
        }
    }

    public function test_a_person_is_found_by_id_and_by_the_identity_on_a_rejected_line(): void {
        $this->assertEquals([\context_system::instance()->id],
            provider::get_contexts_for_userid((int) $this->u['uploader']->id)->get_contextids(), 'the uploader');
        $this->assertEquals([\context_system::instance()->id],
            provider::get_contexts_for_userid((int) $this->u['learner']->id)->get_contextids(), 'the learner');

        $nobody = $this->getDataGenerator()->create_user(['email' => 'nobody@example.com', 'idnumber' => 'EMP000']);
        $this->assertEquals([], provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());

        $list = new userlist(\context_system::instance(), 'local_sentientia_users');
        provider::get_users_in_context($list);
        $ids = $list->get_userids();
        $this->assertContainsEquals((int) $this->u['uploader']->id, $ids);
        $this->assertContainsEquals((int) $this->u['learner']->id, $ids);
        $this->assertContainsEquals((int) $this->u['stranger']->id, $ids);
        $this->assertNotContainsEquals((int) $nobody->id, $ids);
    }

    public function test_the_learner_gets_her_own_lines_and_not_somebody_elses(): void {
        $context = \context_system::instance();
        provider::export_user_data($this->system_list('learner'));
        $name = get_string('pluginname', 'local_sentientia_users');
        $writer = writer::with_context($context);

        $lines = $writer->get_data([$name, 'hrms_rejected_lines_about_you'])->rows;
        $this->assertCount(1, $lines);
        $this->assertSame('asha.rao@example.com', $lines[0]['email']);
        $this->assertStringNotContainsString('EMP555', json_encode($lines));

        $records = $writer->get_data([$name, 'earlier_training_records'])->rows;
        $this->assertCount(2, $records, 'her own row and the unmatched one that carries her employee code');
        $this->assertCount(2, $writer->get_data([$name, 'login_days'])->rows);
        $this->assertEmpty($writer->get_data([$name, 'hrms_uploads']), 'she uploaded nothing');
        $this->assertEmpty($writer->get_data([$name, 'earlier_training_records_you_entered']),
            'she entered no record about anybody else');
    }

    public function test_the_uploader_is_told_about_their_uploads_but_not_given_other_peoples_lines(): void {
        provider::export_user_data($this->system_list('uploader'));
        $name = get_string('pluginname', 'local_sentientia_users');
        $writer = writer::with_context(\context_system::instance());

        $uploads = $writer->get_data([$name, 'hrms_uploads'])->rows;
        $this->assertCount(1, $uploads);
        $this->assertSame('hrms-march.csv', $uploads[0]['filename']);
        $this->assertSame(2, $writer->get_data([$name, 'hrms_upload_error_rows'])->rows['rows_from_your_uploads']);
        $this->assertEmpty($writer->get_data([$name, 'hrms_rejected_lines_about_you']),
            'the uploader is not the person the lines are about');
        $this->assertStringNotContainsString('other@example.com', json_encode($writer->get_data([$name, 'hrms_upload_error_rows'])));

        // The uploader also entered the learner's transcript row: told as a count, the row is hers, not theirs.
        $entered = $writer->get_data([$name, 'earlier_training_records_you_entered'])->rows;
        $this->assertSame(1, $entered['records_you_created']);
        $this->assertSame(0, $entered['records_you_last_changed']);
        $this->assertEmpty($writer->get_data([$name, 'earlier_training_records']),
            'the uploader is not the learner the row is about');
    }

    public function test_a_person_who_only_changed_a_record_is_told_the_count_and_found_by_the_user_list(): void {
        global $DB;
        $editor = $this->getDataGenerator()->create_user(['email' => 'editor@example.com', 'idnumber' => 'EMP321']);
        $DB->set_field('local_sentientia_users_transcript', 'usermodified', $editor->id, ['employee_id' => 'EMP999']);

        $this->assertEquals([\context_system::instance()->id],
            provider::get_contexts_for_userid((int) $editor->id)->get_contextids(), 'a modifier holds data');
        $list = new userlist(\context_system::instance(), 'local_sentientia_users');
        provider::get_users_in_context($list);
        $this->assertContainsEquals((int) $editor->id, $list->get_userids());

        provider::export_user_data(new approved_contextlist($editor, 'local_sentientia_users',
            [\context_system::instance()->id]));
        $name = get_string('pluginname', 'local_sentientia_users');
        $entered = writer::with_context(\context_system::instance())->get_data([$name, 'earlier_training_records_you_entered'])->rows;
        $this->assertSame(0, $entered['records_you_created']);
        $this->assertSame(1, $entered['records_you_last_changed']);

        provider::delete_data_for_user(new approved_contextlist($editor, 'local_sentientia_users',
            [\context_system::instance()->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_transcript', ['usermodified' => $editor->id]));
        $this->assertEquals([], provider::get_contexts_for_userid((int) $editor->id)->get_contextids());
    }

    // The two halves of the privacy API agree, and an ambiguous employee code claims nobody's rows.

    /**
     * Ids of every account get_users_in_context() lists.
     *
     * @return int[]
     */
    private function listed_userids(): array {
        $list = new userlist(\context_system::instance(), 'local_sentientia_users');
        provider::get_users_in_context($list);
        return array_map('intval', $list->get_userids());
    }

    private function error_line(int $runid, array $values): int {
        global $DB;
        return $DB->insert_record('local_sentientia_users_sync_errors', (object) ($values + [
            'runid' => $runid, 'csv_line_number' => 3, 'email' => '-', 'employee_code' => '-', 'username' => '-',
            'firstname' => '', 'lastname' => '', 'error_message' => 'Row failed', 'mandatory_fields' => '',
            'severity' => 'error', 'modified_by' => 0, 'timecreated' => 1700000000]));
    }

    private function transcript_row(array $values): int {
        global $DB;
        return $DB->insert_record('local_sentientia_users_transcript', (object) ($values + [
            'userid' => 0, 'employee_id' => '', 'learner_name' => '', 'title' => 'Safety 101',
            'training_type' => 'Classroom', 'objectref' => '', 'location' => '', 'courseid' => 0, 'status' => 'completed',
            'status_raw' => 'Completed', 'completion_date_raw' => '', 'score_raw' => '', 'hours_raw' => '',
            'timecompleted' => 1700000000, 'score' => null, 'hours' => null, 'costcenterid' => 1, 'open_path' => '/1',
            'source' => 'bizlms', 'usercreated' => 0, 'usermodified' => 0, 'timecreated' => 1700000000,
            'timemodified' => 0]));
    }

    private function first_run_id(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT MIN(id) FROM {local_sentientia_users_sync_runs}');
    }

    public function test_the_user_list_and_the_context_list_agree_for_every_account(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $runid = $this->first_run_id();

        // Named on a rejected line by e-mail address in another case, by username only, and by employee code only;
        // named on an unmatched transcript row by open_employeeid only.
        $byemail = $gen->create_user(['email' => 'case@example.com', 'username' => 'case.user']);
        $byusername = $gen->create_user(['email' => 'nomatch1@example.com', 'username' => 'rejected.user']);
        $bycode = $gen->create_user(['email' => 'nomatch2@example.com', 'username' => 'coded.user', 'idnumber' => 'EMP777']);
        $byemployeeid = $gen->create_user(['email' => 'nomatch3@example.com', 'username' => 'oe.user']);
        $DB->set_field('user', 'open_employeeid', 'OE-77', ['id' => $byemployeeid->id]);
        $untouched = $gen->create_user(['email' => 'nomatch4@example.com', 'username' => 'untouched', 'idnumber' => 'EMP888']);

        $this->error_line($runid, ['email' => 'CASE@Example.COM']);
        $this->error_line($runid, ['username' => 'rejected.user']);
        $this->error_line($runid, ['employee_code' => 'emp777']);
        $this->transcript_row(['employee_id' => 'oe-77']);

        $listed = $this->listed_userids();
        foreach ([$byemail, $byusername, $bycode, $byemployeeid] as $user) {
            $this->assertContains((int) $user->id, $listed, $user->username . ' is listed');
            $this->assertEquals([\context_system::instance()->id],
                provider::get_contexts_for_userid((int) $user->id)->get_contextids(), $user->username . ' has a context');
        }
        $this->assertNotContains((int) $untouched->id, $listed);
        $this->assertEquals([], provider::get_contexts_for_userid((int) $untouched->id)->get_contextids());

        // The two halves agree for EVERY live account, not only the ones this test thought of.
        foreach ($DB->get_records('user', ['deleted' => 0], 'id', 'id, username') as $user) {
            $this->assertSame(in_array((int) $user->id, $listed, true),
                (bool) provider::get_contexts_for_userid((int) $user->id)->get_contextids(),
                'the user list and the context list disagree about ' . $user->username);
        }
    }

    public function test_an_employee_code_two_live_accounts_hold_claims_nobodys_rows(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $a = $gen->create_user(['email' => 'holder.a@example.com', 'username' => 'holder.a', 'idnumber' => 'SHARED1']);
        $b = $gen->create_user(['email' => 'holder.b@example.com', 'username' => 'holder.b']);
        $DB->set_field('user', 'open_employeeid', 'shared1', ['id' => $b->id]);
        $transcript = $this->transcript_row(['employee_id' => 'SHARED1', 'learner_name' => 'Somebody Shared']);
        $line = $this->error_line($this->first_run_id(), ['employee_code' => 'SHARED1', 'email' => 'unknown@example.com']);
        $context = \context_system::instance();

        // Two live accounts hold the code (one as idnumber, one as open_employeeid, in another case).
        foreach ([$a, $b] as $holder) {
            $this->assertEquals([], provider::get_contexts_for_userid((int) $holder->id)->get_contextids(),
                $holder->username . ' is not given a row that may be somebody else\'s');
            $this->assertNotContains((int) $holder->id, $this->listed_userids());
            $export = legacy_history::export_data((int) $holder->id);
            $this->assertArrayNotHasKey('earlier_training_records', $export);
            $this->assertArrayNotHasKey('hrms_rejected_lines_about_you', $export);
        }
        provider::delete_data_for_user(new approved_contextlist($a, 'local_sentientia_users', [$context->id]));
        $this->assertSame('SHARED1', $DB->get_field('local_sentientia_users_transcript', 'employee_id', ['id' => $transcript]),
            'one holder\'s erasure does not blank the other\'s link');
        $this->assertSame('SHARED1', $DB->get_field('local_sentientia_users_sync_errors', 'employee_code', ['id' => $line]));

        // The other holder's account goes: the code now names exactly one live account, hers.
        $DB->set_field('user', 'deleted', 1, ['id' => $b->id]);
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $a->id)->get_contextids());
        $this->assertContains((int) $a->id, $this->listed_userids());
        $export = legacy_history::export_data((int) $a->id);
        $this->assertCount(1, $export['earlier_training_records']);
        $this->assertCount(1, $export['hrms_rejected_lines_about_you']);

        provider::delete_data_for_user(new approved_contextlist($a, 'local_sentientia_users', [$context->id]));
        $this->assertSame('', $DB->get_field('local_sentientia_users_transcript', 'employee_id', ['id' => $transcript]));
        $this->assertSame('-', $DB->get_field('local_sentientia_users_sync_errors', 'employee_code', ['id' => $line]));
    }

    public function test_one_request_naming_every_holder_of_a_code_may_claim_the_rows(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $a = $gen->create_user(['email' => 'both.a@example.com', 'username' => 'both.a', 'idnumber' => 'PAIR1']);
        $b = $gen->create_user(['email' => 'both.b@example.com', 'username' => 'both.b', 'idnumber' => 'pair1']);
        $transcript = $this->transcript_row(['employee_id' => 'PAIR1', 'learner_name' => 'One Of Them']);

        $this->assertArrayNotHasKey('earlier_training_records', legacy_history::export_data((int) $a->id));
        legacy_history::anonymise_users([(int) $a->id]);
        $this->assertSame('PAIR1', $DB->get_field('local_sentientia_users_transcript', 'employee_id', ['id' => $transcript]));

        // Both of the people who hold the code are erased together: nobody else is left to be wrong about.
        legacy_history::anonymise_users([(int) $a->id, (int) $b->id]);
        $this->assertSame('', $DB->get_field('local_sentientia_users_transcript', 'employee_id', ['id' => $transcript]));
        $this->assertSame('', $DB->get_field('local_sentientia_users_transcript', 'learner_name', ['id' => $transcript]));
    }

    public function test_erasing_the_learner_keeps_the_history_and_removes_her_from_it(): void {
        global $DB;
        provider::delete_data_for_user($this->system_list('learner'));

        $rows = $DB->get_records('local_sentientia_users_sync_errors', null, 'id');
        $this->assertCount(2, $rows, 'no row is deleted');
        $first = reset($rows);
        $this->assertSame('-', $first->email);
        $this->assertSame('-', $first->employee_code);
        $this->assertSame('', $first->firstname);
        $this->assertSame('', $first->lastname);
        foreach (['asha.rao@example.com', 'EMP100', 'ASHA', 'Asha'] as $identifier) {
            $this->assertStringNotContainsStringIgnoringCase($identifier, $first->error_message);
        }
        $this->assertStringContainsString('is invalid', $first->error_message, 'the rest of the message is kept');
        $second = next($rows);
        $this->assertSame('other@example.com', $second->email, 'somebody else\'s line is untouched');

        $transcript = $DB->get_records('local_sentientia_users_transcript', null, 'id');
        $this->assertCount(3, $transcript, 'no transcript row is deleted');
        [$own, $unmatched, $other] = array_values($transcript);
        $this->assertSame(0, (int) $own->userid);
        $this->assertSame('', $own->employee_id);
        $this->assertSame('', $own->learner_name);
        $this->assertSame('', $unmatched->employee_id, 'the unmatched row with her employee code is cleared too');
        $this->assertSame('', $unmatched->learner_name);
        $this->assertSame('Safety 101', $own->title, 'what the training was stays');
        $this->assertSame((int) $this->u['stranger']->id, (int) $other->userid);
        $this->assertSame('Stan Ger', $other->learner_name);

        $this->assertSame(0, $DB->count_records('local_sentientia_users_logindays', ['userid' => $this->u['learner']->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_users_logindays', ['userid' => $this->u['stranger']->id]));
    }

    public function test_erasing_the_uploader_unlinks_the_uploads_and_the_rows_they_made(): void {
        global $DB;
        $uploader = (int) $this->u['uploader']->id;
        provider::delete_data_for_user($this->system_list('uploader'));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_sync_runs', ['usercreated' => $uploader]));
        $this->assertSame(1, $DB->count_records('local_sentientia_users_sync_runs'), 'the run and its counts are kept');
        $this->assertSame(0, $DB->count_records('local_sentientia_users_sync_errors', ['modified_by' => $uploader]));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_transcript', ['usercreated' => $uploader]));
        $this->assertSame(2, $DB->count_records('local_sentientia_users_sync_errors'));
    }

    public function test_erasing_a_list_of_users_does_the_same_for_each(): void {
        global $DB;
        $userlist = new approved_userlist(\context_system::instance(), 'local_sentientia_users',
            [(int) $this->u['learner']->id, (int) $this->u['stranger']->id]);
        provider::delete_data_for_users($userlist);
        $this->assertSame(0, $DB->count_records_select('local_sentientia_users_transcript', 'userid > 0'));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_logindays'));
        $this->assertSame(3, $DB->count_records('local_sentientia_users_transcript'));
    }

    public function test_erasing_the_whole_context_anonymises_everything_and_deletes_the_login_days(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records('local_sentientia_users_logindays'));
        $this->assertSame(0, $DB->count_records('local_sentientia_users_sync_runs', ['usercreated' => $this->u['uploader']->id]));
        foreach ($DB->get_records('local_sentientia_users_sync_errors') as $row) {
            $this->assertSame('-', $row->email);
            $this->assertSame('-', $row->employee_code);
            $this->assertSame('', $row->lastname);
            $this->assertSame(0, (int) $row->modified_by);
            $this->assertSame(legacy_history::REMOVED, $row->error_message);
        }
        foreach ($DB->get_records('local_sentientia_users_transcript') as $row) {
            $this->assertSame(0, (int) $row->userid);
            $this->assertSame('', $row->learner_name);
            $this->assertSame('', $row->employee_id);
        }
        $this->assertSame(3, $DB->count_records('local_sentientia_users_transcript'), 'the history is kept');
    }

    public function test_a_context_that_is_not_the_system_is_left_alone(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $before = $DB->count_records('local_sentientia_users_logindays');
        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
        $this->assertSame($before, $DB->count_records('local_sentientia_users_logindays'));
    }

    public function test_scrubbing_leaves_short_identifiers_alone(): void {
        $this->assertSame('a [removed] b', legacy_history::scrub('a EMP100 b', ['emp100']));
        $this->assertSame('E1 stays', legacy_history::scrub('E1 stays', ['e1']), 'too short to scrub without wrecking the text');
    }
}
