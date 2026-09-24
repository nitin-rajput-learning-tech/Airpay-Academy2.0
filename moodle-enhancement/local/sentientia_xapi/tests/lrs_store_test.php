<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * PHPUnit tests for the LRS store.
 *
 * Covers: statement storage, idempotency, actor resolution,
 * tenant isolation, and purge/void.
 *
 * @package    local_sentientia_xapi
 * @category   phpunit
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_xapi\tests;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use local_sentientia_xapi\lrs\store;
use local_sentientia_xapi\model\statement;
use local_sentientia_xapi\privacy\provider;

/**
 * @covers \local_sentientia_xapi\lrs\store
 * @covers \local_sentientia_xapi\privacy\provider
 *
 * @group tenant_isolation
 */
class lrs_store_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    private function make_statement(string $verb = statement::VERB_COMPLETED): statement {
        return new statement([
            'id'     => statement::generate_uuid(),
            'actor'  => [
                'objectType' => 'Agent',
                'account'    => ['homePage' => 'https://airpay.academy', 'name' => '1'],
            ],
            'verb'   => ['id' => $verb, 'display' => ['en-US' => 'completed']],
            'object' => ['objectType' => 'Activity', 'id' => 'https://airpay.academy/course/view.php?id=1'],
        ]);
    }

    // ─── Storage ──────────────────────────────────────────────────────

    public function test_put_stores_row(): void {
        global $DB;
        $lrs  = new store();
        $stmt = $this->make_statement();
        $uuid = $lrs->put($stmt, 1, 42, store::SOURCE_MOODLE);

        $this->assertNotEmpty($uuid);
        $this->assertTrue($DB->record_exists('local_sentientia_xapi_stmts', [
            'statementid'  => $uuid,
            'costcenterid' => 1,
        ]));
    }

    public function test_put_assigns_uuid_when_missing(): void {
        $lrs  = new store();
        $stmt = new statement([
            'actor'  => ['objectType' => 'Agent', 'mbox' => 'mailto:t@airpay.in'],
            'verb'   => ['id' => statement::VERB_EXPERIENCED],
            'object' => ['objectType' => 'Activity', 'id' => 'https://example.com/course'],
        ]);
        $uuid = $lrs->put($stmt, 1);
        $this->assertNotEmpty($uuid);
        $this->assertNotNull($stmt->get_id());
    }

    public function test_put_is_idempotent(): void {
        global $DB;
        $lrs  = new store();
        $stmt = $this->make_statement();

        $uuid1 = $lrs->put($stmt, 1);
        $uuid2 = $lrs->put($stmt, 1);  // Same statement, same tenant.

        $this->assertSame($uuid1, $uuid2);
        $count = $DB->count_records('local_sentientia_xapi_stmts', ['statementid' => $uuid1, 'costcenterid' => 1]);
        $this->assertEquals(1, $count);
    }

    // ─── Tenant isolation ─────────────────────────────────────────────

    public function test_same_uuid_different_tenants(): void {
        global $DB;
        $lrs  = new store();
        $stmt = $this->make_statement();

        $lrs->put($stmt, 1);   // Tenant 1.
        $lrs->put($stmt, 77);  // Tenant 77 — different row.

        $this->assertEquals(2, $DB->count_records('local_sentientia_xapi_stmts',
            ['statementid' => $stmt->get_id()]));
    }

    public function test_get_scoped_to_tenant(): void {
        $lrs   = new store();
        $stmt1 = $this->make_statement();
        $stmt2 = $this->make_statement();

        $lrs->put($stmt1, 1);
        $lrs->put($stmt2, 77);

        $rows_t1 = $lrs->get_statements(1);
        $rows_t2 = $lrs->get_statements(77);

        $this->assertCount(1, $rows_t1);
        $this->assertCount(1, $rows_t2);
        $this->assertEquals($stmt1->get_id(), $rows_t1[0]->statementid);
        $this->assertEquals($stmt2->get_id(), $rows_t2[0]->statementid);
    }

    public function test_get_returns_false_for_other_tenant(): void {
        $lrs  = new store();
        $stmt = $this->make_statement();
        $lrs->put($stmt, 1);

        $result = $lrs->get($stmt->get_id(), 77);  // Wrong tenant.
        $this->assertFalse($result);
    }

    // ─── Actor resolution ─────────────────────────────────────────────
    //
    // actorid is what the privacy provider exports and erases by, so a wrong
    // one files a statement about somebody else under the erased user.
    // Until 2026-09-24 the account IFI resolved whatever its homePage said,
    // and no IFI was checked against the posting client's tenant.

    /** An account actor, on this site's homePage unless told otherwise. */
    private function account_actor(string $name, ?string $homepage = null): array {
        global $CFG;
        return [
            'objectType' => 'Agent',
            'account'    => ['homePage' => $homepage ?? rtrim($CFG->wwwroot, '/'), 'name' => $name],
        ];
    }

    /** A statement by $actor. */
    private function statement_by(array $actor): statement {
        return new statement([
            'id'     => statement::generate_uuid(),
            'actor'  => $actor,
            'verb'   => ['id' => statement::VERB_COMPLETED, 'display' => ['en-US' => 'completed']],
            'object' => ['objectType' => 'Activity', 'id' => 'https://content.example/au/1'],
        ]);
    }

    /** A user placed in the tenant tree at $path (null = no path at all). */
    private function user_at(?string $path, array $record = []): \stdClass {
        global $DB;
        $this->ensure_bizlms_schema();
        $user = $this->getDataGenerator()->create_user($record);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    private function require_tenant_helper(): void {
        if (!class_exists('\local_sentientia_platform\tenant')) {
            $this->markTestSkipped('local_sentientia_platform is not installed.');
        }
    }

    public function test_resolve_actor_by_account(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user();
        $lrs  = new store();

        $this->assertEquals($user->id,
            $lrs->resolve_actor_userid($this->account_actor((string) $user->id), 0));
        // A trailing slash on the homePage is still this site.
        $this->assertEquals($user->id, $lrs->resolve_actor_userid(
            $this->account_actor((string) $user->id, rtrim($CFG->wwwroot, '/') . '/'), 0));
    }

    public function test_resolve_actor_round_trips_build_actor(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user();

        // The actor our own observer emits must resolve back to its user.
        $actor = statement::build_actor($user, $CFG->wwwroot);
        $this->assertEquals($user->id, (new store())->resolve_actor_userid($actor, 0));
    }

    public function test_resolve_actor_foreign_homepage_returns_null(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user();
        $lrs  = new store();
        $site = rtrim($CFG->wwwroot, '/');

        // An account name is unique only within its homePage: every one of
        // these is somebody on another system who happens to carry our id.
        foreach ([
            'https://partner-lms.example',   // Another system entirely.
            $site . '.partner.example',      // Starts with our wwwroot.
            $site . '/other',                // A path under our wwwroot.
            'redacted',                      // A redacted actor posted back.
        ] as $homepage) {
            $this->assertNull(
                $lrs->resolve_actor_userid($this->account_actor((string) $user->id, $homepage), 0),
                $homepage);
        }
    }

    public function test_resolve_actor_by_mbox(): void {
        $user = $this->getDataGenerator()->create_user();
        $lrs  = new store();

        $actorid = $lrs->resolve_actor_userid([
            'objectType' => 'Agent',
            'mbox'       => 'mailto:' . $user->email,
        ], 0);

        $this->assertEquals($user->id, $actorid);
    }

    public function test_resolve_actor_by_openid(): void {
        $user = $this->getDataGenerator()->create_user(['idnumber' => 'https://id.airpay.example/emp/1001']);
        $lrs  = new store();

        $actorid = $lrs->resolve_actor_userid([
            'objectType' => 'Agent',
            'openid'     => 'https://id.airpay.example/emp/1001',
        ], 0);

        $this->assertEquals($user->id, $actorid);
    }

    public function test_resolve_actor_unknown_returns_null(): void {
        $lrs     = new store();
        $actorid = $lrs->resolve_actor_userid([
            'objectType' => 'Agent',
            'mbox'       => 'mailto:nobody@unknown.example',
        ], 0);
        $this->assertNull($actorid);
    }

    public function test_resolve_actor_ambiguous_email_returns_null(): void {
        $this->getDataGenerator()->create_user(['email' => 'shared@airpay.example']);
        $this->getDataGenerator()->create_user(['email' => 'shared@airpay.example']);

        // Two live users share the mailbox: attributing to either is a guess.
        $this->assertNull((new store())->resolve_actor_userid([
            'objectType' => 'Agent',
            'mbox'       => 'mailto:shared@airpay.example',
        ], 0));
    }

    public function test_resolve_actor_tenant_client_maps_only_its_own_users(): void {
        $this->require_tenant_helper();
        $inside    = $this->user_at('/1/2', ['idnumber' => 'https://id.airpay.example/inside']);
        $root      = $this->user_at('/1');
        $other     = $this->user_at('/77/5', ['idnumber' => 'https://id.airpay.example/other']);
        $lookalike = $this->user_at('/177/3');   // '/1' must not prefix-match '/177'.
        $emptypath = $this->user_at('');
        $nopath    = $this->user_at(null);
        $lrs       = new store();

        $account = fn(\stdClass $u) => $this->account_actor((string) $u->id);
        $mbox    = fn(\stdClass $u) => ['objectType' => 'Agent', 'mbox' => 'mailto:' . $u->email];
        $openid  = fn(\stdClass $u) => ['objectType' => 'Agent', 'openid' => $u->idnumber];

        // Tenant 1's client: its own users resolve, by every IFI...
        $this->assertEquals($inside->id, $lrs->resolve_actor_userid($account($inside), 1));
        $this->assertEquals($root->id, $lrs->resolve_actor_userid($account($root), 1));
        $this->assertEquals($inside->id, $lrs->resolve_actor_userid($mbox($inside), 1));
        $this->assertEquals($inside->id, $lrs->resolve_actor_userid($openid($inside), 1));

        // ...and nobody else does, by any IFI.
        foreach ([$other, $lookalike, $emptypath, $nopath] as $u) {
            $this->assertNull($lrs->resolve_actor_userid($account($u), 1), "account {$u->open_path}");
            $this->assertNull($lrs->resolve_actor_userid($mbox($u), 1), "mbox {$u->open_path}");
        }
        $this->assertNull($lrs->resolve_actor_userid($openid($other), 1));

        // Tenant 77's client: the reverse.
        $this->assertEquals($other->id, $lrs->resolve_actor_userid($account($other), 77));
        $this->assertNull($lrs->resolve_actor_userid($account($inside), 77));

        // Being in the tenant does not excuse a foreign homePage.
        $this->assertNull($lrs->resolve_actor_userid(
            $this->account_actor((string) $inside->id, 'https://partner-lms.example'), 1));

        // A platform credential (costcenterid 0) resolves anybody.
        $this->assertEquals($other->id, $lrs->resolve_actor_userid($account($other), 0));
        $this->assertEquals($nopath->id, $lrs->resolve_actor_userid($account($nopath), 0));
    }

    public function test_resolve_actor_same_email_in_two_tenants(): void {
        $this->require_tenant_helper();
        $a     = $this->user_at('/1/2', ['email' => 'shared@airpay.example']);
        $b     = $this->user_at('/77', ['email' => 'shared@airpay.example']);
        $actor = ['objectType' => 'Agent', 'mbox' => 'mailto:shared@airpay.example'];
        $lrs   = new store();

        // Each tenant's client means its own user...
        $this->assertEquals($a->id, $lrs->resolve_actor_userid($actor, 1));
        $this->assertEquals($b->id, $lrs->resolve_actor_userid($actor, 77));
        // ...a platform credential cannot tell them apart: nobody, not the first.
        $this->assertNull($lrs->resolve_actor_userid($actor, 0));
    }

    /**
     * The failure this guards, end to end: erasing a user must redact their
     * own LRS statements and leave alone the ones the resolver refused.
     */
    public function test_erasure_skips_statements_about_somebody_else(): void {
        global $DB;
        $this->require_tenant_helper();
        $subject   = $this->user_at('/1/2', ['email' => 'shared@airpay.example']);
        $bystander = $this->user_at('/77', ['email' => 'shared@airpay.example']);
        $lrs       = new store();

        $post = function (array $actor, int $cid) use ($lrs): array {
            $uuid = $lrs->put($this->statement_by($actor), $cid,
                $lrs->resolve_actor_userid($actor, $cid), store::SOURCE_LRS);
            return [$uuid, $cid, $actor];
        };

        // Tenant 1's client, about the subject.
        $own = $post($this->account_actor((string) $subject->id), 1);
        // Tenant 1's client, about a partner-LMS learner who carries the subject's id there.
        $partner = $post($this->account_actor((string) $subject->id, 'https://partner-lms.example'), 1);
        // Tenant 77's client, about its own user, who shares the subject's mailbox.
        $cross = $post(['objectType' => 'Agent', 'mbox' => 'mailto:shared@airpay.example'], 77);

        provider::anonymise_data_for_user(new approved_contextlist(
            $subject, 'local_sentientia_xapi', [\context_system::instance()->id]));

        $row = fn(array $posted) => $DB->get_record('local_sentientia_xapi_stmts',
            ['statementid' => $posted[0], 'costcenterid' => $posted[1]], '*', MUST_EXIST);

        // The subject's own statement: redacted, still keyed to them.
        $this->assertEquals($subject->id, $row($own)->actorid);
        $this->assertSame('redacted', json_decode($row($own)->actor, true)['account']['name']);

        // The partner learner's: never linked to the subject, untouched.
        $this->assertNull($row($partner)->actorid);
        $this->assertEquals($partner[2], json_decode($row($partner)->actor, true));

        // Tenant 77's: filed under its own user, untouched.
        $this->assertEquals($bystander->id, $row($cross)->actorid);
        $this->assertEquals($cross[2], json_decode($row($cross)->actor, true));
    }

    // ─── Void ─────────────────────────────────────────────────────────

    public function test_void_statement(): void {
        global $DB;
        $lrs  = new store();
        $stmt = $this->make_statement();
        $uuid = $lrs->put($stmt, 1);

        $result = $lrs->void_statement($uuid, 1);
        $this->assertTrue($result);

        $row = $DB->get_record('local_sentientia_xapi_stmts', ['statementid' => $uuid, 'costcenterid' => 1]);
        $this->assertEquals(1, $row->voided);

        // get() should not return voided statements.
        $this->assertFalse($lrs->get($uuid, 1));
    }

    public function test_void_nonexistent_returns_false(): void {
        $lrs    = new store();
        $result = $lrs->void_statement(statement::generate_uuid(), 1);
        $this->assertFalse($result);
    }

    // ─── Purge ───────────────────────────────────────────────────────

    public function test_purge_removes_old_statements(): void {
        global $DB;
        set_config('retention_days', 30, 'local_sentientia_xapi');

        $lrs  = new store();
        $stmt = $this->make_statement();
        $uuid = $lrs->put($stmt, 1);

        // Back-date the stored timestamp to 60 days ago.
        $DB->set_field('local_sentientia_xapi_stmts', 'timestored',
            time() - (60 * DAYSECS),
            ['statementid' => $uuid, 'costcenterid' => 1]);

        $lrs->purge_old_statements();

        $this->assertFalse($DB->record_exists('local_sentientia_xapi_stmts',
            ['statementid' => $uuid, 'costcenterid' => 1]));
    }

    public function test_purge_keeps_recent_statements(): void {
        global $DB;
        set_config('retention_days', 30, 'local_sentientia_xapi');

        $lrs  = new store();
        $stmt = $this->make_statement();
        $uuid = $lrs->put($stmt, 1);

        $lrs->purge_old_statements();

        $this->assertTrue($DB->record_exists('local_sentientia_xapi_stmts',
            ['statementid' => $uuid, 'costcenterid' => 1]));
    }

    public function test_purge_noops_when_retention_zero(): void {
        global $DB;
        set_config('retention_days', 0, 'local_sentientia_xapi');

        $lrs  = new store();
        $stmt = $this->make_statement();
        $uuid = $lrs->put($stmt, 1);

        // Back-date.
        $DB->set_field('local_sentientia_xapi_stmts', 'timestored',
            time() - (3650 * DAYSECS), ['statementid' => $uuid, 'costcenterid' => 1]);

        $lrs->purge_old_statements();

        // Row must still exist.
        $this->assertTrue($DB->record_exists('local_sentientia_xapi_stmts',
            ['statementid' => $uuid, 'costcenterid' => 1]));
    }
}
