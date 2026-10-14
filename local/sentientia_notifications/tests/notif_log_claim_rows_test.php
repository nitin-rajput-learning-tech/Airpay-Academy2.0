<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

defined('MOODLE_INTERNAL') || die();

/**
 * One delivery, one local_sentientia_notif_log row.
 *
 * rule_engine::send() claims a delivery with a 'sending' row (so a parallel
 * cron run skips the duplicate) and used to insert a SECOND row for the
 * outcome, leaving the claim 'sending' forever: logs.php listed every
 * notification twice and the 'sending (N)' count only grew. send() now
 * updates the claim row to 'sent', 'failed' or 'suppressed'. Upgrade step
 * 2026100900 (db/upgradelib.php local_sentientia_notifications_fold_claim_rows)
 * removes the claim rows the old code left beside an outcome row.
 *
 * @package    local_sentientia_notifications
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_notifications\rule_engine
 * @covers     ::local_sentientia_notifications_fold_claim_rows
 * @covers     ::xmldb_local_sentientia_notifications_upgrade
 */
final class notif_log_claim_rows_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/sentientia_notifications/db/upgradelib.php');
    }

    private function seed_rule(string $channel = 'inapp', string $ruletype = 'inactive_user'): \stdClass {
        global $DB;
        $id = $DB->insert_record('local_sentientia_notif_rules', (object) [
            'name'         => 'Test ' . $ruletype,
            'rule_type'    => $ruletype,
            'channel'      => $channel,
            'trigger_days' => 7,
            'audience'     => 'learner',
            'enabled'      => 1,
            'template'     => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        return $DB->get_record('local_sentientia_notif_rules', ['id' => $id], '*', MUST_EXIST);
    }

    /** rule_engine::send() is private; the cron rules reach it through process_rule(). */
    private function send(\stdClass $rule, int $userid, ?int $courseid, string $subject = 'Hello',
                          string $message = 'Body text'): bool {
        $method = new \ReflectionMethod(rule_engine::class, 'send');
        return $method->invoke(null, $rule, $userid, $courseid, $subject, $message);
    }

    private function log_rows(\stdClass $rule): array {
        global $DB;
        return array_values($DB->get_records('local_sentientia_notif_log', ['ruleid' => $rule->id], 'id ASC'));
    }

    private function set_prefs(int $userid, array $overrides): void {
        global $DB;
        $DB->insert_record('local_sentientia_notif_prefs', (object) ($overrides + [
            'userid'            => $userid,
            'channel_inapp'     => 1,
            'channel_email'     => 1,
            'channel_push'      => 0,
            'digest_frequency'  => 'daily',
            'timemodified'      => time(),
        ]));
    }

    // ---------------------------------------------------------------- send(): the single-row lifecycle

    public function test_a_delivery_is_one_row_that_ends_sent_with_its_message(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->seed_rule();
        $sink = $this->redirectMessages();

        $this->assertTrue($this->send($rule, (int) $user->id, null, 'Reminder', 'Please finish your course.'));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows, 'One delivery, one row: the claim row is updated, not followed by a second row.');
        $this->assertSame('sent', $rows[0]->status);
        $this->assertSame('Please finish your course.', $rows[0]->message);
        $this->assertSame('Reminder', $rows[0]->subject);
        $this->assertSame((int) $user->id, (int) $rows[0]->userid);
        $this->assertSame('inapp', $rows[0]->channel);
        $this->assertSame(0, (int) $rows[0]->courseid, 'No course is stored as 0, which is what the duplicate check compares.');
        $this->assertSame(0, $DB->count_records('local_sentientia_notif_log', ['status' => 'sending']),
            'Nothing is left in the claim state.');
        $this->assertSame(1, $sink->count());
        $sink->close();
    }

    public function test_a_course_delivery_keeps_its_course(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $rule = $this->seed_rule();
        $sink = $this->redirectMessages();

        $this->assertTrue($this->send($rule, (int) $user->id, (int) $course->id));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $course->id, (int) $rows[0]->courseid);
        $sink->close();
    }

    public function test_a_second_run_inside_24_hours_is_skipped_and_adds_no_row(): void {
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->seed_rule();
        $sink = $this->redirectMessages();

        $this->assertTrue($this->send($rule, (int) $user->id, null));
        $this->assertFalse($this->send($rule, (int) $user->id, null), 'Same rule + user + course inside 24 hours.');

        $this->assertCount(1, $this->log_rows($rule));
        $this->assertSame(1, $sink->count(), 'The recipient is messaged once.');
        $sink->close();
    }

    public function test_a_pending_claim_stops_a_parallel_run_and_is_left_alone(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->seed_rule();
        // The other run has claimed the delivery and has not recorded an outcome yet.
        $claimid = $DB->insert_record('local_sentientia_notif_log', (object) [
            'ruleid' => $rule->id, 'userid' => $user->id, 'courseid' => 0, 'channel' => 'inapp',
            'status' => 'sending', 'subject' => 'Hello', 'timecreated' => time()]);
        $sink = $this->redirectMessages();

        $this->assertFalse($this->send($rule, (int) $user->id, null));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame($claimid, (int) $rows[0]->id);
        $this->assertSame('sending', $rows[0]->status, 'The running delivery still owns its claim.');
        $this->assertNull($rows[0]->message);
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    public function test_a_claim_older_than_24_hours_no_longer_blocks(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->seed_rule();
        $DB->insert_record('local_sentientia_notif_log', (object) [
            'ruleid' => $rule->id, 'userid' => $user->id, 'courseid' => 0, 'channel' => 'inapp',
            'status' => 'sending', 'subject' => 'Hello', 'timecreated' => time() - DAYSECS - 60]);
        $sink = $this->redirectMessages();

        $this->assertTrue($this->send($rule, (int) $user->id, null));

        $this->assertCount(2, $this->log_rows($rule), 'The old claim is history; this delivery has its own row.');
        $sink->close();
    }

    public function test_a_channel_opt_out_settles_the_claim_as_suppressed_and_keeps_the_window(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->set_prefs((int) $user->id, ['channel_inapp' => 0]);
        $rule = $this->seed_rule('inapp');
        $sink = $this->redirectMessages();

        $this->assertFalse($this->send($rule, (int) $user->id, null, 'Hello', 'Opted out of this.'));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame('suppressed', $rows[0]->status, 'Not left as a permanent "sending" row.');
        $this->assertSame('Opted out of this.', $rows[0]->message);
        $this->assertSame(0, $sink->count(), 'Nothing is sent to a user who opted out.');

        // The settled row is the 24-hour duplicate window: the rule is not re-evaluated on the next cron run.
        $this->assertFalse($this->send($rule, (int) $user->id, null));
        $this->assertCount(1, $this->log_rows($rule));
        $sink->close();
    }

    public function test_a_rule_type_opt_out_is_suppressed(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->set_prefs((int) $user->id, ['disabled_rule_types' => 'streak_broken, inactive_user']);
        $rule = $this->seed_rule('inapp', 'inactive_user');
        $sink = $this->redirectMessages();

        $this->assertFalse($this->send($rule, (int) $user->id, null));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame('suppressed', $rows[0]->status);
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    public function test_quiet_hours_are_suppressed(): void {
        $user = $this->getDataGenerator()->create_user();
        $hour = (int) date('G');
        // A one-hour window that contains this hour (it wraps midnight at 23).
        $this->set_prefs((int) $user->id, ['quiet_hours_start' => $hour, 'quiet_hours_end' => ($hour + 1) % 24]);
        $rule = $this->seed_rule();
        $sink = $this->redirectMessages();

        $this->assertFalse($this->send($rule, (int) $user->id, null));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame('suppressed', $rows[0]->status);
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    public function test_a_message_that_cannot_be_sent_settles_as_failed(): void {
        // message_send() refuses a recipient that does not exist (and says so with a debugging notice).
        $missingid = 987654;
        $rule = $this->seed_rule();
        $sink = $this->redirectMessages();

        $this->assertFalse($this->send($rule, $missingid, null, 'Hello', 'Nobody to read this.'),
            'A delivery that could not be sent is not reported as sent.');
        $this->assertDebuggingCalled('Attempt to send msg to unknown user');

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame('failed', $rows[0]->status);
        $this->assertSame('Nobody to read this.', $rows[0]->message);
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    public function test_the_push_channel_is_logged_sent_without_a_message_send(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->set_prefs((int) $user->id, ['channel_push' => 1]);
        $rule = $this->seed_rule('push');
        $sink = $this->redirectMessages();

        $this->assertTrue($this->send($rule, (int) $user->id, null));

        $rows = $this->log_rows($rule);
        $this->assertCount(1, $rows);
        $this->assertSame('sent', $rows[0]->status);
        $this->assertSame(0, $sink->count(), 'The push channel has no Moodle message behind it.');
        $sink->close();
    }

    public function test_the_navbar_list_shows_delivered_notifications_only(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_notifications/lib.php');
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->seed_rule();
        $now = time();
        foreach (['sent' => 'Delivered', 'read' => 'Already read', 'suppressed' => 'Suppressed one',
                  'failed' => 'Failed one', 'sending' => 'Claim only'] as $status => $subject) {
            $DB->insert_record('local_sentientia_notif_log', (object) [
                'ruleid' => $rule->id, 'userid' => $user->id, 'courseid' => 0, 'channel' => 'inapp',
                'status' => $status, 'subject' => $subject,
                'message' => $status === 'sending' ? null : 'Text of ' . $subject,
                'timecreated' => $now]);
        }

        $list = local_sentientia_notifications_get_for_navbar((int) $user->id);

        $subjects = array_column($list['notifications'], 'subject');
        sort($subjects);
        $this->assertSame(['Already read', 'Delivered'], $subjects,
            'A suppressed row holds the text the user opted out of; failed and claim rows were never delivered.');
        $this->assertSame(1, $list['unread_count']);
    }

    // ------------------------------------------- upgrade 2026100900: fold the claim rows the old code left

    private const FIXED_NOW = 1790000000;

    /** Insert a log row; the defaults are a claim row. Returns its id. */
    private function insert(array $fields): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_notif_log', (object) ($fields + [
            'ruleid'      => 1,
            'userid'      => 1,
            'courseid'    => 0,
            'channel'     => 'inapp',
            'subject'     => 'Subj',
            'message'     => null,
            'status'      => 'sending',
            'timecreated' => self::FIXED_NOW - 10 * DAYSECS,
            'timeread'    => null,
        ]));
    }

    /** The outcome row the old send() inserted beside a claim: NULL courseid when there was no course. */
    private function legacy_outcome(array $claim, array $overrides = []): int {
        return $this->insert($overrides + [
            'ruleid'      => $claim['ruleid'],
            'userid'      => $claim['userid'],
            'courseid'    => $claim['courseid'] ?? null,
            'channel'     => $claim['channel'] ?? 'inapp',
            'subject'     => $claim['subject'] ?? 'Subj',
            'message'     => 'Body text',
            'status'      => 'sent',
            'timecreated' => $claim['timecreated'] + 1,
        ]);
    }

    private function count_log(array $conditions = []): int {
        global $DB;
        return $DB->count_records('local_sentientia_notif_log', $conditions);
    }

    public function test_a_claim_row_beside_its_outcome_row_is_removed_and_the_outcome_kept(): void {
        global $DB;
        $t = self::FIXED_NOW - 10 * DAYSECS;
        $claim = ['ruleid' => 5, 'userid' => 7, 'timecreated' => $t];
        $claimid = $this->insert($claim);
        $outcomeid = $this->legacy_outcome($claim);

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        $this->assertSame(['removed' => 1, 'kept' => 0], $result);
        $this->assertFalse($DB->record_exists('local_sentientia_notif_log', ['id' => $claimid]));
        $outcome = $DB->get_record('local_sentientia_notif_log', ['id' => $outcomeid], '*', MUST_EXIST);
        $this->assertSame('sent', $outcome->status);
        $this->assertSame('Body text', $outcome->message);
        $this->assertSame($t + 1, (int) $outcome->timecreated);
        $this->assertNull($outcome->courseid, 'An old outcome row is not rewritten.');
    }

    public function test_a_read_outcome_row_counts_and_keeps_its_read_state(): void {
        global $DB;
        $t = self::FIXED_NOW - 10 * DAYSECS;
        $claim = ['ruleid' => 5, 'userid' => 7, 'timecreated' => $t];
        $this->insert($claim);
        $outcomeid = $this->legacy_outcome($claim, ['status' => 'read', 'timeread' => $t + 600]);

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        $this->assertSame(1, $result['removed']);
        $outcome = $DB->get_record('local_sentientia_notif_log', ['id' => $outcomeid], '*', MUST_EXIST);
        $this->assertSame('read', $outcome->status);
        $this->assertSame($t + 600, (int) $outcome->timeread);
    }

    public function test_a_course_delivery_pairs_on_the_course(): void {
        $t = self::FIXED_NOW - 10 * DAYSECS;
        $claim = ['ruleid' => 5, 'userid' => 7, 'courseid' => 42, 'timecreated' => $t];
        $this->insert($claim);
        $this->legacy_outcome($claim);

        $other = ['ruleid' => 5, 'userid' => 8, 'courseid' => 42, 'timecreated' => $t];
        $this->insert($other);
        $this->legacy_outcome($other, ['courseid' => 43]);

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        $this->assertSame(['removed' => 1, 'kept' => 1], $result,
            'The claim whose only neighbour is about another course is kept.');
    }

    public function test_a_claim_with_no_outcome_row_is_kept_untouched(): void {
        global $DB;
        // The old code wrote no outcome row for a delivery a preference suppressed (and for an interrupted one).
        $orphanid = $this->insert(['ruleid' => 5, 'userid' => 7]);
        // Not a claim row (it has a message), so never looked at.
        $stray = $this->insert(['ruleid' => 5, 'userid' => 8, 'message' => 'Has text', 'status' => 'sending']);

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        $this->assertSame(['removed' => 0, 'kept' => 1], $result);
        $orphan = $DB->get_record('local_sentientia_notif_log', ['id' => $orphanid], '*', MUST_EXIST);
        $this->assertSame('sending', $orphan->status, 'Neither relabelled "suppressed" nor "failed": the data cannot say which it was.');
        $this->assertTrue($DB->record_exists('local_sentientia_notif_log', ['id' => $stray]));
    }

    public function test_only_a_true_outcome_row_of_the_same_delivery_pairs(): void {
        $t = self::FIXED_NOW - 10 * DAYSECS;
        $variants = [
            'another subject'          => ['subject' => 'Another subject'],
            'another channel'          => ['channel' => 'email'],
            'a failed row'             => ['status' => 'failed'],
            'a settled suppressed row' => ['status' => 'suppressed'],
            'a row with no message'    => ['message' => null],
            'another rule'             => ['ruleid' => 6],
            'written before the claim' => ['timecreated' => $t - 5],
            'written an hour later'    => ['timecreated' => $t + HOURSECS + 1],
            'another course'           => ['courseid' => 99],
        ];
        $rows = 0;
        foreach ($variants as $override) {
            $user = $this->getDataGenerator()->create_user();
            $claim = ['ruleid' => 5, 'userid' => (int) $user->id, 'timecreated' => $t];
            $this->insert($claim);
            $this->legacy_outcome($claim, $override);
            $rows += 2;
        }
        // Another user's outcome row is not this user's.
        $lonely = $this->getDataGenerator()->create_user();
        $neighbour = $this->getDataGenerator()->create_user();
        $this->insert(['ruleid' => 5, 'userid' => (int) $lonely->id, 'timecreated' => $t]);
        $neighbourclaim = ['ruleid' => 5, 'userid' => (int) $neighbour->id, 'timecreated' => $t];
        $this->insert($neighbourclaim);
        $this->legacy_outcome($neighbourclaim);
        $rows += 3;

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        // The lonely claim has no outcome row of its own; only the neighbour's claim pairs.
        $this->assertSame(['removed' => 1, 'kept' => count($variants) + 1], $result);
        $this->assertSame($rows - 1, $this->count_log());
    }

    public function test_the_next_delivery_a_day_later_does_not_cancel_a_kept_claim(): void {
        global $DB;
        $t = self::FIXED_NOW - 10 * DAYSECS;
        // Claim 1 was suppressed (no outcome row). 25 hours later the same rule + user + course delivered:
        // claim 2 + its outcome row. Claim 1 must not pair with that later outcome row.
        $first = ['ruleid' => 5, 'userid' => 7, 'timecreated' => $t];
        $second = ['ruleid' => 5, 'userid' => 7, 'timecreated' => $t + DAYSECS + HOURSECS];
        $claim1 = $this->insert($first);
        $claim2 = $this->insert($second);
        $this->legacy_outcome($second);

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        $this->assertSame(['removed' => 1, 'kept' => 1], $result);
        $this->assertTrue($DB->record_exists('local_sentientia_notif_log', ['id' => $claim1]));
        $this->assertFalse($DB->record_exists('local_sentientia_notif_log', ['id' => $claim2]));
    }

    public function test_removing_a_recent_claim_keeps_the_24_hour_window_for_a_course_less_rule(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->seed_rule();
        // What the old send() left from a delivery an hour ago: claim (courseid 0) + outcome (courseid NULL).
        $now = time();
        $claim = ['ruleid' => (int) $rule->id, 'userid' => (int) $user->id, 'timecreated' => $now - HOURSECS];
        $this->insert($claim);
        $outcomeid = $this->legacy_outcome($claim);
        $this->assertNull($DB->get_field('local_sentientia_notif_log', 'courseid', ['id' => $outcomeid]));

        $result = local_sentientia_notifications_fold_claim_rows($now);

        $this->assertSame(1, $result['removed']);
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_notif_log', 'courseid', ['id' => $outcomeid]),
            'The surviving row now says "no course" the way the claim did.');
        // send() looks for courseid = 0. Without the rewrite the delivery would be sent a second time.
        $sink = $this->redirectMessages();
        $this->assertFalse($this->send($rule, (int) $user->id, null, 'Subj'));
        $this->assertCount(1, $this->log_rows($rule));
        $this->assertSame(0, $sink->count());
        $sink->close();
    }

    public function test_the_clean_up_is_idempotent(): void {
        $t = self::FIXED_NOW - 10 * DAYSECS;
        $claim = ['ruleid' => 5, 'userid' => 7, 'timecreated' => $t];
        $this->insert($claim);
        $this->legacy_outcome($claim);
        $this->insert(['ruleid' => 5, 'userid' => 8, 'timecreated' => $t]);

        $first = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);
        $rows = $this->count_log();
        $second = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW);

        $this->assertSame(['removed' => 1, 'kept' => 1], $first);
        $this->assertSame(['removed' => 0, 'kept' => 1], $second);
        $this->assertSame($rows, $this->count_log());
    }

    public function test_batches_walk_every_claim_row_once(): void {
        $t = self::FIXED_NOW - 10 * DAYSECS;
        $pairs = 0;
        // Pairs and orphans interleaved, so a batch holds both.
        for ($i = 1; $i <= 9; $i++) {
            $claim = ['ruleid' => 5, 'userid' => 100 + $i, 'timecreated' => $t];
            $this->insert($claim);
            if ($i % 3 !== 0) {
                $this->legacy_outcome($claim);
                $pairs++;
            }
        }

        $result = local_sentientia_notifications_fold_claim_rows(self::FIXED_NOW, 2);

        $this->assertSame(['removed' => $pairs, 'kept' => 9 - $pairs], $result);
        $this->assertSame($pairs, $this->count_log(['status' => 'sent']));
        $this->assertSame(9 - $pairs, $this->count_log(['status' => 'sending']));
    }

    public function test_the_upgrade_step_runs_the_clean_up(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/sentientia_notifications/db/upgrade.php');

        $t = time() - 10 * DAYSECS;
        $claim = ['ruleid' => 5, 'userid' => 7, 'timecreated' => $t];
        $claimid = $this->insert($claim);
        $this->legacy_outcome($claim);
        $orphanid = $this->insert(['ruleid' => 5, 'userid' => 8, 'timecreated' => $t]);

        // A site that ran 2026092500 (the version before this step).
        set_config('version', 2026092500, 'local_sentientia_notifications');
        ob_start();
        $ok = xmldb_local_sentientia_notifications_upgrade(2026092500);
        $out = (string) ob_get_clean();

        $this->assertTrue($ok);
        $this->assertFalse($DB->record_exists('local_sentientia_notif_log', ['id' => $claimid]));
        $this->assertTrue($DB->record_exists('local_sentientia_notif_log', ['id' => $orphanid]));
        $this->assertStringContainsString('removed 1 duplicate claim row', $out);
        $this->assertStringContainsString('kept 1 claim row', $out);
        $this->assertGreaterThanOrEqual(2026100900, (int) get_config('local_sentientia_notifications', 'version'));
    }
}
