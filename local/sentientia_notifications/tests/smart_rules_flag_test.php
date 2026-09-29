<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

use local_sentientia_platform\feature_flags;

defined('MOODLE_INTERNAL') || die();

/**
 * The course_not_started, streak_broken and new_course rules: a bounded LIMIT,
 * and sending gated per tenant by sentientia.notifications.smart_rules.enabled.
 *
 * Until 2026-09-26 the three rules built `"... LIMIT " . (int) get_config(...)
 * ?: 500`, which PHP evaluates as `("... LIMIT 0") ?: ...`, so they selected
 * nobody. db/install.php seeds them ENABLED, so the precedence fix ships with
 * sending behind a default-OFF flag.
 *
 * Tenant angle: with the flag ON for one tenant, only that tenant's users are
 * messaged, a new course reaches only its own tenant, and a user with no
 * resolvable tenant is never messaged (ADR-031 fail closed).
 *
 * @package    local_sentientia_notifications
 * @category   test
 * @covers     \local_sentientia_notifications\rule_engine
 * @group      tenant_isolation
 */
final class smart_rules_flag_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var \phpunit_message_sink Every message the rules send, in every test. */
    private $sink;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // The resolver's statics survive resetAfterTest's rollback.
        feature_flags::invalidate_caches();
        // Catch every message, so no test reaches the email processor.
        $this->sink = $this->redirectMessages();
    }

    protected function tearDown(): void {
        $this->sink->close();
        feature_flags::invalidate_caches();
        parent::tearDown();
    }

    /** Override the flag for one tenant root (0 = global), as the Switchboard would. */
    private function set_flag(int $tenant, ?bool $value): void {
        feature_flags::set(rule_engine::SMART_RULES_FLAG, $tenant, $value,
            (int) get_admin()->id, 'phpunit', 0);
        feature_flags::invalidate_caches();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function rule(string $type, int $days): \stdClass {
        global $DB;
        $id = $DB->insert_record('local_sentientia_notif_rules', (object) [
            'name'         => 'Smart ' . $type,
            'rule_type'    => $type,
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

    /** A course (no tenant path, so new_course ignores it) the users enrolled in 10 days ago. */
    private function not_started(\stdClass ...$users): int {
        $course = $this->getDataGenerator()->create_course();
        foreach ($users as $u) {
            $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student', 'manual',
                time() - 10 * DAYSECS);
        }
        return (int) $course->id;
    }

    private function has_streaks(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_sentientia_streaks');
    }

    /** A 5-day streak last counted 5 days ago: "at risk". No-op without gamification. */
    private function broken_streak(\stdClass ...$users): void {
        global $DB;
        if (!$this->has_streaks()) {
            return;
        }
        foreach ($users as $u) {
            $DB->insert_record('local_sentientia_streaks', (object) [
                'userid'          => $u->id,
                'current_streak'  => 5,
                'longest_streak'  => 5,
                'last_login_date' => date('Y-m-d', strtotime('-5 days')),
                'total_points'    => 0,
                'timemodified'    => time(),
            ]);
        }
    }

    /** A visible course created now, in the tenant tree at $path. */
    private function new_course_at(string $path): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return (int) $course->id;
    }

    /** Recipient ids of the rule's 'sent' log rows, sorted, one entry per row. */
    private function sent_to(\stdClass $rule): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select('local_sentientia_notif_log', 'userid',
            'ruleid = :rid AND status = :st', ['rid' => $rule->id, 'st' => 'sent']));
        sort($ids);
        return $ids;
    }

    private static function ids(\stdClass ...$users): array {
        $ids = array_map(fn($u) => (int) $u->id, $users);
        sort($ids);
        return $ids;
    }

    public function test_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(rule_engine::SMART_RULES_FLAG, $registry);
        $this->assertFalse($registry[rule_engine::SMART_RULES_FLAG]['default']);
        $this->assertSame([], rule_engine::smart_rules_roots(), 'Default OFF in every tenant.');
    }

    public function test_flag_off_the_rules_send_and_log_nothing_even_when_users_match(): void {
        global $DB;
        $learner = $this->user_at('/1/5');
        $this->not_started($learner);
        $this->broken_streak($learner);
        $this->new_course_at('/1');
        $rules = [
            $this->rule('course_not_started', 3),
            $this->rule('streak_broken', 2),
            $this->rule('new_course', 0),
        ];

        foreach ($rules as $rule) {
            $this->assertSame(['sent' => 0, 'skipped' => 0], rule_engine::process_rule($rule),
                "$rule->rule_type must be a no-op while the flag is OFF.");
            $this->assertSame(0, $DB->count_records('local_sentientia_notif_log', ['ruleid' => $rule->id]),
                "$rule->rule_type must not log a send (or a 'sending' claim) while the flag is OFF.");
        }
        $this->assertSame(0, $this->sink->count(), 'No message may leave while the flag is OFF.');

        // Control: the same data does match once the flag is ON, with
        // batch_limit unset, so the OFF result above is the flag's doing and
        // not an empty query (the old code ran every one of these at LIMIT 0).
        $this->set_flag(0, true);
        $this->assertSame([(int) $learner->id], $this->sent_to_after($rules[0]));
        $this->assertSame([(int) $learner->id], $this->sent_to_after($rules[2]));
        if ($this->has_streaks()) {
            $this->assertSame([(int) $learner->id], $this->sent_to_after($rules[1]));
        }
        $this->assertGreaterThan(0, $this->sink->count());
    }

    /** Run the rule, then return its recipients. */
    private function sent_to_after(\stdClass $rule): array {
        rule_engine::process_rule($rule);
        return $this->sent_to($rule);
    }

    public function test_flag_on_for_one_tenant_messages_only_that_tenants_users(): void {
        global $DB;
        $own = $this->user_at('/1/5');
        $foreign = $this->user_at('/177/178');
        $notenant = $this->user_at('');
        $this->not_started($own, $foreign, $notenant);
        $this->broken_streak($own, $foreign, $notenant);
        $airpaycourse = $this->new_course_at('/1');
        $zeeacourse = $this->new_course_at('/177');
        $this->set_flag(1, true);

        $this->assertSame([1], rule_engine::smart_rules_roots());
        $this->assertSame(self::ids($own), $this->sent_to_after($this->rule('course_not_started', 3)),
            'A tenant 177 user or a user with no tenant is not nudged while only tenant 1 is ON.');
        if ($this->has_streaks()) {
            $this->assertSame(self::ids($own), $this->sent_to_after($this->rule('streak_broken', 2)));
        }
        $newcourse = $this->rule('new_course', 0);
        $this->assertSame(self::ids($own), $this->sent_to_after($newcourse),
            'The tenant 177 course is not announced while tenant 177 is OFF.');
        $this->assertFalse($DB->record_exists('local_sentientia_notif_log',
            ['ruleid' => $newcourse->id, 'courseid' => $zeeacourse]));
        $this->assertTrue($DB->record_exists('local_sentientia_notif_log',
            ['ruleid' => $newcourse->id, 'userid' => $own->id, 'courseid' => $airpaycourse, 'status' => 'sent']));

        $recipients = array_unique(array_map(fn($m) => (int) $m->useridto, $this->sink->get_messages()));
        $this->assertSame([(int) $own->id], array_values($recipients),
            'Every message went to the tenant 1 user.');
    }

    public function test_flag_on_globally_keeps_each_new_course_in_its_own_tenant(): void {
        global $DB;
        $own = $this->user_at('/1/5');
        $foreign = $this->user_at('/177/178');
        $notenant = $this->user_at('');
        $this->not_started($own, $foreign, $notenant);
        $this->broken_streak($own, $foreign, $notenant);
        $airpaycourse = $this->new_course_at('/1');
        $zeeacourse = $this->new_course_at('/177');
        $this->set_flag(0, true);

        $this->assertSame([1, 77, 177], rule_engine::smart_rules_roots());
        $this->assertSame(self::ids($own, $foreign), $this->sent_to_after($this->rule('course_not_started', 3)),
            'Both tenants are ON; a user with no tenant is still never nudged.');
        if ($this->has_streaks()) {
            $this->assertSame(self::ids($own, $foreign), $this->sent_to_after($this->rule('streak_broken', 2)));
        }

        $newcourse = $this->rule('new_course', 0);
        $this->assertSame(self::ids($own, $foreign), $this->sent_to_after($newcourse));
        $announced = fn(\stdClass $u, int $courseid) => $DB->record_exists('local_sentientia_notif_log',
            ['ruleid' => $newcourse->id, 'userid' => $u->id, 'courseid' => $courseid, 'status' => 'sent']);
        $this->assertTrue($announced($own, $airpaycourse));
        $this->assertTrue($announced($foreign, $zeeacourse));
        $this->assertFalse($announced($own, $zeeacourse), 'A ZEEA course is never announced to Airpay.');
        $this->assertFalse($announced($foreign, $airpaycourse), 'An Airpay course is never announced to ZEEA.');
    }

    public function test_a_tenant_override_off_beats_a_global_on(): void {
        $own = $this->user_at('/1/5');
        $foreign = $this->user_at('/177/178');
        $this->not_started($own, $foreign);
        $this->set_flag(0, true);
        $this->set_flag(177, false);

        $this->assertSame([1, 77], rule_engine::smart_rules_roots());
        $this->assertSame(self::ids($own), $this->sent_to_after($this->rule('course_not_started', 3)));
    }

    public function test_batch_limit_caps_every_run_and_stays_in_tenant(): void {
        $learners = [$this->user_at('/1/5'), $this->user_at('/1/6'), $this->user_at('/1/7')];
        $foreign = $this->user_at('/177/178');
        $this->not_started($foreign, ...$learners);
        $this->broken_streak($foreign, ...$learners);
        $this->new_course_at('/1');
        $this->new_course_at('/177');
        $this->set_flag(1, true);
        set_config('batch_limit', 2, 'local_sentientia_notifications');

        $types = ['course_not_started' => 3, 'new_course' => 0];
        if ($this->has_streaks()) {
            $types['streak_broken'] = 2;
        }
        $own = self::ids(...$learners);
        foreach ($types as $type => $days) {
            $rule = $this->rule($type, $days);
            $result = rule_engine::process_rule($rule);
            $this->assertSame(2, $result['sent'] + $result['skipped'],
                "$type: three tenant 1 users match but batch_limit is 2.");
            $sent = $this->sent_to($rule);
            $this->assertCount(2, $sent);
            $this->assertSame([], array_diff($sent, $own),
                "$type: the capped batch holds tenant 1 users only.");
        }
        $recipients = array_map(fn($m) => (int) $m->useridto, $this->sink->get_messages());
        $this->assertNotContains((int) $foreign->id, $recipients);
    }

    public function test_batch_limit_is_always_a_positive_bounded_number(): void {
        $this->assertSame(rule_engine::DEFAULT_BATCH_LIMIT, rule_engine::batch_limit(), 'Unset.');
        $this->assertSame(500, rule_engine::DEFAULT_BATCH_LIMIT);
        // 0 would reach the DB API as limitnum 0, which means NO limit.
        foreach (['0', '-3', 'abc', ''] as $bad) {
            set_config('batch_limit', $bad, 'local_sentientia_notifications');
            $this->assertSame(rule_engine::DEFAULT_BATCH_LIMIT, rule_engine::batch_limit(), "batch_limit '$bad'.");
        }
        set_config('batch_limit', '25', 'local_sentientia_notifications');
        $this->assertSame(25, rule_engine::batch_limit());
        set_config('batch_limit', '100000', 'local_sentientia_notifications');
        $this->assertSame(rule_engine::MAX_BATCH_LIMIT, rule_engine::batch_limit());
    }

    public function test_course_not_started_nudges_each_course_a_learner_has_not_started(): void {
        global $DB;
        $learner = $this->user_at('/1/5');
        $first = $this->not_started($learner);
        $second = $this->not_started($learner);
        $this->set_flag(1, true);

        // Keyed on userid alone, get_records_sql() kept one of the two rows
        // and raised a debugging notice (which fails this test).
        $rule = $this->rule('course_not_started', 3);
        $this->assertSame(['sent' => 2, 'skipped' => 0], rule_engine::process_rule($rule));
        $courses = array_map('intval', $DB->get_fieldset_select('local_sentientia_notif_log', 'courseid',
            'ruleid = :rid AND status = :st', ['rid' => $rule->id, 'st' => 'sent']));
        sort($courses);
        $this->assertSame([$first, $second], $courses);
    }
}
