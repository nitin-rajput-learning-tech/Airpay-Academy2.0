<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

defined('MOODLE_INTERNAL') || die();

/**
 * The "learning path stalled" rule reads the real path roster and nudges
 * each stalled learner about their own path only.
 *
 * Until 2026-09-25 the moodle-enhancement copy of rule_engine (the one UAT
 * serves) read local_airpay_lp_users, a table no install.xml defines, so
 * table_exists() was false and the rule silently sent nothing. Both copies
 * also filtered the integer status column with the strings 'enrolled' and
 * 'in_progress': PostgreSQL rejects that, and MySQL casts both strings to 0,
 * so in-progress learners were never matched. The rule now reads
 * local_sentientia_learningpath_users with bound path_manager::ENROL_NEW /
 * ENROL_INPROGRESS values.
 *
 * Tenant angle: the rule is platform-wide (cron), so it processes every
 * tenant's learners - each message goes to the learner on the path and names
 * that learner's own path, never another tenant's.
 *
 * @package    local_sentientia_notifications
 * @category   test
 * @covers     \local_sentientia_notifications\rule_engine::process_rule
 * @group      tenant_isolation
 */
final class learning_path_stalled_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath_users')) {
            $this->markTestSkipped('local_sentientia_learningpath is not installed.');
        }
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** An active learning path at $path. */
    private function path_at(string $path, string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => $path,
            'status'       => \local_sentientia_learningpath\path_manager::STATUS_ACTIVE,
            'visible'      => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /** Put $user on $pathid with $status, enrolled at $timecreated. */
    private function on_path(int $pathid, \stdClass $user, int $status, int $timecreated): void {
        global $DB;
        $DB->insert_record('local_sentientia_learningpath_users', (object) [
            'pathid'        => $pathid,
            'userid'        => $user->id,
            'status'        => $status,
            'timecreated'   => $timecreated,
            'timecompleted' => $status === 2 ? $timecreated : null,
        ]);
    }

    private function rule(int $days): \stdClass {
        global $DB;
        $id = $DB->insert_record('local_sentientia_notif_rules', (object) [
            'name'         => 'Learning path stalled',
            'rule_type'    => 'learning_path_stalled',
            'channel'      => 'inapp',
            'trigger_days' => $days,
            'audience'     => 'learner',
            'enabled'      => 1,
            'template'     => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        return $DB->get_record('local_sentientia_notif_rules', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_stalled_learners_are_nudged_about_their_own_path_only(): void {
        global $DB;
        $old = time() - 30 * DAYSECS;
        $airpaypath = $this->path_at('/1', 'Airpay onboarding path');
        $zeeapath = $this->path_at('/177', 'ZEEA induction path');

        $notstarted = $this->user_at('/1/2');
        $this->on_path($airpaypath, $notstarted,
            \local_sentientia_learningpath\path_manager::ENROL_NEW, $old);
        $inprogress = $this->user_at('/177/178');
        $this->on_path($zeeapath, $inprogress,
            \local_sentientia_learningpath\path_manager::ENROL_INPROGRESS, $old);
        $completed = $this->user_at('/1/2');
        $this->on_path($airpaypath, $completed, 2, $old);
        $justjoined = $this->user_at('/1/2');
        $this->on_path($airpaypath, $justjoined,
            \local_sentientia_learningpath\path_manager::ENROL_NEW, time());
        $sink = $this->redirectMessages();

        $rule = $this->rule(14);
        $result = rule_engine::process_rule($rule);

        $this->assertSame(2, $result['sent'],
            'One not-started and one in-progress learner past the window: both are nudged.');
        $this->assertSame(0, $result['skipped']);

        $sent = $DB->get_records('local_sentientia_notif_log',
            ['ruleid' => $rule->id, 'status' => 'sent'], 'userid ASC', 'userid, message');
        $expected = [(int) $notstarted->id, (int) $inprogress->id];
        sort($expected);
        $this->assertSame($expected, array_map('intval', array_keys($sent)),
            'Never the learner who completed, nor the one who joined today.');

        $this->assertStringContainsString('Airpay onboarding path', $sent[$notstarted->id]->message);
        $this->assertStringNotContainsString('ZEEA induction path', $sent[$notstarted->id]->message);
        $this->assertStringContainsString('ZEEA induction path', $sent[$inprogress->id]->message);
        $this->assertStringNotContainsString('Airpay onboarding path', $sent[$inprogress->id]->message,
            'A ZEEA learner is never told about an Airpay path.');

        $recipients = array_map(fn($m) => (int) $m->useridto, $sink->get_messages());
        sort($recipients);
        $this->assertSame($expected, $recipients, 'The messages go to the stalled learners themselves.');
        $sink->close();
    }

    public function test_the_rule_still_does_nothing_when_nobody_is_stalled(): void {
        global $DB;
        $path = $this->path_at('/77', 'Public starter path');
        $this->on_path($path, $this->user_at('/77/80'),
            \local_sentientia_learningpath\path_manager::ENROL_INPROGRESS, time());
        $sink = $this->redirectMessages();

        $rule = $this->rule(14);
        $this->assertSame(['sent' => 0, 'skipped' => 0], rule_engine::process_rule($rule));
        $this->assertSame(0, $DB->count_records('local_sentientia_notif_log', ['ruleid' => $rule->id]));
        $this->assertSame(0, $sink->count());
        $sink->close();
    }
}
