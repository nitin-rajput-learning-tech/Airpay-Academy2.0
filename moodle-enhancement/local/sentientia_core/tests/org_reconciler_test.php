<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the ADR-020 Wave-3.2b org dual-write reconciler.
 *
 * Uses a synthetic in-memory {@see org_source}, so the suite runs on a vanilla
 * Moodle PHPUnit DB without the BizLMS open_path / open_supervisorid /
 * local_costcenter columns — only the local_sentientia_org_* tables (shipped in
 * install.xml) are needed.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\org_reconciler
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_reconciler_test extends \advanced_testcase {

    /**
     * Build a synthetic org_source from arrays.
     *
     * @param array $users list of [userid, openpath, supervisorid]
     * @param array $names cost-center id => display name
     * @return org_source
     */
    private function source(array $users, array $names = []): org_source {
        return new class($users, $names) implements org_source {
            /**
             * @param array $users
             * @param array $names
             */
            public function __construct(private array $users, private array $names) {
            }
            public function users(): iterable {
                foreach ($this->users as $u) {
                    yield (object) ['userid' => $u[0], 'openpath' => $u[1], 'supervisorid' => $u[2]];
                }
            }
            public function unit_name(int $costcenterid): ?string {
                return $this->names[$costcenterid] ?? null;
            }
        };
    }

    public function test_builds_unit_tree_from_open_path_segments(): void {
        global $DB;
        $this->resetAfterTest();
        $src = $this->source(
            [[101, '/1/2/3', 0]],
            [1 => 'Airpay', 2 => 'Engineering', 3 => 'Platform']
        );
        $counts = (new org_reconciler($src))->reconcile();

        $this->assertSame(3, $counts->unitscreated);
        $this->assertSame(1, $counts->memberscreated);
        $this->assertSame(1, $counts->usersprocessed);
        $this->assertSame(0, $counts->usersskipped);

        // Units keyed by idnumber = cost-center id; parent chain; tenant root.
        $u1 = $DB->get_record('local_sentientia_org_unit', ['idnumber' => '1'], '*', MUST_EXIST);
        $u2 = $DB->get_record('local_sentientia_org_unit', ['idnumber' => '2'], '*', MUST_EXIST);
        $u3 = $DB->get_record('local_sentientia_org_unit', ['idnumber' => '3'], '*', MUST_EXIST);
        $this->assertSame(0, (int) $u1->parentid);
        $this->assertSame((int) $u1->id, (int) $u2->parentid);
        $this->assertSame((int) $u2->id, (int) $u3->parentid);
        $this->assertSame(1, (int) $u1->tenantrootid);
        $this->assertSame(1, (int) $u3->tenantrootid, 'tenantrootid is segment[0] for every unit in the path.');
        $this->assertSame('Engineering', $u2->name);
        $this->assertSame('/1/2/3', $u3->path);

        // Member lands in the leaf unit, and the org read API resolves it.
        $this->assertTrue($DB->record_exists('local_sentientia_org_member',
            ['userid' => 101, 'unitid' => $u3->id]));
        $this->assertEqualsCanonicalizing([(int) $u3->id], org::units_of(101));
    }

    public function test_manager_edge_mirrors_supervisor(): void {
        $this->resetAfterTest();
        $src = $this->source([
            [201, '/1/2', 0],     // Manager — no supervisor.
            [202, '/1/2', 201],   // Reports to 201.
            [203, '/1/2', 201],   // Reports to 201.
        ]);
        (new org_reconciler($src))->reconcile();

        $this->assertSame(201, org::manager_via_model(202));
        $this->assertSame(201, org::manager_via_model(203));
        $this->assertSame(org::NO_MANAGER, org::manager_via_model(201));
        $this->assertTrue(org::is_manager(201));
        $this->assertEqualsCanonicalizing([202, 203], org::direct_reports(201));
    }

    public function test_reconcile_is_idempotent(): void {
        $this->resetAfterTest();
        $users = [
            [301, '/1/2/3', 302],
            [302, '/1/2', 0],
            [303, '/77/9', 0],
        ];
        $first = (new org_reconciler($this->source($users)))->reconcile();
        $this->assertGreaterThan(0, $first->unitscreated);
        $this->assertSame(3, $first->memberscreated);

        // Fresh reconciler, identical data — a pure no-op.
        $second = (new org_reconciler($this->source($users)))->reconcile();
        $this->assertSame(0, $second->unitscreated, 'Re-run creates no units.');
        $this->assertSame(0, $second->unitsupdated, 'Re-run updates no units.');
        $this->assertSame(0, $second->memberscreated, 'Re-run creates no members.');
        $this->assertSame(0, $second->membersupdated, 'Re-run updates no members.');
        $this->assertSame(3, $second->usersprocessed);
    }

    public function test_manager_change_updates_the_edge_on_rerun(): void {
        $this->resetAfterTest();
        (new org_reconciler($this->source([[401, '/1/2', 500]])))->reconcile();
        $this->assertSame(500, org::manager_via_model(401));

        // Supervisor changed in the legacy source — reconcile mirrors it without
        // creating a duplicate membership row.
        $counts = (new org_reconciler($this->source([[401, '/1/2', 600]])))->reconcile();
        $this->assertSame(1, $counts->membersupdated);
        $this->assertSame(0, $counts->memberscreated);
        $this->assertSame(600, org::manager_via_model(401));
    }

    public function test_tenant_scope_skips_disallowed_roots(): void {
        global $DB;
        $this->resetAfterTest();
        $src = $this->source([
            [501, '/1/2', 0],     // Allowed.
            [502, '/77/3', 0],    // Allowed.
            [503, '/999/4', 0],   // NOT allowed.
        ]);
        $counts = (new org_reconciler($src))->reconcile([1, 77]);

        $this->assertSame(2, $counts->usersprocessed);
        $this->assertSame(1, $counts->usersskipped);
        $this->assertFalse($DB->record_exists('local_sentientia_org_unit', ['idnumber' => '999']),
            'No unit is created for a tenant root outside the allow-list.');
        $this->assertFalse($DB->record_exists('local_sentientia_org_member', ['userid' => 503]));
    }

    public function test_skips_users_with_unusable_open_path(): void {
        $this->resetAfterTest();
        $src = $this->source([
            [601, '', 0],          // Empty.
            [602, '/abc/2', 0],    // Non-numeric root.
            [603, '/1/2', 0],      // Valid.
        ]);
        $counts = (new org_reconciler($src))->reconcile();
        $this->assertSame(1, $counts->usersprocessed);
        $this->assertSame(2, $counts->usersskipped);
    }

    public function test_falls_back_to_synthetic_unit_name_when_unknown(): void {
        global $DB;
        $this->resetAfterTest();
        // No names provided -> "Unit <id>".
        (new org_reconciler($this->source([[701, '/5', 0]])))->reconcile();
        $u = $DB->get_record('local_sentientia_org_unit', ['idnumber' => '5'], '*', MUST_EXIST);
        $this->assertSame('Unit 5', $u->name);
    }

    public function test_dualwrite_flag_defaults_off(): void {
        $this->resetAfterTest();
        unset_config('org_dualwrite_enabled', 'local_sentientia_core');
        $this->assertFalse(org::use_dualwrite(),
            'Dual-write must default OFF so deploying the wave changes nothing.');
        set_config('org_dualwrite_enabled', 1, 'local_sentientia_core');
        $this->assertTrue(org::use_dualwrite());
    }
}
