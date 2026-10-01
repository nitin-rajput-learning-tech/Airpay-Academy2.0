<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\importer as platform_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_roles\bizlms\importer as org_roles_importer;
use local_sentientia_roles\tests\bizlms\org_stub_importer;

/**
 * The org_roles importer (ADR-032, mapping doc section 4) under the importer contract and its own cases.
 *
 * The seed (seed_org_roles()) is a realistic little BizLMS database: four organisations (tenant 1: orgs 1 and 5;
 * tenant 77: org 6; org 7 is a junk row with no path), seventeen rows of local_costcenter_permissions and six of
 * local_org_dept_roles. Users: u1 to u4 and the actors 'actor' (/1) and 'actor2' (/1/5/99) are tenant 1; u5, u6 and
 * 'actor77' are tenant 77; 'floater' has no tenant path. Roles: manager and creator may be assigned at a course
 * category, teacher (editingteacher) may not. Numbers a test may rely on:
 *
 *  permissions  1  u1,u2,u3 manager @org1      imported: 3 assignments (primary + pos:2 + pos:3), 3 audit rows
 *               2  u4 creator @org5            imported; the actor's path walks up to /1/5
 *               3  roleid 0                    skipped no_role
 *               4  value 0                     archived value_not_assigned
 *               5  a deleted user              skipped no_valid_user (user_deleted)
 *               6  u1,u2 manager @org1         folded already_assigned (both exist after row 1)
 *               7  org 99 (no such org)        skipped org_not_found
 *               8  org 7 (a junk row)          skipped org_not_found (the org feature skipped it)
 *               9  user 999999                 skipped no_valid_user (user_not_found)
 *              10  u3, u3, abc creator @org5   imported once; duplicate_user and user_invalid warnings
 *              11  role 99999                  skipped role_not_found
 *              12  u6 manager @org6            folded: the assignment existed before the import
 *              13  u5 (tenant 77) @org1        skipped user_outside_org_tenant: no role over another tenant's org
 *              14  u4 manager @org1            imported; no timecreated, so the modified time stands in
 *              15  u5 (tenant 77), u2 @org1    imported for u2 only (list position 2); u5 is left out, warned
 *              16  u1 creator @org1, actor77   imported; the actor is in tenant 77, so the audit row has no path
 *              17  floater (no tenant) @org5   imported with the user_without_tenant warning
 *  dept roles   1  u4 creator @dept 5          folded (row 2 made it)
 *               2  u5 creator @org6            imported; times and the actor (actor2, tenant 1) come from the modified
 *                                              columns; the actor is outside org 6's tenant, so no audit path
 *               3  user 0                      skipped no_valid_user (user_invalid)
 *               4  org 7                       skipped org_not_found
 *               5  u6 creator @dept 6 of cc 1  imported; dept_outside_costcenter (org 6 is not under org 1), and the
 *                                              actor is outside org 6's tenant, so the audit row has no path
 *               6  u5 editingteacher @org6     skipped role_not_assignable (no course category level)
 *
 * That is 9 imported, 3 folded, 10 skipped and 1 archived primary rows; 11 assignments and 11 audit rows made.
 *
 * @package    local_sentientia_roles
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \local_sentientia_roles\bizlms\importer
 * @covers     \local_sentientia_roles\bizlms\assignment_step
 * @covers     \local_sentientia_roles\bizlms\permissions_step
 * @covers     \local_sentientia_roles\bizlms\dept_roles_step
 * @covers     \local_sentientia_roles\bizlms\org_contexts
 *
 * @group local_sentientia_roles
 * @group bizlms_import
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture {
        setUp as protected legacy_fixture_setup;
    }
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** Timestamp base of the seed. */
    private const T = 1700000000;

    /** @var int[] name => user id */
    private array $users = [];

    /** @var int[] organisation id => course category id */
    private array $cats = [];

    /** @var int[] name => role id */
    private array $roles = [];

    /** @var int The assignment that existed before the import (u6, manager, org 6). */
    private int $existingra = 0;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/costcenter.install.xml'];
    }

    protected function setUp(): void {
        $this->legacy_fixture_setup();
        // The audit row keeps the actor's open_path, which the phpunit user table does not have.
        $this->ensure_bizlms_schema();
    }

    // The contract.

    protected function contract_importer(): platform_importer {
        return new org_roles_importer();
    }

    /**
     * org_roles depends on the org feature, which the real org importer owns. This stands in for it.
     *
     * @return platform_importer
     */
    protected function contract_begin(): platform_importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new org_stub_importer(), $importer]);
        return $importer;
    }

    protected function contract_seed(): void {
        $this->seed_org_roles();
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array([org_roles_importer::DECISION_VALUE_FILTER => 'value_1_only']);
    }

    protected function contract_batch(): int {
        // The permissions step then spans four batches (17 rows), while the org stub's four rows fit in one, so
        // the contract's injected failure lands in a step of org_roles.
        return 5;
    }

    protected function contract_mutate_source(): void {
        global $DB;
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $DB->import_record('local_costcenter_permissions', (object) ['id' => 900, 'userid' => (string) $this->users['u1'],
            'costcenterid' => 1, 'roleid' => 0, 'value' => 1, 'timecreated' => self::T, 'timemodified' => self::T,
            'usermodified' => 0]);
    }

    protected function contract_user_columns(): array {
        return ['local_sentientia_roles_auditlog' => ['changedby', 'targetuserid']];
    }

    /**
     * The import's assignments are rows of a core table the framework does not clear, so they go by the map, and
     * the org feature's map rows (an import that "already ran") come back.
     *
     * @param platform_importer $importer
     * @return void
     */
    protected function contract_clear_import(platform_importer $importer): void {
        global $DB;
        $created = $DB->get_fieldset_select(legacymap::TABLE, 'targetid',
            "feature = :f AND targettable = 'role_assignments' AND outcome = 'imported'", ['f' => 'org_roles']);
        if ($created) {
            $DB->delete_records_list('role_assignments', 'id', $created);
        }
        foreach ($importer->target_tables() as $table) {
            $DB->delete_records($table);
        }
        $DB->delete_records(legacymap::TABLE);
        $DB->delete_records('local_sentientia_legacystep');
        $DB->delete_records('local_sentientia_legacyrun');
        foreach (['org_roles', 'org'] as $feature) {
            unset_config('bizlms_complete_' . $feature, 'local_sentientia_platform');
            unset_config('bizlms_tripped_' . $feature, 'local_sentientia_platform');
        }
        $this->seed_org_map();
    }

    /**
     * The contract's version of this test expects the framework tables to be empty after the crash. Here the org
     * feature's seeded map rows stay, and so does the assignment that existed before the import.
     */
    public function test_contract_feature_mode_reconciles_and_a_crash_leaves_nothing(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->assertTrue($importer->atomic());
        $this->contract_seed();
        $assignments = $DB->count_records('role_assignments');
        $seeded = $DB->count_records(legacymap::TABLE);
        $feature = ['atomic_threshold' => 50000];

        [$clean] = $this->contract_run(true, $feature);
        $this->assertContains($clean['exit'], [0, 2], implode('; ', $clean['blockers']));
        $expected = $this->contract_signature($importer);
        $this->contract_clear_import($importer);
        $this->assertSame($assignments, $DB->count_records('role_assignments'));

        $thrown = false;
        $failpoint = function (string $stepkey, int $batchno) use (&$thrown): void {
            if (!$thrown && $stepkey === 'org_roles.permissions' && $batchno === 2) {
                $thrown = true;
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->contract_run(true, $feature + ['failpoint' => $failpoint]);
        $this->assertTrue($thrown, 'the permissions step must span at least two batches');
        $this->assertSame(1, $failed['exit']);
        $this->assertSame(0, $DB->count_records('local_sentientia_roles_auditlog'), 'no audit row survives the crash');
        $this->assertSame($assignments, $DB->count_records('role_assignments'), 'no assignment survives the crash');
        $this->assertSame($seeded, $DB->count_records(legacymap::TABLE), 'no map row of org_roles survives the crash');
        $this->assertFalse(legacymap::feature_complete($importer->feature()));

        [$again] = $this->contract_run(true, $feature);
        $this->assertContains($again['exit'], [0, 2], implode('; ', $again['blockers']));
        $this->assertSame($expected, $this->contract_signature($importer), 'the rerun gives the rows of a clean run');
    }

    // The feature.

    public function test_the_importer_is_declared_the_way_the_map_says(): void {
        $importer = new org_roles_importer();
        $this->assertSame('org_roles', $importer->feature());
        $this->assertSame('local_sentientia_roles', $importer->component());
        $this->assertSame(['org'], $importer->depends());
        $this->assertTrue($importer->atomic());
        $this->assertSame(['local_costcenter_permissions', 'local_org_dept_roles'], array_keys($importer->sources()));
        $this->assertSame(['local_sentientia_roles_auditlog'], $importer->target_tables());
        $this->assertSame(['role_assignments'], array_keys($importer->core_writes()));
        $this->assertContains('insert', registry::core_write_operations('role_assignments'));
        $this->assertSame(['org_roles.permissions', 'org_roles.dept_roles'],
            array_map(static fn($step) => $step->key(), $importer->steps()));
        // The map's reasons, plus the two the review round added: a role across tenants and a role the category
        // may not hold. Both need the owner (parity exits 2 until the decisions file accepts them), like the two
        // that say data was lost (role_not_found, org_not_found).
        $codes = array_map(static fn($reason) => $reason->code, $importer->reasons());
        sort($codes);
        $this->assertSame(['already_assigned', 'no_role', 'no_valid_user', 'org_not_found', 'role_not_assignable',
            'role_not_found', 'user_outside_org_tenant', 'value_not_assigned'], $codes);
        $owner = [];
        foreach ($importer->reasons() as $reason) {
            if ($reason->needsowner) {
                $owner[] = $reason->code;
            }
        }
        sort($owner);
        $this->assertSame(['org_not_found', 'role_not_assignable', 'role_not_found', 'user_outside_org_tenant'], $owner);
    }

    public function test_the_registry_accepts_the_importer_with_its_org_dependency(): void {
        $this->contract_begin();
        $loaded = registry::load();
        $this->assertSame(['org', 'org_roles'], array_keys($loaded));
        $this->assertSame(['org', 'org_roles'], registry::sorted($loaded, ['org_roles']));
    }

    public function test_each_source_row_gets_the_documented_outcome(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $p = 'local_costcenter_permissions';
        $d = 'local_org_dept_roles';
        $expected = [
            [$p, 1, 'imported', '', 'role_assignments'],
            [$p, 2, 'imported', '', 'role_assignments'],
            [$p, 3, 'skipped', 'no_role', ''],
            [$p, 4, 'archived', 'value_not_assigned', ''],
            [$p, 5, 'skipped', 'no_valid_user', ''],
            [$p, 6, 'folded', 'already_assigned', 'role_assignments'],
            [$p, 7, 'skipped', 'org_not_found', ''],
            [$p, 8, 'skipped', 'org_not_found', ''],
            [$p, 9, 'skipped', 'no_valid_user', ''],
            [$p, 10, 'imported', '', 'role_assignments'],
            [$p, 11, 'skipped', 'role_not_found', ''],
            [$p, 12, 'folded', 'already_assigned', 'role_assignments'],
            [$p, 13, 'skipped', 'user_outside_org_tenant', ''],
            [$p, 14, 'imported', '', 'role_assignments'],
            [$p, 15, 'imported', '', 'role_assignments'],
            [$p, 16, 'imported', '', 'role_assignments'],
            [$p, 17, 'imported', '', 'role_assignments'],
            [$d, 1, 'folded', 'already_assigned', 'role_assignments'],
            [$d, 2, 'imported', '', 'role_assignments'],
            [$d, 3, 'skipped', 'no_valid_user', ''],
            [$d, 4, 'skipped', 'org_not_found', ''],
            [$d, 5, 'imported', '', 'role_assignments'],
            [$d, 6, 'skipped', 'role_not_assignable', ''],
        ];
        foreach ($expected as [$table, $id, $outcome, $reason, $target]) {
            $row = $this->map_row($table, $id);
            $this->assertSame($outcome, $row->outcome, "{$table} #{$id} outcome");
            $this->assertSame($reason, (string) $row->reason, "{$table} #{$id} reason");
            $this->assertSame($target, (string) $row->targettable, "{$table} #{$id} target table");
        }
        // Skips name their cause in codes.
        $this->assertSame('user_deleted', $this->map_row($p, 5)->detail);
        $this->assertSame('user_not_found', $this->map_row($p, 9)->detail);
        $this->assertSame('user_invalid', $this->map_row($d, 3)->detail);

        // Every source row has exactly one primary row; the fan-out rows are the assignments and audit rows.
        $this->assertSame(23, $DB->count_records(legacymap::TABLE, ['feature' => 'org_roles', 'subkey' => '']));
        // Eleven assignments: nine primary rows that imported one, and the fan-out rows pos:2 and pos:3.
        $this->assertSame(11, $DB->count_records(legacymap::TABLE,
            ['feature' => 'org_roles', 'targettable' => 'role_assignments', 'outcome' => 'imported']));
        $this->assertSame(11, $DB->count_records(legacymap::TABLE,
            ['feature' => 'org_roles', 'targettable' => 'local_sentientia_roles_auditlog', 'outcome' => 'imported']));
        $subkeys = $DB->get_fieldset_select(legacymap::TABLE, 'subkey', "feature = 'org_roles' AND subkey <> ''");
        sort($subkeys);
        // aud:2 is row 1's second user and row 15's only user (list position 2, because position 1 was left out).
        $this->assertSame(['aud:1', 'aud:1', 'aud:1', 'aud:1', 'aud:1', 'aud:1', 'aud:1', 'aud:1', 'aud:2', 'aud:2',
            'aud:3', 'pos:2', 'pos:3'], $subkeys);
        // The position in the list, never the user: no sub-key names a person.
        foreach ($subkeys as $subkey) {
            $this->assertMatchesRegularExpression('/^(pos|aud):[0-9]+$/', $subkey);
        }
    }

    public function test_assignments_are_direct_rows_at_the_organisation_category_with_source_fields(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $before = $DB->count_records('role_assignments');
        $contexts = $DB->count_records('context');
        $events = $this->redirectEvents();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertSame($before + 11, $DB->count_records('role_assignments'), 'eleven assignments made');
        $this->assertSame($contexts, $DB->count_records('context'), 'no context is created, for any organisation');
        $this->assertSame(0, $events->count(), 'no role_assigned event: the rows are inserted, not assigned');

        $ctx1 = \context_coursecat::instance($this->cats[1])->id;
        $ctx5 = \context_coursecat::instance($this->cats[5])->id;
        $ctx6 = \context_coursecat::instance($this->cats[6])->id;
        $t = self::T;
        // [permissions id, subkey, user, role, context, timemodified, modifier]
        $expected = [
            [1, '', 'u1', 'manager', $ctx1, $t + 1, 'actor'],
            [1, 'pos:2', 'u2', 'manager', $ctx1, $t + 1, 'actor'],
            [1, 'pos:3', 'u3', 'manager', $ctx1, $t + 1, 'actor'],
            [2, '', 'u4', 'creator', $ctx5, $t + 2, 'actor2'],
            [10, '', 'u3', 'creator', $ctx5, $t + 10, 'actor'],
            // No timecreated: the modified time stands in.
            [14, '', 'u4', 'manager', $ctx1, $t + 24, 'actor'],
            // The list names u5 first and u5 is left out: u2, at position 2, holds the primary assignment.
            [15, '', 'u2', 'creator', $ctx1, $t + 15, 'actor'],
            // The assignment keeps its actor, even though the actor is in another tenant (only the audit path goes).
            [16, '', 'u1', 'creator', $ctx1, $t + 16, 'actor77'],
            [17, '', 'floater', 'creator', $ctx5, $t + 17, 'actor'],
        ];
        foreach ($expected as [$id, $subkey, $user, $role, $contextid, $time, $modifier]) {
            $map = $this->map_row('local_costcenter_permissions', $id, $subkey);
            $this->assertSame('role_assignments', $map->targettable);
            $ra = $DB->get_record('role_assignments', ['id' => $map->targetid], '*', MUST_EXIST);
            $this->assertSame($this->users[$user], (int) $ra->userid, "permissions #{$id} {$subkey} user");
            $this->assertSame($this->roles[$role], (int) $ra->roleid);
            $this->assertSame($contextid, (int) $ra->contextid);
            $this->assertSame($time, (int) $ra->timemodified, 'the source time is kept');
            $this->assertSame($this->users[$modifier], (int) $ra->modifierid);
            $this->assertSame('', $ra->component, 'a manual assignment');
            $this->assertSame(0, (int) $ra->itemid);
        }
        // The department row: modified columns win, and it lands at the department (org 6 here).
        $map = $this->map_row('local_org_dept_roles', 2);
        $ra = $DB->get_record('role_assignments', ['id' => $map->targetid], '*', MUST_EXIST);
        $this->assertSame($this->users['u5'], (int) $ra->userid);
        $this->assertSame($this->roles['creator'], (int) $ra->roleid);
        $this->assertSame($ctx6, (int) $ra->contextid);
        $this->assertSame($t + 42, (int) $ra->timemodified);
        $this->assertSame($this->users['actor2'], (int) $ra->modifierid);
        // A department named under an organisation it is not in: the department's own tenant counts, and it is reported.
        $map = $this->map_row('local_org_dept_roles', 5);
        $ra = $DB->get_record('role_assignments', ['id' => $map->targetid], '*', MUST_EXIST);
        $this->assertSame($this->users['u6'], (int) $ra->userid);
        $this->assertSame($this->roles['creator'], (int) $ra->roleid);
        $this->assertSame($ctx6, (int) $ra->contextid);
        $this->assertSame($t + 35, (int) $ra->timemodified);
        $this->assertSame($this->users['actor'], (int) $ra->modifierid);

        // Nothing for a user of another tenant: row 13 (tenant 77 user at a tenant 1 organisation) made no assignment
        // and no audit row, and neither did the u5 of row 15. u5's only assignment is the one at their own tenant.
        foreach ([$ctx1, $ctx5] as $foreign) {
            $this->assertFalse($DB->record_exists('role_assignments', ['userid' => $this->users['u5'], 'contextid' => $foreign]),
                'no role over the organisation of another tenant');
            $this->assertFalse($DB->record_exists('local_sentientia_roles_auditlog',
                ['targetuserid' => $this->users['u5'], 'contextid' => $foreign]));
        }
        $this->assertSame(1, $DB->count_records('role_assignments', ['userid' => $this->users['u5'],
            'contextid' => $ctx6]), 'u5 holds exactly one role at the organisation of their own tenant');
        // A role the category may not hold is not assigned there (dept row 6).
        $this->assertFalse($DB->record_exists('role_assignments', ['roleid' => $this->roles['teacher'],
            'contextid' => $ctx6]));

        // Folded rows point at the assignment that already existed, and none was duplicated.
        $this->assertSame($this->existingra, (int) $this->map_row('local_costcenter_permissions', 12)->targetid);
        $this->assertSame(1, $DB->count_records('role_assignments', ['roleid' => $this->roles['manager'],
            'contextid' => $ctx6, 'userid' => $this->users['u6']]));
        $this->assertSame((int) $this->map_row('local_costcenter_permissions', 1)->targetid,
            (int) $this->map_row('local_costcenter_permissions', 6)->targetid, 'row 6 folds into what row 1 made');
        $this->assertSame((int) $this->map_row('local_costcenter_permissions', 2)->targetid,
            (int) $this->map_row('local_org_dept_roles', 1)->targetid, 'the department row folds into what row 2 made');
        $this->assertSame(1, $DB->count_records('role_assignments', ['roleid' => $this->roles['manager'],
            'contextid' => $ctx1, 'userid' => $this->users['u1']]));
    }

    public function test_one_audit_row_per_assignment_the_import_made(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertSame(11, $DB->count_records('local_sentientia_roles_auditlog'));
        $ctx1 =\context_coursecat::instance($this->cats[1])->id;
        $ctx6 = \context_coursecat::instance($this->cats[6])->id;
        $t = self::T;

        $audit = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $this->map_row('local_costcenter_permissions', 1,
            'aud:2')->targetid], '*', MUST_EXIST);
        $this->assertSame('role_assigned', $audit->action);
        $this->assertSame($this->roles['manager'], (int) $audit->roleid);
        $this->assertSame('manager', $audit->roleshortname);
        $this->assertSame($ctx1, (int) $audit->contextid);
        $this->assertSame($this->users['u2'], (int) $audit->targetuserid);
        $this->assertSame($this->users['actor'], (int) $audit->changedby);
        $this->assertSame('bizlms_import:costcenter_permissions', $audit->reason);
        $this->assertNull($audit->capability);
        $this->assertNull($audit->oldpermission);
        $this->assertNull($audit->newpermission);
        $this->assertSame($t + 1, (int) $audit->timecreated, 'the time of the assignment, not of the import');
        // The actor's own path is on the row, because the audit list is scoped by it.
        $this->assertSame('/1', $audit->open_path);

        // An actor whose path is deeper than any organisation: the nearest organisation above stands in for it.
        $audit = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $this->map_row('local_costcenter_permissions', 2,
            'aud:1')->targetid], '*', MUST_EXIST);
        $this->assertSame('/1/5', $audit->open_path);
        $this->assertSame($this->users['actor2'], (int) $audit->changedby);

        // The department row: timecreated is the assignment time, and the modifier beats the creator.
        $audit = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $this->map_row('local_org_dept_roles', 2,
            'aud:1')->targetid], '*', MUST_EXIST);
        $this->assertSame('bizlms_import:org_dept_roles', $audit->reason);
        $this->assertSame($ctx6, (int) $audit->contextid);
        $this->assertSame($this->roles['creator'], (int) $audit->roleid);
        $this->assertSame('coursecreator', $audit->roleshortname);
        $this->assertSame($t + 32, (int) $audit->timecreated);
        $this->assertSame($this->users['actor2'], (int) $audit->changedby);
        // actor2 belongs to tenant 1 and organisation 6 to tenant 77: the actor is still named, but the row carries no
        // path, or the audit list would show a tenant-77 assignment to tenant-1 administrators.
        $this->assertNull($audit->open_path);

        // Row 16 has an actor of another tenant too (tenant 77 at a tenant-1 organisation), and so does dept row 5.
        foreach ([['local_costcenter_permissions', 16, 'actor77'], ['local_org_dept_roles', 5, 'actor']] as [$table, $id, $who]) {
            $audit = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $this->map_row($table, $id,
                'aud:1')->targetid], '*', MUST_EXIST);
            $this->assertNull($audit->open_path, "{$table} #{$id}: no path for an actor outside the organisation's tenant");
            $this->assertSame($this->users[$who], (int) $audit->changedby);
        }
        // A target without a tenant changes nothing about the actor's path (row 17: the floater is the target).
        $audit = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $this->map_row('local_costcenter_permissions', 17,
            'aud:1')->targetid], '*', MUST_EXIST);
        $this->assertSame('/1', $audit->open_path);
        $this->assertSame($this->users['floater'], (int) $audit->targetuserid);

        // A folded row made nothing, so it has no audit row.
        $this->assertFalse($DB->record_exists(legacymap::TABLE, ['feature' => 'org_roles',
            'sourcetable' => 'local_costcenter_permissions', 'sourceid' => 6, 'subkey' => 'aud:1']));
        // Nothing is invented for the native audit log either: the import adds only role_assigned rows.
        $this->assertSame(11, $DB->count_records('local_sentientia_roles_auditlog', ['action' => 'role_assigned']));
    }

    /**
     * @group tenant_isolation
     */
    public function test_the_tenant_path_of_an_audit_row_is_the_actors_and_a_cross_tenant_grant_is_refused(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // Every path the import wrote is a normalised path with a registered root (the importer's verify() says so too).
        // The rows whose actor is outside the organisation's tenant carry none (NULL, never an empty string).
        $paths = $DB->get_fieldset_select('local_sentientia_roles_auditlog', 'DISTINCT open_path', 'open_path IS NOT NULL');
        sort($paths);
        $this->assertSame(['/1', '/1/5'], $paths);
        $this->assertSame(3, $DB->count_records_select('local_sentientia_roles_auditlog', 'open_path IS NULL'),
            'permissions row 16, dept rows 2 and 5: an actor outside the organisation\'s tenant');

        $steps = $report->to_array()['features']['org_roles']['steps'];
        $permissions = $steps['org_roles.permissions'];
        // Rows 13 and 15 name a tenant-77 user at a tenant-1 organisation: row 13 has nobody left and is skipped, row 15
        // is imported for its other user. Both are reported.
        $this->assertSame(2, $permissions['warnings']['user_outside_org_tenant'] ?? 0);
        $this->assertSame(1, $permissions['skipped_by_reason']['user_outside_org_tenant'] ?? 0);
        $this->assertSame(1, $permissions['warnings']['duplicate_user'] ?? 0);
        $this->assertSame(1, $permissions['warnings']['user_invalid'] ?? 0);
        $this->assertSame(1, $permissions['warnings']['assignment_exists'] ?? 0);
        $this->assertSame(1, $permissions['warnings']['user_without_tenant'] ?? 0);
        $this->assertSame(1, $permissions['warnings']['actor_outside_org_tenant'] ?? 0);
        $this->assertSame(7, $permissions['tenant_methods']['exact'] ?? 0);
        $this->assertSame(1, $permissions['tenant_methods']['walked_up'] ?? 0);
        $this->assertSame(1, $permissions['tenant_methods']['unresolved'] ?? 0, 'row 16: its actor is in another tenant');

        $dept = $steps['org_roles.dept_roles'];
        $this->assertSame(1, $dept['warnings']['dept_outside_costcenter'] ?? 0);
        $this->assertSame(2, $dept['warnings']['actor_outside_org_tenant'] ?? 0);
        $this->assertSame(1, $dept['skipped_by_reason']['role_not_assignable'] ?? 0);
        $this->assertSame(1, $dept['tenant_methods']['exact'] ?? 0);
        $this->assertSame(2, $dept['tenant_methods']['unresolved'] ?? 0);
    }

    /**
     * The rule itself, over every assignment the import made: whoever holds a role at an organisation's category has
     * that organisation's tenant (a user with no tenant path is the one exception, and is reported). The grant is
     * authority over every course below the category, so a user of another tenant must never hold it.
     *
     * @group tenant_isolation
     */
    public function test_no_imported_assignment_gives_a_user_a_role_over_another_tenants_organisation(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $roots = [
            \context_coursecat::instance($this->cats[1])->id => 1,
            \context_coursecat::instance($this->cats[5])->id => 1,
            \context_coursecat::instance($this->cats[6])->id => 77,
        ];
        $maps = $DB->get_records(legacymap::TABLE, ['feature' => 'org_roles', 'targettable' => 'role_assignments',
            'outcome' => 'imported']);
        $this->assertCount(11, $maps);
        $checked = 0;
        foreach ($maps as $map) {
            $ra = $DB->get_record('role_assignments', ['id' => $map->targetid], '*', MUST_EXIST);
            $path = (string) $DB->get_field('user', 'open_path', ['id' => $ra->userid]);
            if ($path === '') {
                $this->assertSame($this->users['floater'], (int) $ra->userid, 'only the floater has no tenant path');
                continue;
            }
            $this->assertSame($roots[(int) $ra->contextid], (int) explode('/', ltrim($path, '/'))[0],
                "assignment {$ra->id} of {$map->sourcetable} #{$map->sourceid}");
            $checked++;
        }
        $this->assertSame(10, $checked);

        // The two rows that named a user of another tenant: row 13 is skipped with the owner-visible reason, row 15 is
        // imported for u2 only.
        $row13 = $this->map_row('local_costcenter_permissions', 13);
        $this->assertSame('skipped', $row13->outcome);
        $this->assertSame('user_outside_org_tenant', $row13->reason);
        $this->assertSame('', (string) $row13->targettable);
        $row15 = $this->map_row('local_costcenter_permissions', 15);
        $this->assertSame('imported', $row15->outcome);
        $ra = $DB->get_record('role_assignments', ['id' => $row15->targetid], '*', MUST_EXIST);
        $this->assertSame($this->users['u2'], (int) $ra->userid);
        $this->assertFalse($DB->record_exists(legacymap::TABLE, ['feature' => 'org_roles',
            'sourcetable' => 'local_costcenter_permissions', 'sourceid' => 15, 'subkey' => 'aud:1']),
            'there is no audit row for the user who was left out');
    }

    /**
     * @group tenant_isolation
     */
    public function test_an_organisation_with_no_path_refuses_every_user_even_when_the_org_map_resolves_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // An org map that (unlike the seeded one) says org 7 imported: the tenant rule must not depend on that.
        $DB->set_field(legacymap::TABLE, 'targettable', 'local_sentientia_org',
            ['feature' => 'org', 'sourcetable' => 'local_costcenter', 'sourceid' => 7]);
        $DB->set_field(legacymap::TABLE, 'targetid', 7,
            ['feature' => 'org', 'sourcetable' => 'local_costcenter', 'sourceid' => 7]);
        $DB->set_field(legacymap::TABLE, 'outcome', 'imported',
            ['feature' => 'org', 'sourcetable' => 'local_costcenter', 'sourceid' => 7]);
        $DB->set_field(legacymap::TABLE, 'reason', null,
            ['feature' => 'org', 'sourcetable' => 'local_costcenter', 'sourceid' => 7]);

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $ctx7 = \context_coursecat::instance($this->cats[7])->id;
        foreach ([['local_costcenter_permissions', 8], ['local_org_dept_roles', 4]] as [$table, $id]) {
            $row = $this->map_row($table, $id);
            $this->assertSame('skipped', $row->outcome, "{$table} #{$id}");
            $this->assertSame('org_not_found', $row->reason);
            $this->assertSame('org_without_path', $row->detail);
        }
        $this->assertFalse($DB->record_exists('role_assignments', ['contextid' => $ctx7]), 'nothing at the junk organisation');
    }

    public function test_verify_passes_on_a_clean_import(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame([], $importer->verify(context::build($importer, false, 0, $this->contract_decisions())));
    }

    public function test_verify_reports_an_assignment_that_was_removed_afterwards(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $DB->delete_records('role_assignments', ['id' => $this->map_row('local_costcenter_permissions', 1, 'pos:2')->targetid]);
        $failures = $importer->verify(context::build($importer, false, 0, $this->contract_decisions()));
        // The map still names eleven assignments and eleven audit rows, so only the missing row is reported.
        $this->assertSame(['role_assignment_missing:1'], $failures);
    }

    public function test_verify_reports_a_path_that_is_not_normalised(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $audit = $this->map_row('local_costcenter_permissions', 1, 'aud:1');
        $DB->set_field('local_sentientia_roles_auditlog', 'open_path', '//1//5', ['id' => $audit->targetid]);
        $failures = $importer->verify(context::build($importer, false, 0, $this->contract_decisions()));
        $this->assertSame(['invalid_tenant_value:local_sentientia_roles_auditlog.open_path rows=1'], $failures);
    }

    public function test_native_audit_rows_with_an_empty_path_do_not_fail_verify(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // What the role UI writes for a site admin, who has no tenant path.
        $DB->insert_record('local_sentientia_roles_auditlog', (object) ['roleid' => $this->roles['manager'],
            'roleshortname' => 'manager', 'action' => 'role_assigned', 'contextid' => 1, 'targetuserid' => $this->users['u1'],
            'changedby' => $this->users['actor'], 'open_path' => '', 'timecreated' => self::T]);
        $this->assertSame([], $importer->verify(context::build($importer, false, 0, $this->contract_decisions())));
    }

    public function test_the_privacy_provider_reaches_the_imported_audit_rows(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // The import put audit rows at the category contexts; the provider finds the person there too.
        $ctx1 = \context_coursecat::instance($this->cats[1])->id;
        $ctx5 = \context_coursecat::instance($this->cats[5])->id;
        $subject = $DB->get_record('user', ['id' => $this->users['u2']], '*', MUST_EXIST);
        $contexts = privacy\provider::get_contexts_for_userid((int) $subject->id);
        $this->assertContains($ctx1, array_map('intval', $contexts->get_contextids()));
        $actor = privacy\provider::get_contexts_for_userid($this->users['actor']);
        $this->assertContains($ctx1, array_map('intval', $actor->get_contextids()));
        $this->assertContains($ctx5, array_map('intval', $actor->get_contextids()));

        // Erasing the subject keeps the row and removes the person from it (audit retention).
        $auditid = (int) $this->map_row('local_costcenter_permissions', 1, 'aud:2')->targetid;
        privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($subject, 'local_sentientia_roles', [$ctx1]));
        $row = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $auditid], '*', MUST_EXIST);
        $this->assertNull($row->targetuserid);
        $this->assertSame($this->users['actor'], (int) $row->changedby, 'the actor is not the person being erased');
        $this->assertSame('role_assigned', $row->action);
    }

    public function test_finalise_marks_the_touched_contexts_dirty(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->delete_records('cache_flags', ['flagtype' => 'accesslib/dirtycontexts']);
        $DB->delete_records('cache_flags', ['flagtype' => 'accesslib/dirtyusers']);
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // The assignments were inserted, not assigned, so the users' cached access must be told to reload.
        foreach ([1, 5, 6] as $org) {
            $path = \context_coursecat::instance($this->cats[$org])->path;
            $this->assertTrue($DB->record_exists('cache_flags', ['flagtype' => 'accesslib/dirtycontexts', 'name' => $path]),
                "organisation {$org}'s category context is marked dirty");
        }
        // A context flag reloads access for checks at or below the category only. role_assign() also marks the user dirty,
        // so a live session's system-level checks reload too: every user who received an assignment is marked.
        foreach (['u1', 'u2', 'u3', 'u4', 'u5', 'u6', 'floater'] as $name) {
            $this->assertTrue($DB->record_exists('cache_flags', ['flagtype' => 'accesslib/dirtyusers',
                'name' => (string) $this->users[$name]]), "{$name} received an assignment and is marked dirty");
        }
        // Nobody else is: not the actors, not the deleted user, not the user who was refused (their row was skipped).
        foreach (['actor', 'actor2', 'actor77', 'gone'] as $name) {
            $this->assertFalse($DB->record_exists('cache_flags', ['flagtype' => 'accesslib/dirtyusers',
                'name' => (string) $this->users[$name]]), "{$name} received nothing and is not marked dirty");
        }
    }

    public function test_preflight_counts_and_warns_but_does_not_block_a_good_seed(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $pf = $this->preflight_of('org_roles');
        $this->assertSame([], $pf->blockers());
        // Fifteen permission rows with a role and value 1 (ids 1, 2, 5 to 17), and all six department rows.
        $this->assertSame(21, $pf->counts()['org_roles.assignment_rows']);
        $this->assertSame(5, $pf->counts()['org_roles.organisations']);
        $warnings = implode(' | ', $pf->warnings());
        $this->assertStringContainsString('org_not_found:1', $warnings, 'organisation 99 has no local_costcenter row');
        // Org 7 is a local_costcenter row with no normalised path: reported, and it cannot block (nothing could clear it).
        $this->assertStringContainsString('org_without_path:1 ids=7', $warnings);
        // Rows 13 and 15 name u5 (tenant 77) at a tenant-1 organisation; the floater has no tenant and is not counted.
        $this->assertStringContainsString('user_outside_org_tenant:2', $warnings);
        // editingteacher is not assignable at a category, and role 99999 does not exist: both are reported.
        $this->assertStringContainsString('role_not_assignable_at_category:2', $warnings);
        $this->assertStringContainsString((string) $this->roles['teacher'], $warnings);
        $this->assertSame(['org'], $importer->depends());
    }

    public function test_an_organisation_with_no_path_is_a_warning_not_a_blocker_even_when_it_has_no_category(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // The legacy table can never be edited to clear this, so a junk row must not block the whole feature.
        $DB->set_field('local_costcenter', 'category', 0, ['id' => 7]);
        $pf = $this->preflight_of('org_roles');
        $this->assertSame([], $pf->blockers());
        $this->assertStringContainsString('org_without_path:1 ids=7', implode(' | ', $pf->warnings()));
    }

    public function test_a_missing_category_context_blocks_and_is_never_created(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->delete_records('context', ['contextlevel' => CONTEXT_COURSECAT, 'instanceid' => $this->cats[5]]);
        $contexts = $DB->count_records('context');
        $assignments = $DB->count_records('role_assignments');

        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('org_roles: org_context_missing:1 ids=5', implode(' ', $result['blockers']));
        $this->assertSame($contexts, $DB->count_records('context'), 'context_coursecat::instance() was not called: nothing created');
        $this->assertSame($assignments, $DB->count_records('role_assignments'), 'a blocked run writes nothing');
        $this->assertSame(0, $DB->count_records(legacymap::TABLE, ['feature' => 'org_roles']));
        $this->assertFalse(legacymap::feature_complete('org_roles'));
    }

    public function test_an_organisation_without_a_category_blocks(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_costcenter', 'category', 0, ['id' => 6]);

        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('org_roles: org_without_category:1 ids=6', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE, ['feature' => 'org_roles']));
    }

    public function test_an_unknown_value_blocks_until_the_owner_maps_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_costcenter_permissions', 'value', 2, ['id' => 4]);

        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_costcenter_permissions.value=2', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE, ['feature' => 'org_roles']));
    }

    public function test_the_value_filter_decision_is_required_and_only_value_1_is_supported(): void {
        $this->contract_begin();
        $this->contract_seed();

        $runner = new runner(['decisions' => decisions::none(), 'report' => new report(), 'batch' => 5]);
        $blockers = $runner->preflight([])['preflights']['org_roles']->blockers();
        $this->assertContains('missing_decision:org_roles.value_filter', $blockers);

        $runner = new runner(['decisions' => decisions::from_array([org_roles_importer::DECISION_VALUE_FILTER => 'everything']),
            'report' => new report(), 'batch' => 5]);
        $blockers = $runner->preflight([])['preflights']['org_roles']->blockers();
        $this->assertContains('decision_value_not_allowed:org_roles.value_filter', $blockers);
    }

    public function test_no_org_source_table_with_assignment_rows_blocks(): void {
        $this->contract_begin();
        $this->contract_seed();
        self::drop_legacy_table('local_costcenter');

        $blockers = $this->preflight_of('org_roles')->blockers();
        $this->assertContains('org_source_missing:local_costcenter', $blockers);
    }

    public function test_only_one_of_the_two_tables_is_enough(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        self::drop_legacy_table('local_org_dept_roles');

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['org_roles']);
        $this->assertSame(17, $DB->count_records(legacymap::TABLE, ['feature' => 'org_roles',
            'sourcetable' => 'local_costcenter_permissions', 'subkey' => '']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE, ['feature' => 'org_roles',
            'sourcetable' => 'local_org_dept_roles']));
    }

    public function test_a_dry_run_of_the_feature_alone_reports_the_org_as_deferred_not_failed(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // The org feature's map is what a dependency leaves; take it away to see a single-feature dry run without it.
        $DB->delete_records(legacymap::TABLE, ['feature' => 'org']);
        $report = new report();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => $report, 'batch' => 5]);
        $result = $runner->run(['org_roles']);

        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $reasons = $report->to_array()['features']['org_roles']['steps']['org_roles.permissions']['skipped_by_reason'] ?? [];
        $this->assertGreaterThan(0, $reasons['deferred'] ?? 0, 'rows that need an org are deferred in a one-feature dry run');
        $this->assertSame(0, $reasons['org_not_found'] ?? 0);
    }

    /**
     * The contract tests clear the import between two runs; this class's version of that helper must leave the
     * org feature's map rows and the assignment that predates the import, or the second run would differ.
     */
    public function test_the_clear_import_helper_keeps_what_the_org_feature_and_the_site_left(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $this->contract_clear_import($importer);
        $this->assertSame(4, $DB->count_records(legacymap::TABLE), 'the four org rows, nothing of org_roles');
        $this->assertSame(0, $DB->count_records('local_sentientia_roles_auditlog'));
        $this->assertSame(1, $DB->count_records('role_assignments', ['id' => $this->existingra]), 'the assignment that predates the import stays');
    }

    // Seed and helpers.

    /**
     * The little BizLMS database described at the top of the class.
     *
     * @return void
     */
    private function seed_org_roles(): void {
        global $DB;
        $t = self::T;
        $gen = $this->getDataGenerator();

        foreach (['manager' => 'manager', 'creator' => 'coursecreator', 'teacher' => 'editingteacher'] as $name => $shortname) {
            $this->roles[$name] = (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
        }

        // Users: u1 to u4 belong to tenant 1, u5 and u6 to tenant 77; the actors made the assignments in BizLMS (actor77
        // is a tenant-77 actor, which the native UI would never let assign at a tenant-1 organisation). The floater has
        // no tenant path at all.
        $paths = ['u1' => '/1', 'u2' => '/1', 'u3' => '/1', 'u4' => '/1', 'u5' => '/77', 'u6' => '/77',
            'actor' => '/1', 'actor2' => '/1/5/99', 'actor77' => '/77', 'floater' => ''];
        foreach ($paths as $name => $path) {
            $user = $gen->create_user();
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
            $this->users[$name] = (int) $user->id;
        }
        $gone = $gen->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $this->users['gone'] = (int) $gone->id;

        // Organisations: four local_costcenter rows, each with a course category (org 7 is the junk row the org
        // feature skips), and the local_sentientia_org rows an earlier org import left.
        $DB->delete_records('local_sentientia_org');
        $orgs = [
            1 => ['Airpay', 'airpay', 0, '/1', 1],
            5 => ['Airpay Payments', 'payments', 1, '/1/5', 2],
            6 => ['Public', 'public', 0, '/77', 1],
            7 => ['Junk row', 'junk', 0, null, null],
        ];
        foreach ($orgs as $id => [$fullname, $shortname, $parentid, $path, $depth]) {
            $this->cats[$id] = (int) $gen->create_category(['name' => 'Org ' . $id])->id;
            $DB->import_record('local_costcenter', (object) ['id' => $id, 'fullname' => $fullname, 'shortname' => $shortname,
                'parentid' => $parentid, 'visible' => 1, 'timecreated' => $t, 'timemodified' => $t, 'usermodified' => 0,
                'path' => $path, 'depth' => $depth, 'category' => $this->cats[$id]]);
            if ($path !== null) {
                $DB->import_record('local_sentientia_org', (object) ['id' => $id, 'fullname' => $fullname,
                    'shortname' => $shortname, 'parentid' => $parentid, 'path' => $path, 'depth' => $depth, 'visible' => 1,
                    'sortorder' => 0, 'timecreated' => $t, 'timemodified' => $t]);
            }
        }
        $this->seed_org_map();

        // An assignment that exists before the import: u6 is manager at org 6.
        $this->existingra = (int) role_assign($this->roles['manager'], $this->users['u6'],
            \context_coursecat::instance($this->cats[6])->id);

        $u = $this->users;
        $r = $this->roles;
        $permission = static function (int $id, string $users, int $org, int $role, int $value, int $created, int $modified,
                int $by) use ($DB): void {
            $DB->import_record('local_costcenter_permissions', (object) ['id' => $id, 'userid' => $users,
                'costcenterid' => $org, 'roleid' => $role, 'value' => $value, 'timecreated' => $created,
                'timemodified' => $modified, 'usermodified' => $by]);
        };
        $permission(1, "{$u['u1']},{$u['u2']},{$u['u3']}", 1, $r['manager'], 1, $t + 1, $t + 11, $u['actor']);
        $permission(2, (string) $u['u4'], 5, $r['creator'], 1, $t + 2, $t + 12, $u['actor2']);
        $permission(3, (string) $u['u5'], 1, 0, 1, $t + 3, $t + 13, $u['actor']);
        $permission(4, (string) $u['u1'], 1, $r['manager'], 0, $t + 4, $t + 14, $u['actor']);
        $permission(5, (string) $u['gone'], 6, $r['manager'], 1, $t + 5, $t + 15, $u['actor']);
        $permission(6, "{$u['u1']},{$u['u2']}", 1, $r['manager'], 1, $t + 6, $t + 16, $u['actor']);
        $permission(7, (string) $u['u6'], 99, $r['manager'], 1, $t + 7, $t + 17, $u['actor']);
        $permission(8, (string) $u['u2'], 7, $r['manager'], 1, $t + 8, $t + 18, $u['actor']);
        $permission(9, '999999', 1, $r['manager'], 1, $t + 9, $t + 19, $u['actor']);
        $permission(10, "{$u['u3']}, {$u['u3']},abc", 5, $r['creator'], 1, $t + 10, $t + 20, $u['actor']);
        $permission(11, (string) $u['u1'], 1, 99999, 1, $t + 11, $t + 21, $u['actor']);
        $permission(12, (string) $u['u6'], 6, $r['manager'], 1, $t + 12, $t + 22, $u['actor']);
        $permission(13, (string) $u['u5'], 1, $r['manager'], 1, 0, $t + 23, $u['actor']);
        $permission(14, (string) $u['u4'], 1, $r['manager'], 1, 0, $t + 24, $u['actor']);
        $permission(15, "{$u['u5']},{$u['u2']}", 1, $r['creator'], 1, $t + 15, $t + 25, $u['actor']);
        $permission(16, (string) $u['u1'], 1, $r['creator'], 1, $t + 16, $t + 26, $u['actor77']);
        $permission(17, (string) $u['floater'], 5, $r['creator'], 1, $t + 17, $t + 27, $u['actor']);

        $dept = static function (int $id, int $org, int $department, int $user, int $role, int $created, int $modified,
                int $timemodified, int $timecreated) use ($DB): void {
            $DB->import_record('local_org_dept_roles', (object) ['id' => $id, 'costcenterid' => $org,
                'departmentid' => $department, 'userid' => $user, 'roleid' => $role, 'user_created' => $created,
                'user_modified' => $modified, 'timemodified' => $timemodified, 'timecreated' => $timecreated]);
        };
        $dept(1, 1, 5, $u['u4'], $r['creator'], $u['actor'], 0, 0, $t + 31);
        $dept(2, 6, 0, $u['u5'], $r['creator'], $u['actor'], $u['actor2'], $t + 42, $t + 32);
        $dept(3, 1, 0, 0, $r['manager'], $u['actor'], 0, 0, $t + 33);
        $dept(4, 7, 0, $u['u1'], $r['manager'], $u['actor'], 0, 0, $t + 34);
        // Department 6 (org 6, tenant 77) said to live under cost centre 1 (tenant 1): reported, the department counts.
        $dept(5, 1, 6, $u['u6'], $r['creator'], $u['actor'], 0, 0, $t + 35);
        // A role the category may not hold (editingteacher has no course category level).
        $dept(6, 6, 0, $u['u5'], $r['teacher'], $u['actor'], 0, 0, $t + 36);
    }

    /**
     * One map row per local_costcenter row, as the org feature leaves them: ids kept, and the junk row skipped.
     *
     * @return void
     */
    private function seed_org_map(): void {
        global $DB;
        $rows = [];
        foreach ([1, 5, 6] as $id) {
            $rows[] = (object) ['feature' => 'org', 'sourcetable' => 'local_costcenter', 'sourceid' => $id, 'subkey' => '',
                'targettable' => 'local_sentientia_org', 'targetid' => $id, 'outcome' => 'imported', 'reason' => null,
                'detail' => null, 'runid' => 1, 'timecreated' => self::T];
        }
        $rows[] = (object) ['feature' => 'org', 'sourcetable' => 'local_costcenter', 'sourceid' => 7, 'subkey' => '',
            'targettable' => '', 'targetid' => null, 'outcome' => 'skipped', 'reason' => 'not_an_org_row',
            'detail' => null, 'runid' => 1, 'timecreated' => self::T];
        $DB->insert_records(legacymap::TABLE, $rows);
    }

    /**
     * The map row of one source row of this feature.
     *
     * @param string $table
     * @param int $id
     * @param string $subkey
     * @return \stdClass
     */
    private function map_row(string $table, int $id, string $subkey = ''): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['feature' => 'org_roles', 'sourcetable' => $table, 'sourceid' => $id,
            'subkey' => $subkey], '*', MUST_EXIST);
    }

    /**
     * The preflight of one feature, without running anything.
     *
     * @param string $feature
     * @return \local_sentientia_platform\bizlms\preflight
     */
    private function preflight_of(string $feature): \local_sentientia_platform\bizlms\preflight {
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report(), 'batch' => 5]);
        return $runner->preflight([])['preflights'][$feature];
    }
}
