<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\form\scim_client_form;
use local_sentientia_api\form\subscription_form;
use local_sentientia_api\scim\attestation;
use local_sentientia_api\scim\client;
use local_sentientia_api\webhooks\queue;
use local_sentientia_api\webhooks\subscription;

/**
 * ADR-031 (2026-09-25): the webhooks and SCIM admin surfaces stay inside the
 * caller's tenant.
 *
 * :webhooks_manage and :scim_manage defaulted to the manager archetype, which
 * every tenant admin holds at system context, and the pages behind them
 * listed, created, rotated and deleted every tenant's subscriptions and SCIM
 * clients (including all-tenant costcenterid 0 ones). Both capabilities now
 * have no default, and a non-cross-tenant holder is confined to their own
 * tenant by \local_sentientia_api\admin_scope.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\admin_scope
 * @covers     \local_sentientia_api\webhooks\subscription
 * @covers     \local_sentientia_api\webhooks\queue
 * @covers     \local_sentientia_api\scim\client
 * @covers     \local_sentientia_api\scim\attestation
 * @covers     \local_sentientia_api\form\subscription_form
 * @covers     \local_sentientia_api\form\scim_client_form
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var string A public (TEST-NET-3) address: never in the default blocked ranges, no DNS needed. */
    private const OK_URL = 'https://203.0.113.10/hooks/sentientia';

    /** @var string[] */
    private const CAPS = ['local/sentientia_api:webhooks_manage', 'local/sentientia_api:scim_manage'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

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

    private function make_sub(int $tenant): int {
        return subscription::create((object) [
            'name' => 'Hook ' . $tenant, 'url' => self::OK_URL, 'events' => ['course.completed'],
            'costcenterid' => $tenant,
        ]);
    }

    private function make_delivery(int $subid): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record(queue::TABLE, (object) [
            'subid' => $subid, 'userid' => 0, 'eventkey' => 'course.completed', 'payload' => '{}',
            'status' => queue::STATUS_DEAD, 'attempts' => queue::MAX_ATTEMPTS, 'nextattempt' => 0,
            'httpstatus' => 0, 'lasterror' => 'phpunit', 'timecreated' => $now, 'timeupdated' => $now,
        ]);
    }

    private function make_client(int $tenant): int {
        return client::create((object) ['name' => 'IdP ' . $tenant, 'costcenterid' => $tenant, 'auth' => 'oauth2'])['id'];
    }

    private static function keys(array $rows): array {
        $ids = array_map('intval', array_keys($rows));
        sort($ids);
        return $ids;
    }

    private function assert_out_of_tenant(callable $call, string $message): void {
        try {
            $call();
            $this->fail($message);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $message);
        }
    }

    public function test_manage_capabilities_have_no_default_holder(): void {
        global $DB;
        foreach (self::CAPS as $cap) {
            $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => $cap]),
                "No role may hold {$cap} by default - tenant admins hold manager-archetype roles.");
        }
        $admin = $this->tenant_admin_at('/1');
        foreach (self::CAPS as $cap) {
            $this->assertFalse(has_capability($cap, \context_system::instance(), $admin),
                "A manager-archetype tenant admin must not hold {$cap}.");
        }
    }

    public function test_a_tenant_admin_is_confined_to_their_tenants_webhooks(): void {
        $s1 = $this->make_sub(1);
        $s177 = $this->make_sub(177);
        $s0 = $this->make_sub(0);
        $d1 = $this->make_delivery($s1);
        $d177 = $this->make_delivery($s177);
        $d0 = $this->make_delivery($s0);

        $this->setUser($this->tenant_admin_at('/1'));
        $root = admin_scope::tenant_root();
        $this->assertSame(1, $root);

        $this->assertSame([$s1], self::keys(subscription::list_all($root)));
        $this->assertSame([$d1], self::keys(queue::recent(50, $root)));
        $this->assertSame(1, queue::counts($root)[queue::STATUS_DEAD]);

        admin_scope::require_subscription($s1, $root);
        admin_scope::require_delivery($d1, $root);
        foreach ([$s177, $s0, 999999] as $sub) {
            $this->assert_out_of_tenant(static fn() => admin_scope::require_subscription($sub, $root),
                "Subscription {$sub} is not tenant 1's: enable/disable/delete/rotate must be refused.");
        }
        foreach ([$d177, $d0] as $del) {
            $this->assert_out_of_tenant(static fn() => admin_scope::require_delivery($del, $root),
                "Delivery {$del} is not tenant 1's: retry must be refused.");
        }

        $this->assertSame(1, admin_scope::costcenter_for_create(0, $root), 'A scoped caller cannot create an all-tenant hook.');
        $this->assertSame(1, admin_scope::costcenter_for_create(177, $root), 'Nor one for another tenant.');
    }

    public function test_a_tenant_admin_is_confined_to_their_tenants_scim_clients(): void {
        $c1 = $this->make_client(1);
        $c177 = $this->make_client(177);
        $c0 = $this->make_client(0);
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        attestation::record($c1, (int) $mine->id, attestation::CREATED, 'ext-mine');
        attestation::record($c177, (int) $theirs->id, attestation::CREATED, 'ext-theirs');
        attestation::record($c0, (int) $theirs->id, attestation::UPDATED, 'ext-site');

        $this->setUser($this->tenant_admin_at('/1'));
        $root = admin_scope::tenant_root();

        $this->assertSame([$c1], self::keys(client::list_all($root)));
        admin_scope::require_client($c1, $root);
        foreach ([$c177, $c0] as $cli) {
            $this->assert_out_of_tenant(static fn() => admin_scope::require_client($cli, $root),
                "SCIM client {$cli} is not tenant 1's: rotate/disable/delete must be refused.");
        }

        $events = attestation::recent(100, 0, $root);
        $this->assertSame([(int) $mine->id], array_values(array_map(static fn($e) => (int) $e->userid, $events)));
        $csv = attestation::to_csv(5000, $root);
        $this->assertStringContainsString('ext-mine', $csv);
        $this->assertStringNotContainsString('ext-theirs', $csv, 'Another tenant\'s provisioning events must not export.');
        $this->assertStringNotContainsString('ext-site', $csv, 'Site-level client events are for cross-tenant callers only.');
        $this->assertStringNotContainsString($theirs->username, $csv);
    }

    public function test_the_forms_pin_a_scoped_callers_tenant(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $this->setUser($this->tenant_admin_at('/1'));
        $url = new \moodle_url('/local/sentientia_api/webhooks.php');

        subscription_form::mock_submit([
            'name' => 'Mine', 'url' => self::OK_URL, 'ev_course_completed' => 1, 'ev_enrolment_created' => 0,
            'ev_certificate_issued' => 0, 'costcenterid' => 177, 'enabled' => 1,
        ]);
        $data = (new subscription_form($url, ['scoperoot' => 1]))->get_data();
        $this->assertNotNull($data);
        $this->assertSame(1, (int) $data->costcenterid, 'A tampered costcenterid must not survive the scoped form.');

        scim_client_form::mock_submit([
            'name' => 'Mine', 'costcenterid' => 0, 'auth' => 'oauth2', 'ratelimit' => 0, 'enabled' => 1,
        ]);
        $data = (new scim_client_form($url, ['scoperoot' => 1]))->get_data();
        $this->assertNotNull($data);
        $this->assertSame(1, (int) $data->costcenterid, 'A scoped caller cannot create a site-level SCIM client.');

        // Cross-tenant callers keep the free field, 0 = every tenant, as before.
        $this->setAdminUser();
        scim_client_form::mock_submit([
            'name' => 'Site', 'costcenterid' => 0, 'auth' => 'oauth2', 'ratelimit' => 0, 'enabled' => 1,
        ]);
        $data = (new scim_client_form($url, ['scoperoot' => null]))->get_data();
        $this->assertNotNull($data);
        $this->assertSame(0, (int) $data->costcenterid);
    }

    public function test_a_caller_with_no_tenant_is_refused(): void {
        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin_at($path));
            $this->assert_out_of_tenant(static fn() => admin_scope::tenant_root(),
                "open_path '{$path}' must not unlock every tenant's webhooks or SCIM clients.");
        }
    }

    public function test_cross_tenant_callers_are_unscoped(): void {
        $s1 = $this->make_sub(1);
        $s177 = $this->make_sub(177);
        $s0 = $this->make_sub(0);
        $c177 = $this->make_client(177);
        $d177 = $this->make_delivery($s177);

        $this->setAdminUser();
        $root = admin_scope::tenant_root();
        $this->assertNull($root);
        $expected = [$s1, $s177, $s0];
        sort($expected);
        $this->assertSame($expected, self::keys(subscription::list_all($root)));
        admin_scope::require_subscription($s177, $root);
        admin_scope::require_subscription($s0, $root);
        admin_scope::require_delivery($d177, $root);
        admin_scope::require_client($c177, $root);
        $this->assertSame(0, admin_scope::costcenter_for_create(0, $root));
        $this->assertSame(177, admin_scope::costcenter_for_create(177, $root));

        // A deliberate :crosstenant holder (not a site admin) is unscoped too.
        $platform = $this->user_at('/1');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW, $roleid,
            \context_system::instance()->id);
        role_assign($roleid, $platform->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($platform);
        $this->assertNull(admin_scope::tenant_root());
    }
}
