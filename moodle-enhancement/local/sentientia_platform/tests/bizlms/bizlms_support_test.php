<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\batch_source;
use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\legacy_reader;
use local_sentientia_platform\bizlms\legacy_tables;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\lookups;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\parity;
use local_sentientia_platform\bizlms\provenance;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\bizlms\sideeffect_guard;
use local_sentientia_platform\bizlms\step;
use local_sentientia_platform\bizlms\tenant_resolver;
use local_sentientia_platform\bizlms\text;
use local_sentientia_platform\bizlms\unclaimed;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\toy_files_importer;
use local_sentientia_platform\tests\bizlms\toy_importer;
use local_sentientia_platform\tests\bizlms\toy_seed;
use local_sentientia_platform\bizlms\bizlms_exception;

/**
 * The small collaborators of the BizLMS import: tenant resolver, text, decisions,
 * map reads, fingerprints, tripwire, provenance, legacy-table detection, the
 * batch source and the legacy reader (ADR-032).
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\tenant_resolver
 * @covers     \local_sentientia_platform\bizlms\text
 * @covers     \local_sentientia_platform\bizlms\decisions
 * @covers     \local_sentientia_platform\bizlms\legacymap
 * @covers     \local_sentientia_platform\bizlms\fingerprint
 * @covers     \local_sentientia_platform\bizlms\sideeffect_guard
 * @covers     \local_sentientia_platform\bizlms\provenance
 * @covers     \local_sentientia_platform\bizlms\legacy_tables
 * @covers     \local_sentientia_platform\bizlms\unclaimed
 * @covers     \local_sentientia_platform\bizlms\batch_source
 * @covers     \local_sentientia_platform\bizlms\legacy_reader
 * @covers     \local_sentientia_platform\bizlms\lookups
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_support_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use toy_seed;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/../fixtures/bizlms/toy.install.xml'];
    }

    protected function tearDown(): void {
        registry::set_testing_importers(null);
        toy_importer::reset();
        parent::tearDown();
    }

    /**
     * @param string $key Step key of the toy importer.
     * @return step
     */
    private function toy_step(string $key): step {
        foreach ((new toy_importer())->steps() as $step) {
            if ($step->key() === $key) {
                return $step;
            }
        }
        $this->fail("no toy step {$key}");
    }

    // Tenant resolver.

    /**
     * @dataProvider normalise_provider
     * @param string|null $raw
     * @param string|null $expected
     */
    public function test_normalise(?string $raw, ?string $expected): void {
        $this->assertSame($expected, tenant_resolver::normalise($raw));
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function normalise_provider(): array {
        return [
            'spaces and a trailing slash' => [' 1/5/ ', '/1/5'],
            'doubled slashes' => ['//1//5', '/1/5'],
            'already normal' => ['/1/5', '/1/5'],
            'a bare root' => ['5', '/5'],
            'leading zeros' => ['/01/5', '/1/5'],
            'empty' => ['', null],
            'only spaces' => ['   ', null],
            'null' => [null, null],
            'zero' => ['0', null],
            'a zero segment' => ['/1/0', null],
            'a non-digit segment' => ['1/x', null],
            'a negative segment' => ['/1/-2', null],
        ];
    }

    public function test_resolve_reports_how_it_decided(): void {
        $this->resetAfterTest();
        $resolver = new tenant_resolver(new lookups());
        $this->assertSame(['/1/5', 1, 'exact'], $resolver->resolve(['a' => '/1/5']));
        $this->assertSame(['/1/5', 1, 'normalised'], $resolver->resolve(['a' => ' 1/5/ ']));
        $this->assertSame(['/77', 77, 'exact'], $resolver->resolve(['a' => 77]), 'an integer is a root id');
        $this->assertSame(['/77', 77, 'fallback:b'], $resolver->resolve(['a' => '', 'b' => '/77']));
        $this->assertSame(['/1', 1, 'fallback:b'], $resolver->resolve(['a' => '/999/1', 'b' => '/1']),
            'a root that is not a registered tenant is skipped');
        $this->assertSame([null, null, 'unresolved'], $resolver->resolve(['a' => null, 'b' => 'junk']));
    }

    public function test_resolve_walks_up_to_the_nearest_known_organisation(): void {
        global $DB;
        $this->resetAfterTest();
        foreach (['/1', '/1/5'] as $path) {
            $DB->insert_record('local_sentientia_org', (object) ['fullname' => 'Org ' . $path, 'shortname' => 'o',
                'parentid' => 0, 'path' => $path, 'depth' => count(explode('/', trim($path, '/'))), 'visible' => 1,
                'sortorder' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        }
        $resolver = new tenant_resolver(new lookups());
        $this->assertSame(['/1/5', 1, 'exact'], $resolver->resolve(['a' => '/1/5']));
        $this->assertSame(['/1/5', 1, 'walked_up'], $resolver->resolve(['a' => '/1/5/9']));
        $this->assertSame(['/1', 1, 'walked_up'], $resolver->resolve(['a' => '/1/7']));
        $this->assertSame([null, null, 'unresolved'], $resolver->resolve(['a' => '/77/1']),
            'no organisation at /77 or above it: the candidate is unusable');
    }

    public function test_org_for_path_compares_whole_paths_never_prefixes(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('local_sentientia_org', (object) ['fullname' => 'Two', 'shortname' => 'o', 'parentid' => 0,
            'path' => '/1/2', 'depth' => 2, 'visible' => 1, 'sortorder' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        $resolver = new tenant_resolver(new lookups());
        $this->assertNotNull($resolver->org_for_path('/1/2', false));
        $this->assertNull($resolver->org_for_path('/1/20', false), '/1/2 must never stand in for /1/20');
        $this->assertNull($resolver->org_for_path('/1/20', true), 'and walking up from /1/20 passes /1, not /1/2');
    }

    public function test_root_of_user_reads_the_current_open_path(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $resolver = new tenant_resolver(new lookups());
        if (!array_key_exists('open_path', $DB->get_columns('user'))) {
            $this->assertSame(0, $resolver->root_of_user((int) $user->id), 'a vanilla Moodle has no open_path');
            return;
        }
        $DB->set_field('user', 'open_path', '/77/3', ['id' => $user->id]);
        $this->assertSame(77, (new tenant_resolver(new lookups()))->root_of_user((int) $user->id));
    }

    // Text, decisions.

    public function test_fit_truncates_by_characters_and_records_a_warning(): void {
        $text = new text();
        $this->assertSame('abc', $text->fit('abc', 3, 'name'));
        $this->assertSame([], $text->drain());
        $this->assertSame('hél', $text->fit('héllo', 3, 'name'));
        $this->assertSame(['truncated:name'], $text->drain());
        $this->assertSame([], $text->drain(), 'drain clears');
        $this->assertSame('', $text->fit(null, 5, 'name'));
    }

    public function test_decisions_hash_is_stable_across_line_endings_and_key_order(): void {
        $body = '{"version": 1, "approved_by": "x", "approved_on": "2026-09-30", "decisions": '
            . '{"toy.rounding": {"value": "half_even", "why": "w", "source": "s", "status": "accepted"}}, '
            . '"accepted_reasons": ["toy:orphan_org"]}';
        $lf = tempnam(sys_get_temp_dir(), 'dec');
        $crlf = tempnam(sys_get_temp_dir(), 'dec');
        file_put_contents($lf, str_replace(', ', ",\n  ", $body) . "\n");
        file_put_contents($crlf, str_replace(', ', ",\r\n  ", $body) . "\r\n");
        $this->assertSame(decisions::load($lf)->hash(), decisions::load($crlf)->hash(), 'a checkout on Windows hashes the same');
        $this->assertSame(64, strlen(decisions::load($lf)->hash()));
        $this->assertSame(decisions::from_array(['a' => 1, 'b' => 2])->hash(), decisions::from_array(['b' => 2, 'a' => 1])->hash());

        $loaded = decisions::load($lf);
        $this->assertSame('half_even', $loaded->get('toy.rounding'));
        $this->assertTrue($loaded->accepts('toy', 'orphan_org'));
        $this->assertFalse($loaded->accepts('toy', 'orphan_user'));
        unlink($lf);
        unlink($crlf);
    }

    public function test_decisions_file_must_be_a_json_object_with_a_decisions_object(): void {
        $file = tempnam(sys_get_temp_dir(), 'dec');
        foreach ([
            '[1, 2, 3]' => 'decisions_file_not_a_json_object',
            '{"toy.rounding": "half_even"}' => 'decisions_file_has_no_decisions_object',
            '{"decisions": [1, 2]}' => 'decisions_file_has_no_decisions_object',
            '{"decisions": {"toy.x": {"value": 1}}}' => 'decisions_file_entry_invalid:toy.x',
            '{"decisions": {"toy.x": {"status": "accepted"}}}' => 'decisions_file_entry_invalid:toy.x',
            '{"decisions": {"toy.x": "accepted"}}' => 'decisions_file_entry_invalid:toy.x',
            '{"decisions": {"nodot": {"value": 1, "status": "accepted"}}}' => 'decisions_file_invalid_key:nodot',
            '{"decisions": {}, "accepted_reasons": ["Toy:X"]}' => 'decisions_file_invalid_accepted_reason',
            '{"decisions": {}, "enums": {"nodot": {}}}' => 'decisions_file_invalid_enums:nodot',
        ] as $content => $expected) {
            file_put_contents($file, $content);
            try {
                decisions::load($file);
                $this->fail("accepted: {$content}");
            } catch (blocked $e) {
                $this->assertStringContainsString($expected, $e->getMessage(), $content);
            }
        }
        unlink($file);
        $this->expectException(blocked::class);
        decisions::load($file . '.missing');
    }

    // Map, fingerprint, tripwire, provenance.

    public function test_map_reads_resolve_chunk_preload_and_respect_batches(): void {
        global $DB;
        $this->resetAfterTest();
        $rows = [];
        for ($i = 1; $i <= 1201; $i++) {
            $rows[] = (object) ['feature' => 'toy', 'sourcetable' => 'local_toy_org', 'sourceid' => $i, 'subkey' => '',
                'targettable' => 'local_sentientia_toy_org', 'targetid' => $i + 5000, 'outcome' => 'imported',
                'reason' => null, 'detail' => null, 'runid' => 1, 'timecreated' => 1];
        }
        $rows[] = (object) ['feature' => 'toy', 'sourcetable' => 'local_toy_org', 'sourceid' => 5000, 'subkey' => '',
            'targettable' => '', 'targetid' => null, 'outcome' => 'archived', 'reason' => 'not_history', 'detail' => null,
            'runid' => 1, 'timecreated' => 1];
        $DB->insert_records(legacymap::TABLE, $rows);

        $map = new legacymap();
        $this->assertSame(5007, $map->resolve('local_toy_org', 7));
        $this->assertNull($map->resolve('local_toy_org', 99999), 'unmapped');
        $this->assertNull($map->resolve('local_toy_org', 5000), 'mapped but archived: no target');
        $this->assertSame('archived', $map->entry('local_toy_org', 5000)['outcome']);

        $many = (new legacymap())->resolve_many('local_toy_org', [1, 1201, 5000, 77777]);
        $this->assertSame([1 => 5001, 1201 => 6201, 5000 => null, 77777 => null], $many, 'chunked at 1000 ids per query');

        $preloaded = new legacymap();
        $preloaded->preload('local_toy_org');
        $DB->delete_records(legacymap::TABLE);
        $this->assertSame(5003, $preloaded->resolve('local_toy_org', 3), 'served from memory after preload');
        $this->assertNull($preloaded->resolve('local_toy_org', 77777), 'a miss after preload is known without a query');
    }

    public function test_entries_added_in_a_batch_vanish_on_rollback_and_stay_on_commit(): void {
        $map = new legacymap();
        $entry = ['targettable' => 't', 'targetid' => 9, 'outcome' => 'imported', 'reason' => null];
        $map->begin_batch();
        $map->remember('src', 1, '', $entry);
        $this->assertSame(9, $map->resolve('src', 1), 'visible inside the batch');
        $map->rollback_batch();
        $this->assertNull($map->resolve('src', 1), 'a rolled-back batch leaves no trace');

        $map->begin_batch();
        $map->remember('src', 1, '', $entry);
        $map->commit_batch();
        $this->assertSame(9, $map->resolve('src', 1));
    }

    public function test_fingerprints_count_max_id_columns_and_respect_a_filter(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->seed_toy_data();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', fingerprint::install());
        $this->assertSame(substr(sha1($CFG->wwwroot . '|' . $CFG->dbname . '|' . $CFG->prefix), 0, 12), fingerprint::install());

        $all = fingerprint::table('local_toy_org');
        $this->assertSame(5, $all['count']);
        $this->assertSame(7, $all['maxid']);
        $this->assertSame($all['columns'], array_values(array_unique($all['columns'])));
        $sorted = $all['columns'];
        sort($sorted);
        $this->assertSame($sorted, $all['columns'], 'columns are sorted by name');
        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertNotNull($all['crc']);
        } else {
            $this->assertNull($all['crc'], 'no CRC32 on this engine, and it says so instead of claiming a match');
        }

        $filtered = fingerprint::table('local_toy_org', ['status = :s', ['s' => 9]]);
        $this->assertSame(1, $filtered['count']);
        $this->assertSame(4, $filtered['maxid']);

        $this->expectException(\coding_exception::class);
        fingerprint::table('local_toy_org; DROP TABLE x');
    }

    public function test_tripwire_sees_an_insert_and_ignores_declared_targets(): void {
        global $DB;
        $this->resetAfterTest();
        $before = sideeffect_guard::snapshot();
        $this->assertArrayHasKey('logstore_standard_log', $before);
        $DB->insert_record('logstore_standard_log', (object) ['eventname' => '\\x', 'component' => 'core', 'action' => 'a',
            'target' => 't', 'objecttable' => null, 'objectid' => null, 'crud' => 'c', 'edulevel' => 0, 'contextid' => 1,
            'contextlevel' => 10, 'contextinstanceid' => 0, 'userid' => 0, 'courseid' => 0, 'relateduserid' => null,
            'anonymous' => 0, 'other' => null, 'timecreated' => 1, 'origin' => 'cli', 'ip' => null, 'realuserid' => null]);
        $after = sideeffect_guard::snapshot();
        $this->assertSame(['logstore_standard_log'], sideeffect_guard::violations($before, $after));
        $this->assertSame([], sideeffect_guard::violations($before, $after, ['logstore_standard_log']),
            'a table the feature declares as a target is not a violation');
    }

    public function test_provenance_tells_imported_rows_from_hand_made_ones(): void {
        global $DB;
        $this->resetAfterTest();
        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        $DB->import_record('local_sentientia_toy_org', (object) ['id' => 500, 'name' => 'Hand made', 'path' => null,
            'visible' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        (new runner(['apply' => true, 'permit' => guard::test_permit(), 'batch' => 2, 'atomic_threshold' => 0]))->run([]);

        $this->assertTrue(provenance::is_imported('local_sentientia_toy_org', 1));
        $this->assertFalse(provenance::is_imported('local_sentientia_toy_org', 500));
        [$sql, $params] = provenance::not_imported_sql('o', 'local_sentientia_toy_org');
        $rows = $DB->get_records_sql("SELECT o.id FROM {local_sentientia_toy_org} o WHERE {$sql}", $params);
        $this->assertSame(['500'], array_map('strval', array_keys($rows)));
    }

    // Legacy tables, unclaimed tables.

    public function test_legacy_table_detection(): void {
        $this->resetAfterTest();
        legacy_tables::reset();
        $found = legacy_tables::detect();
        $this->assertContains('local_toy_org', $found, 'a local_ table no installed plugin declares is legacy');
        $this->assertNotContains('local_sentientia_toy_org', $found, 'Sentientia tables are never legacy');
        $this->assertNotContains('user', $found);
        $this->assertFalse(legacy_tables::holds_bizlms(), 'no BizLMS table exists in the phpunit database');

        $known = legacy_tables::KNOWN;
        $this->assertGreaterThan(95, count($known));
        $this->assertSame($known, array_values(array_unique($known)), 'no table listed twice');
        $this->assertContains('local_costcenter', $known);
        $this->assertContains('local_classroom_attendance', $known);
    }

    public function test_unclaimed_tables_are_legacy_tables_no_importer_owns(): void {
        $this->resetAfterTest();
        $this->seed_toy_data();
        legacy_tables::reset();
        $partial = new toy_importer('part', [], ['local_toy_org']);
        $unclaimed = unclaimed::find([$partial]);
        $this->assertNotContains('local_toy_org', $unclaimed);
        $this->assertContains('local_toy_item', $unclaimed);
        $this->assertContains('local_toy_unused', $unclaimed, 'only the full toy importer declines it');
        $this->assertContains('local_toy_item', unclaimed::with_rows([$partial]));
        $this->assertNotContains('local_toy_unused', unclaimed::with_rows([$partial]), 'an empty table is not unproven');

        $this->assertSame([], unclaimed::find([new toy_importer()]), 'the full toy importer claims or declines all six');
    }

    // Batch source, reader, lookups.

    public function test_batch_source_pages_by_keyset_and_resumes_from_a_watermark(): void {
        $this->resetAfterTest();
        $this->seed_toy_data();
        $step = $this->toy_step('toy.item');
        $reader = new legacy_reader();

        $source = new batch_source($reader, $step, 0, 2, 100);
        $keys = [];
        while (($groups = $source->next_batch()) !== []) {
            $keys[] = array_map(fn($g) => $g['key'], $groups);
        }
        $this->assertSame([[1, 2], [3, 4], [5]], $keys);

        $source = new batch_source($reader, $step, 3, 2, 100);
        $this->assertSame([4, 5], array_map(fn($g) => $g['key'], $source->next_batch()));
    }

    public function test_batch_source_groups_by_minimum_id_and_resumes_on_that_key(): void {
        $this->resetAfterTest();
        $this->seed_toy_data();
        $step = $this->toy_step('toy.dup');
        $reader = new legacy_reader();

        $source = new batch_source($reader, $step, 0, 1, 100);
        $first = $source->next_batch();
        $this->assertSame([1], array_map(fn($g) => $g['key'], $first));
        $this->assertSame([1, 3, 5], array_keys($first[0]['rows']), 'interleaved ids of one group, ascending');
        $this->assertSame(2, $source->group_count());
        $second = $source->next_batch();
        $this->assertSame([2, 4], array_keys($second[0]['rows']));
        $this->assertSame([], $source->next_batch());

        $resumed = new batch_source($reader, $step, 1, 10, 100);
        $this->assertSame([2], array_map(fn($g) => $g['key'], $resumed->next_batch()), 'the watermark is a minimum id');

        $only = new batch_source($reader, $step, 0, 10, 100, [4]);
        $batch = $only->next_batch();
        $this->assertSame([2], array_map(fn($g) => $g['key'], $batch), 'retry mode keeps the groups that contain a wanted id');
    }

    public function test_batch_source_caps_the_group_scan(): void {
        $this->resetAfterTest();
        $this->seed_toy_data();
        $source = new batch_source(new legacy_reader(), $this->toy_step('toy.dup'), 0, 10, 3);
        $this->expectException(blocked::class);
        $this->expectExceptionMessage('max_group_scan_exceeded:local_toy_dup');
        $source->next_batch();
    }

    public function test_legacy_reader_is_bounded_and_read_only(): void {
        $this->resetAfterTest();
        $this->seed_toy_data();
        $reader = new legacy_reader();
        $this->assertTrue($reader->exists('local_toy_org'));
        $this->assertFalse($reader->exists('local_toy_nothere'));
        $this->assertTrue($reader->has_column('local_toy_item', 'kind'));
        $this->assertSame(5, $reader->count('local_toy_org'));
        $this->assertSame(1, $reader->count('local_toy_org', ['status = :s', ['s' => 9]]));
        $this->assertSame(7, $reader->max_id('local_toy_org'));
        $this->assertSame([1, 2], array_keys($reader->page('local_toy_org', 0, 2)));
        $this->assertSame([3, 4], array_keys($reader->page('local_toy_org', 2, 2)));
        $this->assertSame([2, 7], array_keys($reader->fetch('local_toy_org', [7, 2, 999])), 'missing ids are absent, order is by id');
        $cols = $reader->page('local_toy_org', 0, 1, ['name', 'nosuchcolumn']);
        $this->assertSame(['id', 'name'], array_keys((array) reset($cols)), 'names the table lacks are dropped; id always leads');

        $this->expectException(\coding_exception::class);
        $reader->page('local_toy_org; DROP TABLE x', 0, 1);
    }

    public function test_forget_drops_what_a_step_cached_but_keeps_a_declared_preload(): void {
        global $DB;
        $this->resetAfterTest();
        $rows = [];
        foreach ([['local_toy_org', 1, 11], ['local_toy_item', 1, 21]] as [$table, $sourceid, $targetid]) {
            $rows[] = (object) ['feature' => 'toy', 'sourcetable' => $table, 'sourceid' => $sourceid, 'subkey' => '',
                'targettable' => 't', 'targetid' => $targetid, 'outcome' => 'imported', 'reason' => null, 'detail' => null,
                'runid' => 1, 'timecreated' => 1];
        }
        $DB->insert_records(legacymap::TABLE, $rows);

        $map = new legacymap();
        $map->preload('local_toy_org');
        $this->assertSame(21, $map->resolve('local_toy_item', 1), 'a lookup caches its answer');
        $DB->delete_records(legacymap::TABLE);

        $map->forget('local_toy_org');
        $map->forget('local_toy_item');
        $this->assertSame(11, $map->resolve('local_toy_org', 1), 'a declared preload survives forget(): no per-row queries');
        $this->assertNull($map->resolve('local_toy_item', 1), 'what a step cached on the way is dropped');

        $map->reset();
        $this->assertNull($map->resolve('local_toy_org', 1), 'reset() forgets everything');
    }

    public function test_lookups_refresh_picks_up_rows_an_earlier_feature_wrote(): void {
        global $DB;
        $this->resetAfterTest();
        $lookups = new lookups();
        $this->assertFalse($lookups->has_orgs(), 'nothing yet: the empty answer is now cached');
        $DB->insert_record('local_sentientia_org', (object) ['fullname' => 'Root', 'shortname' => 'r', 'parentid' => 0,
            'path' => '/1', 'depth' => 1, 'visible' => 1, 'sortorder' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        $this->assertFalse($lookups->has_orgs(), 'a cached set does not see the new row by itself');
        $lookups->refresh();
        $this->assertTrue($lookups->has_orgs());
        $this->assertNotNull($lookups->org_by_path('/1'));
    }

    public function test_every_read_of_the_organisation_table_goes_through_the_rule_the_runner_sets(): void {
        $this->resetAfterTest();
        $lookups = new lookups();
        $lookups->guard_org_reads(function (): void {
            throw new bizlms_exception('org_read_not_allowed');
        });
        $reads = [
            'orgs' => fn() => $lookups->orgs(),
            'has_orgs' => fn() => $lookups->has_orgs(),
            'org' => fn() => $lookups->org(1),
            'org_by_path' => fn() => $lookups->org_by_path('/1'),
            'the tenant resolver' => fn() => (new tenant_resolver($lookups))->org_for_path('/1'),
            'exists() on the organisation table' => fn() => $lookups->exists('local_sentientia_org', 1),
        ];
        foreach ($reads as $name => $read) {
            try {
                $read();
                $this->fail("{$name} read organisations past the rule");
            } catch (bizlms_exception $e) {
                $this->assertSame('org_read_not_allowed', $e->getMessage(), $name);
            }
        }
        // Users, courses and the rest are not organisations.
        $this->assertIsBool($lookups->user_exists(2));
        $this->assertIsBool($lookups->exists('course', 1));

        $lookups->guard_org_reads(null);
        $this->assertIsBool($lookups->has_orgs());
        $this->assertFalse($lookups->exists('local_sentientia_org', 1), 'with no rule, and no organisation, nothing exists');
    }

    public function test_the_tripwire_also_watches_tables_a_core_api_writes_without_an_event(): void {
        global $DB;
        $this->resetAfterTest();
        foreach (['user_preferences', 'role_capabilities', 'context', 'grade_grades', 'grade_grades_history',
                  'groups_members', 'cohort_members'] as $table) {
            $this->assertContains($table, sideeffect_guard::TABLES);
        }
        // files is watched for every importer (IDN-04); only a copies_files importer may add rows, in its areas.
        $this->assertContains('files', sideeffect_guard::TABLES);

        $roleid = $this->getDataGenerator()->create_role();
        $before = sideeffect_guard::snapshot();
        $this->assertArrayHasKey('role_capabilities', $before);
        $DB->insert_record('role_capabilities', (object) ['contextid' => \context_system::instance()->id, 'roleid' => $roleid,
            'capability' => 'local/blmtest:x', 'permission' => CAP_ALLOW, 'timemodified' => time(), 'modifierid' => 0]);
        $this->assertSame(['role_capabilities'], sideeffect_guard::violations($before, sideeffect_guard::snapshot()));
    }

    public function test_the_tripwire_sees_a_file_and_counts_copies_per_declared_area(): void {
        $this->resetAfterTest();
        $before = sideeffect_guard::snapshot();
        $this->assertArrayHasKey('files', $before);

        toy_importer::write_file('local_sentientia_platform', 'toytarget');
        $this->assertSame(['files'], sideeffect_guard::violations($before, sideeffect_guard::snapshot()),
            'an importer with no marker trips on a file');

        // The file API adds a directory row beside the file: it is part of the area and is not a copy.
        $found = sideeffect_guard::files_in_areas((int) $before['files'], ['local_sentientia_platform/toytarget']);
        $this->assertSame([], $found['outside']);
        $this->assertSame(['local_sentientia_platform/toytarget' => 1], $found['copied']);

        toy_importer::write_file('local_sentientia_platform', 'elsewhere');
        $found = sideeffect_guard::files_in_areas((int) $before['files'], ['local_sentientia_platform/toytarget']);
        $this->assertSame(['local_sentientia_platform/elsewhere'], $found['outside']);

        $nothing = sideeffect_guard::files_in_areas((int) sideeffect_guard::snapshot()['files'],
            ['local_sentientia_platform/toytarget']);
        $this->assertSame([], $nothing['outside']);
        $this->assertSame(['local_sentientia_platform/toytarget' => 0], $nothing['copied'], 'a declared area with no copy reads 0');
    }

    public function test_declared_file_areas_are_the_target_half_and_must_be_four_strings(): void {
        $this->resetAfterTest();
        toy_importer::reset();
        $importer = new toy_files_importer();
        $this->assertSame(['local_sentientia_platform/toytarget'], sideeffect_guard::declared_file_areas($importer));
        $this->assertTrue(sideeffect_guard::file_areas_well_formed($importer));

        foreach ([[['a', 'b']], [['a', 'b', 'c', '']], [['a', 'b', 'c', 7]], [['a', 'b', 'c', 'd', 'e']]] as $bad) {
            toy_importer::$fileareas = $bad;
            $this->assertFalse(sideeffect_guard::file_areas_well_formed($importer), json_encode($bad));
        }
        toy_importer::reset();
    }

    public function test_root_is_registered_asks_the_tenant_registry_and_not_the_organisation_table(): void {
        $this->resetAfterTest();
        $this->assertTrue(tenant_resolver::root_is_registered(1));
        $this->assertTrue(tenant_resolver::root_is_registered(77));
        $this->assertTrue(tenant_resolver::root_is_registered(177));
        $this->assertFalse(tenant_resolver::root_is_registered(999));
        $this->assertFalse(tenant_resolver::root_is_registered(0));
        $this->assertFalse(tenant_resolver::root_is_registered(-1));
        // No organisation row exists here: the answer never depended on one.
        $this->assertFalse((new lookups())->has_orgs());
    }

    public function test_the_database_group_count_is_what_the_grouped_scan_is_checked_against(): void {
        $this->resetAfterTest();
        $this->seed_toy_data();
        $reader = new legacy_reader();
        $this->assertSame(2, $reader->count_groups('local_toy_dup', ['natkey']));
        $this->assertSame(1, $reader->count_groups('local_toy_dup', ['natkey'], ['natkey = :k', ['k' => 'k1']]));
        $this->assertSame(3, $reader->count_groups('local_toy_event', ['cartid']));
    }

    public function test_a_resumed_report_continues_the_csv_instead_of_truncating_it(): void {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $first = new bizlms\report();
        $first->open_csv($path);
        $first->non_imported('toy', 'local_toy_org', 3, '', 'skipped', 'no_name', '');
        $first->close();

        $second = new bizlms\report();
        $second->open_csv($path, true);
        $second->non_imported('toy', 'local_toy_org', 4, '', 'archived', 'not_history', '');
        $second->close();

        $lines = array_values(array_filter(array_map('trim', file($path))));
        $this->assertCount(3, $lines, 'one header and both rows');
        $this->assertStringStartsWith('feature,', $lines[0]);
        $this->assertStringContainsString('local_toy_org,3', $lines[1]);
        $this->assertStringContainsString('local_toy_org,4', $lines[2]);
        unlink($path);
    }

    public function test_lookups_load_each_id_set_once_and_tell_deleted_users_apart(): void {
        global $DB;
        $this->resetAfterTest();
        $live = $this->getDataGenerator()->create_user();
        $gone = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $courseid = $this->getDataGenerator()->create_course()->id;

        $lookups = new lookups();
        $this->assertTrue($lookups->user_exists((int) $live->id));
        $this->assertTrue($lookups->user_active((int) $live->id));
        $this->assertTrue($lookups->user_exists((int) $gone->id), 'history of deleted users is imported');
        $this->assertFalse($lookups->user_active((int) $gone->id));
        $this->assertFalse($lookups->user_exists(987654));
        $this->assertTrue($lookups->course_exists((int) $courseid));
        $this->assertFalse($lookups->course_exists(987654));
        $this->assertTrue($lookups->exists('course', (int) $courseid));
    }

    // Outcome detail, report, parity comparison, map view.

    public function test_a_skip_detail_is_codes_only(): void {
        $this->assertSame('', outcome::skip(1, 'orphan_user')->detail);
        $this->assertSame('user_not_found', outcome::skip(1, 'orphan_user', 'user_not_found')->detail);
        $this->assertSame('truncated:title', outcome::skip(1, 'orphan_user', 'truncated:title')->detail);
        // legacymap.detail lives in a framework table that holds no personal data: no id, no name, no text.
        foreach (['orphan_user:123', '123', 'Jane Doe', 'jane@example.com', 'user not found', 'UserNotFound', 'a:b:c:d:e'] as $bad) {
            try {
                outcome::skip(1, 'orphan_user', $bad);
                $this->fail("accepted the detail '{$bad}'");
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('codes only', $e->getMessage());
            }
        }
    }

    public function test_a_fan_out_subkey_is_a_code_and_never_names_a_user(): void {
        $row = (object) ['x' => 1];
        foreach (['', 'part:a', 'course:17', 'quiz:17', 'history', 'synth_payment', 'skill:9', 'pos:2', 'org:extra'] as $good) {
            $this->assertSame($good, outcome::insert(1, 'local_sentientia_toy_fanout', $row, $good)->subkey);
        }
        // legacymap.subkey lives in a framework table that holds no personal data: an id that names a person is one.
        foreach (['user:55', 'userid:55', 'email:a', 'trainer:9', 'name', 'Part:a', 'part:', ':a', 'a:b:c', 'part a',
                  'jane@example.com', 'x:' . str_repeat('1', 31), str_repeat('a', 32)] as $bad) {
            try {
                outcome::insert(1, 'local_sentientia_toy_fanout', $row, $bad);
                $this->fail("accepted the subkey '{$bad}'");
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('subkey must be a code', $e->getMessage());
            }
        }
    }

    public function test_report_holds_per_row_lines_until_the_transaction_commits(): void {
        $report = new report();
        $csv = make_request_directory() . '/lines.csv';
        $report->open_csv($csv);

        // A batch that commits.
        $report->hold();
        $report->count_reason('f', 'f.s', 'a_reason');
        $report->non_imported('f', 't', 1, '', 'skipped', 'a_reason', '');
        $this->assertArrayNotHasKey('steps', $report->to_array()['features']['f'] ?? [], 'nothing is reported before the commit');
        $report->release();
        $this->assertSame(1, $report->to_array()['features']['f']['steps']['f.s']['skipped_by_reason']['a_reason']);

        // A batch that rolls back leaves nothing behind.
        $report->hold();
        $report->count_reason('f', 'f.s', 'a_reason');
        $report->non_imported('f', 't', 2, '', 'skipped', 'a_reason', '');
        $report->discard();

        // A batch inside a feature-mode transaction is not durable until the outer commit.
        $report->hold();
        $report->hold();
        $report->non_imported('f', 't', 3, '', 'skipped', 'a_reason', '');
        $report->release();
        $report->close();
        $this->assertCount(2, file($csv), 'header and row 1 only: the inner commit is not durable yet');
        $report->open_csv($csv, true);
        $report->release();
        $report->close();
        $this->assertCount(3, file($csv), 'the outer commit lists row 3');

        // An outer rollback drops what the inner commit had handed up.
        $report->hold();
        $report->hold();
        $report->non_imported('f', 't', 4, '', 'skipped', 'a_reason', '');
        $report->release();
        $report->discard();
        $report->open_csv($csv, true);
        $report->close();
        $this->assertCount(3, file($csv));
        $this->assertSame(1, $report->to_array()['features']['f']['steps']['f.s']['skipped_by_reason']['a_reason'],
            'only the committed batch was counted');
    }

    public function test_a_comparison_never_reads_a_skipped_crc_as_a_pass(): void {
        $problems = parity::comparison_problems([
            'drift' => ['local_x rows 3->4 maxid 3->4'], 'missing' => ['local_y'], 'skipped' => ['local_z'], 'new' => ['local_new'],
        ]);
        $this->assertSame(['legacy_table_changed:local_x rows 3->4 maxid 3->4', 'legacy_table_missing:local_y'], $problems['hard']);
        $this->assertSame(['legacy_table_crc_skipped:local_z', 'legacy_table_not_in_the_baseline:local_new'], $problems['unproven']);
        $clean = parity::comparison_problems(['drift' => [], 'missing' => [], 'skipped' => [], 'new' => []]);
        $this->assertSame(['hard' => [], 'unproven' => []], $clean);

        // The comparison itself: a null CRC on either side is skipped, never equal.
        $fp = ['count' => 2, 'maxid' => 2, 'columns' => ['id'], 'crc' => null];
        $cmp = parity::compare_fingerprints(['local_z' => $fp], ['local_z' => $fp]);
        $this->assertSame(['local_z'], $cmp['skipped']);
        $this->assertSame([], parity::comparison_problems($cmp)['hard']);
        $this->assertNotSame([], parity::comparison_problems($cmp)['unproven']);
    }

    public function test_a_step_gets_a_read_only_view_of_the_map(): void {
        $this->resetAfterTest();
        $ctx = \local_sentientia_platform\bizlms\context::build(new toy_importer(), true, 0, decisions::none());
        foreach (['remember', 'reset', 'forget', 'begin_batch', 'commit_batch', 'rollback_batch', 'preload'] as $method) {
            $this->assertFalse(method_exists($ctx->map, $method), "the view must not expose {$method}()");
        }
        foreach (['resolve', 'resolve_many', 'entry', 'entries'] as $method) {
            $this->assertTrue(method_exists($ctx->map, $method));
        }
    }
}
