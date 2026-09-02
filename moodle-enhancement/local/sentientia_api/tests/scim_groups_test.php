<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\scim\attestation;
use local_sentientia_api\scim\client;
use local_sentientia_api\scim\group_resource;
use local_sentientia_api\scim\handler;
use local_sentientia_api\scim\response;

/**
 * SCIM Groups (org tree) + attestation log (ADR-030 Wave C).
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\scim\group_resource
 * @covers     \local_sentientia_api\scim\attestation
 * @covers     \local_sentientia_api\scim\handler
 */
final class scim_groups_test extends \advanced_testcase {
    use \local_sentientia_org\test\bizlms_fixture;

    private const BASE = 'https://lms.example.test/local/sentientia_api/scim/v2.php';

    /** @var handler */
    private handler $h;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists('\local_sentientia_platform\feature_flags') || !class_exists('\local_sentientia_users\user_manager')) {
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
        if (class_exists('\local_sentientia_platform\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
        handler::reset_static_caches();
        parent::tearDown();
    }

    /** Insert an org node; returns the row. */
    private function org(string $fullname, string $shortname, string $path, int $parentid, int $depth): \stdClass {
        global $DB;
        $rec = (object) [
            'fullname' => $fullname, 'shortname' => $shortname, 'description' => '', 'parentid' => $parentid,
            'path' => $path, 'depth' => $depth, 'visible' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $rec->id = $DB->insert_record(group_resource::ORG_TABLE, $rec);
        return $rec;
    }

    /** Two tenants with a department each; returns [root9, dept9, root8]. Uses ids as path segments. */
    private function tree(): array {
        global $DB;
        $r9 = $this->org('Tenant Nine', 't9', '/0', 0, 1);
        $DB->set_field(group_resource::ORG_TABLE, 'path', '/' . $r9->id, ['id' => $r9->id]);
        $r9->path = '/' . $r9->id;
        $d9 = $this->org('Nine Sales', 't9-sales', $r9->path . '/0', (int) $r9->id, 2);
        $DB->set_field(group_resource::ORG_TABLE, 'path', $r9->path . '/' . $d9->id, ['id' => $d9->id]);
        $d9->path = $r9->path . '/' . $d9->id;
        $r8 = $this->org('Tenant Eight', 't8', '/0', 0, 1);
        $DB->set_field(group_resource::ORG_TABLE, 'path', '/' . $r8->id, ['id' => $r8->id]);
        $r8->path = '/' . $r8->id;
        return [$r9, $d9, $r8];
    }

    private function make_client(int $tenant): string {
        $made = client::create((object) ['name' => 'IdP', 'costcenterid' => $tenant, 'auth' => 'oauth2']);
        return 'Bearer ' . $made['token'];
    }

    private function call(string $method, string $path, string $auth, ?array $body = null, array $query = []): array {
        return $this->h->handle($method, $path, $query, $body === null ? null : json_encode($body), $auth);
    }

    public function test_groups_list_and_get_are_tenant_scoped(): void {
        global $DB;
        [$r9, $d9, $r8] = $this->tree();
        $auth = $this->make_client((int) $r9->id);

        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $d9->path, ['id' => $u->id]);

        $list = $this->call('GET', '/Groups', $auth);
        $this->assertSame(200, $list['status']);
        $ids = array_map(fn($g) => (int) $g['id'], $list['body']['Resources']);
        $this->assertContains((int) $r9->id, $ids);
        $this->assertContains((int) $d9->id, $ids);
        $this->assertNotContains((int) $r8->id, $ids, 'other tenant root must not be listed');
        $this->assertSame(response::SCHEMA_GROUP, $list['body']['Resources'][0]['schemas'][0]);

        $g = $this->call('GET', '/Groups/' . $d9->id, $auth);
        $this->assertSame(200, $g['status']);
        $this->assertSame('Nine Sales', $g['body']['displayName']);
        $this->assertSame('t9-sales', $g['body']['externalId']);
        $this->assertCount(1, $g['body']['members']);
        $this->assertSame((string) $u->id, $g['body']['members'][0]['value']);
        $this->assertStringEndsWith('/Users/' . $u->id, $g['body']['members'][0]['$ref']);

        $this->assertSame(404, $this->call('GET', '/Groups/' . $r8->id, $auth)['status']);
    }

    public function test_groups_structure_is_read_only(): void {
        [$r9] = $this->tree();
        $auth = $this->make_client((int) $r9->id);
        $body = ['schemas' => [response::SCHEMA_GROUP], 'displayName' => 'New'];
        $this->assertSame(501, $this->call('POST', '/Groups', $auth, $body)['status']);
        $this->assertSame(501, $this->call('PUT', '/Groups/' . $r9->id, $auth, $body)['status']);
        $this->assertSame(501, $this->call('DELETE', '/Groups/' . $r9->id, $auth)['status']);
        $this->assertSame('/Groups', $this->call('GET', '/ResourceTypes', $auth)['body']['Resources'][1]['endpoint']);
    }

    public function test_patch_members_moves_users_and_attests(): void {
        global $DB;
        [$r9, $d9, $r8] = $this->tree();
        $auth = $this->make_client((int) $r9->id);

        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $r9->path, ['id' => $u->id]);
        $outsider = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $r8->path, ['id' => $outsider->id]);

        // Add -> user placed in the department.
        $patch = ['schemas' => [response::SCHEMA_PATCH],
                  'Operations' => [['op' => 'add', 'path' => 'members', 'value' => [['value' => (string) $u->id]]]]];
        $r = $this->call('PATCH', '/Groups/' . $d9->id, $auth, $patch);
        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $this->assertSame($d9->path, $DB->get_field('user', 'open_path', ['id' => $u->id]));
        // open_costcenterid is only written where that BizLMS column exists (update_record skips
        // unknown columns); the fixture provisions open_path only, so assert on the path.
        $this->assertCount(1, $r['body']['members']);
        $this->assertSame(1, $DB->count_records(attestation::TABLE, ['userid' => $u->id, 'action' => attestation::MOVED]));

        // Remove -> back to the tenant root.
        $patch['Operations'] = [['op' => 'remove', 'path' => 'members[value eq "' . $u->id . '"]']];
        $r = $this->call('PATCH', '/Groups/' . $d9->id, $auth, $patch);
        $this->assertSame(200, $r['status']);
        $this->assertSame($r9->path, $DB->get_field('user', 'open_path', ['id' => $u->id]));
        $this->assertCount(0, $r['body']['members']);

        // A user from another tenant cannot be pulled in.
        $patch['Operations'] = [['op' => 'add', 'path' => 'members', 'value' => [['value' => (string) $outsider->id]]]];
        $r = $this->call('PATCH', '/Groups/' . $d9->id, $auth, $patch);
        $this->assertSame(400, $r['status']);
        $this->assertSame($r8->path, $DB->get_field('user', 'open_path', ['id' => $outsider->id]), 'outsider untouched');
    }

    public function test_user_lifecycle_writes_attestation_and_csv(): void {
        global $DB;
        [$r9] = $this->tree();
        $auth = $this->make_client((int) $r9->id);
        $body = [
            'schemas' => [response::SCHEMA_USER], 'userName' => 'att@x.test', 'externalId' => 'ext-att',
            'name' => ['givenName' => 'Att', 'familyName' => 'Est'],
            'emails' => [['value' => 'att@x.test', 'primary' => true]], 'active' => true,
        ];
        $id = (int) $this->call('POST', '/Users', $auth, $body)['body']['id'];
        $this->call('PATCH', "/Users/$id", $auth, ['schemas' => [response::SCHEMA_PATCH],
            'Operations' => [['op' => 'replace', 'path' => 'name.givenName', 'value' => 'Atta']]]);
        $this->call('PATCH', "/Users/$id", $auth, ['schemas' => [response::SCHEMA_PATCH],
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => false]]]);
        $this->call('PATCH', "/Users/$id", $auth, ['schemas' => [response::SCHEMA_PATCH],
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => true]]]);
        $this->call('DELETE', "/Users/$id", $auth);

        $actions = array_values($DB->get_fieldset_sql(
            "SELECT action FROM {" . attestation::TABLE . "} WHERE userid = :u ORDER BY id ASC", ['u' => $id]));
        $this->assertSame(['created', 'updated', 'deactivated', 'reactivated', 'deactivated'], $actions);
        $this->assertSame('ext-att', $DB->get_field(attestation::TABLE, 'externalid', ['userid' => $id, 'action' => 'created']));

        $recent = attestation::recent(10);
        $this->assertSame('att@x.test', reset($recent)->username);

        $csv = attestation::to_csv();
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertSame('time_utc,action,client,userid,username,externalid,detail', trim($lines[array_key_first($lines)]));
        $this->assertCount(6, $lines);   // header + 5 events
        $this->assertStringContainsString(',deactivated,IdP,' . $id . ',att@x.test,', $csv);
    }

    public function test_attestation_prune_respects_retention(): void {
        global $DB;
        set_config('log_retention_days', 30, 'local_sentientia_api');
        attestation::record(0, 42, attestation::CREATED, 'x', null);
        $DB->set_field(attestation::TABLE, 'timecreated', time() - 40 * DAYSECS, ['userid' => 42]);
        attestation::record(0, 43, attestation::CREATED, 'y', null);
        $this->assertSame(1, attestation::prune());
        $this->assertSame(1, $DB->count_records(attestation::TABLE));
    }
}
