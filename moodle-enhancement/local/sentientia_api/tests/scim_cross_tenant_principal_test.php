<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\scim\attestation;
use local_sentientia_api\scim\client;
use local_sentientia_api\scim\group_resource;
use local_sentientia_api\scim\handler;
use local_sentientia_api\scim\mapper;
use local_sentientia_api\scim\response;
use local_sentientia_platform\tenant;

/**
 * ADR-031 fix-forward (adversarial review S1, 2026-09-25): a tenant-scoped SCIM
 * client never reads or changes a cross-tenant principal.
 *
 * A SCIM client with costcenterid N > 0 could PATCH the email or userName of,
 * and suspend, ANY live user whose open_path sat under /N - including a site
 * admin or a local/sentientia_platform:crosstenant holder placed there (Airpay
 * platform staff are expected to sit under /1). A scoped :scim_manage holder
 * could therefore mint a /N token, re-email a platform admin, reset the
 * password and take the account over. Such principals are now outside every
 * scoped client's reach exactly like another tenant's users (404, absent from
 * lists, filters and group membership), while ordinary users of the tenant stay
 * fully manageable and site-level clients are unchanged.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\scim\handler
 * @covers     \local_sentientia_api\scim\group_resource
 * @covers     \local_sentientia_platform\tenant::cross_tenant_userids
 * @group tenant_isolation
 */
