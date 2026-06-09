<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_pwa\subscription_manager
 *
 * Audit fix #4 (2026-05-21) regression suite — verifies that the
 * tenant-boundary guards on subscription_manager work as documented in
 * `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #4.
 *
 * The threat being defended against: a malicious or compromised caller
 * in Tenant A enumerates Tenant B's push subscriptions and sends them
 * payloads they shouldn't receive. The fix scopes every read by
 * (customerid, tenantid) at the data-access layer; this suite confirms
 * the scope filter actually filters.
 *
 * Coverage matrix
 * ---------------
 *   - for_user without scope            returns ALL user rows
 *   - for_user with customerid scope    filters out other customers' rows
 *   - for_user with tenantid scope      filters out other tenants' rows
 *   - for_user with both scopes         filters to the matching subset
 *   - tenant_for_user from open_path    extracts first integer segment
 *   - tenant_for_user for deleted user  returns 0 (defensive default)
 *   - tenant_for_user for suspended     returns 0
 *   - tenant_for_user blank open_path   returns 0
 *   - tenant_for_user malformed path    returns 0 (non-digit first seg)
 *
 * The push_sender end-to-end cross-tenant refusal is covered at the
 * source-level grep in audit_fixes_test.php — testing it as a unit
 * here would require either real VAPID keys or a network mock layer,
 * neither of which is in scope for this PHPUnit suite.
 */
class tenant_isolation_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->ensure_open_path_column();
    }

    /**
     * The `open_path` column on `mdl_user` is added by the BizLMS
     * plugin suite (currently disabled in this XAMPP — see
     * `bizlms_disabled/`). The phpu_user prefix never gets the column
     * via install.xml because BizLMS isn't deployed. We add it here so
     * subscription_manager::tenant_for_user() can SELECT it.
     *
     * Idempotent: the column-exists check makes re-entry safe across
     * tests in the same run.
     */
    private function ensure_open_path_column(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255',
            null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  for_user — scope filter behaviour
    // ════════════════════════════════════════════════════════════════

    /**
     * No scope provided → return every subscription owned by the user
     * regardless of customer or tenant. This is the legacy back-compat
     * path used by `for_user_safe()` (manage-my-devices UI) where the
     * caller is the user themselves.
     */
    public function test_for_user_without_scope_returns_all_rows(): void {
        $user = $this->make_user_minimal([]);

        // Three subs in three different (customer, tenant) combos.
        $this->insert_sub($user->id, 1, 1,  'ep-airpay-t1');
        $this->insert_sub($user->id, 1, 77, 'ep-public-t77');
        $this->insert_sub($user->id, 2, 1,  'ep-cust2-t1');

        $rows = subscription_manager::for_user($user->id);
        $this->assertCount(3, $rows,
            'No-scope read MUST return all 3 rows for the user');
    }

    /**
     * customerid filter: only rows matching that customer come back.
     * A row in customer=2 must NEVER appear when querying customer=1.
     */
    public function test_for_user_customerid_filter_excludes_other_customers(): void {
        $user = $this->make_user_minimal([]);
        $this->insert_sub($user->id, 1, 1, 'ep-c1');
        $this->insert_sub($user->id, 2, 1, 'ep-c2');

        $c1_rows = subscription_manager::for_user($user->id, 1);
        $this->assertCount(1, $c1_rows, 'customer=1 scope returns 1 row');
        $this->assertSame('ep-c1', reset($c1_rows)->endpoint);

        $c2_rows = subscription_manager::for_user($user->id, 2);
        $this->assertCount(1, $c2_rows, 'customer=2 scope returns 1 row');
        $this->assertSame('ep-c2', reset($c2_rows)->endpoint);

        // Cross-check: no leakage either direction.
        foreach ($c1_rows as $r) {
            $this->assertSame(1, (int) $r->customerid,
                'No row from customer=2 may surface in a customer=1 query');
        }
    }

    /**
     * tenantid filter: only rows matching that tenant come back.
     * The Airpay row (T1) must NEVER appear when querying ZEEA (T177).
     */
    public function test_for_user_tenantid_filter_excludes_other_tenants(): void {
        $user = $this->make_user_minimal([]);
        $this->insert_sub($user->id, 1, 1,   'ep-t1');
        $this->insert_sub($user->id, 1, 77,  'ep-t77');
        $this->insert_sub($user->id, 1, 177, 'ep-t177');

        $t1_rows = subscription_manager::for_user($user->id, null, 1);
        $this->assertCount(1, $t1_rows);
        $this->assertSame('ep-t1', reset($t1_rows)->endpoint);

        $t177_rows = subscription_manager::for_user($user->id, null, 177);
        $this->assertCount(1, $t177_rows);
        $this->assertSame('ep-t177', reset($t177_rows)->endpoint);
    }

    /**
     * Both filters set: AND combined — row must match BOTH customer
     * AND tenant. This is the push_sender contract on send().
     */
    public function test_for_user_both_filters_combine_as_and(): void {
        $user = $this->make_user_minimal([]);
        $this->insert_sub($user->id, 1, 1,  'ep-c1-t1');
        $this->insert_sub($user->id, 1, 77, 'ep-c1-t77');
        $this->insert_sub($user->id, 2, 1,  'ep-c2-t1');
        $this->insert_sub($user->id, 2, 77, 'ep-c2-t77');

        $rows = subscription_manager::for_user($user->id, 1, 77);
        $this->assertCount(1, $rows,
            'Both filters AND-combined → exactly 1 matching row');
        $this->assertSame('ep-c1-t77', reset($rows)->endpoint);
    }

    /**
     * Empty result set when scope matches no row — must return [], NOT
     * throw, NOT return false. Downstream `if (empty($subs)) return 0`
     * pattern in push_sender depends on this contract.
     */
    public function test_for_user_returns_empty_array_on_no_match(): void {
        $user = $this->make_user_minimal([]);
        $this->insert_sub($user->id, 1, 1, 'ep-only');

        $rows = subscription_manager::for_user($user->id, 9999, 9999);
        $this->assertIsArray($rows);
        $this->assertCount(0, $rows,
            'Non-matching scope returns empty array, not false/null');
    }

    // ════════════════════════════════════════════════════════════════
    //  tenant_for_user — open_path parsing edge cases
    // ════════════════════════════════════════════════════════════════

    /**
     * Standard open_path "/77/2/3" → first segment 77 is the tenant.
     */
    public function test_tenant_for_user_parses_open_path(): void {
        $user = $this->make_user_minimal(['open_path' => '/77/2/3/']);
        $this->assertSame(77, subscription_manager::tenant_for_user($user->id));
    }

    /**
     * No leading slash, but still valid: "1/2/3" → 1.
     */
    public function test_tenant_for_user_works_without_leading_slash(): void {
        $user = $this->make_user_minimal(['open_path' => '177/2/3']);
        $this->assertSame(177, subscription_manager::tenant_for_user($user->id));
    }

    /**
     * Deleted user → return 0. Important because soft-deleted rows
     * still exist in mdl_user with their open_path intact; we MUST NOT
     * push to a deleted account.
     */
    public function test_tenant_for_user_zero_for_deleted_user(): void {
        global $DB;
        $user = $this->make_user_minimal(['open_path' => '/1/2/3/']);
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        $this->assertSame(0, subscription_manager::tenant_for_user($user->id),
            'Deleted user MUST resolve to tenant=0 (defensive default → refuse push)');
    }

    /**
     * Suspended user → return 0. Same reasoning as deleted.
     */
    public function test_tenant_for_user_zero_for_suspended_user(): void {
        global $DB;
        $user = $this->make_user_minimal(['open_path' => '/1/2/3/']);
        $DB->set_field('user', 'suspended', 1, ['id' => $user->id]);

        $this->assertSame(0, subscription_manager::tenant_for_user($user->id));
    }

    /**
     * Blank open_path → return 0. Users created before BizLMS bootstrap
     * may have empty open_path; refuse push to them rather than guessing.
     */
    public function test_tenant_for_user_zero_for_blank_open_path(): void {
        $user = $this->make_user_minimal(['open_path' => '']);
        $this->assertSame(0, subscription_manager::tenant_for_user($user->id));
    }

    /**
     * Malformed open_path (first segment isn't digits) → return 0.
     * Defensive: a stale dev fixture might leave garbage in open_path;
     * don't blow up, just refuse the push.
     */
    public function test_tenant_for_user_zero_for_malformed_path(): void {
        $user = $this->make_user_minimal(['open_path' => '/abc/def/']);
        $this->assertSame(0, subscription_manager::tenant_for_user($user->id));
    }

    /**
     * Unknown user id (no row at all) → return 0. The push_sender
     * cron does NOT pre-validate user existence; this is its safety net.
     */
    public function test_tenant_for_user_zero_for_unknown_user_id(): void {
        $this->assertSame(0, subscription_manager::tenant_for_user(999999));
    }

    // ════════════════════════════════════════════════════════════════
    //  Scenario test — two users in two tenants
    // ════════════════════════════════════════════════════════════════

    /**
     * Realistic scenario: User A lives in tenant 1 (Airpay), user B
     * lives in tenant 77 (Public). Both have push subscriptions in
     * THEIR respective tenant rows. A query scoped to tenant 77 MUST
     * NOT surface user A's row even when both share customer=1.
     */
    public function test_cross_tenant_isolation_realistic_scenario(): void {
        $airpay_user = $this->make_user_minimal(['open_path' => '/1/2/3/']);
        $public_user = $this->make_user_minimal(['open_path' => '/77/2/3/']);

        $this->insert_sub($airpay_user->id, 1, 1,  'airpay-sub');
        $this->insert_sub($public_user->id, 1, 77, 'public-sub');

        // Querying public_user with airpay's tenant (1) → no rows.
        $cross = subscription_manager::for_user($public_user->id, null, 1);
        $this->assertCount(0, $cross,
            'Querying a user with a tenant they do NOT belong to leaks zero rows');

        // Querying airpay_user with airpay's tenant (1) → 1 row.
        $own = subscription_manager::for_user($airpay_user->id, null, 1);
        $this->assertCount(1, $own);
        $this->assertSame('airpay-sub', reset($own)->endpoint);

        // tenant_for_user correctly identifies each.
        $this->assertSame(1, subscription_manager::tenant_for_user($airpay_user->id));
        $this->assertSame(77, subscription_manager::tenant_for_user($public_user->id));
    }

    // ════════════════════════════════════════════════════════════════
    //  Helpers
    // ════════════════════════════════════════════════════════════════

    /**
     * Create a minimal user row directly via $DB->insert_record, bypassing
     * `getDataGenerator()->create_user()`. The data-generator fires the
     * full user_created event chain, and the deployed `learnerscript`
     * block has a parse_url(null) deprecation in its observer that
     * PHPUnit treats as a failure (failOnDeprecation="true" in
     * phpunit.xml). subscription_manager only reads open_path / id /
     * deleted / suspended, so a synthetic row is functionally equivalent.
     *
     * @param array<string,mixed> $extra Properties to override on the row.
     * @return \stdClass The created user row (with id populated).
     */
    private function make_user_minimal(array $extra = []): \stdClass {
        global $DB;
        static $counter = 0;
        $counter++;
        $base = [
            'auth'          => 'manual',
            'confirmed'     => 1,
            'mnethostid'    => 1,
            'deleted'       => 0,
            'suspended'     => 0,
            'username'      => 'phpunit_user_' . $counter . '_' . random_int(10000, 99999),
            'password'      => 'unused',
            'idnumber'      => '',
            'firstname'     => 'PHPUnit',
            'lastname'      => 'User' . $counter,
            'email'         => 'phpunit_' . $counter . '@example.test',
            'open_path'     => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];
        $row = (object) array_merge($base, $extra);
        $row->id = $DB->insert_record('user', $row);
        return $row;
    }

    /**
     * Insert a synthetic push_subs row. We bypass subscription_manager::save()
     * because save() validates endpoint host against the allowlist (audit fix
     * #1/#2) which would reject our synthetic endpoints. Tests of the read
     * path don't need that validation.
     *
     * @return int The id of the inserted row.
     */
    private function insert_sub(int $userid, int $customerid, int $tenantid,
                                  string $endpoint): int {
        global $DB;
        $now = time();
        $row = (object) [
            'userid'        => $userid,
            'customerid'    => $customerid,
            'tenantid'      => $tenantid,
            'endpoint'      => $endpoint,
            'endpoint_hash' => sha1($endpoint),
            'p256dh'        => str_repeat('A', 87),
            'auth_secret'   => str_repeat('B', 22),
            'user_agent'    => 'PHPUnit/test',
            'fail_count'    => 0,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        return $DB->insert_record('local_sentientia_push_subs', $row);
    }
}
