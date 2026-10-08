<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_emails\privacy\provider;

/**
 * Owner decision, 2026-10-07 (courses cluster doc item "privacy", rule R9 of the BizLMS import): the two configuration
 * tables that record who last edited a row, local_sentientia_email_overrides and local_sentientia_email_rules, are declared
 * by the privacy provider, exported, and kept with the editor removed when that person is erased.
 *
 * The rows are a tenant's e-mail templates and notification rules. They are not the editor's data, so an erasure keeps
 * them and sets usermodified to 0 (the signed users.erasure_treatment = anonymise design for actor columns); the export
 * lists what the user edited (ids, key or name, when) and never a template body.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\privacy\provider
 *
 * @group local_sentientia_emails
 */
final class privacy_actor_columns_test extends \core_privacy\tests\provider_testcase {

    private const OVERRIDES = 'local_sentientia_email_overrides';
    private const RULES = 'local_sentientia_email_rules';
    private const COMPONENT = 'local_sentientia_emails';

    /** @var \stdClass The user who edited override A and rule A. */
    private \stdClass $editor;
    /** @var \stdClass The user who edited override B and rule B. */
    private \stdClass $other;
    /** @var int[] Row ids by name. */
    private array $rows = [];

    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        $this->editor = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        $t = 1700000000;
        foreach (['a' => $this->editor, 'b' => $this->other] as $name => $user) {
            $this->rows['override_' . $name] = (int) $DB->insert_record(self::OVERRIDES, (object) [
                'tenant_id' => 1, 'template_key' => 'compliance/' . $name, 'subject' => 'Subject ' . $name,
                'body_html' => '<p>Template body ' . $name . '</p>', 'is_active' => 1, 'usermodified' => $user->id,
                'timecreated' => $t, 'timemodified' => $t + 5]);
            $this->rows['rule_' . $name] = (int) $DB->insert_record(self::RULES, (object) [
                'rule_name' => 'Rule ' . $name, 'rule_type' => 'custom', 'channel' => 'email', 'audience' => 'learner',
                'tenant_id' => 1, 'enabled' => 1, 'priority' => 50, 'conditions_json' => '{"secret":"' . $name . '"}',
                'usermodified' => $user->id, 'timecreated' => $t, 'timemodified' => $t + 7]);
        }
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, self::COMPONENT, [\context_system::instance()->id]);
    }

    private function editor_of(string $table, int $id): int {
        global $DB;
        return (int) $DB->get_field($table, 'usermodified', ['id' => $id], MUST_EXIST);
    }

    public function test_both_tables_are_declared_with_the_actor_column_and_every_string_exists(): void {
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = $item->get_privacy_fields();
        }
        foreach ([self::OVERRIDES, self::RULES] as $table) {
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
        include(__DIR__ . '/../lang/en/local_sentientia_emails.php');
        $en = $string;
        $string = [];
        include(__DIR__ . '/../lang/hi/local_sentientia_emails.php');
        $hi = $string;
        foreach (array_keys($en) as $key) {
            if (strpos($key, 'privacy:metadata:emailoverrides') === 0 || strpos($key, 'privacy:metadata:emailrules') === 0) {
                $this->assertArrayHasKey($key, $hi, "{$key} has a Hindi string");
            }
        }
    }

    public function test_an_editor_has_a_context_and_a_stranger_does_not(): void {
        $system = \context_system::instance()->id;
        $this->assertContains($system, provider::get_contexts_for_userid($this->editor->id)->get_contextids());
        $stranger = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid($stranger->id)->get_contextids());
    }

    public function test_the_editor_exports_what_they_edited_and_never_a_body(): void {
        provider::export_user_data($this->approved($this->editor));
        $data = json_decode(json_encode(
            writer::with_context(\context_system::instance())->get_data(['sentientia_emails'])), true);

        $this->assertSame([$this->rows['override_a']], array_map('intval', array_column($data['overrides_edited'], 'id')),
            'only the override this user edited');
        $this->assertSame('compliance/a', $data['overrides_edited'][0]['template_key']);
        $this->assertSame([$this->rows['rule_a']], array_map('intval', array_column($data['rules_edited'], 'id')));
        $this->assertSame('Rule a', $data['rules_edited'][0]['rule_name']);
        $json = json_encode($data);
        $this->assertStringNotContainsString('Template body', $json, 'a template text is the tenant\'s, not the editor\'s');
        $this->assertStringNotContainsString('secret', $json, 'a rule\'s conditions are the tenant\'s, not the editor\'s');
    }

    public function test_erasing_an_editor_keeps_the_configuration_and_removes_the_person(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->editor));

        $this->assertSame(0, $this->editor_of(self::OVERRIDES, $this->rows['override_a']));
        $this->assertSame(0, $this->editor_of(self::RULES, $this->rows['rule_a']));
        $this->assertSame('Subject a', $DB->get_field(self::OVERRIDES, 'subject', ['id' => $this->rows['override_a']]),
            'the template stays');
        $this->assertSame('Rule a', $DB->get_field(self::RULES, 'rule_name', ['id' => $this->rows['rule_a']]),
            'the rule stays');
        $this->assertSame((int) $this->other->id, $this->editor_of(self::OVERRIDES, $this->rows['override_b']),
            'another editor\'s rows are untouched');
        $this->assertSame((int) $this->other->id, $this->editor_of(self::RULES, $this->rows['rule_b']));
    }

    public function test_the_user_list_and_the_bulk_erasure_cover_editors(): void {
        $context = \context_system::instance();
        $list = new userlist($context, self::COMPONENT);
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        $this->assertContains((int) $this->editor->id, $ids);
        $this->assertContains((int) $this->other->id, $ids);

        provider::delete_data_for_users(new approved_userlist($context, self::COMPONENT, [$this->editor->id]));
        $this->assertSame(0, $this->editor_of(self::OVERRIDES, $this->rows['override_a']));
        $this->assertSame(0, $this->editor_of(self::RULES, $this->rows['rule_a']));
        $this->assertSame((int) $this->other->id, $this->editor_of(self::OVERRIDES, $this->rows['override_b']));
    }

    public function test_a_context_wipe_removes_every_editor_and_deletes_no_configuration(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(2, $DB->count_records(self::OVERRIDES), 'the templates stay');
        $this->assertSame(2, $DB->count_records(self::RULES), 'the rules stay');
        $this->assertSame(0, $DB->count_records_select(self::OVERRIDES, 'usermodified <> 0'));
        $this->assertSame(0, $DB->count_records_select(self::RULES, 'usermodified <> 0'));
    }
}
