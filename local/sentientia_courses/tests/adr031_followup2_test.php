<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_courses/db/upgradelib.php');

use local_sentientia_courses\external\list_courses;
use local_sentientia_courses\external\unenrol_single;

/**
 * ADR-031 follow-up 2 (2026-09-25): three should-fix items from the
 * cross-cutting review of the merged wave, for the course engine.
 *
 *   F1  featured rows a tenant admin pinned before ADR-031 are all
 *       costcenterid 0, because featured.php offered non-site-admins only
 *       "All tenants". After wave 1 their curator could no longer see or
 *       remove them, and the learner widget still showed them to every
 *       tenant. Upgrade step 2026092501
 *       (local_sentientia_courses_rehome_global_featured()) moves each to
 *       its course's tenant list;
 *   F2  a tenant admin could not remove a pathless, out-of-tenant or
 *       site-admin enrolee from a course their tenant owns. Classrooms,
 *       programs and paths already allowed this
 *       (course_manager::require_unenrol_target() and
 *       unenrol_users_by_email());
 *   F3  the Manage Courses "Enrolled" column counted every tenant's
 *       enrolment rows, not the distinct own-tenant users the course's
 *       Enrolled users page counts.
 *
 * Each test uses a manager-archetype role assigned at system context, the
 * shape of UAT role 9, for a tenant admin, alongside the site admin.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_courses\course_manager
 * @covers     \local_sentientia_courses\featured_manager
 * @covers     \local_sentientia_courses\external\unenrol_single
 * @covers     \local_sentientia_courses\external\list_courses
 * @group      tenant_isolation
 */
