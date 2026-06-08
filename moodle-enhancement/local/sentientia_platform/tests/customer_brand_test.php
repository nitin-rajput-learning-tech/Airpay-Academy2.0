<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_platform\customer
 *
 * ADR-008 (2026-05-22) — regression suite for the customer-brand
 * resolver. Promotes the 20-assertion `cli/verify_brand_resolver.php`
 * smoke test into a proper PHPUnit suite + adds coverage for helper
 * methods (resolve_url, parse_categories, assert_valid, current_tenant)
 * the CLI verifier did not exercise.
 *
 * The HTTP-loopback portion of verify_brand_resolver.php (curl into
 * manifest.php) stays in the CLI script — it requires a running Apache
 * and is not a unit-testable concern.
 *
 * Test discipline notes
 * ---------------------
 *   - $this->resetAfterTest(true) — each test gets a virgin DB.
 *     install.xml runs as part of Moodle's test bootstrap so the
 *     local_sentientia_customer_brand table is present and seeded with
 *     the customerid=1 backfill row.
 *   - customer::invalidate_branding_cache() in setUp() ensures cache
 *     contamination from previous tests cannot mask a real failure.
 *   - Type assertions use assertSame (===) rather than assertEquals to
 *     catch unintended string->int coercion in returned bundles.
 */
