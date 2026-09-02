<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\scim\client;
use local_sentientia_api\scim\filter;
use local_sentientia_api\scim\handler;
use local_sentientia_api\scim\mapper;
use local_sentientia_api\scim\response;
use local_sentientia_api\scim\scim_exception;

/**
 * SCIM 2.0 Users endpoint (ADR-030 Wave B), exercised through the
 * transport-neutral handler.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\scim\handler
 * @covers     \local_sentientia_api\scim\client
 * @covers     \local_sentientia_api\scim\authenticator
 * @covers     \local_sentientia_api\scim\filter
 * @covers     \local_sentientia_api\scim\mapper
 * @covers     \local_sentientia_api\scim\user_resource
 */
final class scim_test extends \advanced_testcase {
    use \local_sentientia_org\test\bizlms_fixture;

    private const BASE = 'https://lms.example.test/local/sentientia_api/scim/v2.php';

    /** @var handler */
    private handler $h;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform not installed');
        }
        if (!class_exists('\local_sentientia_users\user_manager')) {
            $this->markTestSkipped('local_sentientia_users not installed');
        }
        \local_sentientia_platform\feature_flags::invalidate_caches();
        handler::reset_static_caches();
        $this->h = new handler(self::BASE);
    }

    protected function tearDown(): void {
        // Flag statics survive resetAfterTest — never leak our ON flags into later test classes.
        if (class_exists('\local_sentientia_platform\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
        handler::reset_static_caches();
        parent::tearDown();
    }

    private function flags_on(): void {
        $ff = '\local_sentientia_platform\feature_flags';
        $ff::set(handler::FLAG_MASTER, 0, true, null, 'phpunit');
        $ff::set(handler::FLAG_SCIM, 0, true, null, 'phpunit');
        $ff::invalidate_caches();
    }

    /** @return array{0:\stdClass,1:string} client row + bearer header */
    private function make_client(int $tenant = 0, array $extra = []): array {
        $made = client::create((object) (['name' => 'Entra test', 'costcenterid' => $tenant, 'auth' => 'oauth2'] + $extra));
        return [client::get($made['id']), 'Bearer ' . $made['token']];
    }

    private function call(string $method, string $path, string $auth, ?array $body = null, array $query = []): array {
        return $this->h->handle($method, $path, $query, $body === null ? null : json_encode($body), $auth);
    }

    private function user_body(string $username, string $email, string $ext = 'ext-1', bool $active = true): array {
        return [
            'schemas'    => [response::SCHEMA_USER],
            'userName'   => $username,
            'externalId' => $ext,
            'name'       => ['givenName' => 'Asha', 'familyName' => 'Rao'],
            'emails'     => [['value' => $email, 'type' => 'work', 'primary' => true]],
            'active'     => $active,
        ];
    }

    // ── Gates ───────────────────────────────────────────────────────────

    public function test_missing_or_bad_token_is_401(): void {
        $this->flags_on();
        $r = $this->call('GET', '/Users', '');
        $this->assertSame(401, $r['status']);
        $this->assertSame(response::SCHEMA_ERROR, $r['body']['schemas'][0]);
        $this->assertArrayHasKey('WWW-Authenticate', $r['headers']);
        $r = $this->call('GET', '/Users', 'Bearer not-a-real-token');
        $this->assertSame(401, $r['status']);
    }

    public function test_flags_off_is_503_even_with_valid_token(): void {
        [, $auth] = $this->make_client();
        $r = $this->call('GET', '/Users', $auth);
        $this->assertSame(503, $r['status']);
    }

    public function test_disabled_client_is_401(): void {
        $this->flags_on();
        [$c, $auth] = $this->make_client();
        client::set_enabled((int) $c->id, false);
        $this->assertSame(401, $this->call('GET', '/Users', $auth)['status']);
    }

    public function test_per_client_rate_limit_429(): void {
        $this->flags_on();
        [, $auth] = $this->make_client(0, ['ratelimit' => 2]);
        $this->assertSame(200, $this->call('GET', '/ServiceProviderConfig', $auth)['status']);
        $this->assertSame(200, $this->call('GET', '/ServiceProviderConfig', $auth)['status']);
        $r = $this->call('GET', '/ServiceProviderConfig', $auth);
        $this->assertSame(429, $r['status']);
        $this->assertSame('tooMany', $r['body']['scimType']);
    }

    // ── Discovery ───────────────────────────────────────────────────────

    public function test_discovery_documents(): void {
        $this->flags_on();
        [, $auth] = $this->make_client();
        $spc = $this->call('GET', '/ServiceProviderConfig', $auth);
        $this->assertSame(200, $spc['status']);
        $this->assertTrue($spc['body']['patch']['supported']);
        $this->assertFalse($spc['body']['bulk']['supported']);
        $rt = $this->call('GET', '/ResourceTypes', $auth);
        $this->assertSame('/Users', $rt['body']['Resources'][0]['endpoint']);
        $sc = $this->call('GET', '/Schemas', $auth);
        $this->assertSame(response::SCHEMA_USER, $sc['body']['Resources'][0]['id']);
        $this->assertSame(404, $this->call('GET', '/Groups', $auth)['status']);
    }

    // ── Create / read ───────────────────────────────────────────────────

    public function test_create_user_201_with_mapping_and_auth(): void {
        global $DB;
        $this->flags_on();
        [$c, $auth] = $this->make_client();
        $r = $this->call('POST', '/Users', $auth, $this->user_body('asha.rao@corp.example', 'asha.rao@corp.example'));
        $this->assertSame(201, $r['status'], json_encode($r['body']));
        $this->assertSame('asha.rao@corp.example', $r['body']['userName']);
        $this->assertSame('ext-1', $r['body']['externalId']);
        $this->assertTrue($r['body']['active']);
        $this->assertStringEndsWith('/Users/' . $r['body']['id'], $r['headers']['Location']);

        $u = $DB->get_record('user', ['id' => (int) $r['body']['id']], '*', MUST_EXIST);
        $this->assertSame('oauth2', $u->auth);
        $this->assertSame('Asha', $u->firstname);
        $this->assertSame((int) $u->id, mapper::userid_for((int) $c->id, 'ext-1'));

        $g = $this->call('GET', '/Users/' . $u->id, $auth);
        $this->assertSame(200, $g['status']);
        $this->assertSame('asha.rao@corp.example', $g['body']['emails'][0]['value']);
        $this->assertArrayHasKey('ETag', $g['headers']);
    }

    public function test_create_duplicate_active_username_409(): void {
        $this->flags_on();
        [, $auth] = $this->make_client();
        $this->assertSame(201, $this->call('POST', '/Users', $auth, $this->user_body('dup@x.test', 'dup@x.test', 'e1'))['status']);
        $r = $this->call('POST', '/Users', $auth, $this->user_body('dup@x.test', 'other@x.test', 'e2'));
        $this->assertSame(409, $r['status']);
        $this->assertSame('uniqueness', $r['body']['scimType']);
    }

    public function test_create_inactive_then_reprovision_reactivates(): void {
        global $DB;
        $this->flags_on();
        [, $auth] = $this->make_client();
        $r = $this->call('POST', '/Users', $auth, $this->user_body('re@x.test', 're@x.test', 'e-re', false));
        $this->assertSame(201, $r['status']);
        $id = (int) $r['body']['id'];
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $id]));

        // IdP re-creates the same externalId as active -> 200, reactivated, no duplicate.
        $r2 = $this->call('POST', '/Users', $auth, $this->user_body('re@x.test', 're@x.test', 'e-re', true));
        $this->assertSame(200, $r2['status']);
        $this->assertSame($id, (int) $r2['body']['id']);
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $id]));
    }

    public function test_bad_json_and_missing_required_400(): void {
        $this->flags_on();
        [, $auth] = $this->make_client();
        $r = $this->h->handle('POST', '/Users', [], '{not json', $auth);
        $this->assertSame(400, $r['status']);
        $r = $this->call('POST', '/Users', $auth, ['schemas' => [response::SCHEMA_USER], 'name' => ['givenName' => 'x']]);
        $this->assertSame(400, $r['status']);
        $this->assertSame('invalidValue', $r['body']['scimType']);
    }

    // ── List / filter ───────────────────────────────────────────────────

    public function test_list_filter_and_pagination(): void {
        $this->flags_on();
        [, $auth] = $this->make_client();
        foreach (['a', 'b', 'c'] as $i) {
            $this->call('POST', '/Users', $auth, $this->user_body("$i@x.test", "$i@x.test", "ext-$i"));
        }
        $all = $this->call('GET', '/Users', $auth, null, ['startIndex' => 1, 'count' => 2]);
        $this->assertSame(200, $all['status']);
        $this->assertSame(response::SCHEMA_LIST, $all['body']['schemas'][0]);
        $this->assertGreaterThanOrEqual(3, $all['body']['totalResults']);
        $this->assertSame(2, $all['body']['itemsPerPage']);
        $this->assertSame(1, $all['body']['startIndex']);

        $one = $this->call('GET', '/Users', $auth, null, ['filter' => 'userName eq "b@x.test"']);
        $this->assertSame(1, $one['body']['totalResults']);
        $this->assertSame('b@x.test', $one['body']['Resources'][0]['userName']);

        $ext = $this->call('GET', '/Users', $auth, null, ['filter' => 'externalId eq "ext-c"']);
        $this->assertSame(1, $ext['body']['totalResults']);
        $this->assertSame('ext-c', $ext['body']['Resources'][0]['externalId']);

        $none = $this->call('GET', '/Users', $auth, null, ['filter' => 'externalId eq "nope"']);
        $this->assertSame(0, $none['body']['totalResults']);

        $bad = $this->call('GET', '/Users', $auth, null, ['filter' => 'title co "x"']);
        $this->assertSame(400, $bad['status']);
        $this->assertSame('invalidFilter', $bad['body']['scimType']);
    }

    public function test_filter_parser_edge_cases(): void {
        $this->assertNull(filter::parse(''));
        $this->assertSame(['attr' => 'username', 'value' => 'a"b'], filter::parse('userName eq "a\"b"'));
        $this->assertSame(['attr' => 'email', 'value' => 'x@y'], filter::parse('emails[type eq "work"].value eq "x@y"'));
        $this->assertSame(['attr' => 'username', 'value' => 'z'],
            filter::parse('urn:ietf:params:scim:schemas:core:2.0:User:userName eq "z"'));
        $this->expectException(scim_exception::class);
        filter::parse('userName sw "a"');
    }

    // ── Update / patch / deactivate ─────────────────────────────────────

    public function test_patch_active_false_suspends_and_true_restores(): void {
        global $DB;
        $this->flags_on();
        [, $auth] = $this->make_client();
        $id = (int) $this->call('POST', '/Users', $auth, $this->user_body('p@x.test', 'p@x.test'))['body']['id'];

        $patch = ['schemas' => [response::SCHEMA_PATCH],
                  'Operations' => [['op' => 'Replace', 'path' => 'active', 'value' => 'False']]];
        $r = $this->call('PATCH', "/Users/$id", $auth, $patch);
        $this->assertSame(200, $r['status']);
        $this->assertFalse($r['body']['active']);
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $id]));

        $patch['Operations'][0]['value'] = true;
        $r = $this->call('PATCH', "/Users/$id", $auth, $patch);
        $this->assertTrue($r['body']['active']);
        $this->assertSame(0, (int) $DB->get_field('user', 'suspended', ['id' => $id]));
    }

    public function test_patch_name_email_username_and_ignores_unknown(): void {
        global $DB;
        $this->flags_on();
        [, $auth] = $this->make_client();
        $id = (int) $this->call('POST', '/Users', $auth, $this->user_body('n@x.test', 'n@x.test'))['body']['id'];
        $patch = ['schemas' => [response::SCHEMA_PATCH], 'Operations' => [
            ['op' => 'replace', 'path' => 'name.givenName', 'value' => 'Meera'],
            ['op' => 'replace', 'path' => 'emails[type eq "work"].value', 'value' => 'meera@x.test'],
            ['op' => 'replace', 'path' => 'userName', 'value' => 'Meera@X.test'],
            ['op' => 'replace', 'path' => 'phoneNumbers[type eq "work"].value', 'value' => '+91 1'],
            ['op' => 'replace', 'value' => ['name' => ['familyName' => 'Iyer']]],
        ]];
        $r = $this->call('PATCH', "/Users/$id", $auth, $patch);
        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $u = $DB->get_record('user', ['id' => $id]);
        $this->assertSame('Meera', $u->firstname);
        $this->assertSame('Iyer', $u->lastname);
        $this->assertSame('meera@x.test', $u->email);
        $this->assertSame('meera@x.test', $u->username);
    }

    public function test_put_replaces_and_delete_deactivates(): void {
        global $DB;
        $this->flags_on();
        [$c, $auth] = $this->make_client();
        $id = (int) $this->call('POST', '/Users', $auth, $this->user_body('d@x.test', 'd@x.test', 'ext-d'))['body']['id'];

        $put = $this->user_body('d@x.test', 'd2@x.test', 'ext-d');
        $put['name'] = ['givenName' => 'Dev', 'familyName' => 'Nair'];
        $r = $this->call('PUT', "/Users/$id", $auth, $put);
        $this->assertSame(200, $r['status']);
        $this->assertSame('d2@x.test', $DB->get_field('user', 'email', ['id' => $id]));
        $this->assertSame('Nair', $DB->get_field('user', 'lastname', ['id' => $id]));

        $r = $this->call('DELETE', "/Users/$id", $auth);
        $this->assertSame(204, $r['status']);
        $this->assertNull($r['body']);
        $this->assertSame(1, (int) $DB->get_field('user', 'suspended', ['id' => $id]));
        $this->assertSame(0, (int) $DB->get_field('user', 'deleted', ['id' => $id]), 'soft deactivation only');
        $this->assertNull(mapper::userid_for((int) $c->id, 'ext-d'));
    }

    public function test_unknown_user_404_and_method_405(): void {
        $this->flags_on();
        [, $auth] = $this->make_client();
        $this->assertSame(404, $this->call('GET', '/Users/999999', $auth)['status']);
        $this->assertSame(405, $this->call('DELETE', '/Users', $auth)['status']);
    }

    // ── Tenant isolation (needs the BizLMS open_path column) ────────────

    public function test_tenant_scoped_client_cannot_see_other_tenant(): void {
        global $DB;
        $this->ensure_bizlms_schema();   // Provisions open_* columns on the phpunit schema.
        handler::reset_static_caches();
        $this->flags_on();

        $other = $this->getDataGenerator()->create_user(['username' => 'zeea.user']);
        $DB->set_field('user', 'open_path', '/177', ['id' => $other->id]);

        [, $auth1] = $this->make_client(1);
        $this->assertSame(404, $this->call('GET', '/Users/' . $other->id, $auth1)['status'], 'tenant 1 client must not see a /177 user');

        $r = $this->call('POST', '/Users', $auth1, $this->user_body('t1@x.test', 't1@x.test', 'ext-t1'));
        $this->assertSame(201, $r['status']);
        $this->assertSame('/1', $DB->get_field('user', 'open_path', ['id' => (int) $r['body']['id']]));

        $list = $this->call('GET', '/Users', $auth1);
        foreach ($list['body']['Resources'] as $res) {
            $this->assertNotSame((string) $other->id, $res['id']);
        }
    }
}