final class scim_cross_tenant_principal_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const BASE = 'https://lms.example.test/local/sentientia_api/scim/v2.php';

    /** @var handler */
    private handler $h;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists('\local_sentientia_platform\feature_flags')
                || !class_exists('\local_sentientia_users\user_manager')) {
            $this->markTestSkipped('platform/users plugins not installed');
        }
        $this->ensure_bizlms_schema();
        \local_sentientia_platform\feature_flags::invalidate_caches();
        handler::reset_static_caches();
        $ff = '\local_sentientia_platform\feature_flags';
        $ff::set(handler::FLAG_MASTER, 0, true, null, 'phpunit');
        $ff::set(handler::FLAG_SCIM, 0, true, null, 'phpunit');
        $ff::invalidate_caches();
        $this->h = new handler(self::BASE);
    }

    protected function tearDown(): void {
        // Flag statics survive resetAfterTest - never leak our ON flags into later classes.
        if (class_exists('\local_sentientia_platform\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
        handler::reset_static_caches();
        parent::tearDown();
    }

    private function user_at(string $path, array $extra = []): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user($extra);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** The site admin, placed under the given tenant path (as Airpay platform staff sit under /1). */
    private function admin_at(string $path): \stdClass {
        global $DB;
        $admin = get_admin();
        $DB->set_field('user', 'open_path', $path, ['id' => $admin->id]);
        return $DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST);
    }

    /** A deliberate :crosstenant holder (not a site admin) placed under the given path. */
    private function platform_at(string $path): \stdClass {
        $u = $this->user_at($path);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /** @return array{0:\stdClass,1:string} client row + bearer header */
    private function make_client(int $tenant): array {
        $made = client::create((object) ['name' => 'IdP ' . $tenant, 'costcenterid' => $tenant, 'auth' => 'oauth2']);
        return [client::get($made['id']), 'Bearer ' . $made['token']];
    }

    private function call(string $method, string $path, string $auth, ?array $body = null, array $query = []): array {
        return $this->h->handle($method, $path, $query, $body === null ? null : json_encode($body), $auth);
    }

    private static function patch(array $operations): array {
        return ['schemas' => [response::SCHEMA_PATCH], 'Operations' => $operations];
    }

    private function user_body(string $username, string $email, string $ext, bool $active = true): array {
        return [
            'schemas'    => [response::SCHEMA_USER],
            'userName'   => $username,
            'externalId' => $ext,
            'name'       => ['givenName' => 'Taken', 'familyName' => 'Over'],
            'emails'     => [['value' => $email, 'type' => 'work', 'primary' => true]],
            'active'     => $active,
        ];
    }

    /** @return \stdClass[] label => cross-tenant principal sitting under /1 */
    private function principals_under_tenant_one(): array {
        return ['site admin' => $this->admin_at('/1'), ':crosstenant holder' => $this->platform_at('/1/5')];
    }

    public function test_cross_tenant_userids_is_the_bulk_form_of_is_cross_tenant(): void {
        global $DB;
        $admin = $this->admin_at('/1');
        $platform = $this->platform_at('/1/5');
        $tenantadmin = $this->user_at('/1');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $tenantadmin->id, \context_system::instance()->id);
        // Granted :crosstenant by one role, prohibited by another: NOT cross-tenant.
        $prohibited = $this->platform_at('/1/6');
        $denyid = $this->getDataGenerator()->create_role();
        assign_capability(tenant::CROSS_TENANT_CAPABILITY, CAP_PROHIBIT, $denyid, \context_system::instance()->id);
        role_assign($denyid, $prohibited->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $ids = tenant::cross_tenant_userids();
        foreach ([$admin, $platform, $tenantadmin, $prohibited, guest_user()] as $u) {
            $this->assertSame(tenant::is_cross_tenant((int) $u->id), in_array((int) $u->id, $ids, true),
                "cross_tenant_userids() and is_cross_tenant() disagree about user {$u->id}.");
        }
        $this->assertContains((int) $admin->id, $ids);
        $this->assertContains((int) $platform->id, $ids);
        $this->assertNotContains((int) $tenantadmin->id, $ids, 'A manager-archetype tenant admin is not cross-tenant.');
        $this->assertNotContains((int) $prohibited->id, $ids);
        $this->assertNotContains((int) guest_user()->id, $ids);
    }

    public function test_a_scoped_client_cannot_see_a_cross_tenant_principal_in_its_tenant(): void {
        $ordinary = $this->user_at('/1/2');
        [, $auth] = $this->make_client(1);

        foreach ($this->principals_under_tenant_one() as $label => $p) {
            $this->assertSame(404, $this->call('GET', '/Users/' . $p->id, $auth)['status'],
                "A /1 client must not read the {$label}.");
            foreach (['userName eq "' . $p->username . '"', 'emails.value eq "' . $p->email . '"', 'id eq "' . $p->id . '"'] as $f) {
                $r = $this->call('GET', '/Users', $auth, null, ['filter' => $f]);
                $this->assertSame(200, $r['status']);
                $this->assertSame(0, $r['body']['totalResults'], "Filter {$f} must not reveal the {$label}.");
            }
        }

        $list = $this->call('GET', '/Users', $auth, null, ['count' => 200]);
        $ids = array_map(static fn($res) => (int) $res['id'], $list['body']['Resources']);
        $this->assertContains((int) $ordinary->id, $ids, 'Ordinary /1 users stay listed.');
        $this->assertNotContains((int) get_admin()->id, $ids);
    }

    public function test_a_scoped_client_cannot_patch_put_or_suspend_a_cross_tenant_principal(): void {
        global $DB;
        [$c, $auth] = $this->make_client(1);

        foreach ($this->principals_under_tenant_one() as $label => $p) {
            $before = $DB->get_record('user', ['id' => $p->id], 'id, username, email, firstname, suspended', MUST_EXIST);

            $attempts = [
                'PATCH email'    => ['PATCH', self::patch([['op' => 'replace', 'path' => 'emails[type eq "work"].value',
                    'value' => 'attacker@evil.test']])],
                'PATCH userName' => ['PATCH', self::patch([['op' => 'replace', 'path' => 'userName', 'value' => 'attacker']])],
                'PATCH active'   => ['PATCH', self::patch([['op' => 'replace', 'path' => 'active', 'value' => false]])],
                'PUT'            => ['PUT', $this->user_body('attacker2', 'attacker2@evil.test', 'ext-put')],
                'DELETE'         => ['DELETE', null],
            ];
            foreach ($attempts as $what => [$method, $body]) {
                $r = $this->call($method, '/Users/' . $p->id, $auth, $body);
                $this->assertSame(404, $r['status'], "{$what} on the {$label} must be refused as out of scope.");
            }

            $after = $DB->get_record('user', ['id' => $p->id], 'id, username, email, firstname, suspended', MUST_EXIST);
            $this->assertEquals($before, $after, "The {$label}'s account must be untouched.");
            $this->assertSame(0, $DB->count_records(attestation::TABLE, ['cliid' => $c->id, 'userid' => $p->id]),
                "Nothing may be attested against the {$label}.");
        }
    }

    public function test_a_scoped_client_cannot_reprovision_a_cross_tenant_principal(): void {
        global $DB;
        [$c, $auth] = $this->make_client(1);
        $p = $this->platform_at('/1/5');
        $DB->set_field('user', 'suspended', 1, ['id' => $p->id]);

        // By userName: a suspended in-scope account used to be re-provisioned (reactivated + re-emailed).
        $r = $this->call('POST', '/Users', $auth, $this->user_body($p->username, 'attacker@evil.test', 'ext-new'));
        $this->assertSame(409, $r['status']);

        // By an externalId this client had mapped to the account earlier.
        mapper::set((int) $c->id, (int) $p->id, 'ext-old');
        $r = $this->call('POST', '/Users', $auth, $this->user_body('someone.else', 'attacker@evil.test', 'ext-old'));
        $this->assertSame(409, $r['status']);

        $after = $DB->get_record('user', ['id' => $p->id], 'suspended, email', MUST_EXIST);
        $this->assertSame(1, (int) $after->suspended, 'The principal must stay suspended.');
        $this->assertSame($p->email, $after->email);
    }

    public function test_a_scoped_client_cannot_move_or_list_a_cross_tenant_principal_through_groups(): void {
        global $DB;
        $now = time();
        $org = static function (string $name, int $parentid, int $depth) use ($DB, $now): \stdClass {
            $rec = (object) ['fullname' => $name, 'shortname' => strtolower($name), 'description' => '',
                'parentid' => $parentid, 'path' => '/0', 'depth' => $depth, 'visible' => 1, 'sortorder' => 0,
                'timecreated' => $now, 'timemodified' => $now];
            $rec->id = (int) $DB->insert_record(group_resource::ORG_TABLE, $rec);
            return $rec;
        };
        $root = $org('Root', 0, 1);
        $root->path = '/' . $root->id;
        $DB->set_field(group_resource::ORG_TABLE, 'path', $root->path, ['id' => $root->id]);
        $dept = $org('Dept', (int) $root->id, 2);
        $dept->path = $root->path . '/' . $dept->id;
        $DB->set_field(group_resource::ORG_TABLE, 'path', $dept->path, ['id' => $dept->id]);

        $admin = $this->admin_at($root->path);
        $ordinary = $this->user_at($root->path);
        [, $auth] = $this->make_client((int) $root->id);

        $g = $this->call('GET', '/Groups/' . $root->id, $auth);
        $this->assertSame(200, $g['status']);
        $members = array_map(static fn($m) => (int) $m['value'], $g['body']['members']);
        $this->assertContains((int) $ordinary->id, $members);
        $this->assertNotContains((int) $admin->id, $members, 'A cross-tenant principal is not a member this client may see.');

        $add = self::patch([['op' => 'add', 'path' => 'members', 'value' => [['value' => (string) $admin->id]]]]);
        $this->assertSame(400, $this->call('PATCH', '/Groups/' . $dept->id, $auth, $add)['status']);
        $this->assertSame($root->path, $DB->get_field('user', 'open_path', ['id' => $admin->id]), 'The admin must not move.');

        // Ordinary members still move.
        $add = self::patch([['op' => 'add', 'path' => 'members', 'value' => [['value' => (string) $ordinary->id]]]]);
        $this->assertSame(200, $this->call('PATCH', '/Groups/' . $dept->id, $auth, $add)['status']);
        $this->assertSame($dept->path, $DB->get_field('user', 'open_path', ['id' => $ordinary->id]));
    }

    public function test_a_scoped_client_still_manages_its_ordinary_users(): void {
        global $DB;
        $this->principals_under_tenant_one();
        $ordinary = $this->user_at('/1/2');
        [, $auth] = $this->make_client(1);

        $this->assertSame(200, $this->call('GET', '/Users/' . $ordinary->id, $auth)['status']);
        $r = $this->call('PATCH', '/Users/' . $ordinary->id, $auth,
            self::patch([['op' => 'replace', 'path' => 'emails[type eq "work"].value', 'value' => 'moved@corp.example']]));
        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $this->assertSame('moved@corp.example', $DB->get_field('user', 'email', ['id' => $ordinary->id]));
        $this->assertSame(204, $this->call('DELETE', '/Users/' . $ordinary->id, $auth)['status']);
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $ordinary->id]));
    }

    public function test_a_site_level_client_is_unchanged(): void {
        $admin = $this->admin_at('/1');
        [, $auth] = $this->make_client(0);
        $this->assertSame(200, $this->call('GET', '/Users/' . $admin->id, $auth)['status'],
            'A site-level client (creatable only by a cross-tenant caller) keeps its unscoped reach.');
    }
}