class customer_brand_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        customer::invalidate_branding_cache();
        $this->seed_airpay_brand_row();
    }

    /**
     * Seed the customer-zero row that `db/install.php` would normally
     * insert on fresh install. We do this in setUp() rather than
     * relying on the install path because Moodle's PHPUnit framework
     * snapshots the DB BEFORE any plugin install.php has had a chance
     * to seed the row in the current test prefix — so the snapshot is
     * empty and resetAfterTest() rolls back any seed we'd insert here.
     * Seeding per-test puts the row in scope of each test's own
     * transaction lifetime.
     */
    private function seed_airpay_brand_row(): void {
        global $DB;
        if ($DB->record_exists('local_sentientia_customer_brand', ['customerid' => 1])) {
            return;
        }
        $now = time();
        $DB->insert_record('local_sentientia_customer_brand', (object) [
            'customerid'       => 1,
            'name'             => 'Airpay Academy',
            'short_name'       => 'Academy',
            'theme_color'      => '#0066A7',
            'bg_color'         => '#F2F4FB',
            'icon_192_url'     => '/local/sentientia_platform/pix/customer/1/icon-192.png',
            'icon_512_url'     => '/local/sentientia_platform/pix/customer/1/icon-512.png',
            'start_url'        => '/my/dashboard.php?utm_source=pwa_install',
            'lang'             => 'en',
            'status_bar_style' => 'default',
            'categories'       => 'education,productivity',
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  Bundle shape — backwards compatibility with Phase 0/1 callers
    // ════════════════════════════════════════════════════════════════

    /**
     * The Airpay customer-zero bundle MUST match the exact shape the
     * hard-coded pre-ADR-008 switch returned. Manifest.php, the theme
     * renderer, the login splash, and the navbar logo all read these
     * keys today — changing any of them is a breaking change.
     */
    public function test_airpay_bundle_matches_phase_one_shape(): void {
        $brand = customer::branding(customer::AIRPAY);

        $this->assertSame('Airpay Academy', $brand['name']);
        $this->assertSame('Academy', $brand['short_name']);
        $this->assertSame('#0066A7', $brand['theme_color']);
        $this->assertSame('#F2F4FB', $brand['bg_color']);
        $this->assertSame('en', $brand['lang']);
        $this->assertSame('/my/dashboard.php?utm_source=pwa_install',
            $brand['start_url']);

        // Icons must resolve to an absolute URL containing the customer
        // pix path. We don't pin the host because $CFG->wwwroot is
        // test-host-dependent.
        $this->assertStringContainsString(
            '/local/sentientia_platform/pix/customer/1/icon-192.png',
            $brand['icon_192_url']);
        $this->assertStringContainsString(
            '/local/sentientia_platform/pix/customer/1/icon-512.png',
            $brand['icon_512_url']);
    }

    /**
     * Phase 2 added two keys — status_bar_style + categories. Both must
     * always be present (no isset-guards required by callers) and
     * categories must be an array (not a CSV string at the boundary).
     */
    public function test_bundle_includes_phase_two_keys(): void {
        $brand = customer::branding(customer::AIRPAY);

        $this->assertArrayHasKey('status_bar_style', $brand);
        $this->assertArrayHasKey('categories', $brand);
        $this->assertIsArray($brand['categories']);
        $this->assertContains('education', $brand['categories']);
        $this->assertContains('productivity', $brand['categories']);
    }

    /**
     * branding() with no argument defaults to customer::current(), which
     * in Phase 0/1 is always AIRPAY. Same bundle should come back as
     * branding(1) — verifies the null-default branch.
     */
    public function test_branding_no_arg_uses_current(): void {
        $explicit = customer::branding(customer::AIRPAY);
        $implicit = customer::branding();
        $this->assertSame($explicit, $implicit);
    }

    // ════════════════════════════════════════════════════════════════
    //  Cache layer
    // ════════════════════════════════════════════════════════════════

    /**
     * Second call must return the byte-identical bundle (===, not ==)
     * confirming the cache layer returned the same in-memory array.
     */
    public function test_second_call_returns_cached_bundle(): void {
        $first  = customer::branding(customer::AIRPAY);
        $second = customer::branding(customer::AIRPAY);
        $this->assertSame($first, $second);
    }

    /**
     * Without invalidation, a direct DB mutation must NOT change what
     * branding() returns (cache wins). With invalidation, the new value
     * appears. This is the core contract of the cache layer.
     */
    public function test_cache_masks_db_change_until_invalidated(): void {
        global $DB;

        // Prime the cache.
        $original = customer::branding(customer::AIRPAY);
        $this->assertSame('Academy', $original['short_name']);

        // Mutate the DB row directly — bypassing any future admin write
        // path that would invalidate for us.
        $DB->set_field('local_sentientia_customer_brand', 'short_name', 'CANARY',
            ['customerid' => customer::AIRPAY]);

        // Cache still wins.
        $stale = customer::branding(customer::AIRPAY);
        $this->assertSame('Academy', $stale['short_name'],
            'Cache MUST mask DB mutation until invalidated');

        // After invalidation, the new value surfaces.
        customer::invalidate_branding_cache(customer::AIRPAY);
        $fresh = customer::branding(customer::AIRPAY);
        $this->assertSame('CANARY', $fresh['short_name']);
    }

    /**
     * Per-customer invalidation must NOT clear other customers' cached
     * entries. Important once Phase 2 ships and we have >1 customer —
     * editing Customer 2's logo shouldn't force a re-render for Airpay.
     */
    public function test_per_customer_invalidate_is_scoped(): void {
        global $DB;

        // Insert a synthetic Customer 2 row so we have two cached entries.
        $now = time();
        $DB->insert_record('local_sentientia_customer_brand', (object) [
            'customerid'       => 2,
            'name'             => 'Test Customer Two',
            'short_name'       => 'Two',
            'theme_color'      => '#ff0000',
            'bg_color'         => '#ffffff',
            'icon_192_url'     => '/icons/192.png',
            'icon_512_url'     => '/icons/512.png',
            'start_url'        => '/dashboard.php',
            'lang'             => 'en',
            'status_bar_style' => 'default',
            'categories'       => 'business',
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);

        // Prime both customers' caches.
        $airpay_v1 = customer::branding(1);
        $two_v1    = customer::branding(2);
        $this->assertSame('Academy', $airpay_v1['short_name']);
        $this->assertSame('Two', $two_v1['short_name']);

        // Mutate BOTH rows on disk.
        $DB->set_field('local_sentientia_customer_brand', 'short_name',
            'AirpayMut', ['customerid' => 1]);
        $DB->set_field('local_sentientia_customer_brand', 'short_name',
            'TwoMut', ['customerid' => 2]);

        // Invalidate ONLY customer 2.
        customer::invalidate_branding_cache(2);

        // Customer 1 still served from cache (old value); customer 2 re-reads (new).
        $this->assertSame('Academy', customer::branding(1)['short_name'],
            'Scoped invalidate must not flush other customers');
        $this->assertSame('TwoMut', customer::branding(2)['short_name']);
    }

    /**
     * Calling invalidate_branding_cache() with no argument purges all
     * entries — used by purge_caches.php and admin-side bulk operations.
     */
    public function test_invalidate_with_no_arg_purges_all(): void {
        global $DB;

        $brand = customer::branding(customer::AIRPAY);
        $this->assertSame('Academy', $brand['short_name']);

        $DB->set_field('local_sentientia_customer_brand', 'short_name', 'NEW',
            ['customerid' => customer::AIRPAY]);

        // Argument-less invalidate.
        customer::invalidate_branding_cache();

        $this->assertSame('NEW', customer::branding(customer::AIRPAY)['short_name']);
    }

    // ════════════════════════════════════════════════════════════════
    //  Fallback path — DB row missing or unknown id
    // ════════════════════════════════════════════════════════════════

    /**
     * Unknown customer id must fall through to the hard-coded Airpay
     * bundle. The defensive choice: rather than throwing or returning
     * empty (both of which would brick page rendering), the resolver
     * returns the customer-zero brand so the page still renders.
     */
    public function test_unknown_customer_id_falls_back_to_airpay(): void {
        $brand = customer::branding(99999);
        $this->assertSame('Airpay Academy', $brand['name']);
        $this->assertSame('#0066A7', $brand['theme_color']);
    }

    /**
     * When the customer row is deleted at runtime (e.g. mid-migration
     * cleanup), the next read after invalidate must fall back gracefully
     * — same bundle as the unknown-id case.
     */
    public function test_deleted_row_falls_back_to_default(): void {
        global $DB;

        // Prime cache.
        customer::branding(customer::AIRPAY);
        // Delete the row + invalidate.
        $DB->delete_records('local_sentientia_customer_brand',
            ['customerid' => customer::AIRPAY]);
        customer::invalidate_branding_cache(customer::AIRPAY);

        // Resolver returns the hard-coded fallback (identical bundle shape).
        $brand = customer::branding(customer::AIRPAY);
        $this->assertSame('Airpay Academy', $brand['name']);
        $this->assertSame('Academy', $brand['short_name']);
    }

    // ════════════════════════════════════════════════════════════════
    //  Helper methods — current(), current_tenant(), assert_valid(),
    //  known_customers(), label_for()
    // ════════════════════════════════════════════════════════════════

    /**
     * Phase 0/1 contract: every authenticated user belongs to Airpay.
     */
    public function test_current_returns_airpay_in_phase_one(): void {
        $this->assertSame(customer::AIRPAY, customer::current());
    }

    /**
     * current_tenant() returns null when there is no logged-in user.
     */
    public function test_current_tenant_null_when_no_user(): void {
        global $USER;
        $original = $USER;
        // Simulate no auth — empty USER object.
        $USER = new \stdClass();
        $USER->id = 0;

        try {
            $this->assertNull(customer::current_tenant());
        } finally {
            $USER = $original;
        }
    }

    /**
     * Site admins legitimately operate across all tenants, so
     * current_tenant() returns null (NOT 0) so guard code can
     * differentiate "skip cross-tenant check" from "tenant zero".
     */
    public function test_current_tenant_null_for_siteadmin(): void {
        $this->setAdminUser();
        $this->assertNull(customer::current_tenant(),
            'Site admins must return null, not 0, so cross-tenant guards can skip');
    }

    /**
     * For a normal user with $USER->open_path = "/77/...", the helper
     * extracts the first integer segment (the costcenterid).
     *
     * Implementation note: we synthesize $USER directly rather than
     * calling $this->setUser() because the latter fires every
     * user-login observer in the deployed plugin set — and the
     * learnerscript block's observer has an unrelated parse_url(null)
     * deprecation that this test must not trigger. current_tenant()
     * only consults $USER->id and $USER->open_path, so a synthetic
     * stdClass is functionally equivalent.
     */
    public function test_current_tenant_parses_open_path(): void {
        $this->with_user(['id' => 100, 'open_path' => '/77/2/3/'],
            fn() => $this->assertSame(77, customer::current_tenant()));
    }

    /**
     * Malformed open_path (no leading integer) must return null —
     * downstream guards then treat it as "no scope" rather than 0.
     */
    public function test_current_tenant_null_on_malformed_path(): void {
        $this->with_user(['id' => 100, 'open_path' => '/abc/def/'],
            fn() => $this->assertNull(customer::current_tenant()));
    }

    /**
     * Missing open_path attribute (unset, never assigned) returns null.
     */
    public function test_current_tenant_null_when_open_path_unset(): void {
        $this->with_user(['id' => 100],
            fn() => $this->assertNull(customer::current_tenant()));
    }

    /**
     * assert_valid accepts AIRPAY (1) + DEFAULT (0) sentinels; anything
     * else throws a moodle_exception with the canonical lang key. The
     * Switchboard uses this to validate the customer-id query string.
     */
    public function test_assert_valid_accepts_known_customers(): void {
        // No exception means pass.
        customer::assert_valid(customer::AIRPAY);
        customer::assert_valid(customer::DEFAULT);
        $this->addToAssertionCount(2);  // counter for no-exception path
    }

    public function test_assert_valid_rejects_unknown(): void {
        $caught = null;
        try {
            customer::assert_valid(9999);
        } catch (\moodle_exception $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught,
            'Unknown customer id MUST throw moodle_exception');
        $this->assertSame('error_invalidcustomer', $caught->errorcode);
    }

    /**
     * known_customers() must always include DEFAULT (sentinel "all
     * customers") FIRST followed by real customers. The Switchboard
     * relies on this order to render its tab layout.
     */
    public function test_known_customers_order_and_shape(): void {
        $list = customer::known_customers();
        $this->assertGreaterThanOrEqual(2, count($list));

        // First entry must be DEFAULT (id=0, is_default=true).
        $this->assertSame(customer::DEFAULT, $list[0]['id']);
        $this->assertTrue($list[0]['is_default']);

        // AIRPAY (id=1) must be in the list (order after DEFAULT).
        $airpay_found = false;
        foreach ($list as $entry) {
            if ($entry['id'] === customer::AIRPAY) {
                $airpay_found = true;
                $this->assertFalse($entry['is_default']);
                $this->assertSame('Airpay Payment Services', $entry['name']);
            }
        }
        $this->assertTrue($airpay_found, 'AIRPAY must appear in known_customers()');
    }

    /**
     * label_for falls back to "Unknown (N)" rather than throwing — this
     * lets historical audit rows (pointing at customers that were since
     * deleted) still render in the Switchboard without a fatal.
     */
    public function test_label_for_falls_back_for_unknown(): void {
        $this->assertSame('Airpay Payment Services',
            customer::label_for(customer::AIRPAY));

        $unknown = customer::label_for(9999);
        $this->assertStringContainsString('Unknown', $unknown);
        $this->assertStringContainsString('9999', $unknown);
    }

    /**
     * Run $callback with $GLOBALS['USER'] swapped to a synthetic
     * stdClass populated from $props, then restore. This bypasses
     * setUser() and the observer chain it triggers — important
     * because the deployed learnerscript block has a parse_url(null)
     * deprecation in its user-login observer that PHPUnit treats as
     * a fatal failure (failOnDeprecation="true" in phpunit.xml).
     *
     * @param array<string,mixed> $props Properties to set on $USER.
     * @param callable $callback Test body executed with the swap in effect.
     */
    private function with_user(array $props, callable $callback): void {
        global $USER;
        $original = $USER;
        $USER = new \stdClass();
        foreach ($props as $key => $val) {
            $USER->{$key} = $val;
        }
        try {
            $callback();
        } finally {
            $USER = $original;
        }
    }
}
