<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

/**
 * Tests for the Sentientia Author role provisioner (T-01 fresh-install parity).
 *
 * Note: the plugin's db/install.php already calls author_role::ensure() during
 * phpunit init, so the role normally exists at the start of each test (with
 * resetAfterTest restoring that baseline). These tests therefore validate the
 * END STATE, reconciliation, and idempotency rather than first-time creation.
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\author_role
 */
final class author_role_test extends \advanced_testcase {

    /**
     * ensure() yields an archetype-less, system-context-only role holding the
     * full author cap set at ALLOW, and NOTHING admin/tenant-admin.
     */
    public function test_ensure_provisions_scoped_author_role(): void {
        global $DB;
        $this->resetAfterTest();

        $roleid = author_role::ensure();
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);

        $this->assertSame(author_role::SHORTNAME, $role->shortname);
        $this->assertSame('', (string) $role->archetype,
            'Author role must be archetype-less (no broad teacher/manager caps)');
        $this->assertEquals([CONTEXT_SYSTEM],
            array_values(get_role_contextlevels($roleid)),
            'Author role must be assignable at CONTEXT_SYSTEM only');

        // Every registered author cap is ALLOW at system context.
        $syscontext = \context_system::instance();
        foreach (author_role::caps() as $cap) {
            if (!$DB->record_exists('capabilities', ['name' => $cap])) {
                continue; // Owning plugin not installed in this test site.
            }
            $perm = $DB->get_field('role_capabilities', 'permission',
                ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id]);
            $this->assertEquals(CAP_ALLOW, (int) $perm, "author role should ALLOW {$cap}");
        }

        // Scope discipline — the author must NEVER hold admin / tenant-admin /
        // user-management caps.
        foreach ([
            'local/sentientia_courses:manage',
            'local/sentientia_courses:view',
            'local/sentientia_users:view',
            'moodle/site:config',
        ] as $forbidden) {
            $this->assertFalse(
                $DB->record_exists('role_capabilities',
                    ['roleid' => $roleid, 'capability' => $forbidden]),
                "author role must NOT hold {$forbidden}");
        }
    }

    /**
     * ensure() reconciles: a cap removed out-of-band is restored to ALLOW.
     */
    public function test_ensure_reconciles_missing_cap(): void {
        global $DB;
        $this->resetAfterTest();

        $roleid = author_role::ensure();
        $syscontext = \context_system::instance();
        $cap = 'local/sentientia_authoring:generate';

        // Remove the cap, then re-run ensure().
        unassign_capability($cap, $roleid, $syscontext->id);
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id]));

        author_role::ensure();

        $perm = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => $cap, 'contextid' => $syscontext->id]);
        $this->assertEquals(CAP_ALLOW, (int) $perm, 'ensure() must restore the removed cap');
    }

    /**
     * ensure() is idempotent — no duplicate roles, stable id.
     */
    public function test_ensure_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        $first  = author_role::ensure();
        $second = author_role::ensure();

        $this->assertSame($first, $second, 'ensure() must reuse the existing role');
        $this->assertSame(1,
            $DB->count_records('role', ['shortname' => author_role::SHORTNAME]),
            'ensure() must not create duplicate author roles');
    }
}
