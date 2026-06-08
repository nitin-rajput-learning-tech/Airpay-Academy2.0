<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Security regression tests for org_manager.
 *
 * Locks in fixes from the May 5 2026 security audit:
 * - C2 LIKE wildcard escape in count_users / count_descendants.
 * - Tenant deletion refusal.
 * - Descendant + user blockers.
 * - H2 transactional delete.
 *
 * Tests use synthetic IDs (8000+) to avoid collision with any real
 * pre-seeded org rows.
 *
 * @package    local_sentientia_org
 * @category   test
 */
final class org_manager_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * Insert a synthetic org row at the given path. Pass `$forceid` to
     * override the path-derived ID (needed for paths like '/8001_x'
     * where `(int) '8001_x'` collides with `/8001`).
     */
    private function seed_org(string $path, int $parentid = 0, int $depth = 1, ?int $forceid = null): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_org')) {
            $this->markTestSkipped('local_sentientia_org table not present.');
        }
        $id = $forceid ?? (int) ltrim(strrchr($path, '/'), '/');
        $rec = (object)[
            'id' => $id, 'fullname' => "Org $id", 'shortname' => "org_$id",
            'parentid' => $parentid, 'path' => $path, 'depth' => $depth,
            'visible' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $DB->insert_record_raw('local_sentientia_org', $rec, false, false, true);
        return $id;
    }

    /**
     * Assert that the callable throws a moodle_exception whose errorcode
     * equals the expected value. Use this instead of
     * expectExceptionMessage() because Moodle resolves the errorcode
     * against the lang file — matching by errorcode is stable.
     */
    private function assertMoodleException(string $errorcode, callable $fn): void {
        try {
            $fn();
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode,
                "Expected moodle_exception errorcode '$errorcode' but got '{$e->errorcode}'");
            return;
        }
        $this->fail("Expected moodle_exception with errorcode '$errorcode' but none was thrown");
    }

    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    /**
     * C2: count_users for /8001 must NOT include users at /80010 or /8002.
     */
    public function test_c2_count_users_does_not_leak(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8001', 0, 1);
        $sub    = $this->seed_org('/8001/9001', $tenant, 2);

        $this->user_at_path('/8001');        // tenant root
        $this->user_at_path('/8001/9001');   // sub-org
        $this->user_at_path('/80010');       // decimal overlap — must NOT count
        $this->user_at_path('/8001_x');      // literal underscore — must NOT count
        $this->user_at_path('/8002');        // sibling tenant — must NOT count

        $count = org_manager::count_users($tenant);
        $this->assertSame(2, $count,
            'C2: count_users(/8001) should be 2 (root + sub). Got ' . $count
          . ' — values > 2 mean LIKE escape failed and a sibling/decoy leaked');
    }

    /**
     * C2: count_descendants for /8001 must NOT include /80010 or /8002.
     */
    public function test_c2_count_descendants_does_not_leak(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8001', 0, 1);
        $this->seed_org('/8001/9001', $tenant, 2);
        $this->seed_org('/8001/9002', $tenant, 2);
        // Decoys with overlapping decimals or literal underscore.
        // forceid avoids collision with /8001 (since (int)'8001_x' = 8001).
        $this->seed_org('/80010',           0,     1, 80010);
        $this->seed_org('/80010/99001', 80010,     2, 99001);
        $this->seed_org('/8001_x',          0,     1, 88001);

        $count = org_manager::count_descendants($tenant);
        $this->assertSame(2, $count,
            'C2: count_descendants(/8001) should be 2 (/8001/9001, /8001/9002), '
          . 'never include /80010/* or /8001_x. Got ' . $count);
    }

    /**
     * Delete refuses on tenant (depth = 1) — assert errorcode, not message.
     */
    public function test_delete_refuses_tenant(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8101', 0, 1);
        $this->assertMoodleException('cannotdeletetenant',
            fn() => org_manager::delete($tenant));
    }

    /**
     * Delete refuses on org with descendants — assert errorcode.
     */
    public function test_delete_refuses_org_with_children(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8201', 0, 1);
        $parent = $this->seed_org('/8201/9201', $tenant, 2);
        $this->seed_org('/8201/9201/9301', $parent, 3);

        $this->assertMoodleException('orghaschildren',
            fn() => org_manager::delete($parent));
    }

    /**
     * Delete refuses on org with assigned users — assert errorcode.
     */
    public function test_delete_refuses_org_with_users(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8301', 0, 1);
        $sub    = $this->seed_org('/8301/9301', $tenant, 2);
        $this->user_at_path('/8301/9301');

        $this->assertMoodleException('orghasusers',
            fn() => org_manager::delete($sub));
    }

    /**
     * Delete succeeds on a clean leaf.
     */
    public function test_delete_clean_leaf_succeeds(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8401', 0, 1);
        $leaf   = $this->seed_org('/8401/9401', $tenant, 2);

        $ok = org_manager::delete($leaf);
        $this->assertTrue($ok);
        $this->assertFalse($DB->record_exists('local_sentientia_org', ['id' => $leaf]));
    }

    /**
     * create() computes path from parent: parent_path + / + new_id.
     */
    public function test_create_computes_path(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8501', 0, 1);

        $id = org_manager::create((object)[
            'fullname' => 'New child',
            'shortname' => 'newchild',
            'parentid'  => $tenant,
            'visible'   => 1,
        ]);
        $row = $DB->get_record('local_sentientia_org', ['id' => $id]);
        $this->assertSame('/8501/' . $id, $row->path);
        $this->assertSame(2, (int) $row->depth);
    }
}
