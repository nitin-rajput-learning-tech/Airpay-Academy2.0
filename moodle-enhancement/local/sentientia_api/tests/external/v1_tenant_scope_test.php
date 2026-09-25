<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * ADR-031 fix-forward for the v1 REST endpoints (adversarial review S2/S3, 2026-09-25).
 *
 * S2 - create_enrolment is a write that names a target user. It checked the
 * target with tenant::require_path_access(), which lets an empty or NULL
 * open_path through, so a scoped API caller could enrol a no-tenant account
 * (a site admin without an open_path, say) into their tenant's course. It also
 * accepted any roleid. Now: the target goes through
 * tenant::require_same_tenant_user() (fails closed on no tenant), an unscoped
 * course is refused for a scoped caller, an explicit roleid must be one
 * get_assignable_roles() offers in the course, and a scoped caller never gives
 * a manager-archetype role - not even as the site's default.
 *
 * S3 - base::open_v1 and the endpoints branched on is_siteadmin(). A non-admin
 * local/sentientia_platform:crosstenant holder was therefore scoped (and, with
 * no open_path, refused). They now branch on tenant::is_cross_tenant(). The
 * email column of list_enrolments stays SITE-ADMIN ONLY, exactly as before:
 * exposing it to :crosstenant holders is an open product decision.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\external\v1\base
 * @covers     \local_sentientia_api\external\v1\create_enrolment
 * @covers     \local_sentientia_api\external\v1\list_enrolments
 * @covers     \local_sentientia_api\external\v1\list_completions
 * @group tenant_isolation
 */
