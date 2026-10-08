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
 * The privacy provider covers what the BizLMS e-mail import added to the delivery log (ADR-032, mapping doc
 * section 11, code fix 7).
 *
 * An imported row names two people. The recipient is the data subject of the whole row (subject, body, status).
 * The user who queued the message (sender_userid) is a data subject of the rows that name them on OTHER people's
 * history: they get those rows in their export (when, never the recipient or the body) and, when erased, the
 * recipients' history is kept with the sender set to 0 rather than deleted.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\privacy\provider
 *
 * @group local_sentientia_emails
 * @group bizlms_import
 */
final class privacy_imported_history_test extends \core_privacy\tests\provider_testcase {

    private const LOG = 'local_sentientia_email_log';
    private const COMPONENT = 'local_sentientia_emails';

    /** @var \stdClass The recipient of the imported row X and of the native row Z, and the sender of row Y. */
    private \stdClass $recipient;
    /** @var \stdClass The sender of row X. */
    private \stdClass $sender;
    /** @var \stdClass The recipient of row Y. */
    private \stdClass $other;
    /** @var int[] Row ids by name. */
    private array $rows = [];

    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        $this->recipient = $this->getDataGenerator()->create_user();
        $this->sender = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        $base = ['channel' => 'email', 'tenant_id' => 1, 'status' => 'sent', 'timecreated' => 1700000000,
            'legacy_source' => 'bizlms', 'timesent' => 1700000100, 'body_html' => '<p>Private body</p>'];
        // X: imported, recipient R, queued by S.
        $this->rows['x'] = (int) $DB->insert_record(self::LOG, (object) ($base + [
            'userid' => $this->recipient->id, 'sender_userid' => $this->sender->id, 'subject' => 'Row X']));
        // Y: imported, recipient O, queued by R.
        $this->rows['y'] = (int) $DB->insert_record(self::LOG, (object) ($base + [
            'userid' => $this->other->id, 'sender_userid' => $this->recipient->id, 'subject' => 'Row Y']));
        // Z: a native row of R: no sender, no body.
        $this->rows['z'] = (int) $DB->insert_record(self::LOG, (object) [
            'userid' => $this->recipient->id, 'tenant_id' => 1, 'channel' => 'email', 'subject' => 'Row Z',
            'template_key' => 'reminders/not_started', 'status' => 'sent', 'timecreated' => 1700000200]);
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, self::COMPONENT, [\context_system::instance()->id]);
    }

    /**
     * @param int $id
     * @return \stdClass|false
     */
    private function row(int $id) {
        global $DB;
        return $DB->get_record(self::LOG, ['id' => $id]);
    }

    public function test_the_columns_the_import_added_are_declared_with_strings(): void {
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = $item->get_privacy_fields();
        }
        $this->assertArrayHasKey(self::LOG, $declared);
        foreach (['sender_userid', 'body_html', 'timesent'] as $column) {
            $this->assertArrayHasKey($column, $declared[self::LOG], "{$column} is declared");
        }
        foreach ($declared as $table => $fields) {
            foreach ($fields as $column => $identifier) {
                $this->assertTrue(get_string_manager()->string_exists($identifier, self::COMPONENT),
                    "{$table}.{$column}: the string {$identifier} exists");
            }
        }
        $this->assertNotContains(\core_privacy\local\metadata\null_provider::class, class_implements(provider::class),
            'the provider holds personal data and must not claim otherwise');
    }

    public function test_a_sender_and_a_recipient_both_have_a_context_but_a_stranger_does_not(): void {
        // get_contextids() hands back the ids as the database returns them (strings on MySQL/MariaDB), and
        // assertContains() compares strictly: compare integers.
        $ids = static fn(int $userid): array => array_map('intval', provider::get_contexts_for_userid($userid)->get_contextids());
        $system = (int) \context_system::instance()->id;
        $this->assertContains($system, $ids((int) $this->recipient->id));
        $this->assertContains($system, $ids((int) $this->sender->id),
            'a user who only queued messages for other people is a data subject');
        $stranger = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid($stranger->id)->get_contextids());
    }

    public function test_the_recipient_exports_their_rows_whole_and_what_they_queued_without_the_body(): void {
        provider::export_user_data($this->approved($this->recipient));
        $data = json_decode(json_encode(
            writer::with_context(\context_system::instance())->get_data(['sentientia_emails'])), true);

        $own = array_column($data['log'], null, 'id');
        $this->assertEqualsCanonicalizing([$this->rows['x'], $this->rows['z']], array_keys($own));
        $this->assertSame('<p>Private body</p>', $own[$this->rows['x']]['body_html'], 'their own message, in full');

        $sent = $data['log_as_sender'];
        $this->assertSame([$this->rows['y']], array_map('intval', array_column($sent, 'id')));
        foreach ($sent as $line) {
            $this->assertArrayNotHasKey('body_html', $line, 'the body belongs to the recipient');
            $this->assertArrayNotHasKey('userid', $line, 'the recipient is somebody else');
            $this->assertArrayNotHasKey('subject', $line);
        }
    }

    public function test_a_sender_who_is_nobodys_recipient_exports_what_they_queued_only(): void {
        provider::export_user_data($this->approved($this->sender));
        $data = json_decode(json_encode(
            writer::with_context(\context_system::instance())->get_data(['sentientia_emails'])), true);
        $this->assertSame([], $data['log']);
        $this->assertSame([$this->rows['x']], array_map('intval', array_column($data['log_as_sender'], 'id')));
    }

    public function test_erasing_a_sender_keeps_the_recipients_history_and_removes_the_sender(): void {
        provider::delete_data_for_user($this->approved($this->sender));

        $x = $this->row($this->rows['x']);
        $this->assertNotFalse($x, 'the recipient\'s history is not deleted');
        $this->assertSame(0, (int) $x->sender_userid, 'the sender is anonymised');
        $this->assertSame((int) $this->recipient->id, (int) $x->userid);
        $this->assertSame('<p>Private body</p>', $x->body_html);
        $this->assertSame((int) $this->recipient->id, (int) $this->row($this->rows['y'])->sender_userid,
            'rows that name somebody else are untouched');
    }

    public function test_erasing_a_recipient_deletes_their_rows_and_anonymises_what_they_queued(): void {
        provider::delete_data_for_user($this->approved($this->recipient));

        $this->assertFalse($this->row($this->rows['x']), 'their own rows go');
        $this->assertFalse($this->row($this->rows['z']));
        $y = $this->row($this->rows['y']);
        $this->assertNotFalse($y, 'another user\'s history stays');
        $this->assertSame(0, (int) $y->sender_userid, 'but no longer names the erased user');
        $this->assertSame((int) $this->other->id, (int) $y->userid);
    }

    public function test_the_user_list_and_the_bulk_erasure_cover_senders(): void {
        $context = \context_system::instance();
        $list = new userlist($context, self::COMPONENT);
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        foreach ([$this->recipient, $this->sender, $this->other] as $user) {
            $this->assertContains((int) $user->id, $ids);
        }

        provider::delete_data_for_users(new approved_userlist($context, self::COMPONENT, [$this->sender->id]));
        $this->assertSame(0, (int) $this->row($this->rows['x'])->sender_userid);
        $this->assertNotFalse($this->row($this->rows['x']), 'the recipient\'s row stays');

        provider::delete_data_for_users(new approved_userlist($context, self::COMPONENT, [$this->recipient->id]));
        $this->assertFalse($this->row($this->rows['x']));
        $this->assertSame(0, (int) $this->row($this->rows['y'])->sender_userid);
    }
}
