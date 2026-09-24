<?php
// This file is part of Sentientia LMS.

/**
 * Right-to-erasure tests for privacy_manager::process_deletion().
 *
 * The defect these guard against has shipped twice: an erasure that left
 * personal data behind and still told the data subject it was 'completed'.
 * On 2026-09-22 the cause was a renamed table in a hand-kept list; on
 * 2026-09-24 it was every table outside that list (the WhatsApp send log with
 * each mobile number, cart credits, calendar tokens, ...). So these tests seed
 * data owned by plugins the list never named, and assert on the rows, not on
 * the method's return value.
 *
 * @package    local_sentientia_privacy
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_privacy;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/failing_erasure_provider.php');

/**
 * @covers \local_sentientia_privacy\privacy_manager
 */
final class privacy_manager_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * A tenant user with data in tables owned by four different plugins, and
     * an approved-to-run erasure request for them.
     *
     * @return array{0: \stdClass, 1: int} the user and the request id
     */
    private function user_with_data_everywhere(): array {
        global $DB;

        $this->ensure_bizlms_schema();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Erasure', 'lastname' => 'Subject', 'open_path' => '/1/2',
        ]);
        $now = time();

        // WhatsApp: a send-log row and NO channel preference. Before
        // 2026-09-24 the provider only reported users with a preference, so
        // this row - and the mobile number in it - survived every erasure.
        $DB->insert_record('local_sentientia_send_log', (object) [
            'userid' => $user->id, 'channel' => 'whatsapp', 'template_key' => 'erasure_probe',
            'status' => 'sent', 'recipient' => '+910000000000', 'timecreated' => $now,
        ]);
        // Cart: a credit balance (deleted outright, not a tax record).
        $DB->insert_record('local_sentientia_cart_credits', (object) [
            'userid' => $user->id,
        ]);
        // Calendar: a feed token, held in the USER context, not the system one.
        $DB->insert_record('local_sentientia_calendar_token', (object) [
            'userid' => $user->id, 'token' => 'erasure-probe-' . $user->id, 'timecreated' => $now,
        ]);
        // Skills: both tables the 2026-09-22 fix was about.
        $DB->insert_record('local_sentientia_user_skills', (object) [
            'userid' => $user->id, 'skillid' => 1,
        ]);
        $DB->insert_record('local_sentientia_user_skill_hist', (object) [
            'userid' => $user->id, 'skillid' => 1, 'timecreated' => $now,
        ]);

        // Inserted directly rather than through request_account_deletion(),
        // which also messages every site admin; the request row is all
        // process_deletion() reads.
        $requestid = $DB->insert_record('local_privacy_requests', (object) [
            'userid' => $user->id, 'request_type' => 'account_delete', 'status' => 'pending',
            'reason' => 'test', 'timecreated' => $now,
        ]);
        return [$user, $requestid];
    }

    /**
     * Rows each seeded table still holds for the user.
     *
     * @param int $userid
     * @return array<string, int>
     */
    private function surviving_rows(int $userid): array {
        global $DB;
        $counts = [];
        foreach (['local_sentientia_send_log', 'local_sentientia_cart_credits',
                  'local_sentientia_calendar_token', 'local_sentientia_user_skills',
                  'local_sentientia_user_skill_hist'] as $table) {
            $counts[$table] = $DB->count_records($table, ['userid' => $userid]);
        }
        return $counts;
    }

    public function test_erasure_reaches_tables_outside_the_hand_kept_list(): void {
        global $DB;
        $this->resetAfterTest();
        [$user, $requestid] = $this->user_with_data_everywhere();
        $adminid = (int) get_admin()->id;

        privacy_manager::process_deletion($requestid, $adminid);

        $this->assertSame(array_fill_keys(array_keys($this->surviving_rows($user->id)), 0),
            $this->surviving_rows($user->id),
            'Every seeded row must be gone - a survivor is personal data the '
            . 'data subject was told had been erased.');

        $request = $DB->get_record('local_privacy_requests', ['id' => $requestid], '*', MUST_EXIST);
        $this->assertSame('completed', $request->status,
            'Nothing failed, so the request must read completed. If this reads partial, '
            . 'the note says why: ' . $request->admin_notes);
        $this->assertEquals($adminid, $request->processed_by);
        $this->assertStringNotContainsString('INCOMPLETE', (string) $request->admin_notes);

        $after = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $this->assertEquals(1, $after->deleted);
        $this->assertSame('Deleted', $after->firstname);
    }

    public function test_a_failing_provider_marks_the_request_partial_not_completed(): void {
        global $DB;
        $this->resetAfterTest();
        [$user, $requestid] = $this->user_with_data_everywhere();

        test\privacy_manager_with_a_failing_provider::process_deletion(
            $requestid, (int) get_admin()->id);

        $request = $DB->get_record('local_privacy_requests', ['id' => $requestid], '*', MUST_EXIST);
        $this->assertSame('partial', $request->status,
            'A provider threw, so some data may survive; completed would be a false success.');
        $this->assertStringContainsString('INCOMPLETE', $request->admin_notes);
        $this->assertStringContainsString('local_sentientia_failing', $request->admin_notes,
            'The note must name what was not erased, or the DPO cannot finish the job.');
        $this->assertStringContainsString(test\failing_erasure_provider::MESSAGE,
            $request->admin_notes);

        // One failure must not stop the others: everything else is still erased.
        $this->assertSame(array_fill_keys(array_keys($this->surviving_rows($user->id)), 0),
            $this->surviving_rows($user->id));
    }

    public function test_only_a_deletion_request_is_processed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $user = $this->getDataGenerator()->create_user(['open_path' => '/77']);
        $requestid = $DB->insert_record('local_privacy_requests', (object) [
            'userid' => $user->id, 'request_type' => 'data_download', 'status' => 'pending',
            'timecreated' => time(),
        ]);

        $this->assertFalse(privacy_manager::process_deletion($requestid, (int) get_admin()->id));
        $this->assertEquals(0, $DB->get_field('user', 'deleted', ['id' => $user->id]));
        $this->assertSame('pending',
            $DB->get_field('local_privacy_requests', 'status', ['id' => $requestid]));
    }
}
