<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\capability_repair;

/**
 * The review-and-repair of role grants on capabilities of plugins that are missing from disk (ADR-032,
 * "Capabilities"), the replacement of the capability copy in the retired migrate_all.php.
 *
 * The tests build their own legacy and target capabilities, so they do not depend on which Sentientia plugins the
 * test site has installed. The legacy plugin (local_blmlegacy) is on no disk; the targets carry the component of an
 * installed plugin, as the real ones do.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\capability_repair
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_capability_repair_test extends \advanced_testcase {

    private const OLD = 'local/blmlegacy:manageclassroom';
    private const NEW = 'local/blmtarget:manage';
    private const OLD_ADMIN = 'local/blmlegacy:admin';
    private const NEVER = 'local/blmtarget:admin';

    /**
     * Two legacy capabilities of a plugin that is not on disk, their two Sentientia equivalents, and a role that
     * holds both legacy capabilities in the system context.
     *
     * @param int $permission What the role holds on the first legacy capability.
     * @return array{0: int, 1: capability_repair} The role id and a repair that maps OLD to NEW and OLD_ADMIN to NEVER.
     */
    private function scenario(int $permission = CAP_ALLOW): array {
        global $DB;
        $this->resetAfterTest();
        foreach ([[self::OLD, 'local_blmlegacy'], [self::OLD_ADMIN, 'local_blmlegacy'],
                  [self::NEW, 'local_sentientia_platform'], [self::NEVER, 'local_sentientia_platform']] as [$name, $component]) {
            $DB->insert_record('capabilities', (object) ['name' => $name, 'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM, 'component' => $component, 'riskbitmask' => 0]);
        }
        \cache::make('core', 'capabilities')->delete('core_capabilities');

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'blmtrainer']);
        $system = \context_system::instance();
        foreach ([self::OLD => $permission, self::OLD_ADMIN => CAP_ALLOW] as $capability => $held) {
            $DB->insert_record('role_capabilities', (object) ['contextid' => $system->id, 'roleid' => $roleid,
                'capability' => $capability, 'permission' => $held, 'timemodified' => time(), 'modifierid' => 0]);
        }
        return [$roleid, new capability_repair([self::OLD => self::NEW, self::OLD_ADMIN => self::NEVER], [self::NEVER])];
    }

    /**
     * Write an allow-list file.
     *
     * @param array $grants
     * @param array $header Overrides of the signed header.
     * @return string Path.
     */
    private function allowlist(array $grants, array $header = []): string {
        $path = make_request_directory() . '/allowlist.json';
        file_put_contents($path, json_encode($header + ['version' => 1, 'approved_by' => 'Nitin Rajput',
            'approved_on' => '2026-09-30', 'basis' => 'test', 'grants' => $grants]));
        return $path;
    }

    /**
     * @param string $old
     * @param string $new
     * @param int $permission
     * @return array
     */
    private function grant(string $old = self::OLD, string $new = self::NEW, int $permission = CAP_ALLOW): array {
        return ['role' => 'blmtrainer', 'context' => 'system', 'legacy' => $old, 'target' => $new, 'permission' => $permission];
    }

    /**
     * @param int $roleid
     * @param array $inventory
     * @return array[] The inventory rows of the test role.
     */
    private function rows_of(int $roleid, array $inventory): array {
        return array_values(array_filter($inventory, fn(array $row): bool => $row['roleid'] === $roleid));
    }

    public function test_the_map_is_the_ten_capabilities_the_retired_script_copied(): void {
        $this->assertCount(10, capability_repair::MAP);
        foreach (capability_repair::MAP as $old => $new) {
            $this->assertMatchesRegularExpression('~^local/(costcenter|courses|classroom|users):~', $old);
            $this->assertStringStartsWith('local/sentientia_', $new);
        }
        $this->assertSame('local/sentientia_classroom:manage', capability_repair::MAP['local/classroom:manageclassroom']);
        foreach (['local/sentientia_org:manage', 'local/sentientia_org:manage_multiorganizations',
                  'local/sentientia_platform:crosstenant'] as $never) {
            $this->assertContains($never, capability_repair::NEVER_GRANT);
        }
    }

    public function test_the_inventory_lists_grants_on_capabilities_of_a_plugin_missing_from_disk(): void {
        [$roleid, $repair] = $this->scenario();
        $rows = $this->rows_of($roleid, $repair->inventory());
        $this->assertCount(2, $rows);

        $classroom = $rows[array_search(self::OLD, array_column($rows, 'legacy'), true)];
        $this->assertSame('blmtrainer', $classroom['role']);
        $this->assertSame(CAP_ALLOW, $classroom['permission']);
        $this->assertSame('local_blmlegacy', $classroom['component']);
        $this->assertSame(self::NEW, $classroom['target']);
        $this->assertTrue($classroom['target_exists']);
        $this->assertNull($classroom['target_permission'], 'the role holds no row for the equivalent');
        $this->assertFalse($classroom['held']);
        $this->assertFalse($classroom['divergent']);
        $this->assertFalse($classroom['withheld']);

        $admin = $rows[array_search(self::OLD_ADMIN, array_column($rows, 'legacy'), true)];
        $this->assertTrue($admin['withheld'], 'the equivalent of this one is never granted here');
    }

    public function test_an_approved_grant_is_made_once_and_nothing_is_revoked(): void {
        global $DB;
        [$roleid, $repair] = $this->scenario();
        $allowlist = capability_repair::load_allowlist($this->allowlist([$this->grant()]));
        $this->assertSame('Nitin Rajput', $allowlist['approved_by']);
        $this->assertSame(64, strlen($allowlist['hash']));

        $plan = $repair->plan($allowlist['grants']);
        $this->assertSame([], $plan['refused']);
        $this->assertCount(1, $plan['apply']);
        $this->assertSame([], $plan['uncovered']);
        $this->assertCount(1, $plan['withheld'], 'the admin capability is reported as withheld, not granted');
        $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEW]),
            'planning writes nothing');

        $this->assertSame(1, $repair->apply($plan['apply']));
        $granted = $DB->get_record('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEW], '*', MUST_EXIST);
        $this->assertEquals(CAP_ALLOW, $granted->permission);
        $this->assertEquals(\context_system::instance()->id, $granted->contextid);
        // The repair only adds: both legacy grants are still there.
        $this->assertTrue($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => self::OLD]));
        $this->assertTrue($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => self::OLD_ADMIN]));

        // Idempotent: the second plan finds it held, and a second apply changes nothing.
        $again = $repair->plan($allowlist['grants']);
        $this->assertSame([], $again['apply']);
        $this->assertCount(1, $again['held']);
        $repair->apply($plan['apply']);
        $this->assertSame(1, $DB->count_records('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEW]));
        $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEVER]));
    }

    public function test_a_grant_nobody_approved_is_reported_and_not_made(): void {
        global $DB;
        [$roleid, $repair] = $this->scenario();
        $plan = $repair->plan([]);
        $this->assertSame([], $plan['apply']);
        $this->assertCount(1, $plan['uncovered']);
        $this->assertStringContainsString(self::OLD . ' -> ' . self::NEW, $plan['uncovered'][0]);
        $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEW]));
    }

    public function test_a_prohibit_override_is_carried_only_when_approved_with_that_permission(): void {
        global $DB;
        [$roleid, $repair] = $this->scenario(CAP_PROHIBIT);

        $plan = $repair->plan([$this->grant(self::OLD, self::NEW, CAP_ALLOW)]);
        $this->assertNotEmpty($plan['refused'], 'the role holds a prohibit there, not an allow');
        $this->assertStringContainsString('does not hold that legacy grant with that permission', $plan['refused'][0]);

        $plan = $repair->plan([$this->grant(self::OLD, self::NEW, CAP_PROHIBIT)]);
        $this->assertSame([], $plan['refused']);
        $repair->apply($plan['apply']);
        $this->assertEquals(CAP_PROHIBIT, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => self::NEW]));
    }

    /**
     * Give a role a row on a capability in the system context, as the plugin install's archetype grants do.
     *
     * @param int $roleid
     * @param string $capability
     * @param int $permission
     * @return void
     */
    private function hold(int $roleid, string $capability, int $permission): void {
        global $DB;
        $DB->insert_record('role_capabilities', (object) ['contextid' => \context_system::instance()->id,
            'roleid' => $roleid, 'capability' => $capability, 'permission' => $permission, 'timemodified' => time(),
            'modifierid' => 0]);
    }

    public function test_a_target_held_with_the_same_permission_is_already_carried(): void {
        global $DB;
        // ALLOW against ALLOW, the manager-archetype case: nothing to decide, nothing to grant.
        [$roleid, $repair] = $this->scenario(CAP_ALLOW);
        $this->hold($roleid, self::NEW, CAP_ALLOW);
        $rows = $this->rows_of($roleid, $repair->inventory());
        $row = $rows[array_search(self::OLD, array_column($rows, 'legacy'), true)];
        $this->assertSame(CAP_ALLOW, $row['target_permission']);
        $this->assertTrue($row['held']);
        $this->assertFalse($row['divergent']);

        $plan = $repair->plan([], $rows);
        $this->assertSame([], $plan['uncovered']);
        $this->assertSame([], $plan['divergent']);
        $this->assertSame(0, capability_repair::open_count($plan));
        $this->assertSame(0, capability_repair::exit_code($plan), 'the archetype already gives what BizLMS had');

        // An approved grant against it is "already held", not refused and not made again.
        $plan = $repair->plan([$this->grant()], $rows);
        $this->assertSame([], $plan['refused']);
        $this->assertSame([], $plan['apply']);
        $this->assertCount(1, $plan['held']);
        $this->assertSame(0, capability_repair::exit_code($plan));
        $this->assertSame(1, $DB->count_records('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEW]));
    }

    public function test_a_legacy_prohibit_against_a_target_held_as_allow_stays_open_until_a_named_decline(): void {
        global $DB;
        [$roleid, $repair] = $this->scenario(CAP_PROHIBIT);
        // The install's manager archetype gave the equivalent ALLOW to a role that BizLMS restricted.
        $this->hold($roleid, self::NEW, CAP_ALLOW);

        $rows = $this->rows_of($roleid, $repair->inventory());
        $row = $rows[array_search(self::OLD, array_column($rows, 'legacy'), true)];
        $this->assertSame(CAP_ALLOW, $row['target_permission']);
        $this->assertFalse($row['held'], 'a row with another permission is not a grant that is carried');
        $this->assertTrue($row['divergent']);

        $plan = $repair->plan([], $rows);
        $this->assertCount(1, $plan['divergent']);
        $this->assertStringContainsString(self::OLD . ' -> ' . self::NEW, $plan['divergent'][0]);
        $this->assertStringContainsString('legacy PROHIBIT', $plan['divergent'][0]);
        $this->assertStringContainsString('holds the target as ALLOW', $plan['divergent'][0]);
        $this->assertSame([], $plan['uncovered']);
        $this->assertSame([], $plan['held']);
        $this->assertSame(1, capability_repair::open_count($plan));
        $this->assertSame(2, capability_repair::exit_code($plan), 'nobody decided that the restriction may widen');

        // A decline that names another role or another capability decides nothing here.
        foreach ([['role' => 'manager'], ['legacy' => self::OLD_ADMIN]] as $change) {
            $elsewhere = $change + ['role' => 'blmtrainer', 'context' => 'system', 'legacy' => self::OLD,
                'reason' => 'not this row'];
            $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => [$elsewhere]]));
            $plan = $repair->plan($loaded['grants'], $rows, $loaded['declines']);
            $this->assertCount(1, $plan['divergent']);
            $this->assertSame(2, capability_repair::exit_code($plan));
        }

        // A decline that names this role grant closes it, and writes nothing.
        $decline = ['role' => 'blmtrainer', 'context' => 'system', 'legacy' => self::OLD,
            'reason' => 'the restriction is lifted on purpose'];
        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => [$decline]]));
        $plan = $repair->plan($loaded['grants'], $rows, $loaded['declines']);
        $this->assertSame([], $plan['divergent']);
        $this->assertCount(1, $plan['declined']);
        $this->assertSame('the restriction is lifted on purpose',
            $plan['declined_by']['decline 1 (blmtrainer ' . self::OLD . ')']['reason']);
        $this->assertSame(0, capability_repair::exit_code($plan));
        $this->assertSame(0, $repair->apply($plan['apply']));
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => self::NEW]), 'the repair overwrote nothing');
    }

    /**
     * @dataProvider divergent_permission_provider
     * @param int $legacy What the role holds on the BizLMS capability.
     * @param int $target What the role holds on the Sentientia equivalent.
     */
    public function test_an_approved_grant_whose_target_is_held_with_another_permission_is_refused(int $legacy, int $target): void {
        global $DB;
        [$roleid, $repair] = $this->scenario($legacy);
        $this->hold($roleid, self::NEW, $target);
        $rows = $this->rows_of($roleid, $repair->inventory());

        $plan = $repair->plan([$this->grant(self::OLD, self::NEW, $legacy)], $rows);
        $this->assertCount(1, $plan['refused']);
        $this->assertStringContainsString('target_held_with_a_different_permission', $plan['refused'][0]);
        $this->assertStringContainsString('never overwrites', $plan['refused'][0]);
        $this->assertSame([], $plan['apply']);
        $this->assertSame([], $plan['held'], 'not "already held": the role holds the target as something else');
        $this->assertCount(1, $plan['divergent'], 'and the row is still undecided');
        $this->assertSame(1, capability_repair::exit_code($plan));

        $this->assertSame(0, $repair->apply($plan['apply']));
        $this->assertEquals($target, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => self::NEW]));
    }

    /**
     * @return array<string, array{0: int, 1: int}> Legacy permission, then the permission the target is held with.
     */
    public static function divergent_permission_provider(): array {
        return [
            'PROHIBIT widened to ALLOW' => [CAP_PROHIBIT, CAP_ALLOW],
            'PREVENT widened to ALLOW' => [CAP_PREVENT, CAP_ALLOW],
            'ALLOW narrowed to PROHIBIT' => [CAP_ALLOW, CAP_PROHIBIT],
            'ALLOW narrowed to PREVENT' => [CAP_ALLOW, CAP_PREVENT],
        ];
    }

    public function test_the_capabilities_that_belong_to_site_admins_cannot_be_allow_listed(): void {
        global $DB;
        [$roleid, $repair] = $this->scenario();
        $plan = $repair->plan([$this->grant(self::OLD_ADMIN, self::NEVER)]);
        $this->assertStringContainsString('target_is_never_granted:' . self::NEVER, $plan['refused'][0]);
        $this->assertSame([], $plan['apply']);

        // The real list, with the real capabilities: tenant admins do not get organisation delete, edit or
        // cross-tenant visibility (ADR-031), and nothing grants the cross-tenant capability.
        $real = new capability_repair();
        foreach ([
            ['local/costcenter:manage', 'local/sentientia_org:manage'],
            ['local/costcenter:manage_multiorganizations', 'local/sentientia_org:manage_multiorganizations'],
        ] as [$old, $new]) {
            $refused = $real->plan([$this->grant($old, $new)], [])['refused'];
            $this->assertStringContainsString('target_is_never_granted:' . $new, $refused[0]);
        }
        $refused = $real->plan([$this->grant('local/costcenter:view', 'local/sentientia_platform:crosstenant')], [])['refused'];
        $this->assertStringContainsString('target_is_never_granted:local/sentientia_platform:crosstenant', $refused[0]);

        // A plan built by hand does not get past apply() either.
        try {
            $repair->apply([['target' => self::NEVER, 'permission' => CAP_ALLOW, 'roleid' => $roleid,
                'contextid' => \context_system::instance()->id]]);
            $this->fail('apply() granted a capability that is never granted');
        } catch (blocked $e) {
            $this->assertStringContainsString('capability_is_never_granted:' . self::NEVER, $e->getMessage());
        }
        $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => self::NEVER]));
    }

    public function test_a_grant_is_refused_unless_it_matches_what_the_role_really_holds(): void {
        [$roleid, $repair] = $this->scenario();
        $cases = [
            'role_not_found' => ['role' => 'nosuchrole'] + $this->grant(),
            'context_not_found' => ['context' => '999999999'] + $this->grant(),
            'target_is_not_the_equivalent_of_the_legacy_capability' => $this->grant(self::OLD, 'local/blmtarget:other'),
            'the role does not hold that legacy grant' => ['role' => 'manager'] + $this->grant(),
        ];
        foreach ($cases as $expected => $grant) {
            $plan = $repair->plan([$grant]);
            $this->assertSame([], $plan['apply'], $expected);
            $this->assertCount(1, $plan['refused'], $expected);
            $this->assertStringContainsString($expected, $plan['refused'][0]);
        }
    }

    public function test_a_legacy_grant_with_no_known_equivalent_is_unmapped(): void {
        global $DB;
        [$roleid, $repair] = $this->scenario();
        $DB->insert_record('capabilities', (object) ['name' => 'local/blmlegacy:other', 'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM, 'component' => 'local_blmlegacy', 'riskbitmask' => 0]);
        \cache::make('core', 'capabilities')->delete('core_capabilities');
        $DB->insert_record('role_capabilities', (object) ['contextid' => \context_system::instance()->id, 'roleid' => $roleid,
            'capability' => 'local/blmlegacy:other', 'permission' => CAP_ALLOW, 'timemodified' => time(), 'modifierid' => 0]);

        $plan = $repair->plan([$this->grant()]);
        $this->assertCount(1, $plan['unmapped']);
        $this->assertStringContainsString('local/blmlegacy:other', $plan['unmapped'][0]);
    }

    public function test_an_allowlist_must_be_signed_and_well_formed(): void {
        $bad = [
            'capability_allowlist_is_not_signed' => $this->allowlist([$this->grant()], ['approved_by' => '']),
            'capability_allowlist_has_no_grants_list' => $this->allowlist([], ['grants' => 'all']),
            'capability_allowlist_grant_invalid:0:permission' => $this->allowlist([$this->grant(self::OLD, self::NEW, 5)]),
            'capability_allowlist_grant_invalid:0:target' => $this->allowlist([['role' => 'x', 'context' => 'system',
                'legacy' => self::OLD, 'permission' => 1]]),
        ];
        foreach ($bad as $expected => $path) {
            try {
                capability_repair::load_allowlist($path);
                $this->fail("accepted: {$expected}");
            } catch (blocked $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
        $this->expectException(blocked::class);
        capability_repair::load_allowlist('/nonexistent/allowlist.json');
    }

    // The declined section (the owner's "reviewed, not carried" decisions), ADR-032 "Capabilities".

    private const ARCH_ROLES = ['blmmanager', 'blmadmin'];

    /**
     * BizLMS's manager-archetype grants on the two roles that hold them on production (the core manager role and the
     * tenant-admin role 9), with fake plugin names so the test does not depend on which plugins are on disk.
     *
     * Seven legacy capabilities on each role: view (its Sentientia equivalent has a manager archetype, so the
     * install already grants it to both roles), manage (the equivalent is never granted), manage_ownorganization and
     * manage_owndepartments (equivalents with no archetype, on purpose), and three with no equivalent at all.
     *
     * @return array{0: int[], 1: capability_repair} The two role ids and a repair with the injected map.
     */
    private function archetype_scenario(): array {
        global $DB;
        $this->resetAfterTest();
        $legacy = [
            'local/blmcost:view' => 'local/blmtarget:view',
            'local/blmcost:manage' => 'local/blmtarget:admin',
            'local/blmcost:manage_ownorganization' => 'local/blmtarget:ownorg',
            'local/blmcost:manage_owndepartments' => 'local/blmtarget:owndept',
            'local/blmcost:create' => null,
            'local/blmforum:view' => null,
            'local/blmforum:post' => null,
        ];
        foreach (array_keys($legacy) as $name) {
            $DB->insert_record('capabilities', (object) ['name' => $name, 'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM, 'riskbitmask' => 0,
                'component' => str_starts_with($name, 'local/blmcost') ? 'local_blmcost' : 'local_blmforum']);
        }
        foreach (array_filter($legacy) as $target) {
            $DB->insert_record('capabilities', (object) ['name' => $target, 'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM, 'component' => 'local_sentientia_platform', 'riskbitmask' => 0]);
        }
        \cache::make('core', 'capabilities')->delete('core_capabilities');

        $system = \context_system::instance();
        $roleids = [];
        foreach (self::ARCH_ROLES as $shortname) {
            $roleid = $this->getDataGenerator()->create_role(['shortname' => $shortname]);
            $roleids[] = $roleid;
            foreach ([...array_keys($legacy), 'local/blmtarget:view'] as $name) {
                $DB->insert_record('role_capabilities', (object) ['contextid' => $system->id, 'roleid' => $roleid,
                    'capability' => $name, 'permission' => CAP_ALLOW, 'timemodified' => time(), 'modifierid' => 0]);
            }
        }
        return [$roleids, new capability_repair(array_filter($legacy), ['local/blmtarget:admin'])];
    }

    /**
     * @param int[] $roleids
     * @param array[] $inventory
     * @return array[] The inventory rows of those roles (the test database may hold other roles' rows).
     */
    private function only_roles(array $roleids, array $inventory): array {
        return array_values(array_filter($inventory, fn(array $row): bool => in_array($row['roleid'], $roleids, true)));
    }

    /**
     * The declines the owner would sign for the archetype scenario: the two organisation capabilities ADR-031
     * withholds, on both roles, and both plugins by component.
     *
     * @return array[]
     */
    private function archetype_declines(): array {
        $declined = [];
        foreach (self::ARCH_ROLES as $role) {
            foreach (['manage_ownorganization', 'manage_owndepartments'] as $capability) {
                $declined[] = ['role' => $role, 'context' => 'system', 'legacy' => 'local/blmcost:' . $capability,
                    'reason' => 'ADR-031 withholds it from tenant admins'];
            }
        }
        $declined[] = ['legacy_component' => 'local_blmcost', 'reason' => 'code not deployed; Sentientia archetypes grant the replacements'];
        $declined[] = ['legacy_component' => 'local_blmforum', 'reason' => 'code not deployed; Sentientia archetypes grant the replacements'];
        return $declined;
    }

    public function test_the_manager_archetype_set_with_the_owners_declines_finishes_clean_and_grants_nothing(): void {
        global $DB;
        [$roleids, $repair] = $this->archetype_scenario();
        $inventory = $this->only_roles($roleids, $repair->inventory());
        $this->assertCount(14, $inventory, 'seven legacy capabilities on each of two roles');
        $before = $DB->count_records('role_capabilities');

        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => $this->archetype_declines()]));
        $this->assertCount(6, $loaded['declines']);
        $plan = $repair->plan($loaded['grants'], $inventory, $loaded['declines']);

        $this->assertSame([], $plan['refused']);
        $this->assertSame([], $plan['apply'], 'nothing is granted');
        $this->assertSame([], $plan['uncovered']);
        $this->assertSame([], $plan['unmapped']);
        $this->assertSame([], $plan['unused_declines']);
        $this->assertCount(2, $plan['withheld'], 'the never-granted equivalent, on both roles');
        // Per role: ownorg, owndept, create and the two forum capabilities are declined; view is held; manage is withheld.
        $this->assertCount(10, $plan['declined']);
        $this->assertSame(2, $plan['declined_by']['decline 5 (local_blmcost)']['rows']);
        $this->assertSame(4, $plan['declined_by']['decline 6 (local_blmforum)']['rows']);
        $this->assertSame('ADR-031 withholds it from tenant admins',
            $plan['declined_by']['decline 1 (blmmanager local/blmcost:manage_ownorganization)']['reason']);

        $this->assertSame(0, capability_repair::open_count($plan));
        $this->assertSame(0, capability_repair::exit_code($plan), 'every grant is decided');
        $this->assertSame(0, $repair->apply($plan['apply']));
        $this->assertSame($before, $DB->count_records('role_capabilities'), 'the review wrote nothing');
    }

    public function test_the_same_review_without_the_declines_exits_2(): void {
        [$roleids, $repair] = $this->archetype_scenario();
        $inventory = $this->only_roles($roleids, $repair->inventory());

        $plan = $repair->plan([], $inventory);
        $this->assertCount(4, $plan['uncovered'], 'ownorg and owndept, on both roles');
        $this->assertCount(6, $plan['unmapped'], 'create and the two forum capabilities, on both roles');
        $this->assertSame([], $plan['declined']);
        $this->assertSame(10, capability_repair::open_count($plan));
        $this->assertSame(2, capability_repair::exit_code($plan));
    }

    public function test_a_prohibit_override_on_a_mapped_capability_keeps_the_signed_review_at_exit_2(): void {
        global $DB;
        [$roleids, $repair] = $this->archetype_scenario();
        // BizLMS restricted the tenant-admin role on local/blmcost:view (PROHIBIT at system); the Sentientia install
        // then gave that role the equivalent as ALLOW through the manager archetype.
        $DB->set_field('role_capabilities', 'permission', CAP_PROHIBIT,
            ['roleid' => $roleids[1], 'capability' => 'local/blmcost:view']);
        $inventory = $this->only_roles($roleids, $repair->inventory());

        // Everything the owner signed in the archetype review, and nothing about that override.
        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => $this->archetype_declines()]));
        $plan = $repair->plan($loaded['grants'], $inventory, $loaded['declines']);
        $this->assertSame([], $plan['refused']);
        $this->assertSame([], $plan['uncovered']);
        $this->assertSame([], $plan['unmapped']);
        $this->assertCount(1, $plan['divergent']);
        $this->assertStringContainsString('blmadmin', $plan['divergent'][0]);
        $this->assertStringContainsString('local/blmcost:view -> local/blmtarget:view', $plan['divergent'][0]);
        $this->assertSame(1, capability_repair::open_count($plan));
        $this->assertSame(2, capability_repair::exit_code($plan), 'the signed declines do not cover the override');
        $this->assertSame([], $plan['apply'], 'and nothing is granted or overwritten');

        // The owner names that role grant in a decline, with a reason: the review finishes clean.
        $declined = $this->archetype_declines();
        $declined[] = ['role' => 'blmadmin', 'context' => 'system', 'legacy' => 'local/blmcost:view',
            'reason' => 'tenant admins may see organisations; the PROHIBIT was a BizLMS quirk'];
        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => $declined]));
        $plan = $repair->plan($loaded['grants'], $inventory, $loaded['declines']);
        $this->assertSame([], $plan['divergent']);
        $this->assertSame([], $plan['unused_declines']);
        $this->assertCount(11, $plan['declined']);
        $this->assertSame(0, capability_repair::exit_code($plan));

        // The core manager role, whose legacy view is ALLOW against the archetype's ALLOW, was never affected.
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleids[0], 'capability' => 'local/blmtarget:view']));
    }

    public function test_a_plugin_decline_covers_only_capabilities_with_no_equivalent(): void {
        [$roleids, $repair] = $this->archetype_scenario();
        $inventory = $this->only_roles($roleids, $repair->inventory());
        $plugins = array_slice($this->archetype_declines(), 4);
        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => $plugins]));

        $plan = $repair->plan([], $inventory, $loaded['declines']);
        $this->assertSame([], $plan['unmapped'], 'no equivalent: the plugin decline covers them');
        $this->assertCount(6, $plan['declined']);
        $this->assertCount(4, $plan['uncovered'], 'the two that have an equivalent are decided per role, never by plugin');
        $this->assertSame(2, capability_repair::exit_code($plan));
    }

    public function test_a_row_decline_names_one_role_and_one_context(): void {
        [$roleids, $repair] = $this->archetype_scenario();
        $inventory = $this->only_roles($roleids, $repair->inventory());
        $decline = ['role' => 'blmmanager', 'context' => 'system', 'legacy' => 'local/blmcost:manage_ownorganization',
            'reason' => 'ADR-031'];
        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => [$decline]]));

        $plan = $repair->plan([], $inventory, $loaded['declines']);
        $this->assertCount(1, $plan['declined']);
        $this->assertCount(3, $plan['uncovered'], 'the other role, and the other capability, are still open');
        $this->assertStringContainsString('blmadmin', implode(' ', $plan['uncovered']));

        // The same role in a context the role holds nothing in: it names no row, so it changes nothing and is noted.
        $elsewhere = ['context' => '999999999'] + $decline;
        $loaded = capability_repair::load_allowlist($this->allowlist([], ['declined' => [$elsewhere]]));
        $plan = $repair->plan([], $inventory, $loaded['declines']);
        $this->assertSame([], $plan['declined']);
        $this->assertCount(4, $plan['uncovered']);
        $this->assertCount(1, $plan['unused_declines']);
        $this->assertStringContainsString('matches no grant in the inventory', $plan['unused_declines'][0]);
    }

    public function test_a_line_that_is_both_granted_and_declined_is_refused(): void {
        global $DB;
        [$roleids, $repair] = $this->archetype_scenario();
        $inventory = $this->only_roles($roleids, $repair->inventory());
        $legacy = 'local/blmcost:manage_ownorganization';
        $grant = ['role' => 'blmmanager', 'context' => (string) \context_system::instance()->id, 'legacy' => $legacy,
            'target' => 'local/blmtarget:ownorg', 'permission' => CAP_ALLOW];
        // "system" in the decline and the context id in the grant are the same context.
        $decline = ['role' => 'blmmanager', 'context' => 'system', 'legacy' => $legacy, 'reason' => 'ADR-031'];
        $loaded = capability_repair::load_allowlist($this->allowlist([$grant], ['declined' => [$decline]]));

        $plan = $repair->plan($loaded['grants'], $inventory, $loaded['declines']);
        $this->assertCount(2, $plan['refused'], 'both lines are refused');
        $this->assertStringContainsString('is_also_declined_by:decline 1', implode("\n", $plan['refused']));
        $this->assertStringContainsString('is_also_granted_by:grant 1', implode("\n", $plan['refused']));
        $this->assertSame([], $plan['apply'], 'a refused grant is not made');
        $this->assertSame([], $plan['declined'], 'and the decline is not honoured either');
        $this->assertSame(1, capability_repair::exit_code($plan));
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => 'local/blmtarget:ownorg']));

        // The same grant on the other role is not in conflict and is made.
        $other = ['role' => 'blmadmin'] + $grant;
        $loaded = capability_repair::load_allowlist($this->allowlist([$other], ['declined' => [$decline]]));
        $plan = $repair->plan($loaded['grants'], $inventory, $loaded['declines']);
        $this->assertSame([], $plan['refused']);
        $this->assertCount(1, $plan['apply']);
        $this->assertCount(1, $plan['declined']);
    }

    public function test_a_decline_does_not_lift_the_never_granted_rule(): void {
        [$roleids, $repair] = $this->archetype_scenario();
        $inventory = $this->only_roles($roleids, $repair->inventory());
        $grant = ['role' => 'blmmanager', 'context' => 'system', 'legacy' => 'local/blmcost:manage',
            'target' => 'local/blmtarget:admin', 'permission' => CAP_ALLOW];
        $declined = $this->archetype_declines();

        $loaded = capability_repair::load_allowlist($this->allowlist([$grant], ['declined' => $declined]));
        $plan = $repair->plan($loaded['grants'], $inventory, $loaded['declines']);
        $this->assertStringContainsString('target_is_never_granted:local/blmtarget:admin', $plan['refused'][0]);
        $this->assertSame([], $plan['apply']);
        $this->assertSame(1, capability_repair::exit_code($plan));
    }

    public function test_the_declined_section_must_be_well_formed(): void {
        $component = ['legacy_component' => 'local_forum', 'reason' => 'gone'];
        $row = ['role' => 'manager', 'context' => 'system', 'legacy' => 'local/costcenter:view', 'reason' => 'gone'];
        $bad = [
            ['capability_allowlist_declined_is_not_a_list', ['declined' => 'all']],
            ['capability_allowlist_declined_is_not_a_list', ['declined' => ['a' => $component]]],
            ['capability_allowlist_decline_invalid:0:entry', ['declined' => ['local_forum']]],
            ['capability_allowlist_decline_invalid:0:reason', ['declined' => [['reason' => ''] + $component]]],
            ['capability_allowlist_decline_invalid:0:reason', ['declined' => [['reason' => '   '] + $row]]],
            ['capability_allowlist_decline_invalid:0:reason', ['declined' => [['legacy_component' => 'local_forum']]]],
            ['capability_allowlist_decline_invalid:0:shape', ['declined' => [['reason' => 'gone']]]],
            ['capability_allowlist_decline_invalid:0:shape', ['declined' => [['role' => 'manager'] + $component]]],
            ['capability_allowlist_decline_invalid:0:legacy_component', ['declined' => [['legacy_component' => 'Local Forum'] + $component]]],
            ['capability_allowlist_decline_invalid:0:legacy_component', ['declined' => [['legacy_component' => 'forum'] + $component]]],
            ['capability_allowlist_decline_invalid:0:role', ['declined' => [array_diff_key($row, ['role' => 1])]]],
            ['capability_allowlist_decline_invalid:0:context', ['declined' => [['context' => ''] + $row]]],
            ['capability_allowlist_decline_invalid:0:legacy', ['declined' => [['legacy' => 'not a capability'] + $row]]],
            ['capability_allowlist_decline_invalid:1:entry', ['declined' => [$component, 5]]],
        ];
        foreach ($bad as [$expected, $header]) {
            try {
                capability_repair::load_allowlist($this->allowlist([], $header));
                $this->fail("accepted: {$expected}");
            } catch (blocked $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }

        // An allow-list with no declined section (the shape before this section existed) still loads.
        $this->assertSame([], capability_repair::load_allowlist($this->allowlist([]))['declines']);
        // A decline is part of what was signed: it changes the hash.
        $this->assertNotSame(capability_repair::load_allowlist($this->allowlist([]))['hash'],
            capability_repair::load_allowlist($this->allowlist([], ['declined' => [$component, $row]]))['hash']);
    }

    public function test_the_checked_in_draft_declines_the_22_plugins_and_grants_nothing(): void {
        $path = __DIR__ . '/../fixtures/bizlms/bizlms-capability-allowlist.copy.json';
        $this->assertFileExists($path);

        // Unsigned as checked in: the owner signs it, and the loader refuses it until then.
        try {
            capability_repair::load_allowlist($path);
            $this->fail('an unsigned allow-list was accepted');
        } catch (blocked $e) {
            $this->assertStringContainsString('capability_allowlist_is_not_signed', $e->getMessage());
        }

        $data = json_decode((string) file_get_contents($path), true);
        $signed = make_request_directory() . '/signed.json';
        file_put_contents($signed, json_encode(array_merge($data, ['approved_by' => 'Test Owner', 'approved_on' => '2026-09-30'])));
        $loaded = capability_repair::load_allowlist($signed);

        $this->assertSame([], $loaded['grants'], 'the draft carries no grant: those are the owner\'s to add');
        $components = array_column(array_filter($loaded['declines'], fn(array $d): bool => $d['kind'] === 'component'), 'component');
        $plugins = ['local_assignroles', 'local_biz_cart', 'local_classroom', 'local_costcenter', 'local_courses',
            'local_custom_category', 'local_evaluation', 'local_forum', 'local_groups', 'local_learningplan', 'local_location',
            'local_myteam', 'local_notifications', 'local_onlineexams', 'local_program', 'local_ratings', 'local_recompletion',
            'local_request', 'local_search', 'local_skillrepository', 'local_tags', 'local_users'];
        $this->assertEqualsCanonicalizing($plugins, $components, 'the 22 BizLMS plugins of the production snapshot');

        // Roles 1 and 9 (manager, administrator): the two organisation capabilities ADR-031 withholds.
        $rows = array_values(array_filter($loaded['declines'], fn(array $d): bool => $d['kind'] === 'row'));
        $this->assertCount(4, $rows);
        foreach ($rows as $decline) {
            $this->assertContains($decline['role'], ['manager', 'administrator']);
            $this->assertSame('system', $decline['context']);
            $this->assertContains($decline['legacy'],
                ['local/costcenter:manage_ownorganization', 'local/costcenter:manage_owndepartments']);
            $this->assertContains(capability_repair::MAP[$decline['legacy']], [
                'local/sentientia_org:manage_ownorganization', 'local/sentientia_org:manage_owndepartments',
            ]);
        }

        // manageclassroom is an open decision: nothing names it as a decline.
        $this->assertNotContains('local/classroom:manageclassroom', array_column($rows, 'legacy'));
        $this->assertSame('local/classroom:manageclassroom', $data['open_decisions'][0]['legacy']);
    }

    public function test_the_test_copy_of_the_draft_is_the_checked_in_file_wherever_the_checkout_has_both(): void {
        $signed = __DIR__ . '/../../../../docs/cutover/bizlms-capability-allowlist.json';
        if (!is_readable($signed)) {
            $this->markTestSkipped('docs/ is not deployed with the plugin; tools/check-bizlms-fixture-copies.php checks this in CI');
        }
        $this->assertSame(
            str_replace("\r\n", "\n", (string) file_get_contents($signed)),
            str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../fixtures/bizlms/bizlms-capability-allowlist.copy.json')),
            'copy the checked-in file over tests/fixtures/bizlms/bizlms-capability-allowlist.copy.json in both trees'
        );
    }
}
