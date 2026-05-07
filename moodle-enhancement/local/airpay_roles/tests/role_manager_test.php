<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for {@see role_manager}.
 *
 * Locks in the read-side stats (counts, filters) and the write-side
 * audit-log invariant: every successful capability change writes
 * exactly one audit row with the correct old/new values.
 *
 * @package    local_airpay_roles
 * @category   test
 * @copyright  2026 Airpay Payment Services
 */
final class role_manager_test extends \advanced_testcase {

    public function test_permission_from_string_round_trip(): void {
        $this->assertSame(CAP_INHERIT,  role_manager::permission_from_string('inherit'));
        $this->assertSame(CAP_INHERIT,  role_manager::permission_from_string(''));
        $this->assertSame(CAP_INHERIT,  role_manager::permission_from_string('unset'));
        $this->assertSame(CAP_ALLOW,    role_manager::permission_from_string('allow'));
        $this->assertSame(CAP_PREVENT,  role_manager::permission_from_string('prevent'));
        $this->assertSame(CAP_PROHIBIT, role_manager::permission_from_string('prohibit'));

        $this->assertSame('inherit',  role_manager::permission_to_string(CAP_INHERIT));
        $this->assertSame('allow',    role_manager::permission_to_string(CAP_ALLOW));
        $this->assertSame('prevent',  role_manager::permission_to_string(CAP_PREVENT));
        $this->assertSame('prohibit', role_manager::permission_to_string(CAP_PROHIBIT));
    }

    public function test_permission_from_string_rejects_garbage(): void {
        $this->expectException(\invalid_parameter_exception::class);
        role_manager::permission_from_string('superuser');
    }

    public function test_list_roles_returns_all_with_counts(): void {
        $this->resetAfterTest();
        $rows = role_manager::list_roles();
        $this->assertNotEmpty($rows, 'Moodle ships with stock roles — list cannot be empty');
        $shortnames = array_column($rows, 'shortname');
        $this->assertContains('manager', $shortnames);
        $this->assertContains('student', $shortnames);
        // Every row must have all reporting fields.
        foreach ($rows as $r) {
            $this->assertArrayHasKey('id', $r);
            $this->assertArrayHasKey('capcount', $r);
            $this->assertArrayHasKey('assigncount', $r);
            $this->assertGreaterThanOrEqual(0, $r['capcount']);
            $this->assertGreaterThanOrEqual(0, $r['assigncount']);
        }
    }

    public function test_list_roles_search_substring(): void {
        $this->resetAfterTest();
        $rows = role_manager::list_roles('manager');
        $this->assertNotEmpty($rows);
        foreach ($rows as $r) {
            $haystack = strtolower($r['name'] . ' ' . $r['shortname']);
            $this->assertStringContainsString('manager', $haystack);
        }
    }

    public function test_list_roles_archetype_filter(): void {
        $this->resetAfterTest();
        $rows = role_manager::list_roles('', 'student');
        $this->assertNotEmpty($rows);
        foreach ($rows as $r) {
            $this->assertSame('student', $r['archetype']);
        }
    }

    public function test_list_roles_custom_archetype_filter_excludes_built_ins(): void {
        $this->resetAfterTest();
        $rows = role_manager::list_roles('', 'custom');
        // Stock Moodle has all built-in archetypes, but no custom roles —
        // so the filter should yield zero rows on a fresh test DB.
        foreach ($rows as $r) {
            $this->assertSame('', $r['archetype'],
                'custom filter must exclude rows that have an archetype');
        }
    }

    public function test_get_role_returns_struct(): void {
        global $DB;
        $this->resetAfterTest();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $role = role_manager::get_role($managerid);
        $this->assertSame($managerid, $role['id']);
        $this->assertSame('manager', $role['shortname']);
        $this->assertSame('manager', $role['archetype']);
        $this->assertGreaterThan(0, $role['caps_total']);
        $this->assertGreaterThanOrEqual(0, $role['caps_allow']);
    }

    public function test_get_role_throws_on_missing_role(): void {
        $this->expectException(\moodle_exception::class);
        role_manager::get_role(999999);
    }

