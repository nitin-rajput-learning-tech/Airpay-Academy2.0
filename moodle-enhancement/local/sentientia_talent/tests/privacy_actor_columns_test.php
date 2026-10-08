<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_talent;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_talent\privacy\provider;

/**
 * Owner decision, 2026-10-07 (courses cluster doc item "privacy", rule R9 of the BizLMS import): the user who last edited a
 * career path, a succession nomination or an opportunity (usermodified) is declared, exported, and removed from the row
 * when that person is erased. Career paths were not declared at all.
 *
 * The rows belong to the tenant's talent configuration and to the people they name, not to the editor, so an erasure keeps
 * them and sets usermodified to 0 (the signed users.erasure_treatment = anonymise design for actor columns).
 *
 * @package    local_sentientia_talent
 * @category   test
 * @covers     \local_sentientia_talent\privacy\provider
 *
 * @group local_sentientia_talent
 */
final class privacy_actor_columns_test extends \core_privacy\tests\provider_testcase {

    private const PATH = 'local_sentientia_talent_path';
    private const SUCC = 'local_sentientia_talent_succ';
    private const OPP = 'local_sentientia_talent_opp';
    private const COMPONENT = 'local_sentientia_talent';

    /** @var \stdClass Edited the "a" rows. */
    private \stdClass $editor;
    /** @var \stdClass Edited the "b" rows. */
    private \stdClass $other;
    /** @var \stdClass The nominated candidate. */
    private \stdClass $candidate;
    /** @var int[] Row ids by name. */
    private array $rows = [];

    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        $this->editor = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        $this->candidate = $this->getDataGenerator()->create_user();
        $t = 1700000000;
        foreach (['a' => $this->editor, 'b' => $this->other] as $name => $user) {
            $this->rows['path_' . $name] = (int) $DB->insert_record(self::PATH, (object) [
                'costcenterid' => 1, 'name' => 'Path ' . $name, 'from_designation' => 'Agent', 'to_designation' => 'Lead',
                'sort_order' => 0, 'active' => 1, 'usermodified' => $user->id, 'timecreated' => $t, 'timemodified' => $t + 3]);
            $this->rows['succ_' . $name] = (int) $DB->insert_record(self::SUCC, (object) [
                'costcenterid' => 1, 'designation' => 'Role ' . $name, 'candidateid' => $this->candidate->id,
                'readiness' => 'developing', 'notes' => 'Sensitive note ' . $name, 'usermodified' => $user->id,
                'timecreated' => $t, 'timemodified' => $t + 4]);
            $this->rows['opp_' . $name] = (int) $DB->insert_record(self::OPP, (object) [
                'costcenterid' => 1, 'title' => 'Opportunity ' . $name, 'postedby' => $user->id, 'status' => 'open',
                'usermodified' => $user->id, 'timecreated' => $t, 'timemodified' => $t + 5]);
        }
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, self::COMPONENT, [\context_system::instance()->id]);
    }

    private function editor_of(string $table, int $id): int {
        global $DB;
        return (int) $DB->get_field($table, 'usermodified', ['id' => $id], MUST_EXIST);
    }

    public function test_career_paths_are_declared_and_every_usermodified_is_declared_with_a_string(): void {
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = $item->get_privacy_fields();
        }
        foreach ([self::PATH, self::SUCC, self::OPP] as $table) {
            $this->assertArrayHasKey($table, $declared, "{$table} is declared");
            $this->assertArrayHasKey('usermodified', $declared[$table], "{$table}.usermodified is declared");
        }
        foreach ($declared as $table => $fields) {
            foreach ($fields as $column => $identifier) {
                $this->assertTrue(get_string_manager()->string_exists($identifier, self::COMPONENT),
                    "{$table}.{$column}: the string {$identifier} exists");
            }
        }
    }

    public function test_every_new_string_has_a_hindi_pair(): void {
        $string = [];
        include(__DIR__ . '/../lang/en/local_sentientia_talent.php');
        $en = $string;
        $string = [];
        include(__DIR__ . '/../lang/hi/local_sentientia_talent.php');
        $hi = $string;
        foreach (['privacy:metadata:path', 'privacy:metadata:path:name', 'privacy:metadata:path:usermodified',
                  'privacy:metadata:path:timemodified', 'privacy:metadata:succ:usermodified',
                  'privacy:metadata:opp:usermodified'] as $key) {
            $this->assertArrayHasKey($key, $en, "{$key} exists");
            $this->assertArrayHasKey($key, $hi, "{$key} has a Hindi string");
        }
    }

    public function test_the_editor_exports_what_they_last_edited_and_never_a_note(): void {
        provider::export_user_data($this->approved($this->editor));

        $data = json_decode(json_encode(writer::with_context(\context_system::instance())
            ->get_data(['Talent — records I last edited'])), true);
        $seen = [];
        foreach ($data['records'] as $record) {
            $seen[$record['table']][] = (int) $record['id'];
        }
        $this->assertSame([$this->rows['path_a']], $seen[self::PATH]);
        $this->assertSame([$this->rows['succ_a']], $seen[self::SUCC]);
        $this->assertSame([$this->rows['opp_a']], $seen[self::OPP]);
        $this->assertStringNotContainsString('Sensitive note', json_encode($data));
    }

    public function test_erasing_an_editor_keeps_the_rows_and_removes_the_person(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->editor));

        foreach (['path' => self::PATH, 'succ' => self::SUCC, 'opp' => self::OPP] as $name => $table) {
            $this->assertSame(0, $this->editor_of($table, $this->rows[$name . '_a']), "{$table}: the editor is removed");
            $this->assertSame((int) $this->other->id, $this->editor_of($table, $this->rows[$name . '_b']),
                "{$table}: another editor's row is untouched");
        }
        $this->assertSame('Path a', $DB->get_field(self::PATH, 'name', ['id' => $this->rows['path_a']]), 'the path stays');
        $this->assertSame('Opportunity a', $DB->get_field(self::OPP, 'title', ['id' => $this->rows['opp_a']]));
        $this->assertTrue($DB->record_exists(self::SUCC, ['id' => $this->rows['succ_a']]),
            'the nomination of another person stays');
    }

    public function test_the_user_list_and_the_bulk_erasure_cover_editors(): void {
        $context = \context_system::instance();
        $list = new userlist($context, self::COMPONENT);
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        $this->assertContains((int) $this->editor->id, $ids);
        $this->assertContains((int) $this->other->id, $ids);

        provider::delete_data_for_users(new approved_userlist($context, self::COMPONENT, [$this->editor->id]));
        foreach (['path' => self::PATH, 'succ' => self::SUCC, 'opp' => self::OPP] as $name => $table) {
            $this->assertSame(0, $this->editor_of($table, $this->rows[$name . '_a']), $table);
            $this->assertSame((int) $this->other->id, $this->editor_of($table, $this->rows[$name . '_b']), $table);
        }
    }

    public function test_a_context_wipe_removes_every_editor_from_paths_and_opportunities_and_deletes_no_path(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(2, $DB->count_records(self::PATH), 'career paths stay');
        $this->assertSame(2, $DB->count_records(self::OPP), 'postings stay');
        $this->assertSame(0, $DB->count_records_select(self::PATH, 'usermodified <> 0'));
        $this->assertSame(0, $DB->count_records_select(self::OPP, 'usermodified <> 0'));
        $this->assertSame(0, $DB->count_records(self::SUCC), 'nominations name people and are deleted, as before');
    }
}
