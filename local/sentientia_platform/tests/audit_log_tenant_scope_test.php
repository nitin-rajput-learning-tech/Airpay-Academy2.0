<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: the audit-log readers stay inside the caller's tenant (2026-09-25).
 *
 * tenant_actions() let any holder of moodle/site:viewreports name ANY tenant,
 * and that core capability defaults to the manager archetype, which every
 * tenant admin holds at system context. actions_by_user() had no gate at all.
 * Both are now confined to the caller's own tenant unless the caller is
 * cross-tenant (site admin or local/sentientia_platform:crosstenant), and a
 * caller with no resolvable tenant gets nothing.
 *
 * Decision A5 (2026-11-13): tenant_actions() also lists rows with no real
 * actor (userid 0, or the guest user) under their related user's tenant, so an
 * order paid through the gateway callback reaches the buyer's tenant trail.
 * An actor-less row about the guest, or (for a scoped reader) about a
 * cross-tenant principal, stays out. The rows here are inserted straight into
 * logstore_standard_log; no event is triggered.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_platform\audit_log
 * @group tenant_isolation
 */
final class audit_log_tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** Fixed, long-past window so rows the fixture itself logs never match. */
    private const FROM = 1000000;
    private const TO   = 1000100;

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A manager-archetype role at system context, as UAT's tenant admins hold. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /**
     * Insert one standard-log row directly (no event is triggered).
     *
     * @param array $fields column values that replace the defaults below
     */
    private function log_row(int $actorid, ?int $relateduserid, int $time,
                             string $eventname = '\\core\\event\\role_assigned', array $fields = []): int {
        global $DB;
        return (int) $DB->insert_record('logstore_standard_log', (object) array_merge([
            'eventname'         => $eventname,
            'component'         => 'core',
            'action'            => 'assigned',
            'target'            => 'role',
            'objecttable'       => null,
            'objectid'          => null,
            'crud'              => 'c',
            'edulevel'          => 0,
            'contextid'         => \context_system::instance()->id,
            'contextlevel'      => CONTEXT_SYSTEM,
            'contextinstanceid' => 0,
            'userid'            => $actorid,
            'courseid'          => 0,
            'relateduserid'     => $relateduserid,
            'anonymous'         => 0,
            'other'             => null,
            'timecreated'       => $time,
            'origin'            => 'cli',
            'ip'                => null,
            'realuserid'        => null,
        ], $fields));
    }

    /**
     * An order_paid row as the standard store writes it when the gateway's callback.php runs
     * cart_manager::mark_paid(): callback.php sets NO_MOODLE_COOKIES and logs no one in, so the event's
     * userid is 0; relateduserid is the buyer; the context is the system; the object is the order.
     * The ids in `other` are illustrative: tenant_actions() does not read them.
     */
    private function order_paid_row(int $actorid, ?int $buyerid, int $time): int {
        return $this->log_row($actorid, $buyerid, $time, '\\local_sentientia_cart\\event\\order_paid', [
            'component'   => 'local_sentientia_cart',
            'action'      => 'paid',
            'target'      => 'order',
            'objecttable' => 'local_sentientia_cart_history',
            'objectid'    => 1,
            'crud'        => 'u',
            'other'       => json_encode(['orderid' => 1, 'amount' => 118000, 'currency' => 'INR', 'withheld' => 0]),
            'origin'      => 'web',
        ]);
    }

    private static function ids(array $rows): array {
        return array_map(static fn($r) => (int) $r->id, array_values($rows));
    }

    private function assert_out_of_tenant(callable $call, string $message): void {
        try {
            $call();
            $this->fail($message);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $message);
        }
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    public function test_a_tenant_admin_reads_only_their_own_tenants_trail(): void {
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $a = $this->log_row((int) $mine->id, null, self::FROM + 10);
        $b = $this->log_row((int) $theirs->id, null, self::FROM + 20);

        $this->setUser($this->tenant_admin_at('/1'));

        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertContains($a, $ids);
        $this->assertNotContains($b, $ids);

        $this->assert_out_of_tenant(
            static fn() => audit_log::tenant_actions(177, self::FROM, self::TO),
            'Holding moodle/site:viewreports must not let a /1 tenant admin name tenant 177.');
    }

    public function test_a_tenant_admin_cannot_read_a_foreign_users_trail(): void {
        $colleague = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $a = $this->log_row((int) $colleague->id, null, self::FROM + 10);
        $this->log_row((int) $foreign->id, null, self::FROM + 20);

        $this->setUser($this->tenant_admin_at('/1'));

        $this->assertSame([$a],
            self::ids(audit_log::actions_by_user((int) $colleague->id, self::FROM, self::TO)));
        $this->assert_out_of_tenant(
            static fn() => audit_log::actions_by_user((int) $foreign->id, self::FROM, self::TO),
            'A /1 tenant admin must not read a /177 user\'s activity trail.');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $colleague = $this->user_at('/1/2');
        $this->log_row((int) $colleague->id, null, self::FROM + 10);
        $recent = $this->log_row((int) $colleague->id, (int) $colleague->id, time() - 60);

        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin_at($path));
            foreach ([1, 177, 0] as $tenant) {
                $this->assert_out_of_tenant(
                    static fn() => audit_log::tenant_actions($tenant, self::FROM, self::TO),
                    "open_path '{$path}' must not unlock tenant {$tenant}.");
            }
            $this->assert_out_of_tenant(
                static fn() => audit_log::actions_by_user((int) $colleague->id, self::FROM, self::TO),
                "open_path '{$path}' must not unlock anybody's trail.");
            $this->assertNotContains($recent, self::ids(audit_log::sensitive_actions(24)),
                "open_path '{$path}' must see no tenant's sensitive actions.");
        }
    }

    public function test_own_trail_needs_no_capability_but_anyone_elses_does(): void {
        $learner = $this->user_at('/1/5');
        $colleague = $this->user_at('/1/2');
        $own = $this->log_row((int) $learner->id, null, self::FROM + 10);
        $this->log_row((int) $colleague->id, null, self::FROM + 20);

        $this->setUser($learner);
        $this->assertSame([$own],
            self::ids(audit_log::actions_by_user((int) $learner->id, self::FROM, self::TO)));

        foreach ([
            static fn() => audit_log::actions_by_user((int) $colleague->id, self::FROM, self::TO),
            static fn() => audit_log::tenant_actions(1, self::FROM, self::TO),
            static fn() => audit_log::sensitive_actions(24),
        ] as $call) {
            try {
                $call();
                $this->fail('Without moodle/site:viewreports only the caller\'s own trail is readable.');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    public function test_sensitive_actions_are_confined_to_the_viewers_tenant(): void {
        $actor = $this->user_at('/1');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $now = time() - 60;
        $s1 = $this->log_row((int) $actor->id, (int) $mine->id, $now);
        $s2 = $this->log_row((int) $actor->id, (int) $theirs->id, $now);
        $s3 = $this->log_row((int) $actor->id, null, $now);

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::sensitive_actions(24));
        $this->assertContains($s1, $ids);
        $this->assertNotContains($s2, $ids, 'Another tenant\'s role assignment must not show.');
        $this->assertNotContains($s3, $ids, 'Platform-level rows are for cross-tenant viewers only.');

        $this->setAdminUser();
        $ids = self::ids(audit_log::sensitive_actions(24));
        foreach ([$s1, $s2, $s3] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_cross_tenant_callers_still_read_every_tenant(): void {
        $foreign = $this->user_at('/177/178');
        $b = $this->log_row((int) $foreign->id, null, self::FROM + 20);

        $this->setAdminUser();
        $this->assertContains($b, self::ids(audit_log::tenant_actions(177, self::FROM, self::TO)));
        $this->assertSame([$b],
            self::ids(audit_log::actions_by_user((int) $foreign->id, self::FROM, self::TO)));

        // A deliberate :crosstenant holder (no site admin) is also unscoped.
        $platform = $this->user_at('/1');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW, $roleid,
            \context_system::instance()->id);
        role_assign($roleid, $platform->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($platform);
        $this->assertContains($b, self::ids(audit_log::tenant_actions(177, self::FROM, self::TO)));
        $this->assertSame([$b],
            self::ids(audit_log::actions_by_user((int) $foreign->id, self::FROM, self::TO)));
    }

    /**
     * The site admin and a deliberate :crosstenant holder, both sitting under
     * /1 as Airpay platform staff are expected to.
     *
     * @return \stdClass[] label => user record
     */
    private function principals_under_tenant_one(): array {
        global $DB;
        $admin = get_admin();
        $DB->set_field('user', 'open_path', '/1', ['id' => $admin->id]);
        $platform = $this->user_at('/1/5');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW, $roleid,
            \context_system::instance()->id);
        role_assign($roleid, $platform->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return [
            'site admin'          => $DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST),
            ':crosstenant holder' => $platform,
        ];
    }

    /**
     * Adversarial review S4 (2026-09-25): a cross-tenant principal whose
     * open_path sits under /1 used to pass require_same_tenant_user() for a
     * /1 tenant admin, whose viewreports then read that principal's whole
     * trail - courseid, relateduserid and contextinstanceid values from every
     * other tenant.
     */
    public function test_a_tenant_admin_cannot_read_a_cross_tenant_principals_trail(): void {
        $principals = $this->principals_under_tenant_one();
        $colleague = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $rows = [];
        foreach ($principals as $label => $p) {
            $rows[$label] = $this->log_row((int) $p->id, (int) $foreign->id, self::FROM + 10);
        }
        $c = $this->log_row((int) $colleague->id, null, self::FROM + 20);

        $this->setUser($this->tenant_admin_at('/1'));
        foreach ($principals as $label => $p) {
            $this->assert_out_of_tenant(
                static fn() => audit_log::actions_by_user((int) $p->id, self::FROM, self::TO),
                "A /1 tenant admin must not read the {$label}'s trail, though it sits under /1.");
        }
        $this->assertSame([$c],
            self::ids(audit_log::actions_by_user((int) $colleague->id, self::FROM, self::TO)),
            'An ordinary colleague\'s trail stays readable to the tenant admin.');

        // Each principal still reads their own trail; cross-tenant callers read anyone's.
        foreach ($principals as $label => $p) {
            $this->setUser($p);
            $this->assertSame([$rows[$label]],
                self::ids(audit_log::actions_by_user((int) $p->id, self::FROM, self::TO)),
                "The {$label} reads their own trail.");
        }
        $this->setAdminUser();
        $platform = $principals[':crosstenant holder'];
        $this->assertSame([$rows[':crosstenant holder']],
            self::ids(audit_log::actions_by_user((int) $platform->id, self::FROM, self::TO)));
    }

    /**
     * S4, tenant_actions(): rows are picked by the ACTOR's open_path, so
     * platform staff sitting under /1 put everything they did in every tenant
     * into tenant 1's trail. A scoped reader now sees what they did to tenant
     * 1's own people only.
     */
    public function test_tenant_trail_hides_what_platform_staff_did_elsewhere(): void {
        $principals = $this->principals_under_tenant_one();
        $colleague = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $ordinary = $this->log_row((int) $colleague->id, null, self::FROM + 5);
        $inside = $elsewhere = $unrelated = [];
        foreach ($principals as $label => $p) {
            $inside[$label]    = $this->log_row((int) $p->id, (int) $colleague->id, self::FROM + 10);
            $elsewhere[$label] = $this->log_row((int) $p->id, (int) $foreign->id, self::FROM + 20);
            $unrelated[$label] = $this->log_row((int) $p->id, null, self::FROM + 30);
        }

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertContains($ordinary, $ids, 'Ordinary tenant users\' rows are unchanged.');
        foreach ($principals as $label => $p) {
            $this->assertContains($inside[$label], $ids,
                "What the {$label} did to a /1 user stays visible to tenant 1.");
            $this->assertNotContains($elsewhere[$label], $ids,
                "What the {$label} did to a /177 user must not show in tenant 1's trail.");
            $this->assertNotContains($unrelated[$label], $ids,
                "A platform-level row of the {$label} (no related user) is cross-tenant only.");
        }

        $this->setAdminUser();
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        foreach (array_merge([$ordinary], $inside, $elsewhere, $unrelated) as $id) {
            $this->assertContains($id, $ids, 'A cross-tenant reader still sees every row.');
        }
    }

    /**
     * Decision A5 (2026-11-13): an order paid through the gateway is logged with userid 0. The actor
     * JOIN used to drop it, so it reached no tenant's trail. It now belongs to the buyer's tenant.
     */
    public function test_an_actor_less_payment_is_in_the_buyers_tenant_trail_only(): void {
        $buyer = $this->user_at('/1/2');
        $buyer177 = $this->user_at('/177/178');
        $paid = $this->order_paid_row(0, (int) $buyer->id, self::FROM + 10);
        $paid177 = $this->order_paid_row(0, (int) $buyer177->id, self::FROM + 20);

        $this->setUser($this->tenant_admin_at('/1'));
        $rows = audit_log::tenant_actions(1, self::FROM, self::TO);
        $ids = self::ids($rows);
        $this->assertContains($paid, $ids, 'A /1 buyer\'s gateway payment is in tenant 1\'s trail.');
        $this->assertNotContains($paid177, $ids,
            'A /177 buyer\'s payment must not show in tenant 1\'s trail (/1 is not a prefix of /177).');
        $row = array_values(array_filter($rows, static fn($r) => (int) $r->id === $paid))[0];
        $this->assertSame(0, (int) $row->actor_userid);
        $this->assertSame((int) $buyer->id, (int) $row->relateduserid);
        $this->assertSame('\\local_sentientia_cart\\event\\order_paid', $row->eventname);

        $this->setUser($this->tenant_admin_at('/77'));
        $this->assertNotContains($paid, self::ids(audit_log::tenant_actions(77, self::FROM, self::TO)),
            'A /1 buyer\'s payment must not show in tenant 77\'s trail.');

        $this->setUser($this->tenant_admin_at('/177'));
        $ids = self::ids(audit_log::tenant_actions(177, self::FROM, self::TO));
        $this->assertContains($paid177, $ids);
        $this->assertNotContains($paid, $ids);

        $this->setAdminUser();
        $this->assertContains($paid, self::ids(audit_log::tenant_actions(1, self::FROM, self::TO)),
            'A cross-tenant reader sees the payment in the buyer\'s tenant.');
        $this->assertNotContains($paid, self::ids(audit_log::tenant_actions(77, self::FROM, self::TO)),
            'A cross-tenant reader does not see it under another tenant either.');
    }

    public function test_an_actor_less_row_without_a_related_user_is_in_no_tenants_trail(): void {
        $nobody = $this->order_paid_row(0, null, self::FROM + 10);
        // A relateduserid naming no user row (e.g. a hard-deleted account): ru is NULL.
        $gone = $this->order_paid_row(0, 987654321, self::FROM + 20);

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertNotContains($nobody, $ids, 'An actor-less row with no related user has no tenant.');
        $this->assertNotContains($gone, $ids, 'Nor does one whose related user does not exist.');

        $this->setAdminUser();
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertNotContains($nobody, $ids, 'It is in no tenant\'s trail, whoever reads it.');
        $this->assertNotContains($gone, $ids);
    }

    /**
     * The guest account is one site-wide user, so a guest row is classed by its related user like a
     * userid 0 row, whatever the guest account's own open_path holds (set to /1 here on purpose). For
     * the same reason an actor-less row ABOUT the guest is in no tenant's trail.
     */
    public function test_a_guest_row_belongs_to_its_related_users_tenant(): void {
        global $CFG, $DB;
        $guestid = (int) $CFG->siteguest;
        $this->assertGreaterThan(0, $guestid);
        $DB->set_field('user', 'open_path', '/1', ['id' => $guestid]);
        $learner = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $mine = $this->log_row($guestid, (int) $learner->id, self::FROM + 10);
        $theirs = $this->log_row($guestid, (int) $foreign->id, self::FROM + 20);
        $none = $this->log_row($guestid, null, self::FROM + 30);
        $aboutguest = $this->log_row(0, $guestid, self::FROM + 40);

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertContains($mine, $ids, 'A guest row about a /1 user is in tenant 1\'s trail.');
        $this->assertNotContains($theirs, $ids, 'A guest row about a /177 user is not, though the guest sits at /1.');
        $this->assertNotContains($none, $ids, 'A guest row with no related user is in no tenant\'s trail.');
        $this->assertNotContains($aboutguest, $ids,
            'An actor-less row about the guest is not tenant 1\'s, though the guest sits at /1.');

        $this->setUser($this->tenant_admin_at('/177'));
        $ids = self::ids(audit_log::tenant_actions(177, self::FROM, self::TO));
        $this->assertContains($theirs, $ids);
        $this->assertNotContains($mine, $ids);
        $this->assertNotContains($none, $ids);
        $this->assertNotContains($aboutguest, $ids);

        $this->setAdminUser();
        $this->assertNotContains($aboutguest, self::ids(audit_log::tenant_actions(1, self::FROM, self::TO)),
            'An actor-less row about the guest is in no tenant\'s trail, whoever reads it.');
    }

    /**
     * Review of decision A5 (2026-11-13): in an actor-less row the related user stands in for the actor.
     * A row about a cross-tenant principal sitting under /1 (their own gateway payment, or the core
     * forgotten-password reset, which logs user_password_updated with userid 0) is that principal's own
     * activity, so a scoped /1 reader does not see it. A cross-tenant reader does.
     */
    public function test_an_actor_less_row_about_a_principal_is_for_cross_tenant_readers_only(): void {
        $principals = $this->principals_under_tenant_one();
        $colleague = $this->user_at('/1/2');
        $ordinary = $this->order_paid_row(0, (int) $colleague->id, self::FROM + 5);
        $paid = $reset = [];
        foreach ($principals as $label => $p) {
            $paid[$label] = $this->order_paid_row(0, (int) $p->id, self::FROM + 10);
            $reset[$label] = $this->log_row(0, (int) $p->id, self::FROM + 20,
                '\\core\\event\\user_password_updated', [
                    'action'            => 'updated',
                    'target'            => 'user_password',
                    'crud'              => 'u',
                    'contextid'         => \context_user::instance($p->id)->id,
                    'contextlevel'      => CONTEXT_USER,
                    'contextinstanceid' => $p->id,
                    'other'             => json_encode(['forgottenreset' => false]),
                    'origin'            => 'web',
                ]);
        }

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertContains($ordinary, $ids, 'A /1 colleague\'s gateway payment stays in tenant 1\'s trail.');
        foreach ($principals as $label => $p) {
            $this->assertNotContains($paid[$label], $ids,
                "The {$label}'s own gateway payment is not a scoped reader's, though they sit under /1.");
            $this->assertNotContains($reset[$label], $ids,
                "The {$label}'s forgotten-password reset is not a scoped reader's either.");
        }

        $this->setAdminUser();
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        foreach (array_merge([$ordinary], $paid, $reset) as $id) {
            $this->assertContains($id, $ids, 'A cross-tenant reader of tenant 1 sees every one of these rows.');
        }
    }

    /**
     * A row with a real actor still goes by the ACTOR's open_path only: the related-user rule is for
     * actor-less rows. Unchanged by decision A5.
     */
    public function test_a_real_actors_row_keeps_the_actor_rule(): void {
        $colleague = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $own = $this->log_row((int) $colleague->id, null, self::FROM + 10);
        $tothem = $this->log_row((int) $colleague->id, (int) $foreign->id, self::FROM + 20);
        $bythem = $this->log_row((int) $foreign->id, (int) $colleague->id, self::FROM + 30);

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertContains($own, $ids);
        $this->assertContains($tothem, $ids, 'A /1 actor\'s row is in tenant 1\'s trail, whoever it is about.');
        $this->assertNotContains($bythem, $ids,
            'A /177 actor\'s row is not in tenant 1\'s trail, though its related user is at /1.');

        $this->setUser($this->tenant_admin_at('/177'));
        $ids = self::ids(audit_log::tenant_actions(177, self::FROM, self::TO));
        $this->assertContains($bythem, $ids);
        $this->assertNotContains($own, $ids);
        $this->assertNotContains($tothem, $ids);
    }

    /**
     * The S4 rule for cross-tenant actors stands beside the actor-less rule: a principal's row about a
     * /177 user stays out of tenant 1's scoped trail, and does not enter tenant 177's either (its actor
     * is a real one, at /1).
     */
    public function test_a_cross_tenant_actors_row_keeps_the_s4_rule(): void {
        $principals = $this->principals_under_tenant_one();
        $colleague = $this->user_at('/1/2');
        $foreign = $this->user_at('/177/178');
        $paid = $this->order_paid_row(0, (int) $colleague->id, self::FROM + 5);
        $inside = $elsewhere = $unrelated = [];
        foreach ($principals as $label => $p) {
            $inside[$label]    = $this->log_row((int) $p->id, (int) $colleague->id, self::FROM + 10);
            $elsewhere[$label] = $this->log_row((int) $p->id, (int) $foreign->id, self::FROM + 20);
            $unrelated[$label] = $this->log_row((int) $p->id, null, self::FROM + 30);
        }

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        $this->assertContains($paid, $ids);
        foreach ($principals as $label => $p) {
            $this->assertContains($inside[$label], $ids, "The {$label}'s row about a /1 user stays visible.");
            $this->assertNotContains($elsewhere[$label], $ids, "The {$label}'s row about a /177 user stays hidden.");
            $this->assertNotContains($unrelated[$label], $ids, "The {$label}'s platform-level row stays hidden.");
        }

        $this->setUser($this->tenant_admin_at('/177'));
        $ids = self::ids(audit_log::tenant_actions(177, self::FROM, self::TO));
        foreach ($principals as $label => $p) {
            $this->assertNotContains($elsewhere[$label], $ids,
                "The {$label} is a real actor at /1, so the row is not tenant 177's.");
        }

        $this->setAdminUser();
        $ids = self::ids(audit_log::tenant_actions(1, self::FROM, self::TO));
        foreach (array_merge([$paid], $inside, $elsewhere, $unrelated) as $id) {
            $this->assertContains($id, $ids, 'A cross-tenant reader of tenant 1 sees every one of these rows.');
        }
        $ids = self::ids(audit_log::tenant_actions(177, self::FROM, self::TO));
        foreach ($elsewhere as $id) {
            $this->assertNotContains($id, $ids, 'Nor is it in tenant 177 for a cross-tenant reader: the actor rule.');
        }
    }
}