final class adr031_followup2_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int manager-archetype role, as UAT's tenant-admin role 9 */
    private $tenantadminrole;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->tenantadminrole = (int) $this->getDataGenerator()->create_role([
            'shortname' => 'adr031ff2tenantadmin', 'archetype' => 'manager']);
    }

    // ── fixtures ─────────────────────────────────────────────────────────

    /** A user whose open_path is $path ('' = unresolvable tenant). */
    private function user_at(string $path, array $record = []): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user($record);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin: manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        $u = $this->user_at($path);
        role_assign($this->tenantadminrole, $u->id, \context_system::instance()->id);
        return $u;
    }

    /** A course at an open_path (null = legacy course with no open_path). */
    private function course_at(?string $path, array $record = []): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course($record + ['visible' => 1]);
        $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    private function enrol(\stdClass $user, \stdClass $course): void {
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual');
    }

    /** Run $fn and return the moodle_exception error code it threw ('' if none). */
    private function errorcode(callable $fn): string {
        try {
            $fn();
        } catch (\moodle_exception $e) {
            return (string) $e->errorcode;
        }
        return '';
    }

    private function enrolled(\stdClass $user, \stdClass $course): bool {
        return is_enrolled(\context_course::instance($course->id), $user->id);
    }

    // ── F2: unenrolling from one's own roster ────────────────────────────

    public function test_own_roster_members_can_be_removed_whatever_their_tenant(): void {
        $own = $this->course_at('/1/2');
        $theirs = $this->user_at('/177');
        $pathless = $this->user_at('');
        $siteadmin = get_admin();
        $stranger = $this->user_at('/177');
        foreach ([$theirs, $pathless, $siteadmin] as $u) {
            $this->enrol($u, $own);
        }

        $this->setUser($this->tenant_admin('/1'));
        foreach ([$theirs, $pathless, $siteadmin] as $u) {
            $r = unenrol_single::execute((int) $own->id, (int) $u->id);
            $this->assertTrue($r['unenrolled']);
            $this->assertFalse($this->enrolled($u, $own),
                'a /1 admin may clean an out-of-tenant, pathless or site-admin enrolee off a /1 course');
        }
        // Naming someone who is not on the roster is still refused: the
        // idempotent "success" would otherwise confirm the id exists anywhere.
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => unenrol_single::execute((int) $own->id, (int) $stranger->id)));
        $this->assertSame('error_outoftenant', $this->errorcode(
            fn() => course_manager::require_unenrol_target((int) $own->id, 1, (int) $stranger->id)));

        // An own-tenant user is removable whether enrolled or not (unchanged).
        $mine = $this->user_at('/1/3');
        course_manager::require_unenrol_target((int) $own->id, 1, (int) $mine->id);
        // A cross-tenant caller is never restricted.
        course_manager::require_unenrol_target((int) $own->id, null, (int) $stranger->id);
    }

    public function test_the_roster_exception_does_not_reach_shared_or_legacy_courses(): void {
        $shared = $this->course_at('/177');
        $legacy = $this->course_at(null);
        $theirs = $this->user_at('/177');
        $mine = $this->user_at('/1');
        $this->setAdminUser();
        sharing_manager::share_course((int) $shared->id, [1]);
        foreach ([$shared, $legacy] as $c) {
            $this->enrol($theirs, $c);
            $this->enrol($mine, $c);
        }

        $this->setUser($this->tenant_admin('/1'));
        foreach ([$shared, $legacy] as $c) {
            $this->assertSame('error_outoftenant',
                $this->errorcode(fn() => unenrol_single::execute((int) $c->id, (int) $theirs->id)),
                'a course the /1 tenant does not own keeps the rule-5 target check');
            $this->assertTrue($this->enrolled($theirs, $c));
            unenrol_single::execute((int) $c->id, (int) $mine->id);
            $this->assertFalse($this->enrolled($mine, $c), 'own-tenant learners stay removable');
        }

        // A foreign course is still refused outright, and a caller with no tenant gets nothing.
        $zeea = $this->course_at('/177');
        $this->enrol($theirs, $zeea);
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => unenrol_single::execute((int) $zeea->id, (int) $theirs->id)));
        $this->setUser($this->tenant_admin(''));
        $this->assertSame('invalidtenant',
            $this->errorcode(fn() => unenrol_single::execute((int) $zeea->id, (int) $theirs->id)));
        $this->assertTrue($this->enrolled($theirs, $zeea));
    }

    public function test_bulk_unenrol_lookup_reaches_only_the_callers_own_roster(): void {
        set_config('allowaccountssameemail', 1);
        $own = $this->course_at('/1');
        $shared = $this->course_at('/177');
        $theirs = $this->user_at('/177', ['email' => 'adr031ff2theirs@example.com']);
        $this->user_at('/177', ['email' => 'adr031ff2stranger@example.com']);
        $mine = $this->user_at('/1/2', ['email' => 'adr031ff2mine@example.com']);
        $this->setAdminUser();
        sharing_manager::share_course((int) $shared->id, [1]);
        $this->enrol($theirs, $own);
        $this->enrol($theirs, $shared);

        $this->setUser($this->tenant_admin('/1'));
        $ids = fn(string $email, \stdClass $course) => array_map('intval',
            array_column(course_manager::unenrol_users_by_email($email, $course, 1), 'id'));
        $this->assertSame([(int) $theirs->id], $ids('adr031ff2theirs@example.com', $own),
            'an out-of-tenant enrolee of a /1 course is found by email');
        $this->assertSame([], $ids('adr031ff2stranger@example.com', $own),
            'an out-of-tenant address that is not on the roster still reads "not found"');
        $this->assertSame([], $ids('adr031ff2theirs@example.com', $shared),
            'a course shared in from another tenant gets the in-tenant lookup only');
        $this->assertSame([(int) $mine->id], $ids('adr031ff2mine@example.com', $own));
        $this->assertSame([(int) $mine->id], $ids('adr031ff2mine@example.com', $shared));

        // Two enrolled accounts behind one address: ambiguous, so nobody is guessed.
        $twin = $this->user_at('', ['email' => 'adr031ff2theirs@example.com']);
        $this->enrol($twin, $own);
        $this->assertCount(2, course_manager::unenrol_users_by_email('adr031ff2theirs@example.com', $own, 1));

        // Cross-tenant: the unbounded lookup, as before.
        $this->assertCount(2, course_manager::unenrol_users_by_email('adr031ff2theirs@example.com', $shared, null));
    }

    // ── F3: the Enrolled column ──────────────────────────────────────────

    /** The Enrolled users page's KPI for $course, as the current user (enrolledusers.php). */
    private function page_total_enrolled(\stdClass $course): int {
        global $DB;
        [$usql, $uargs] = \local_sentientia_platform\tenant::path_filter('u');
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE e.courseid = :cid AND {$usql}", ['cid' => $course->id] + $uargs);
    }

    /** The "Enrolled" figure list_courses gives $course, keyed by course id. */
    private function listed_enrolled(): array {
        $r = list_courses::execute('', 'fullname', 'asc', 0, 100, '{}');
        return array_column($r['rows'], 'enrolled', 'id');
    }

    public function test_enrolled_column_counts_what_the_enrolled_users_page_counts(): void {
        global $DB;
        $own = $this->course_at('/1');
        $legacy = $this->course_at(null);
        $twice = $this->user_at('/1/2');
        $mine = $this->user_at('/1');
        $theirs = $this->user_at('/177');
        $pathless = $this->user_at('');
        foreach ([$twice, $mine, $theirs, $pathless] as $u) {
            $this->enrol($u, $own);
        }
        $this->enrol($mine, $legacy);
        $this->enrol($theirs, $legacy);
        // A second enrolment method: one user, two user_enrolments rows. (Manual
        // allows one instance per course, so the second is a self-enrol one.)
        $self = enrol_get_plugin('self');
        $instanceid = $self->add_instance(get_course($own->id), ['status' => ENROL_INSTANCE_ENABLED]);
        $self->enrol_user($DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST), $twice->id,
            (int) $DB->get_field('role', 'id', ['shortname' => 'student']));
        $this->assertSame(2, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :c AND ue.userid = :u", ['c' => $own->id, 'u' => $twice->id]));

        $this->setUser($this->tenant_admin('/1'));
        $listed = $this->listed_enrolled();
        $this->assertSame(2, $listed[(int) $own->id],
            'distinct users of the viewer\'s tenant only: not 5 rows of every tenant');
        $this->assertSame(1, $listed[(int) $legacy->id],
            'a legacy course no longer shows other tenants\' enrolment totals');
        $this->assertSame($this->page_total_enrolled($own), $listed[(int) $own->id]);
        $this->assertSame($this->page_total_enrolled($legacy), $listed[(int) $legacy->id]);

        $this->setAdminUser();
        $listed = $this->listed_enrolled();
        $this->assertSame(4, $listed[(int) $own->id], 'cross-tenant: every enrolee, counted once');
        $this->assertSame(2, $listed[(int) $legacy->id]);
        $this->assertSame($this->page_total_enrolled($own), $listed[(int) $own->id]);
    }

    // ── F1: featured rows rehomed by the 2026092501 upgrade step ─────────

    private function featured_tenant(int $rowid): int {
        global $DB;
        return (int) $DB->get_field('local_sentientia_featured_courses', 'costcenterid', ['id' => $rowid],
            MUST_EXIST);
    }

    /** Distinct course ids the dashboard widget shows $user. */
    private function widget_courseids(\stdClass $user): array {
        $ids = array_values(array_unique(array_map('intval', array_column(
            featured_manager::get_widget_for_user((int) $user->id, 50)['courses'], 'courseid'))));
        sort($ids);
        return $ids;
    }

    public function test_upgrade_moves_tenant_owned_global_rows_to_their_tenant(): void {
        global $DB;
        $airpay = $this->course_at('/1/2');
        $zeea = $this->course_at('/177');
        $legacy = $this->course_at(null);
        $shared = $this->course_at('/1');
        $withdrawn = $this->course_at('/1');
        $already = $this->course_at('/1');
        $unknown = $this->course_at('/999');
        $zeeaonly = $this->course_at('/177');

        $this->setAdminUser();
        sharing_manager::share_course((int) $shared->id, [177]);
        sharing_manager::share_course((int) $withdrawn->id, [77]);
        sharing_manager::unshare_course((int) $withdrawn->id, 77);
        $row = [];
        foreach (['airpay' => $airpay, 'zeea' => $zeea, 'legacy' => $legacy, 'shared' => $shared,
                'withdrawn' => $withdrawn, 'already' => $already, 'unknown' => $unknown] as $k => $c) {
            $row[$k] = featured_manager::add((int) $c->id, 0);
        }
        $alreadyown = featured_manager::add((int) $already->id, 1);
        $zeeaown = featured_manager::add((int) $zeeaonly->id, 177);

        // Before: the /1 curator cannot touch the row they pinned, and ZEEA's
        // learners see the Airpay course.
        $curator = $this->tenant_admin('/1');
        $this->setUser($curator);
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => featured_manager::assert_can_edit_rows([$row['airpay']])));
        $this->assertNotContains($row['airpay'], array_column(featured_manager::list_all(1), 'id'));
        $zeealearner = $this->user_at('/177/5');
        $this->assertContains((int) $airpay->id, $this->widget_courseids($zeealearner));

        $result = local_sentientia_courses_rehome_global_featured();

        $this->assertSame(1, $this->featured_tenant($row['airpay']));
        $this->assertSame(177, $this->featured_tenant($row['zeea']));
        $this->assertSame(1, $this->featured_tenant($row['withdrawn']), 'a withdrawn share is no share');
        foreach (['legacy', 'shared', 'already', 'unknown'] as $k) {
            $this->assertSame(0, $this->featured_tenant($row[$k]), "{$k} stays on All tenants");
        }
        $this->assertSame(1, $this->featured_tenant($alreadyown));
        $this->assertSame(177, $this->featured_tenant($zeeaown), 'tenant rows are never touched');
        $this->assertCount(2, $DB->get_records('local_sentientia_featured_courses', ['courseid' => $already->id]),
            'rows are re-tagged, never deleted');
        $rehomed = array_map(fn($r) => (int) $r->id, $result['rehomed']);
        sort($rehomed);
        $expected = [$row['airpay'], $row['zeea'], $row['withdrawn']];
        sort($expected);
        $this->assertSame($expected, $rehomed);
        $this->assertCount(4, $result['kept']);

        // After: each tenant's learners see their own list plus the global rows.
        $airpaylearner = $this->user_at('/1/9');
        $mine = [(int) $airpay->id, (int) $legacy->id, (int) $shared->id, (int) $withdrawn->id,
            (int) $already->id, (int) $unknown->id];
        sort($mine);
        $this->assertSame($mine, $this->widget_courseids($airpaylearner));
        $this->assertNotContains((int) $airpay->id, $this->widget_courseids($zeealearner),
            'an Airpay-owned, unshared course no longer reaches ZEEA\'s dashboard');
        $this->assertContains((int) $shared->id, $this->widget_courseids($zeealearner));
        $this->assertContains((int) $zeea->id, $this->widget_courseids($zeealearner));
        $this->assertNotContains((int) $zeea->id, $this->widget_courseids($airpaylearner));

        // ...and the /1 curator can manage the row they pinned again.
        $this->setUser($curator);
        $this->assertContains($row['airpay'], array_column(featured_manager::list_all(1), 'id'));
        featured_manager::assert_can_edit_rows([$row['airpay'], $row['withdrawn']]);
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => featured_manager::assert_can_edit_rows([$row['legacy']])),
            'rows left on All tenants stay cross-tenant only');

        // Idempotent.
        $again = local_sentientia_courses_rehome_global_featured();
        $this->assertSame([], $again['rehomed']);
        $this->assertCount(4, $again['kept']);
    }

    public function test_the_upgrade_step_records_every_row_in_the_config_log(): void {
        global $DB;
        $airpay = $this->course_at('/1');
        $legacy = $this->course_at(null);
        $this->setAdminUser();
        featured_manager::add((int) $airpay->id, 0);
        featured_manager::add((int) $legacy->id, 0);

        $lines = local_sentientia_courses_run_featured_rehome();

        $count = fn(string $name) => $DB->count_records('config_log',
            ['plugin' => 'local_sentientia_courses', 'name' => $name]);
        $this->assertSame(1, $count('adr031_featured_rehomed'));
        $this->assertSame(1, $count('adr031_featured_review'));
        $this->assertSame(1, $count('adr031_featured_audit'));
        $this->assertCount(3, $lines);
        foreach ($DB->get_records('config_log', ['plugin' => 'local_sentientia_courses']) as $log) {
            $this->assertStringNotContainsString((string) $airpay->fullname, (string) $log->value,
                'the log carries ids and tenant roots, never names');
        }
    }
}
