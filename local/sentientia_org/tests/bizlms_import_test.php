<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\bizlms\costcenter_step;
use local_sentientia_org\bizlms\importer;
use local_sentientia_org\bizlms\org_source;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The org feature of the BizLMS import (ADR-032, mapping doc section 3): local_costcenter -> local_sentientia_org.
 *
 * The importer contract (tests/bizlms of local_sentientia_platform, "Test approach" 4) runs against a seed that
 * looks like production: three tenants, departments two and three deep, sibling order that differs from id order,
 * a padded path, a long shortname, a missing depth and name, a junk row that holds only multipleorg, and a root
 * that is not a registered tenant. The tests after it check the column map, the reasons, the owner's decision,
 * the logo copy, tenant isolation for a tenant admin, and the two reader fixes that ship with the importer.
 *
 * @package    local_sentientia_org
 * @category   test
 * @covers     \local_sentientia_org\bizlms\importer
 * @covers     \local_sentientia_org\bizlms\costcenter_step
 * @covers     \local_sentientia_org\bizlms\org_source
 *
 * @group local_sentientia_org
 * @group bizlms_import
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed. */
    private const T0 = 1600000000;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/costcenter.install.xml'];
    }

    protected function contract_importer(): framework_importer {
        return new importer();
    }

    /**
     * The owner's choices. The real file carries org.unmapped_columns; accepted_reasons is what Nitin adds after
     * the rehearsal, so the contract runs with the two needs-owner reasons accepted and exits 0.
     *
     * @return decisions
     */
    protected function contract_decisions(): decisions {
        return decisions::from_array([
            'org.unmapped_columns' => 'not_copied',
            'accepted_reasons' => ['org:invalid_tenant_root', 'org:unmapped_enum'],
        ]);
    }

    /**
     * Insert one local_costcenter row with BizLMS's own defaults for what the caller leaves out.
     *
     * @param int $id
     * @param array<string, mixed> $values
     * @return void
     */
    private function put(int $id, array $values): void {
        global $DB;
        $DB->import_record('local_costcenter', (object) ($values + [
            'id' => $id, 'fullname' => null, 'shortname' => null, 'parentid' => 0, 'description' => null,
            'visible' => 1, 'timecreated' => self::T0 + $id, 'timemodified' => self::T0 + 1000 + $id,
            'usermodified' => 0, 'path' => null, 'depth' => null, 'sortorder' => null, 'childpermission' => 0,
            'theme' => null, 'shell' => null, 'costcenter_logo' => 0, 'category' => null, 'multipleorg' => null,
            'button_color' => null, 'brand_color' => null, 'hover_color' => null,
        ]));
    }

    /**
     * Thirteen rows: eleven organisations and two that cannot be imported.
     *
     *   1, 77, 177   the three tenants ('01', '02', '03'); 177 is hidden; 1 has a logo, colours and a theme
     *   6, 5, 30, 31, 8   departments of 1 whose BizLMS sort orders rank 6 before 5 (id order would not)
     *   12           a third level under 5
     *   32, 33       under 77: 32 has no depth, 33 has no name
     *   20           junk: only multipleorg (skipped, not_org_row)
     *   40           root 999 is not a registered tenant (skipped, invalid_tenant_root)
     *
     * @return void
     */
    protected function contract_seed(): void {
        $this->put(1, ['fullname' => 'Airpay Payment Services', 'shortname' => 'airpay', 'path' => '/1', 'depth' => 1,
            'sortorder' => '01', 'description' => '<p>Head office</p>', 'theme' => 'airpay', 'costcenter_logo' => 11,
            'category' => 2, 'multipleorg' => '1', 'childpermission' => 1, 'shell' => 'airpay', 'usermodified' => 2,
            'brand_color' => '#0066A7', 'button_color' => '#0066A7', 'hover_color' => '#004d80']);
        $this->put(77, ['fullname' => 'Airpay Learning', 'shortname' => 'external', 'path' => '/77', 'depth' => 1,
            'sortorder' => '02', 'multipleorg' => '77']);
        $this->put(177, ['fullname' => 'ZEEA Mafunzo', 'shortname' => 'zeea', 'path' => '/177', 'depth' => 1,
            'sortorder' => '03', 'visible' => 0]);
        $this->put(5, ['fullname' => 'Technology', 'shortname' => 'airpay_tech', 'parentid' => 1, 'path' => '/1/5',
            'depth' => 2, 'sortorder' => '01.02']);
        $this->put(6, ['fullname' => 'Finance', 'shortname' => 'airpay_fin', 'parentid' => 1, 'path' => '/1/6',
            'depth' => 2, 'sortorder' => '01.01']);
        $this->put(8, ['fullname' => 'Operations', 'shortname' => 'airpay_ops', 'parentid' => 1, 'path' => '/1/8',
            'depth' => 2, 'sortorder' => '01.110']);
        $this->put(12, ['fullname' => 'Platform', 'shortname' => 'airpay_tech_plat', 'parentid' => 5,
            'path' => '/1/5/12', 'depth' => 3, 'sortorder' => '01.02.01']);
        $this->put(30, ['fullname' => 'Long name', 'shortname' => str_repeat('s', 120), 'parentid' => 1,
            'path' => '/1/30', 'depth' => 2, 'sortorder' => '01.0a']);
        $this->put(31, ['fullname' => 'Padded path', 'shortname' => 'padded', 'parentid' => 1, 'path' => ' /1/31 ',
            'depth' => 2, 'sortorder' => '01.0b']);
        $this->put(32, ['fullname' => 'No depth', 'shortname' => 'nodepth', 'parentid' => 77, 'path' => '/77/32',
            'depth' => null, 'sortorder' => '02.01']);
        $this->put(33, ['fullname' => null, 'shortname' => 'noname', 'parentid' => 77, 'path' => '/77/33',
            'depth' => 2, 'sortorder' => '02.02']);
        $this->put(20, ['multipleorg' => '20']);
        $this->put(40, ['fullname' => 'Unregistered tenant', 'shortname' => 'other', 'path' => '/999', 'depth' => 1,
            'sortorder' => '04']);
    }

    protected function contract_mutate_source(): void {
        $this->put(500, ['fullname' => 'Late arrival', 'shortname' => 'late', 'parentid' => 77, 'path' => '/77/500',
            'depth' => 2, 'sortorder' => '02.03']);
    }

    protected function contract_collision(): ?array {
        // Somebody else's organisation at the BizLMS id 1: not a copy of the source row.
        return ['table' => 'local_sentientia_org', 'row' => (object) ['id' => 1, 'fullname' => 'Somebody else',
            'shortname' => 'other', 'path' => '/1', 'parentid' => 0, 'depth' => 1, 'visible' => 1, 'sortorder' => 0,
            'timecreated' => 5, 'timemodified' => 5]];
    }

    protected function contract_adoptable(): ?array {
        // What the retired data_migration.php wrote: the same id, shortname and path, but a parent of 0, depth 1,
        // no sort order and "now" as the modification time.
        return ['table' => 'local_sentientia_org', 'sourcetable' => 'local_costcenter',
            'row' => (object) ['id' => 5, 'fullname' => 'Technology (copy)', 'shortname' => 'airpay_tech',
                'path' => '/1/5', 'parentid' => 0, 'depth' => 1, 'visible' => 1, 'sortorder' => 0,
                'timecreated' => self::T0 + 5, 'timemodified' => time()]];
    }

    /**
     * A row of local_sentientia_org.
     *
     * @param int $id
     * @return \stdClass
     */
    private function org(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_org', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The warning counts, reason counts and tenant methods of the org step in a report.
     *
     * @param report $report
     * @param string $section warnings, skipped_by_reason, tenant_methods or counters.
     * @return array
     */
    private function step_section(report $report, string $section): array {
        return $report->to_array()['features']['org']['steps'][costcenter_step::KEY][$section] ?? [];
    }

    // The column map.

    public function test_columns_are_mapped_and_ids_are_kept(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $this->assertSame(11, $DB->count_records('local_sentientia_org'));
        $this->assertFalse($DB->record_exists('local_sentientia_org', ['id' => 20]));
        $this->assertFalse($DB->record_exists('local_sentientia_org', ['id' => 40]));

        $airpay = $this->org(1);
        $this->assertSame('Airpay Payment Services', $airpay->fullname);
        $this->assertSame('airpay', $airpay->shortname);
        $this->assertSame('<p>Head office</p>', $airpay->description);
        $this->assertSame(0, (int) $airpay->parentid);
        $this->assertSame('/1', $airpay->path);
        $this->assertSame(1, (int) $airpay->depth);
        $this->assertSame(1, (int) $airpay->visible);
        $this->assertSame(11, (int) $airpay->org_logo, 'the logo item id is kept');
        $this->assertSame('#0066A7', $airpay->brand_color);
        $this->assertSame('#0066A7', $airpay->button_color);
        $this->assertSame('#004d80', $airpay->hover_color);
        $this->assertSame('airpay', $airpay->theme_scheme, 'theme is copied into theme_scheme');
        $this->assertSame(self::T0 + 1, (int) $airpay->timecreated, 'the source timestamps are kept');
        $this->assertSame(self::T0 + 1001, (int) $airpay->timemodified);
        foreach (['favicon', 'footer_text', 'email_from_name', 'email_from_addr', 'support_email', 'help_url',
                'hero_title', 'hero_subtitle', 'custom_css'] as $column) {
            $this->assertNull($airpay->{$column}, "{$column} has no BizLMS source and stays NULL");
        }

        $this->assertNull($this->org(77)->org_logo, 'a logo of 0 means no logo');
        $this->assertSame(0, (int) $this->org(177)->visible);
        $this->assertSame(3, (int) $this->org(12)->depth);
        $this->assertSame(5, (int) $this->org(12)->parentid);
        $this->assertSame('/1/5/12', $this->org(12)->path);

        // What the map does not copy stays in the legacy table, untouched.
        $legacy = $DB->get_record('local_costcenter', ['id' => 1], '*', MUST_EXIST);
        $this->assertSame('1', $legacy->multipleorg);
        $this->assertSame(1, (int) $legacy->childpermission);
        $this->assertSame('airpay', $legacy->shell);
        $this->assertSame(2, (int) $legacy->category);
        $this->assertSame(13, $DB->count_records('local_costcenter'));
    }

    public function test_siblings_are_ranked_by_their_bizlms_sort_order_not_by_id(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        // Under /1: 6 ('01.01'), 5 ('01.02'), 30 ('01.0a'), 31 ('01.0b'), 8 ('01.110' is child number 36).
        $this->assertSame(10, (int) $this->org(6)->sortorder);
        $this->assertSame(20, (int) $this->org(5)->sortorder);
        $this->assertSame(30, (int) $this->org(30)->sortorder);
        $this->assertSame(40, (int) $this->org(31)->sortorder);
        $this->assertSame(50, (int) $this->org(8)->sortorder);
        // The tenants, in the order BizLMS listed them.
        $this->assertSame(10, (int) $this->org(1)->sortorder);
        $this->assertSame(20, (int) $this->org(77)->sortorder);
        $this->assertSame(30, (int) $this->org(177)->sortorder);
        // Only children: rank 1.
        $this->assertSame(10, (int) $this->org(12)->sortorder);
    }

    public function test_the_sort_order_ranking_is_pure(): void {
        $rows = [];
        foreach ([[3, 0, '02'], [1, 0, '01'], [2, 0, '110'], [4, 0, null], [9, 0, '01']] as [$id, $parent, $order]) {
            $rows[$id] = (object) ['id' => $id, 'parentid' => $parent, 'sortorder' => $order, 'path' => '/' . $id];
        }
        $rows[7] = (object) ['id' => 7, 'parentid' => 0, 'sortorder' => '00', 'path' => null];
        // 1 and 9 tie on '01' (id breaks it), then 3, then 2 ('110' is number 36), then 4 (no order goes last);
        // 7 has no path, so it is not an organisation and takes no rank.
        $this->assertSame([1 => 10, 9 => 20, 3 => 30, 2 => 40, 4 => 50], org_source::sort_orders($rows));
        $this->assertSame(-1, org_source::compare_sortorder('01.09', '01.0a'));
        $this->assertSame(1, org_source::compare_sortorder('01.110', '01.0z'));
        $this->assertSame(-1, org_source::compare_sortorder('01', '01.01'));
        $this->assertSame(0, org_source::compare_sortorder(null, ''));
    }

    public function test_odd_values_are_reported_and_handled(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->contract_run(true);

        $shortname = $this->org(30)->shortname;
        $this->assertSame(100, \core_text::strlen($shortname), 'shortname is cut to its column and the cut is reported');
        $this->assertSame(str_repeat('s', 100), $shortname);

        $this->assertSame('/1/31', $this->org(31)->path, 'a padded path is stored normalised');
        $this->assertSame(2, (int) $this->org(32)->depth, 'no depth: the number of path segments');
        $this->assertSame('', $this->org(33)->fullname, 'no name: empty, not NULL');

        $warnings = $this->step_section($report, 'warnings');
        $this->assertSame(1, $warnings['truncated:shortname'] ?? 0);
        $this->assertSame(1, $warnings['path_normalised'] ?? 0);
        $this->assertSame(1, $warnings['derived_depth'] ?? 0);
        $this->assertSame(1, $warnings['empty_fullname'] ?? 0);
        $methods = $this->step_section($report, 'tenant_methods');
        $this->assertSame(1, $methods['normalised'] ?? 0);
        $this->assertSame(10, $methods['exact'] ?? 0);
    }

    // Reasons and accounting.

    public function test_the_rows_that_cannot_be_imported_are_mapped_with_a_reason(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->contract_run(true);

        $junk = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_costcenter', 'sourceid' => 20, 'subkey' => ''],
            '*', MUST_EXIST);
        $this->assertSame('skipped', $junk->outcome);
        $this->assertSame('not_org_row', $junk->reason);
        $this->assertSame('no_path', $junk->detail);
        $this->assertSame('', $junk->targettable);

        $other = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_costcenter', 'sourceid' => 40, 'subkey' => ''],
            '*', MUST_EXIST);
        $this->assertSame('skipped', $other->outcome);
        $this->assertSame('invalid_tenant_root', $other->reason);

        $this->assertSame(['not_org_row' => 1, 'invalid_tenant_root' => 1], $this->step_section($report, 'skipped_by_reason'));
        $counters = $this->step_section($report, 'counters');
        $this->assertSame(13, $counters['processed']);
        $this->assertSame(11, $counters['imported']);
        $this->assertSame(2, $counters['skipped']);
    }

    public function test_an_unaccepted_needs_owner_reason_leaves_the_run_unproven(): void {
        $this->contract_begin();
        $this->contract_seed();
        $decisions = decisions::from_array(['org.unmapped_columns' => 'not_copied']);
        [$result] = $this->contract_run(true, ['decisions' => $decisions]);
        $this->assertSame(2, $result['exit'], 'done, but a root that is not a tenant is waiting for the owner');
        $this->assertSame(['org:invalid_tenant_root=1'], $result['unproven']);
        $this->assertSame('complete', $result['features']['org']);
    }

    public function test_a_dry_run_counts_what_an_apply_would_do(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(false);
        $this->assertSame(0, $result['exit']);
        $this->assertSame('simulated', $result['features']['org']);
        $counters = $this->step_section($report, 'counters');
        $this->assertSame(11, $counters['imported']);
        $this->assertSame(2, $counters['skipped']);
        $this->assertSame(0, $counters['adopted']);
    }

    public function test_the_legacy_table_is_never_written(): void {
        $this->contract_begin();
        $this->contract_seed();
        $before = fingerprint::table('local_costcenter');
        $this->contract_run(true);
        $this->contract_run(true);
        $this->assertSame($before, fingerprint::table('local_costcenter'), 'count, max id, columns and CRC are unchanged');
    }

    // The owner's decision, enums, adoption, sequence.

    public function test_the_unmapped_columns_decision_is_required_and_has_one_answer(): void {
        $this->contract_begin();
        $this->contract_seed();

        [$result] = $this->contract_run(false, ['decisions' => decisions::none()]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:org.unmapped_columns', implode(' ', $result['blockers']));

        [$result] = $this->contract_run(false, ['decisions' => decisions::from_array(['org.unmapped_columns' => 'copy_them'])]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:org.unmapped_columns', implode(' ', $result['blockers']));

        $unfinished = decisions::from_array(['org.unmapped_columns' => 'not_copied'], ['org.unmapped_columns' => 'open']);
        [$result] = $this->contract_run(false, ['decisions' => $unfinished]);
        $this->assertSame(1, $result['exit'], 'a decision that is not accepted is not a decision');
        $this->assertStringContainsString('decision_not_accepted:org.unmapped_columns', implode(' ', $result['blockers']));
    }

    public function test_an_unknown_visible_value_blocks_until_the_owner_maps_it_and_is_then_skipped(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->put(50, ['fullname' => 'Odd', 'shortname' => 'odd', 'parentid' => 1, 'path' => '/1/50', 'depth' => 2,
            'sortorder' => '01.0c', 'visible' => 5]);

        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_costcenter.visible=5', implode(' ', $result['blockers']));

        // The owner lets the value through; the importer has no meaning for it and says so.
        $mapped = decisions::from_array([
            'org.unmapped_columns' => 'not_copied',
            'accepted_reasons' => ['org:invalid_tenant_root', 'org:unmapped_enum'],
            'enums.local_costcenter.visible' => ['5' => 'hidden on an old BizLMS'],
        ]);
        [$result] = $this->contract_run(true, ['decisions' => $mapped]);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $row = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_costcenter', 'sourceid' => 50, 'subkey' => ''],
            '*', MUST_EXIST);
        $this->assertSame('skipped', $row->outcome);
        $this->assertSame('unmapped_enum', $row->reason);
        $this->assertFalse($DB->record_exists('local_sentientia_org', ['id' => 50]));
    }

    public function test_an_adopted_copy_is_rewritten_with_the_full_mapping(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $copy = $this->contract_adoptable();
        $DB->import_record($copy['table'], $copy['row']);

        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $this->assertSame('adopted', $DB->get_field(legacymap::TABLE, 'outcome',
            ['sourcetable' => 'local_costcenter', 'sourceid' => 5, 'subkey' => '']));

        $org = $this->org(5);
        $this->assertSame('Technology', $org->fullname, 'the real name, not the copy\'s');
        $this->assertSame(1, (int) $org->parentid);
        $this->assertSame(2, (int) $org->depth);
        $this->assertSame(20, (int) $org->sortorder);
        $this->assertSame(self::T0 + 1005, (int) $org->timemodified, 'the source timestamp, not "now"');
    }

    public function test_a_native_insert_after_the_import_gets_an_id_above_every_legacy_id(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $id = (int) $DB->insert_record('local_sentientia_org', (object) ['fullname' => 'Native', 'shortname' => 'native',
            'path' => '/77/999', 'parentid' => 77, 'depth' => 2, 'visible' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        $this->assertGreaterThan(177, $id, 'finalise reset the sequence after the highest kept id');
    }

    public function test_the_importer_is_registered_and_needs_no_dependency(): void {
        $this->contract_begin();
        $loaded = registry::load();
        $this->assertArrayHasKey('org', $loaded);
        $this->assertSame([], $loaded['org']->depends(), 'org is the first feature of every run');
        $this->assertSame('org', registry::TENANT_OWNER);
        $this->assertSame(['org'], registry::sorted($loaded));

        $imports = [];
        include(__DIR__ . '/../db/bizlms_import.php');
        $this->assertSame(importer::class, $imports['org'], 'db/bizlms_import.php declares it');
    }

    // Preflight.

    public function test_preflight_counts_the_table_and_warns_about_a_missing_logo_file(): void {
        $this->contract_begin();
        $this->contract_seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report()]);
        $out = $runner->preflight(['org']);
        $pf = $out['preflights']['org']->to_array();
        $this->assertSame([], $pf['blockers']);
        $this->assertSame(13, $pf['counts']['org_rows']);
        $this->assertSame(1, $pf['counts']['org_logos']);
        $this->assertSame(1, $pf['counts']['anomaly:not_org_row']);
        $this->assertSame(1, $pf['counts']['anomaly:invalid_tenant_root']);
        $this->assertSame(1, $pf['counts']['anomaly:derived_depth']);
        $this->assertContains('not_org_row:1', $pf['warnings']);
        $this->assertContains('invalid_tenant_root:1', $pf['warnings']);
        $this->assertContains('logo_file_missing:1', $pf['warnings'], 'logo 11 has no file in this seed');
    }

    public function test_preflight_warns_about_a_duplicate_path_an_orphan_parent_and_a_path_outside_its_parent(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->put(60, ['fullname' => 'Same path as 5', 'shortname' => 'dup', 'parentid' => 1, 'path' => '/1/5',
            'depth' => 2, 'sortorder' => '01.0d']);
        $this->put(61, ['fullname' => 'Orphan', 'shortname' => 'orphan', 'parentid' => 99, 'path' => '/1/61',
            'depth' => 2, 'sortorder' => '01.0e']);
        $this->put(62, ['fullname' => 'Elsewhere', 'shortname' => 'elsewhere', 'parentid' => 77, 'path' => '/1/62',
            'depth' => 2, 'sortorder' => '01.0f']);
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report()]);
        $pf = $runner->preflight(['org'])['preflights']['org']->to_array();
        $this->assertSame([], $pf['blockers'], 'odd data is reported, not a blocker: the legacy table cannot be corrected');
        $this->assertContains('duplicate_path:2', $pf['warnings']);
        $this->assertContains('path_id_mismatch:1', $pf['warnings']);
        $this->assertContains('orphan_parent:1', $pf['warnings']);
        $this->assertContains('path_not_under_parent:1', $pf['warnings']);
    }

    public function test_a_native_organisation_with_an_invalid_tenant_path_blocks_before_the_verify_does(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // Not one of the ids the import writes, and its root is not a registered tenant: the framework's tenant
        // check reads every row of the table after the load and would fail the feature once the work is done.
        $DB->import_record('local_sentientia_org', (object) ['id' => 900, 'fullname' => 'Native', 'shortname' => 'native',
            'path' => '/5', 'parentid' => 0, 'depth' => 1, 'visible' => 1, 'sortorder' => 0,
            'timecreated' => 1, 'timemodified' => 1]);
        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('native_org_invalid_path:1 ids=900', implode(' ', $result['blockers']));
    }

    // Logos.

    public function test_a_logo_is_copied_into_the_org_file_area_and_the_original_stays(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $category = $this->getDataGenerator()->create_category();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_coursecat::instance($category->id)->id, 'component' => 'local_costcenter',
            'filearea' => 'costcenter_logo', 'itemid' => 11, 'filepath' => '/', 'filename' => 'logo.png',
        ], 'not really a png');
        $systemid = \context_system::instance()->id;

        if (!\core_component::get_component_directory('local_costcenter')) {
            $this->assertSame('', branding_manager::get_logo_url(11), 'nothing serves the BizLMS copy before the import');
        }

        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);
        // IDN-04: the copy is a declared side effect. The tripwire is clean and the report counts the copy.
        $feature = $report->to_array()['features']['org'];
        $this->assertSame('clean', $feature['tripwire']);
        $this->assertSame(['local_sentientia_org/org_logo' => 1], $feature['files_copied']);
        $copy = $fs->get_file($systemid, 'local_sentientia_org', 'org_logo', 11, '/', 'logo.png');
        $this->assertNotFalse($copy, 'copied into the system context under this plugin');
        $this->assertSame('not really a png', $copy->get_content());
        $this->assertStringContainsString('/local_sentientia_org/org_logo/11/logo.png', branding_manager::get_logo_url(11));
        $this->assertTrue($DB->record_exists('files', ['component' => 'local_costcenter', 'filearea' => 'costcenter_logo',
            'itemid' => 11, 'filename' => 'logo.png']), 'the original is left where it was');

        $this->contract_run(true);
        $this->assertCount(1, $fs->get_area_files($systemid, 'local_sentientia_org', 'org_logo', 11, 'id', false),
            'a second run copies nothing');
    }

    public function test_the_org_importer_declares_its_logo_copy_through_the_copies_files_marker(): void {
        $importer = new importer();
        $this->assertInstanceOf(\local_sentientia_platform\bizlms\copies_files::class, $importer);
        $this->assertSame([['local_costcenter', 'costcenter_logo', 'local_sentientia_org', 'org_logo']],
            $importer->allowed_file_areas());
        $this->assertSame([], $importer->core_writes(), 'a file copy is not a core write, so --purge-feature stays available');
        $this->assertTrue(\local_sentientia_platform\bizlms\sideeffect_guard::file_areas_well_formed($importer));
    }

    public function test_the_logo_copy_is_the_only_thing_the_run_adds_to_the_file_table(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $category = $this->getDataGenerator()->create_category();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_coursecat::instance($category->id)->id, 'component' => 'local_costcenter',
            'filearea' => 'costcenter_logo', 'itemid' => 11, 'filepath' => '/', 'filename' => 'logo.png',
        ], 'not really a png');
        $before = (int) $DB->get_field_sql('SELECT MAX(id) FROM {files}');
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);

        // The only new rows are the declared ones: nothing else in {files} moved during the run, so the importer
        // would trip the tripwire if it wrote anywhere else (the runner's own check is tested in the platform).
        $found = \local_sentientia_platform\bizlms\sideeffect_guard::files_in_areas($before, ['local_sentientia_org/org_logo']);
        $this->assertSame([], $found['outside']);
        $this->assertSame(1, $found['copied']['local_sentientia_org/org_logo']);
    }

    public function test_a_legacy_logo_url_is_not_offered_when_the_bizlms_plugin_is_gone(): void {
        if (\core_component::get_component_directory('local_costcenter')) {
            $this->markTestSkipped('local_costcenter is on disk here, so its fallback is legitimate');
        }
        $this->contract_begin();
        $category = $this->getDataGenerator()->create_category();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_coursecat::instance($category->id)->id, 'component' => 'local_costcenter',
            'filearea' => 'costcenter_logo', 'itemid' => 99, 'filepath' => '/', 'filename' => 'old.png',
        ], 'not really a png');
        $this->assertSame('', branding_manager::get_logo_url(99), 'a link to a plugin that no longer serves files is a dead image');
    }

    // Verify.

    public function test_verify_passes_after_an_import_and_names_damage_to_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);

        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $this->assertSame(['exit' => 0, 'failures' => ['org' => []]], $runner->verify(['org']));

        $DB->set_field('local_sentientia_org', 'path', '/77/5', ['id' => 5]);
        $DB->delete_records('local_sentientia_org', ['id' => 12]);
        $failures = $runner->verify(['org']);
        $this->assertSame(1, $failures['exit']);
        $this->assertContains('org_path_differs:5', $failures['failures']['org']);
        $this->assertContains('org_missing:12', $failures['failures']['org']);
    }

    public function test_verify_leaves_an_organisation_an_admin_edited_after_the_import_alone(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        // A valid path (the framework's own tenant check reads every row), a new order and a later timemodified.
        $DB->update_record('local_sentientia_org', (object) ['id' => 5, 'path' => '/1/5/99', 'sortorder' => 99,
            'timemodified' => time() + 100]);
        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $this->assertSame([], $runner->verify(['org'])['failures']['org'],
            'the admin\'s edit is the admin\'s, not the importer\'s to check');
    }

    // Tenant isolation.

    /**
     * A tenant admin as UAT has them: a manager-archetype role at system context.
     *
     * @param string $path
     * @return \stdClass
     */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $user->id, \context_system::instance()->id);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * @group tenant_isolation
     */
    public function test_a_tenant_admin_sees_only_their_own_imported_tree(): void {
        $this->contract_begin();
        $this->ensure_bizlms_schema();
        $this->contract_seed();
        $this->contract_run(true);

        $this->setUser($this->tenant_admin('/1'));
        $ids = array_map('intval', array_keys(org_manager::get_all_in_scope()));
        sort($ids);
        $this->assertSame([1, 5, 6, 8, 12, 30, 31], $ids, 'Airpay\'s own tree, not /77, not /177, not the skipped root');

        $this->setUser($this->tenant_admin('/177'));
        $this->assertSame([177], array_map('intval', array_keys(org_manager::get_all_in_scope())));

        $this->setUser($this->tenant_admin(''));
        $this->assertSame([], org_manager::get_all_in_scope(), 'no tenant, no organisations');

        $this->setAdminUser();
        $this->assertCount(11, org_manager::get_all_in_scope(), 'the site admin sees every imported tenant');
    }

    // The reader fixes that ship with the importer (ADR-032 gate 3, mapping doc code fixes 2b and 3).

    public function test_accesslib_no_longer_honours_a_bizlms_capability(): void {
        foreach (['can_manage_multi', 'can_view', 'can_manage', 'is_org_head', 'is_dept_head', 'can_manage_classroom'] as $name) {
            $method = new \ReflectionMethod(accesslib::class, $name);
            $lines = file($method->getFileName());
            $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
            $this->assertStringNotContainsString('legacy_cap', $body, "{$name} has no legacy fallback");
            $this->assertStringNotContainsString('local/costcenter', $body, "{$name} ignores local/costcenter:*");
            $this->assertStringNotContainsString('local/classroom', $body, "{$name} ignores local/classroom:*");
        }
    }

    public function test_a_user_with_no_grants_holds_none_of_the_org_checks(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $context = \context_system::instance();
        $this->assertFalse(accesslib::can_manage_multi($context));
        $this->assertFalse(accesslib::can_view($context));
        $this->assertFalse(accesslib::can_manage($context));
        $this->assertFalse(accesslib::is_org_head($context));
        $this->assertFalse(accesslib::is_dept_head($context));
        $this->assertFalse(accesslib::can_manage_classroom($context));
    }

    public function test_the_core_legacy_source_reads_the_organisation_name_from_fullname(): void {
        if (!class_exists('\local_sentientia_core\org_legacy_source')) {
            $this->markTestSkipped('local_sentientia_core is not installed');
        }
        $this->resetAfterTest();
        $this->put(5, ['fullname' => 'Technology', 'shortname' => 'airpay_tech', 'parentid' => 1, 'path' => '/1/5']);
        $this->put(6, ['fullname' => null, 'shortname' => 'noname', 'parentid' => 1, 'path' => '/1/6']);
        $source = new \local_sentientia_core\org_legacy_source();
        $this->assertSame('Technology', $source->unit_name(5), 'it asked for a name column that does not exist and got "Unit 5"');
        $this->assertNull($source->unit_name(6), 'no name: the caller falls back to "Unit <id>"');
        $this->assertNull($source->unit_name(404));
    }

    // The real owner file.

    public function test_the_signed_decisions_file_drives_the_importer(): void {
        $file = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        if (!is_readable($file)) {
            $this->markTestSkipped('the platform fixture copy of the decisions file is not here');
        }
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => decisions::load($file)]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['org']);
    }
}
