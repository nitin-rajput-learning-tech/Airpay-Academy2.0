<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_integrations;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests that don't need real KeKa API calls.
 *
 * The OAuth + paged-sync path needs network; this class covers the
 * pure-logic / DB pieces of the 2026-08-07 JML hardening:
 *   - feature-flag + hrms_enable gating (webhook ships dark)
 *   - upsert_employee: joiner creation through user_create_user() with a
 *     REAL \core\event\user_created, tenant placement (dept-code map,
 *     validated default), mover identity matching by open_employeeid
 *     (email change must not fork a duplicate account)
 *   - leaver path: employeeId-only payload, suspend via user_update_user
 *     (user_updated fires, timemodified stamped), sessions destroyed
 *   - manager two-pass: reportsTo → open_supervisorid
 *
 * Uses the bizlms_fixture trait to provision the production-only open_*
 * columns on the phpunit DB.
 *
 * @package    local_sentientia_integrations
 * @category   test
 */
final class keka_client_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * Provision BizLMS columns + reset keka_client's schema-probe cache.
     */
    private function prepare_schema(): void {
        $this->ensure_bizlms_schema();
        keka_client::reset_static_caches();
    }

    /**
     * Insert an org node with a self-consistent path. Returns the record.
     */
    private function create_org(string $fullname, string $shortname, int $parentid = 0,
            string $parentpath = ''): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_sentientia_org', (object) [
            'fullname'     => $fullname,
            'shortname'    => $shortname,
            'parentid'     => $parentid,
            'depth'        => $parentid ? 2 : 1,
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $path = $parentpath . '/' . $id;
        $DB->set_field('local_sentientia_org', 'path', $path, ['id' => $id]);
        return $DB->get_record('local_sentientia_org', ['id' => $id]);
    }

    // ─── Gating (work item 1) ────────────────────────────────────────────

    public function test_webhook_gate_defaults_off_and_needs_flag_plus_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // Flag overrides are process-cached; drop them so earlier tests
        // in the same run can't leak state into this one.
        \local_sentientia_platform\feature_flags::invalidate_caches();

        // Registered default is OFF — endpoint ships dark.
        $this->assertFalse(keka_client::webhook_enabled());

        // hrms_enable alone is not enough.
        set_config('hrms_enable', 1, 'local_sentientia_integrations');
        $this->assertFalse(keka_client::webhook_enabled());

        // Flag alone is not enough either.
        set_config('hrms_enable', 0, 'local_sentientia_integrations');
        \local_sentientia_platform\feature_flags::set(keka_client::FLAG_WEBHOOK, 0, true);
        $this->assertFalse(keka_client::webhook_enabled());

        // Both on → open.
        set_config('hrms_enable', 1, 'local_sentientia_integrations');
        $this->assertTrue(keka_client::webhook_enabled());

        // Reconcile flag is independent and still off.
        $this->assertFalse(keka_client::reconcile_enabled());
        \local_sentientia_platform\feature_flags::set(keka_client::FLAG_RECONCILE, 0, true);
        $this->assertTrue(keka_client::reconcile_enabled());
    }

    // ─── Joiner (work items 4 + 5) ───────────────────────────────────────

    public function test_joiner_created_via_user_create_user_with_real_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();
        $root = $this->create_org('Airpay', 'airpay');

        $sink = $this->redirectEvents();
        $outcome = (new keka_client())->upsert_employee([
            'employeeNumber' => 'E100',
            'firstName'      => 'Asha',
            'lastName'       => 'Verma',
            'email'          => 'asha.verma@airpay.co.in',
            'jobTitle'       => 'Analyst',
            'department'     => 'No Such Department',
            'status'         => 'active',
        ]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('created', $outcome['action']);
        $user = $DB->get_record('user', ['id' => $outcome['userid']], '*', MUST_EXIST);
        $this->assertSame('asha.verma@airpay.co.in', $user->email);
        $this->assertSame('E100', $user->open_employeeid);
        $this->assertEquals(0, $user->deleted);

        // A REAL user_created event (from user_create_user), not a forged one.
        $created = array_filter($events, fn($e) => $e instanceof \core\event\user_created);
        $this->assertCount(1, $created);

        // Unmatched department → validated default placement, never tenantless.
        $this->assertSame($root->path, $user->open_path);
    }

    public function test_department_code_maps_to_org_shortname(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();
        $root = $this->create_org('Airpay', 'airpay');
        $fin = $this->create_org('Finance', 'FIN', (int) $root->id, $root->path);

        $outcome = (new keka_client())->upsert_employee([
            'employeeNumber' => 'E101',
            'firstName'      => 'Ravi',
            'lastName'       => 'Nair',
            'email'          => 'ravi.nair@airpay.co.in',
            // Case-insensitive shortname match — codes beat display names.
            'departmentCode' => 'fin',
            'department'     => 'Some Renamed Display Name',
            'status'         => 'active',
        ]);

        $this->assertSame('created', $outcome['action']);
        $this->assertSame($fin->path,
            $DB->get_field('user', 'open_path', ['id' => $outcome['userid']]));
    }

    public function test_terminated_employee_with_no_account_is_not_created(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();

        $before = $DB->count_records('user');
        $outcome = (new keka_client())->upsert_employee([
            'employeeNumber' => 'E999',
            'email'          => 'gone@airpay.co.in',
            'status'         => 'terminated',
        ]);

        $this->assertSame('skipped', $outcome['action']);
        $this->assertSame($before, $DB->count_records('user'));
    }

    // ─── Mover identity matching (work item 7) ──────────────────────────

    public function test_email_change_matches_by_employeeid_no_duplicate(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();

        $user = $this->getDataGenerator()->create_user(['email' => 'old.address@airpay.co.in']);
        $DB->set_field('user', 'open_employeeid', 'E200', ['id' => $user->id]);

        $before = $DB->count_records('user');
        $outcome = (new keka_client())->upsert_employee([
            'employeeNumber' => 'E200',
            'firstName'      => $user->firstname,
            'lastName'       => $user->lastname,
            'email'          => 'new.address@airpay.co.in',   // Changed in KeKa.
            'status'         => 'active',
        ]);

        // Same account updated — the email-first matcher used to fork a duplicate here.
        $this->assertSame('updated', $outcome['action']);
        $this->assertSame((int) $user->id, $outcome['userid']);
        $this->assertSame($before, $DB->count_records('user'));
        $this->assertSame('new.address@airpay.co.in',
            $DB->get_field('user', 'email', ['id' => $user->id]));
    }

    public function test_unmatched_department_never_retenants_existing_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();
        $root = $this->create_org('Airpay', 'airpay');
        $fin = $this->create_org('Finance', 'FIN', (int) $root->id, $root->path);

        $user = $this->getDataGenerator()->create_user(['email' => 'placed@airpay.co.in']);
        $DB->set_field('user', 'open_employeeid', 'E300', ['id' => $user->id]);
        $DB->set_field('user', 'open_path', $fin->path, ['id' => $user->id]);

        (new keka_client())->upsert_employee([
            'employeeNumber' => 'E300',
            'firstName'      => 'Changed',
            'lastName'       => $user->lastname,
            'email'          => 'placed@airpay.co.in',
            'department'     => 'Department KeKa Renamed Yesterday',
            'status'         => 'active',
        ]);

        // The DEFAULT fallback path applies to creations only — an unmatched
        // department must not silently move an existing user to the root.
        $this->assertSame($fin->path,
            $DB->get_field('user', 'open_path', ['id' => $user->id]));
    }

    // ─── Leaver hardening (work item 2) ─────────────────────────────────

    public function test_terminated_webhook_suspends_by_employeeid_and_kills_sessions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();

        $user = $this->getDataGenerator()->create_user(['email' => 'leaver@airpay.co.in']);
        $DB->set_field('user', 'open_employeeid', 'E400', ['id' => $user->id]);
        $DB->set_field('user', 'timemodified', 0, ['id' => $user->id]);

        // Give the leaver a live session.
        $now = time();
        $DB->insert_record('sessions', (object) [
            'state' => 0, 'sid' => 'kekatestsid123', 'userid' => $user->id,
            'timecreated' => $now, 'timemodified' => $now,
            'firstip' => '10.0.0.1', 'lastip' => '10.0.0.1',
        ]);

        // Payload with NO email — the employeeId fallback must resolve it.
        $sink = $this->redirectEvents();
        $result = keka_client::handle_webhook('employee.terminated', ['employeeId' => 'E400']);
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result['success']);
        $fresh = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $this->assertEquals(1, $fresh->suspended);
        $this->assertGreaterThan(0, (int) $fresh->timemodified,
            'suspend must go through user_update_user which stamps timemodified');

        // Real user_updated event fired (suspend no longer bypasses the events API).
        $updated = array_filter($events, fn($e) => $e instanceof \core\event\user_updated);
        $this->assertNotEmpty($updated);

        // Live sessions destroyed — a leaver cannot keep a browser logged in.
        $this->assertFalse($DB->record_exists('sessions', ['userid' => $user->id]));
    }

    // ─── Manager sync (work item 6) ─────────────────────────────────────

    public function test_reports_to_resolves_to_open_supervisorid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();

        $manager = $this->getDataGenerator()->create_user(['email' => 'boss@airpay.co.in']);
        $DB->set_field('user', 'open_employeeid', 'M1', ['id' => $manager->id]);

        $outcome = (new keka_client())->upsert_employee([
            'employeeNumber' => 'E500',
            'firstName'      => 'New',
            'lastName'       => 'Joiner',
            'email'          => 'joiner@airpay.co.in',
            'reportsTo'      => 'M1',
            'status'         => 'active',
        ]);
        $this->assertSame('M1', $outcome['manager_empid']);

        // PASS 2 (as sync_employees / the webhook dispatcher run it).
        $linked = keka_client::resolve_manager_links([
            ['userid' => $outcome['userid'], 'manager_empid' => 'M1'],
        ]);

        $this->assertSame(1, $linked);
        $this->assertEquals($manager->id,
            $DB->get_field('user', 'open_supervisorid', ['id' => $outcome['userid']]));
    }

    public function test_unresolved_manager_left_null(): void {
        global $DB;
        $this->resetAfterTest();
        $this->prepare_schema();

        $user = $this->getDataGenerator()->create_user();
        $linked = keka_client::resolve_manager_links([
            ['userid' => $user->id, 'manager_empid' => 'NOBODY'],
        ]);

        $this->assertSame(0, $linked);
        $this->assertEmpty($DB->get_field('user', 'open_supervisorid', ['id' => $user->id]));
        $this->resetDebugging(); // Unresolved manager emits a developer debugging() note.
    }

    // ─── Legacy regression locks ────────────────────────────────────────

    public function test_handle_webhook_routes_unknown_event_to_failure(): void {
        $this->resetAfterTest();
        $result = keka_client::handle_webhook('mystery_event', []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        // Unknown events shouldn't cause a fatal — they should just return
        // an unsuccessful structured response so the webhook receiver can
        // log status='failed' rather than 500.
        $this->assertFalse($result['success']);
    }

    public function test_class_uses_sentientia_org_not_legacy_costcenter(): void {
        // Phase-0A migration audit: keka_client.php must NOT contain
        // the legacy {local_costcenter} reference. INTEGRATIONS-AUDIT.md §3.1.
        $source = file_get_contents(
            __DIR__ . '/../classes/keka_client.php');
        $this->assertStringNotContainsString("'local_costcenter'", $source,
            'legacy local_costcenter reference must be removed (Phase-0A)');
        $this->assertStringContainsString('local_sentientia_org', $source,
            'must use the sentientia_org-owned table');
    }

    public function test_webhook_endpoint_rejects_get_param_secret(): void {
        // Item 1 regression lock: the ?secret= GET path leaked secrets into
        // access logs. The endpoint must read the header, never the query
        // string, and must compare with hash_equals().
        $source = file_get_contents(__DIR__ . '/../webhook.php');
        $this->assertStringNotContainsString("\$_GET['secret']", $source);
        $this->assertStringContainsString('hash_equals', $source);
        $this->assertStringContainsString('HTTP_X_WEBHOOK_SECRET', $source);
        $this->assertStringContainsString('webhook_enabled()', $source);
    }
}
