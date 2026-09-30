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
        $this->assertSame(self::NEW, $classroom['target']);
        $this->assertTrue($classroom['target_exists']);
        $this->assertFalse($classroom['held']);
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
}
