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

use local_sentientia_xapi\lrs\store;
use local_sentientia_xapi\model\statement;

/**
 * @covers \local_sentientia_xapi\lrs\store
 */
class lrs_store_test extends \advanced_testcase {

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

    public function test_resolve_actor_by_account(): void {
        $user = $this->getDataGenerator()->create_user();
        $lrs  = new store();

        $actorid = $lrs->resolve_actor_userid([
            'objectType' => 'Agent',
            'account'    => ['homePage' => 'https://airpay.academy', 'name' => (string) $user->id],
        ]);

        $this->assertEquals($user->id, $actorid);
    }

    public function test_resolve_actor_by_mbox(): void {
        $user = $this->getDataGenerator()->create_user();
        $lrs  = new store();

        $actorid = $lrs->resolve_actor_userid([
            'objectType' => 'Agent',
            'mbox'       => 'mailto:' . $user->email,
        ]);

        $this->assertEquals($user->id, $actorid);
    }

    public function test_resolve_actor_unknown_returns_null(): void {
        $lrs     = new store();
        $actorid = $lrs->resolve_actor_userid([
            'objectType' => 'Agent',
            'mbox'       => 'mailto:nobody@unknown.example',
        ]);
        $this->assertNull($actorid);
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
