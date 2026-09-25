<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_proctoring/lib.php');

/**
 * ADR-031: :review says WHAT, never WHERE.
 *
 * Every reviewer web service was already tenant-scoped (B2). Two gaps are
 * pinned here: the "Review queue (N)" navigation badge counted every tenant's
 * flagged sessions, and a reviewer with no tenant (root 0) passed
 * tenant::require_access(0) for every session stamped costcenterid 0 - every
 * other no-tenant candidate's recordings and verdicts.
 *
 * @package    local_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_proctoring\session_manager
 * @covers     \local_sentientia_proctoring\external\flag_session
 * @covers     \local_sentientia_proctoring\external\get_attempt
 * @covers     ::local_sentientia_proctoring_extend_navigation_user_settings
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function seed_session(int $costcenterid, string $status = 'flagged'): int {
        global $DB;
        $candidate = $this->getDataGenerator()->create_user();
        $now = time();
        return (int) $DB->insert_record('local_sentientia_proctor_sessions', (object) [
            'userid'       => $candidate->id,
            'quizid'       => 0,
            'costcenterid' => $costcenterid,
            'status'       => $status,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /** A tenant admin: the stock manager-archetype role (holds :review, :viewattempts). */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** The label of the review-queue node the navigation hook adds for $USER. */
    private function badge_label(): string {
        global $USER;
        $nav = new \navigation_node(['text' => 'root', 'key' => 'root']);
        local_sentientia_proctoring_extend_navigation_user_settings($nav, $USER,
            \context_system::instance());
        $node = $nav->get('proctorreview');
        $this->assertNotFalse($node, 'A reviewer gets the review-queue node.');
        return (string) $node->text;
    }

    private function assert_outoftenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    public function test_badge_counts_only_the_reviewers_tenant(): void {
        $this->seed_session(1);
        $this->seed_session(177);
        $this->seed_session(177);
        $this->seed_session(1, 'reviewed');
        $queue = get_string('reviewqueue', 'local_sentientia_proctoring');

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame($queue . ' (1)', $this->badge_label(),
            'A /1 reviewer must not see /177\'s flagged sessions in the badge.');

        $this->setUser($this->tenant_admin(''));
        $this->assertSame($queue, $this->badge_label(), 'A reviewer with no tenant counts nothing.');

        $this->setAdminUser();
        $this->assertSame($queue . ' (3)', $this->badge_label());
    }

    public function test_reviewer_with_no_tenant_reaches_no_session(): void {
        global $DB;
        $orphan = $this->seed_session(0);
        $this->setUser($this->tenant_admin(''));

        $this->assert_outoftenant(fn() => session_manager::require_session_access(0),
            'Root 0 must not match the costcenterid-0 bucket.');
        $this->assert_outoftenant(fn() => external\flag_session::execute($orphan),
            'A reviewer with no tenant must not flag anything.');
        $this->assert_outoftenant(fn() => external\get_attempt::execute($orphan),
            'A viewer with no tenant must not read any attempt.');
        $this->assertSame('flagged', $DB->get_field('local_sentientia_proctor_sessions', 'status',
            ['id' => $orphan]));
    }

    public function test_scoped_reviewer_stays_in_tenant_and_admin_is_unchanged(): void {
        $own = $this->seed_session(1);
        $foreign = $this->seed_session(177);

        $this->setUser($this->tenant_admin('/1/4'));
        session_manager::require_session_access(1);
        $this->assertTrue(external\flag_session::execute($own)['success']);
        $this->assert_outoftenant(fn() => external\flag_session::execute($foreign),
            'A /1 reviewer must not flag a /177 session.');

        $this->setAdminUser();
        session_manager::require_session_access(177);
        session_manager::require_session_access(0);
        $this->assertTrue(external\flag_session::execute($foreign)['success']);
    }

    public function test_flag_notification_goes_only_to_a_reviewer_of_that_tenant(): void {
        global $DB;
        $session = fn(int $tenant) => $DB->get_record('local_sentientia_proctor_sessions',
            ['id' => $this->seed_session($tenant)], '*', MUST_EXIST);
        $own = $session(1);
        $foreign = $session(177);
        $orphan = $session(0);

        $reviewer1 = $this->tenant_admin('/1');
        set_config('default_reviewer', $reviewer1->id, 'local_sentientia_proctoring');
        $this->assertSame((int) $reviewer1->id, session_manager::flag_recipient($own));
        $this->assertSame(0, session_manager::flag_recipient($foreign),
            'A /1 default reviewer must not be told about a /177 candidate\'s flagged session.');
        $this->assertSame(0, session_manager::flag_recipient($orphan));

        set_config('default_reviewer', $this->tenant_admin('')->id, 'local_sentientia_proctoring');
        $this->assertSame(0, session_manager::flag_recipient($own), 'A reviewer with no tenant reaches no session.');
        $this->assertSame(0, session_manager::flag_recipient($orphan));

        set_config('default_reviewer', 999999, 'local_sentientia_proctoring');
        $this->assertSame(0, session_manager::flag_recipient($own), 'A reviewer id that is nobody gets nothing.');

        // The shipped default (userid 2, the site admin) still hears about every tenant.
        unset_config('default_reviewer', 'local_sentientia_proctoring');
        $this->assertSame(2, (int) get_admin()->id);
        $this->assertSame(2, session_manager::flag_recipient($foreign));
        $this->assertSame(2, session_manager::flag_recipient($orphan));
    }
}