    public function test_get_role_caps_returns_paginated_list(): void {
        global $DB;
        $this->resetAfterTest();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        $page = role_manager::get_role_caps($managerid, '', 'all', 0, 25);
        $this->assertGreaterThan(0, $page['total']);
        $this->assertCount(25, $page['rows']);
        $this->assertSame(0, $page['page']);
        $this->assertSame(25, $page['perpage']);

        // Each row has the structural fields.
        foreach ($page['rows'] as $r) {
            $this->assertArrayHasKey('capability', $r);
            $this->assertArrayHasKey('permission', $r);
            $this->assertArrayHasKey('permission_label', $r);
        }
    }

    public function test_get_role_caps_perm_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        $allowed = role_manager::get_role_caps($managerid, '', 'allow', 0, 100);
        $this->assertGreaterThan(0, $allowed['total']);
        foreach ($allowed['rows'] as $r) {
            $this->assertSame(CAP_ALLOW, (int) $r['permission']);
        }
    }

    public function test_get_role_caps_search_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        // Search for a substring that appears in at least one cap. Stock
        // Moodle has multiple caps containing 'site:config' (e.g.
        // moodle/site:config, moodle/site:configview), so assert ≥ 1
        // and require every match to contain the needle.
        $page = role_manager::get_role_caps($managerid, 'site:config', 'all', 0, 50);
        $this->assertGreaterThanOrEqual(1, $page['total']);
        foreach ($page['rows'] as $row) {
            $this->assertStringContainsString('site:config', $row['capability']);
        }
        // Spot-check that the canonical cap is in there.
        $names = array_column($page['rows'], 'capability');
        $this->assertContains('moodle/site:config', $names);
    }

    public function test_update_capability_writes_audit_entry(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a custom role we can mutate without touching production roles.
        $roleid = create_role('Test Role', 'testrole', 'Custom role for testing');

        $before = $DB->count_records('local_airpay_roles_auditlog');
        $result = role_manager::update_capability($roleid, 'moodle/course:manageactivities',
            'allow', 'Granting for unit-test scenario');
        $after = $DB->count_records('local_airpay_roles_auditlog');

        $this->assertSame($before + 1, $after, 'exactly one audit row written');
        $this->assertSame(CAP_INHERIT, $result['oldpermission']);
        $this->assertSame(CAP_ALLOW,    $result['newpermission']);

        // The cap is actually applied at system context.
        $context = \context_system::instance();
        $perm = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => 'moodle/course:manageactivities',
             'contextid' => $context->id]);
        $this->assertSame((string) CAP_ALLOW, (string) $perm);
    }

    public function test_update_capability_audit_records_correct_old_value(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = create_role('Test Role 2', 'testrole2', '');

        // First mutation: inherit → allow.
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'allow');
        // Second mutation: allow → prevent. The audit entry MUST capture the
        // old value as ALLOW, not INHERIT.
        $result = role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'prevent');

        $this->assertSame(CAP_ALLOW,   $result['oldpermission']);
        $this->assertSame(CAP_PREVENT, $result['newpermission']);

        $entry = $DB->get_record('local_airpay_roles_auditlog', ['id' => $result['id']], '*', MUST_EXIST);
        $this->assertSame((string) CAP_ALLOW,   (string) $entry->oldpermission);
        $this->assertSame((string) CAP_PREVENT, (string) $entry->newpermission);
    }

    public function test_update_capability_action_unset_when_resetting_to_inherit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = create_role('Test Role 3', 'testrole3', '');
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'allow');
        $result = role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'inherit');

        $entry = $DB->get_record('local_airpay_roles_auditlog', ['id' => $result['id']], '*', MUST_EXIST);
        $this->assertSame('capability_unset', $entry->action);
    }

    public function test_update_capability_blocks_admin_lockout(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        $this->expectException(\moodle_exception::class);
        // Even though caller has manage cap, we refuse to remove
        // moodle/site:config from the manager archetype — that's how
        // admins lock themselves out of the site.
        role_manager::update_capability($managerid, 'moodle/site:config', 'prohibit');
    }

    public function test_update_capability_throws_on_unknown_role(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        role_manager::update_capability(999999, 'moodle/site:config', 'allow');
    }

    public function test_update_capability_throws_on_unknown_cap(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = create_role('Test Role 4', 'testrole4', '');
        $this->expectException(\moodle_exception::class);
        role_manager::update_capability($roleid, 'made/up:capability', 'allow');
    }

    public function test_update_capability_throws_on_invalid_permission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = create_role('Test Role 5', 'testrole5', '');
        $this->expectException(\invalid_parameter_exception::class);
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'superuser');
    }

    public function test_list_audit_returns_recent_first(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = create_role('Audit Test', 'audittest', '');
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'allow');
        role_manager::update_capability($roleid, 'moodle/course:create', 'prevent');

        $page = role_manager::list_audit(0, '', '', 0, 50);
        $this->assertGreaterThanOrEqual(2, $page['total']);

        // Most recent first.
        $first  = $page['rows'][0];
        $second = $page['rows'][1];
        $this->assertGreaterThanOrEqual($second['timecreated'], $first['timecreated']);
    }

    public function test_list_audit_filters_by_role(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $r1 = create_role('Audit R1', 'auditr1', '');
        $r2 = create_role('Audit R2', 'auditr2', '');
        role_manager::update_capability($r1, 'moodle/course:manageactivities', 'allow');
        role_manager::update_capability($r2, 'moodle/course:manageactivities', 'allow');

        $page = role_manager::list_audit($r1, '', '', 0, 50);
        $this->assertSame(1, $page['total']);
        $this->assertSame($r1, $page['rows'][0]['roleid']);
    }

    public function test_list_audit_filters_by_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = create_role('Audit R3', 'auditr3', '');
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'allow');
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'inherit');

        $set   = role_manager::list_audit($roleid, 'capability_set', '', 0, 50);
        $unset = role_manager::list_audit($roleid, 'capability_unset', '', 0, 50);

        $this->assertSame(1, $set['total']);
        $this->assertSame(1, $unset['total']);
    }

    public function test_list_audit_filters_by_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = create_role('Audit R4', 'auditr4', '');
        role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'allow');
        role_manager::update_capability($roleid, 'moodle/course:create', 'allow');

        $page = role_manager::list_audit($roleid, '', 'moodle/course:create', 0, 50);
        $this->assertSame(1, $page['total']);
        $this->assertSame('moodle/course:create', $page['rows'][0]['capability']);
    }

    public function test_csv_iterator_yields_header_then_rows(): void {
        $this->resetAfterTest();
        $iter = role_manager::csv_iterator();
        $first = $iter->current();
        $this->assertIsArray($first);
        $this->assertSame('Role ID', $first[0],
            'first yielded row must be the CSV header');
        $iter->next();
        // Second row (if any) must be a data row.
        if ($iter->valid()) {
            $second = $iter->current();
            $this->assertIsArray($second);
            $this->assertSame(7, count($second), 'each data row has 7 columns');
        }
    }

    public function test_audit_records_open_path_from_user(): void {
        global $DB, $USER;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        // Defensively check whether the field exists on the user table.
        // BizLMS production has it; stock Moodle test DB may not.
        if ($DB->get_manager()->field_exists('user',
                new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255'))) {
            $DB->set_field('user', 'open_path', '/1/2', ['id' => $u->id]);
            $u->open_path = '/1/2';
        }
        // Grant the user the manage cap so role_manager will accept their write.
        $context = \context_system::instance();
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $u->id, $context->id);
        $this->setUser($u);

        $roleid = create_role('Path Test', 'pathtest', '');
        $r = role_manager::update_capability($roleid, 'moodle/course:manageactivities', 'allow');

        $entry = $DB->get_record('local_airpay_roles_auditlog', ['id' => $r['id']], '*', MUST_EXIST);
        $this->assertSame((int) $u->id, (int) $entry->changedby);
        if (isset($u->open_path) && $u->open_path !== '') {
            $this->assertSame('/1/2', $entry->open_path);
        }
    }
}