final class v1_tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform not installed.');
        }
        $this->ensure_bizlms_schema();
        $ff = '\local_sentientia_platform\feature_flags';
        $ff::set('sentientia.api.enabled', 0, true, null, 'phpunit', 0);
        $ff::set('sentientia.api.write.enabled', 0, true, null, 'phpunit', 0);
        $ff::invalidate_caches(); // Statics survive resetAfterTest.
    }

    protected function tearDown(): void {
        if (class_exists('\local_sentientia_platform\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
        parent::tearDown();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function course_at(string $path): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    /**
     * A caller at $path holding the given capabilities at system context.
     *
     * @return array{0:\stdClass,1:int} user, the role id granting the caps
     */
    private function caller_at(string $path, array $caps): array {
        $u = $this->user_at($path);
        $ctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $ctx->id);
        }
        role_assign($roleid, $u->id, $ctx->id);
        accesslib_clear_all_caches_for_unit_testing();
        return [$u, $roleid];
    }

    private static function roleid(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    private function assert_refused(callable $call, string $errorcode, string $message): void {
        try {
            $call();
            $this->fail($message);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $message);
        }
    }

    // ── S2: create_enrolment ─────────────────────────────────────────────

    public function test_a_scoped_caller_cannot_enrol_a_target_with_no_tenant(): void {
        global $DB;
        $course = $this->course_at('/1');
        $pathless = $this->user_at('');
        $admin = get_admin();
        $DB->set_field('user', 'open_path', null, ['id' => $admin->id]);

        [$caller] = $this->caller_at('/1', ['local/sentientia_api:write']);
        $this->setUser($caller);

        foreach (['a user with an empty open_path' => (int) $pathless->id,
                  'a site admin with no open_path' => (int) $admin->id] as $label => $targetid) {
            $this->assert_refused(
                static fn() => create_enrolment::execute((int) $course->id, $targetid, 0),
                'error_outoftenant', "A /1 caller must not enrol {$label}.");
            $this->assertFalse(is_enrolled(\context_course::instance($course->id), $targetid), $label);
        }
    }

    public function test_a_scoped_caller_cannot_enrol_into_an_unscoped_course(): void {
        $course = $this->course_at('');
        $target = $this->user_at('/1/2');
        [$caller] = $this->caller_at('/1', ['local/sentientia_api:write']);
        $this->setUser($caller);

        $this->assert_refused(
            static fn() => create_enrolment::execute((int) $course->id, (int) $target->id, 0),
            'error_outoftenant', 'A course with no open_path cannot be shown to be the caller\'s.');
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $target->id));
    }

    public function test_a_scoped_caller_may_only_give_a_role_they_could_assign(): void {
        $course = $this->course_at('/1');
        $target = $this->user_at('/1/2');
        [$caller] = $this->caller_at('/1', ['local/sentientia_api:write']);
        $this->setUser($caller);

        // No moodle/role:assign at all: an explicit role is refused, the default still works.
        $this->assert_refused(
            static fn() => create_enrolment::execute((int) $course->id, (int) $target->id,
                self::roleid('editingteacher')),
            'error_role_not_assignable', 'The old code accepted any roleid.');
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $target->id));

        $result = create_enrolment::execute((int) $course->id, (int) $target->id, 0);
        $this->assertSame('enrolled', $result['status']);
        $this->assertSame(self::roleid('student'), $result['roleid']);
    }

    public function test_a_scoped_caller_never_gives_a_manager_role(): void {
        $course = $this->course_at('/1');
        $coursectx = \context_course::instance($course->id);
        [$caller, $callerrole] = $this->caller_at('/1',
            ['local/sentientia_api:write', 'moodle/role:assign']);
        // Allowed to assign manager and student - get_assignable_roles() offers both.
        core_role_set_assign_allowed($callerrole, self::roleid('manager'));
        core_role_set_assign_allowed($callerrole, self::roleid('student'));
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($caller);
        $this->assertArrayHasKey(self::roleid('manager'), get_assignable_roles($coursectx, ROLENAME_SHORT),
            'Precondition: the caller could assign manager here by hand.');

        $a = $this->user_at('/1/2');
        $this->assert_refused(
            static fn() => create_enrolment::execute((int) $course->id, (int) $a->id, self::roleid('manager')),
            'error_role_not_assignable', 'A scoped caller must never hand out a manager-archetype role.');
        $this->assertFalse(is_enrolled($coursectx, $a->id));

        // Not even when a site admin has made manager the site's default enrolment role.
        set_config('roleid', self::roleid('manager'), 'enrol_manual');
        $b = $this->user_at('/1/3');
        $this->assert_refused(
            static fn() => create_enrolment::execute((int) $course->id, (int) $b->id, 0),
            'error_role_not_assignable', 'The default role is no way round the manager ban.');
        $this->assertFalse(is_enrolled($coursectx, $b->id));

        // An assignable, ordinary role still works.
        $result = create_enrolment::execute((int) $course->id, (int) $b->id, self::roleid('student'));
        $this->assertSame('enrolled', $result['status']);
        $this->assertTrue(user_has_role_assignment((int) $b->id, self::roleid('student'), $coursectx->id));
    }

    public function test_site_admin_enrolment_is_unchanged(): void {
        $course = $this->course_at('');
        $pathless = $this->user_at('');
        $this->setAdminUser();

        $result = create_enrolment::execute((int) $course->id, (int) $pathless->id,
            self::roleid('editingteacher'));
        $this->assertSame('enrolled', $result['status']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $pathless->id));
    }

    // ── S3: is_cross_tenant(), not is_siteadmin() ────────────────────────

    public function test_a_crosstenant_holder_is_unscoped_in_v1(): void {
        global $DB;
        $course = $this->course_at('/77');
        $public = $this->user_at('/77/80');
        $zeea = $this->user_at('/177/178');
        $this->getDataGenerator()->enrol_user($public->id, $course->id);
        $this->getDataGenerator()->enrol_user($zeea->id, $course->id);
        $now = time();
        foreach ([$public, $zeea] as $u) {
            $DB->insert_record('course_completions', (object) ['userid' => $u->id, 'course' => $course->id,
                'timeenrolled' => $now, 'timestarted' => $now, 'timecompleted' => $now, 'reaggregate' => 0]);
        }

        // Not a site admin, no open_path: open_v1 used to refuse them with error_notenant.
        [$platform] = $this->caller_at('', [tenant::CROSS_TENANT_CAPABILITY,
            'local/sentientia_api:read', 'local/sentientia_api:write']);
        $this->setUser($platform);
        $this->assertFalse(is_siteadmin());

        $enrolled = list_enrolments::execute((int) $course->id, 0, 50)['enrolments'];
        $ids = array_column($enrolled, 'userid');
        $this->assertContains((int) $public->id, $ids);
        $this->assertContains((int) $zeea->id, $ids, 'A :crosstenant holder sees every tenant\'s enrolees.');
        foreach ($enrolled as $row) {
            $this->assertSame('', $row['email'],
                'Email stays site-admin only (exposing it to :crosstenant holders is an open product decision).');
        }

        $done = array_column(list_completions::execute((int) $course->id, 0, 50)['completions'], 'userid');
        $this->assertContains((int) $public->id, $done);
        $this->assertContains((int) $zeea->id, $done);

        // Writes too: another tenant's course, a target with no tenant.
        $pathless = $this->user_at('');
        $result = create_enrolment::execute((int) $course->id, (int) $pathless->id, 0);
        $this->assertSame('enrolled', $result['status']);
    }

    public function test_site_admin_still_sees_email_and_scoped_readers_stay_in_their_tenant(): void {
        global $DB;
        $course = $this->course_at('/1');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $this->getDataGenerator()->enrol_user($mine->id, $course->id);
        $this->getDataGenerator()->enrol_user($theirs->id, $course->id);
        $now = time();
        foreach ([$mine, $theirs] as $u) {
            $DB->insert_record('course_completions', (object) ['userid' => $u->id, 'course' => $course->id,
                'timeenrolled' => $now, 'timestarted' => $now, 'timecompleted' => $now, 'reaggregate' => 0]);
        }

        $this->setAdminUser();
        $rows = list_enrolments::execute((int) $course->id, 0, 50)['enrolments'];
        $byid = array_column($rows, 'email', 'userid');
        $this->assertSame($mine->email, $byid[(int) $mine->id]);
        $this->assertSame($theirs->email, $byid[(int) $theirs->id]);

        [$reader] = $this->caller_at('/1', ['local/sentientia_api:read']);
        $this->setUser($reader);
        $rows = list_enrolments::execute((int) $course->id, 0, 50)['enrolments'];
        $this->assertSame([(int) $mine->id], array_column($rows, 'userid'));
        $this->assertSame('', $rows[0]['email']);
        $this->assertSame([(int) $mine->id],
            array_column(list_completions::execute((int) $course->id, 0, 50)['completions'], 'userid'));
    }

    public function test_a_non_cross_tenant_caller_with_no_tenant_is_refused(): void {
        $course = $this->course_at('/1');
        $target = $this->user_at('/1/2');
        foreach (['', 'garbage'] as $path) {
            [$caller] = $this->caller_at($path, ['local/sentientia_api:read', 'local/sentientia_api:write']);
            $this->setUser($caller);
            foreach ([
                'list_enrolments'  => static fn() => list_enrolments::execute((int) $course->id, 0, 50),
                'list_completions' => static fn() => list_completions::execute((int) $course->id, 0, 50),
                'create_enrolment' => static fn() => create_enrolment::execute((int) $course->id, (int) $target->id, 0),
            ] as $label => $call) {
                $this->assert_refused($call, 'error_notenant', "{$label}: open_path '{$path}' must be refused.");
            }
        }
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $target->id));
    }
}
