<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: recompletion rules, their courses and the reset history stay
 * inside the caller's tenant (2026-09-25).
 *
 * The plugin resolved no tenant at all. :view (manager archetype) listed
 * every tenant's rules and reset history with names and emails; :manage
 * (granted by db/install.php to the tenant-admin role) opened any rule by id
 * and stored every new rule with costcenterid 0, which the engine reads as
 * EVERY tenant - so a tenant admin's rule deleted every tenant's
 * completions, grades and quiz attempts on the daily cron.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_recompletion\rule_access
 * @covers \local_sentientia_recompletion\recompletion_engine
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(?string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A manager-archetype role at system context that also holds :manage,
     * as db/install.php grants it to UAT's tenant-admin role.
     */
    private function tenant_admin(?string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $syscontext = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability('local/sentientia_recompletion:manage', CAP_ALLOW, $managerid, $syscontext->id, true);
        role_assign($managerid, $u->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function rule(int $costcenterid, int $courseid = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_recompletion_rules', (object) [
            'name' => 'Rule ' . $costcenterid, 'courseid' => $courseid, 'period_days' => 30,
            'trigger_type' => 'completion', 'reset_grades' => 0, 'reset_attempts' => 0,
            'enabled' => 1, 'costcenterid' => $costcenterid,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function course_at(?string $openpath): int {
        global $DB;
        $c = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $DB->set_field('course', 'open_path', $openpath, ['id' => $c->id]);
        return (int) $c->id;
    }

    private function completed_long_ago(int $userid, int $courseid): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $courseid, 'timeenrolled' => 0, 'timestarted' => 0,
            'timecompleted' => time() - 400 * DAYSECS, 'reaggregate' => 0,
        ]);
    }

    private function history_row(int $userid, int $courseid): void {
        global $DB;
        $DB->insert_record('local_sentientia_recompletion_history', (object) [
            'ruleid' => 0, 'userid' => $userid, 'courseid' => $courseid, 'reason' => 'cron',
            'reset_grades' => 0, 'reset_attempts' => 0, 'dryrun' => 1, 'timecreated' => time(),
        ]);
    }

    private function history_userids(): array {
        global $DB;
        [$sql, $params] = rule_access::history_user_filter('u');
        $ids = array_map('intval', $DB->get_fieldset_sql(
            "SELECT h.userid FROM {local_sentientia_recompletion_history} h
               JOIN {user} u ON u.id = h.userid
              WHERE $sql", $params));
        sort($ids);
        return $ids;
    }

    private function visible_rule_ccids(): array {
        global $DB;
        [$sql, $params] = rule_access::rules_filter();
        $ids = array_map('intval', $DB->get_fieldset_select('local_sentientia_recompletion_rules',
            'costcenterid', $sql, $params));
        sort($ids);
        return $ids;
    }

    private function assert_refused(callable $call, string $what): void {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, "{$what}: refused for the wrong reason.");
            return;
        }
        $this->fail("{$what}: was not refused.");
    }

    public function test_reset_is_not_granted_to_anyone_by_default(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['capability' => 'local/sentientia_recompletion:reset']),
            ':reset deletes compliance records and nothing tenant-scopes it yet.');
    }

    public function test_a_tenant_admins_rule_resets_only_their_own_tenant(): void {
        global $DB;
        $course = $this->course_at('/1');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $this->completed_long_ago((int) $mine->id, $course);
        $this->completed_long_ago((int) $theirs->id, $course);

        // A tenant admin saves a new rule: it is stamped with their tenant,
        // never 0 (= every tenant).
        $this->setUser($this->tenant_admin('/1'));
        $ccid = rule_access::costcenterid_for_save(null);
        $this->assertSame(1, $ccid);
        $ruleid = $this->rule($ccid, $course);

        // The cron runs it (dry run: history rows, nothing deleted).
        $this->setUser(null);
        recompletion_engine::run_rule($DB->get_record('local_sentientia_recompletion_rules', ['id' => $ruleid]), true);
        $hit = array_map('intval', $DB->get_fieldset_select('local_sentientia_recompletion_history',
            'userid', 'ruleid = :r', ['r' => $ruleid]));
        $this->assertSame([(int) $mine->id], $hit, 'A tenant rule must never touch another tenant\'s learner.');
    }

    public function test_a_tenant_admin_cannot_open_another_tenants_rule_or_a_global_one(): void {
        $mine = $this->rule(1);
        $theirs = $this->rule(177);
        $global = $this->rule(0);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame($mine, (int) rule_access::require_rule($mine)->id);
        $this->assert_refused(fn() => rule_access::require_rule($theirs), 'another tenant\'s rule');
        $this->assert_refused(fn() => rule_access::require_rule($global), 'a global rule');
        $this->assertNull(rule_access::costcenterid_for_save(rule_access::require_rule($mine)),
            'An update keeps the stored tenant.');
        $this->assertSame([1], $this->visible_rule_ccids());
    }

    public function test_history_and_courses_are_the_callers_tenant(): void {
        $own = $this->course_at('/1/4');
        $legacy = $this->course_at(null);
        $foreign = $this->course_at('/177');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $this->history_row((int) $mine->id, $own);
        $this->history_row((int) $theirs->id, $foreign);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame([(int) $mine->id], $this->history_userids());
        rule_access::require_course($own);
        rule_access::require_course($legacy);
        rule_access::require_course(0);
        $this->assert_refused(fn() => rule_access::require_course($foreign), 'another tenant\'s course');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $this->rule(1);
        $this->rule(0);
        $u = $this->user_at('/1/2');
        $this->history_row((int) $u->id, $this->course_at('/1'));

        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin($path));
            $this->assert_refused(fn() => rule_access::caller_root(), "open_path '{$path}' edit.php");
            $this->assert_refused(fn() => rule_access::costcenterid_for_save(null), "open_path '{$path}' save");
            $this->assertSame([], $this->visible_rule_ccids(), "open_path '{$path}' rules list");
            $this->assertSame([], $this->history_userids(), "open_path '{$path}' history");
            $this->assert_refused(fn() => rule_access::require_course($this->course_at('/1')),
                "open_path '{$path}' course picker");
        }
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $theirs = $this->rule(177);
        $global = $this->rule(0);
        $this->rule(1);
        $a = $this->user_at('/1/2');
        $b = $this->user_at('/177/178');
        $this->history_row((int) $a->id, $this->course_at('/1'));
        $this->history_row((int) $b->id, $this->course_at('/177'));

        $this->setAdminUser();
        $this->assertSame(0, rule_access::caller_root());
        $this->assertSame(0, rule_access::costcenterid_for_save(null), 'Site-admin rules stay global, as before.');
        $this->assertSame($theirs, (int) rule_access::require_rule($theirs)->id);
        $this->assertSame($global, (int) rule_access::require_rule($global)->id);
        $this->assertSame([0, 1, 177], $this->visible_rule_ccids());
        $expected = [(int) $a->id, (int) $b->id];
        sort($expected);
        $this->assertSame($expected, $this->history_userids());
    }
}
