<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacy_tables;
use local_sentientia_platform\bizlms\parity;
use local_sentientia_platform\bizlms\parity_gate;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\parity\baseline as parity_baseline;
use local_sentientia_platform\parity\config as parity_config;
use local_sentientia_platform\parity\core as parity_core;
use local_sentientia_platform\parity\legacy as parity_legacy;
use local_sentientia_platform\parity\metrics as parity_metrics;
use local_sentientia_platform\tests\parity\fake_database;

/**
 * The parity library (cli/source_baseline.php): the version-aware metrics, the legacy fingerprints' comparison, the
 * explanation of the import's core writes, and the config reader. No database: the metrics run over a fake one that
 * records the statements they ask.
 *
 * The same file is copied to the SOURCE box and run there with PHP 7.4 to 8.4, so one test also reads it with the PHP 7.4
 * grammar.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\parity_gate
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class parity_library_test extends \basic_testcase {

    /** @var string[] The columns of scorm_scoes_track in Moodle 4.1 (mod/scorm/db/install.xml). */
    private const TRACK = ['id', 'userid', 'scormid', 'scoid', 'attempt', 'element', 'value', 'timemodified'];

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        parity_gate::load_library();
    }

    // The library is one file that runs on the source.

    public function test_the_library_reads_as_php_74(): void {
        if (!class_exists(\PhpParser\ParserFactory::class) || !method_exists(\PhpParser\ParserFactory::class, 'createForVersion')) {
            $this->markTestSkipped('nikic/php-parser 5 is not installed.');
        }
        $parser = (new \PhpParser\ParserFactory())->createForVersion(\PhpParser\PhpVersion::fromComponents(7, 4));
        $code = (string) file_get_contents(__DIR__ . '/../cli/source_baseline.php');
        $this->assertNotEmpty($parser->parse($code), 'the file the source box runs must parse with the PHP 7.4 grammar');
    }

    public function test_the_two_lists_of_bizlms_tables_are_one_list(): void {
        $this->assertSame(legacy_tables::KNOWN, parity_legacy::KNOWN,
            'cli/source_baseline.php cannot load the plugin, so it holds a copy: change both together');
    }

    public function test_every_core_write_the_registry_allows_is_in_the_baseline_and_explained(): void {
        $allowed = array_keys(registry::CORE_WRITES_ALLOWED);
        $captured = array_keys(parity_core::WRITES);
        sort($allowed);
        sort($captured);
        $this->assertSame($allowed, $captured, 'a core table an importer may write must be in the baseline (parity\core::WRITES)');
        $this->assertSame([], parity_gate::unexplained_core_writes(),
            'and must be explained by the import\'s records (parity_gate::INSERT_TABLES or ::LEDGERS)');
        foreach (parity_core::WRITES as $table => $spec) {
            $this->assertContains($spec['mode'], ['insert', 'update'], $table);
            $this->assertSame([], array_intersect($spec['fixed'], $spec['writable']),
                "{$table}: a writable column is not in the fixed hash, or its change would move the old rows' CRC");
            if ($spec['mode'] === 'insert') {
                $this->assertContains($table, parity_gate::INSERT_TABLES, $table);
                $this->assertSame([], $spec['writable'], $table);
                // The parity check holds every old row of an insert table to the baseline, so the registry must not review an UPDATE of
                // it: that would pass review and only fail at the post-import compare (Stage B tools review, 2026-10-08).
                $this->assertSame(['insert'], registry::core_write_operations($table), $table);
            } else {
                $this->assertArrayHasKey($table, parity_gate::LEDGERS, $table);
                $this->assertNotEmpty($spec['writable'], $table);
                $this->assertContains('update', registry::core_write_operations($table), $table);
            }
        }
        // Both directions, operation by operation: the gate explains exactly what the registry reviews. A reviewed INSERT needs the
        // map (INSERT_TABLES), a reviewed UPDATE needs the importer's ledger (LEDGERS) and a baseline that lets the old rows change in
        // the ledger's columns only. enrol is both (the G6 instance, and the CRS-01 switch-off): it was held insert-only once, which
        // refused a clean import at the post-import compare (Stage B tools fix round 2).
        foreach (registry::CORE_WRITES_ALLOWED as $table => $entry) {
            $operations = $entry['operations'];
            $this->assertSame(in_array('insert', $operations, true), in_array($table, parity_gate::INSERT_TABLES, true),
                "{$table}: insert is reviewed exactly when the map explains it");
            $this->assertSame(in_array('update', $operations, true), array_key_exists($table, parity_gate::LEDGERS),
                "{$table}: update is reviewed exactly when a ledger explains it");
            $this->assertSame(in_array('update', $operations, true), parity_core::WRITES[$table]['mode'] === 'update',
                "{$table}: an updated table is held as an update table by the baseline");
            if (isset(parity_gate::LEDGERS[$table]) && parity_gate::LEDGERS[$table]['columns'] === null) {
                $written = parity_gate::LEDGERS[$table]['written'];
                $writable = parity_core::WRITES[$table]['writable'];
                sort($written);
                sort($writable);
                $this->assertSame($writable, $written, "{$table}: the ledger names the columns the baseline lets change");
            }
        }
        $this->assertSame(['insert', 'update'], registry::core_write_operations('enrol'));
        foreach (parity_gate::COUNT_KEYS as $metric => $table) {
            $this->assertContains($table, parity_gate::INSERT_TABLES, $metric);
        }
    }

    public function test_enrol_status_and_timemodified_are_the_only_columns_a_switched_off_instance_may_change(): void {
        $spec = parity_core::WRITES['enrol'];
        $this->assertSame('update', $spec['mode']);
        $this->assertSame(['status', 'timemodified'], $spec['writable']);
        $this->assertNotContains('status', $spec['fixed']);
        $this->assertNotContains('timemodified', $spec['fixed']);
        foreach (['id', 'enrol', 'courseid', 'roleid', 'customint1', 'timecreated'] as $column) {
            $this->assertContains($column, $spec['fixed'], $column);
        }
        $this->assertSame(['status', 'timemodified'], parity_gate::LEDGERS['enrol']['written']);
        $this->assertSame('local_sentientia_courses_enroloff', parity_gate::LEDGERS['enrol']['table']);
        $this->assertContains('enrol', parity_gate::INSERT_TABLES, 'it is also inserted into');
    }

    public function test_the_gate_names_the_bizlms_enrol_methods_the_importer_switches_off(): void {
        $this->assertSame(['classroom', 'learningplan', 'program'], parity_gate::BIZLMS_ENROL_METHODS);
        $importer = '\local_sentientia_courses\bizlms\enrolments_importer';
        if (!class_exists($importer)) {
            $this->markTestSkipped('local_sentientia_courses is not installed on this site');
        }
        $this->assertSame($importer::METHODS, parity_gate::BIZLMS_ENROL_METHODS,
            'the platform cannot depend on the courses plugin, so it holds a copy: change both together');
    }

    // The metrics are version aware.

    public function test_scorm_is_read_from_scorm_scoes_track_before_moodle_4_3(): void {
        $db = new fake_database(['user' => ['id', 'deleted', 'suspended'], 'scorm_scoes_track' => self::TRACK],
            ['GROUP BY userid, scormid, attempt' => '17', 'FROM {scorm_scoes_track} t' => '240']);
        $m = parity_metrics::collect($db);

        $this->assertSame('track', $m['layout']['scorm']);
        $this->assertSame(17, $m['counts']['scorm_attempts'], 'distinct (user, scorm, attempt)');
        $this->assertSame(240, $m['counts']['scorm_tracks']);
        $this->assertArrayHasKey('scorm_tracks', $m['checksums']);
        $this->assertStringNotContainsString('scorm_attempt', $db->asked(), 'a 4.1 database has no such table');
        $this->assertStringNotContainsString('scorm_scoes_value', $db->asked());
    }

    public function test_scorm_is_read_from_scorm_attempt_from_moodle_4_3(): void {
        $db = new fake_database(['user' => ['id', 'deleted', 'suspended'], 'scorm_attempt' => ['id', 'userid', 'scormid', 'attempt'],
            'scorm_scoes_value' => ['id', 'scoid', 'attemptid', 'elementid', 'value', 'timemodified'],
            'scorm_element' => ['id', 'element']],
            ['FROM {scorm_attempt} t' => '17', 'FROM {scorm_scoes_value} t' => '240']);
        $m = parity_metrics::collect($db);

        $this->assertSame('value', $m['layout']['scorm']);
        $this->assertSame(17, $m['counts']['scorm_attempts']);
        $this->assertSame(240, $m['counts']['scorm_tracks']);
        $this->assertStringNotContainsString('scorm_scoes_track', $db->asked(), 'the 4.3 upgrade dropped that table');
        $this->assertStringContainsString('JOIN {scorm_attempt} a ON a.id = v.attemptid', $db->asked());
        $this->assertSame(['userid', 'scormid', 'scoid', 'attempt', 'element', 'value', 'timemodified'],
            $m['checksums']['scorm_tracks']['cols'], 'the same logical row on either layout');
    }

    public function test_an_unfinished_scorm_upgrade_leaves_the_scorm_numbers_out_and_says_so(): void {
        $db = new fake_database(['user' => ['id', 'deleted', 'suspended'], 'scorm_scoes_track' => self::TRACK,
            'scorm_attempt' => ['id'], 'scorm_scoes_value' => ['id'], 'scorm_element' => ['id']]);
        $m = parity_metrics::collect($db);
        $this->assertSame('both', $m['layout']['scorm']);
        $this->assertArrayNotHasKey('scorm_attempts', $m['counts']);
        $this->assertArrayNotHasKey('scorm_tracks', $m['checksums']);
        $this->assertNotEmpty(preg_grep('/both exist/', $m['notes']));
    }

    public function test_no_scorm_tables_means_no_scorm_numbers(): void {
        $m = parity_metrics::collect(new fake_database(['user' => ['id', 'deleted', 'suspended']]));
        $this->assertSame('none', $m['layout']['scorm']);
        $this->assertArrayNotHasKey('scorm_attempts', $m['counts']);
    }

    public function test_a_table_or_column_a_version_lacks_is_left_out_not_an_error(): void {
        $db = new fake_database(['user' => ['id', 'deleted', 'suspended', 'username']]);
        $m = parity_metrics::collect($db);
        $this->assertArrayHasKey('users_total_active', $m['counts']);
        $this->assertArrayNotHasKey('users_tenant_airpay', $m['counts'], 'no open_path column, no tenant counts');
        $this->assertNotEmpty(preg_grep('/open_path is absent/', $m['notes']));
        $this->assertArrayNotHasKey('forum_posts', $m['counts']);
        $this->assertArrayNotHasKey('grade_finalgrade_sum', $m['aggregates']);
        $this->assertSame(['id', 'username', 'suspended', 'deleted'], $m['checksums']['user']['cols'],
            'only the listed columns the table has, in the listed order');
    }

    public function test_the_tenant_cross_foot(): void {
        $this->assertNull(parity_metrics::cross_foot([]), 'no tenant counts, nothing to foot');
        $ok = ['users_tenant_airpay' => 5, 'users_tenant_public' => 3, 'users_tenant_zeea' => 1, 'users_tenant_other' => 2,
            'users_total_active' => 11];
        $this->assertNull(parity_metrics::cross_foot($ok));
        $this->assertStringContainsString('add up to 10', (string) parity_metrics::cross_foot(['users_tenant_other' => 1] + $ok));
    }

    // Metrics version 3: the substrate the first sets left out.

    public function test_the_bizlms_user_and_course_substrate_is_checksummed_under_keys_of_its_own(): void {
        $user = array_merge(['id', 'deleted', 'suspended', 'username', 'password', 'idnumber', 'theme', 'timemodified'],
            ['open_path', 'open_supervisorid', 'open_employeeid', 'open_designation', 'gender']);
        $db = new fake_database([
            'user' => $user,
            'course' => ['id', 'shortname', 'idnumber', 'theme', 'open_hrmsrole', 'open_cost', 'enableaitools'],
            'course_modules' => ['id', 'course', 'module', 'instance', 'section', 'completion', 'lang', 'enableaitools'],
            'course_sections' => ['id', 'course', 'section', 'name', 'sequence', 'component'],
        ]);
        $m = parity_metrics::collect($db);

        // The first sets are unchanged: their keys and columns are what a format 1 or 2 baseline holds.
        $this->assertSame(['id', 'username', 'open_path', 'suspended', 'deleted'], $m['checksums']['user']['cols']);
        $this->assertSame(['id', 'shortname'], $m['checksums']['course']['cols']);

        $this->assertSame(['id', 'password', 'idnumber', 'open_supervisorid', 'open_employeeid', 'open_designation', 'gender'],
            $m['checksums']['user_bizlms']['cols'], 'password hashes and the BizLMS open_* columns, own key');
        $this->assertSame(['id', 'idnumber', 'open_hrmsrole'], $m['checksums']['course_bizlms']['cols'],
            'the open_* columns the import writes (open_cost) are not here, and neither is the theme');
        $this->assertSame(['id', 'course', 'module', 'instance', 'section', 'completion', 'lang'],
            $m['checksums']['course_modules']['cols'], 'a column a version lacks (5.x enableaitools) is never hashed');
        $this->assertSame(['id', 'course', 'section'], $m['checksums']['course_sections']['cols'],
            'neither the name nor the sequence, which an upgrade or a first visit fills in');
        $this->assertSame(0, $m['counts']['course_modules']);
    }

    public function test_no_checksum_ever_hashes_a_theme_column_or_a_column_the_import_writes(): void {
        $constants = (new \ReflectionClass(parity_metrics::class))->getConstants();
        $lists = [];
        foreach (['CHECKSUMS', 'ROUNDED'] as $name) {
            $lists += $constants[$name];
        }
        foreach ($constants['MORE'] as $key => $spec) {
            $lists['more:' . $key] = $spec[1];
        }
        foreach ($lists as $key => $columns) {
            $this->assertNotContains('theme', $columns, "{$key}: step 07 of the rehearsal kit clears theme overrides");
        }
        // The eight course columns the import fills (parity\core::WRITES) are in no whole-table checksum of course.
        foreach (parity_core::WRITES['course']['writable'] as $column) {
            $this->assertNotContains($column, $constants['MORE']['course_bizlms'][1], $column);
            $this->assertNotContains($column, $constants['CHECKSUMS']['course'], $column);
        }
        foreach (parity_core::WRITES as $table => $spec) {
            $this->assertNotContains('theme', array_merge($spec['fixed'], $spec['writable']), $table);
        }
    }

    public function test_a_baseline_of_another_metrics_version_is_refused_never_half_compared(): void {
        $mine = ['metrics' => parity_metrics::VERSION, 'sha256' => parity_metrics::tool_sha256()];
        $this->assertNull(parity_metrics::baseline_problem(['counts' => ['courses' => 1]]), 'format 1 has no tool section');
        $this->assertNull(parity_metrics::baseline_problem(['tool' => $mine]));
        $older = (string) parity_metrics::baseline_problem(['tool' => ['metrics' => parity_metrics::VERSION - 1] + $mine]);
        $this->assertStringContainsString('metrics version ' . (parity_metrics::VERSION - 1), $older);
        $this->assertStringContainsString('Take the baseline again', $older);
        $this->assertNotNull(parity_metrics::baseline_problem(['tool' => ['metrics' => parity_metrics::VERSION + 1] + $mine]));
    }

    public function test_the_same_metrics_version_from_another_file_is_refused_too(): void {
        // A change that adds a checksum without bumping VERSION would otherwise compare only the keys the baseline holds.
        $sha = parity_metrics::tool_sha256();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sha);
        $this->assertSame(hash('sha256', str_replace("\r", '', (string) file_get_contents(__DIR__ . '/../cli/source_baseline.php'))),
            $sha, 'the hash is of this file with every CR removed: tr -d "\r" < cli/source_baseline.php | sha256sum');

        $other = (string) parity_metrics::baseline_problem(['tool' => ['metrics' => parity_metrics::VERSION,
            'sha256' => str_repeat('a', 64)]]);
        $this->assertStringContainsString('another version of cli/source_baseline.php', $other);
        $this->assertStringContainsString(substr($sha, 0, 12), $other, 'it says which file this is');

        $none = (string) parity_metrics::baseline_problem(['tool' => ['metrics' => parity_metrics::VERSION]]);
        $this->assertStringContainsString('does not say which tool took it', $none);

        $this->assertNull(parity_metrics::baseline_problem(['tool' => ['metrics' => parity_metrics::VERSION,
            'sha256' => strtoupper($sha)]]), 'the case of the hex digits does not matter');
    }

    public function test_the_baseline_records_its_metrics_version_and_the_tool_that_took_it(): void {
        $doc = parity_baseline::build(new fake_database(['user' => ['id', 'deleted', 'suspended']]), [], null, ['metrics']);
        $this->assertSame(parity_metrics::VERSION, $doc['tool']['metrics']);
        $this->assertSame(parity_metrics::tool_sha256(), $doc['tool']['sha256']);
        $this->assertNull(parity_metrics::baseline_problem($doc), 'a baseline this tool just took is one it can compare');
    }

    public function test_module_types_the_release_no_longer_has_are_named_beside_the_drift(): void {
        $base = ['counts' => ['course_modules' => 5], 'checksums' => [], 'dbfamily' => 'mysql',
            'layout' => ['modules' => ['assign', 'chat', 'survey']]];
        $now = ['counts' => ['course_modules' => 4], 'checksums' => [], 'dbfamily' => 'mysql',
            'layout' => ['modules' => ['assign', 'subsection']]];
        $lines = [];
        $r = parity_baseline::compare_metrics($base, $now, function (string $l) use (&$lines): void {
            $lines[] = $l;
        });
        $this->assertSame(1, $r['drift'], 'the lost activity is the drift; the note only says why');
        $note = implode("\n", preg_grep('/NOTE\s+module type/', $lines));
        $this->assertStringContainsString('chat, survey', $note);
        $this->assertStringNotContainsString('subsection', $note, 'a module type the release added is not a loss');

        // No module list on one side (an older baseline, or a database without the table): no claim either way.
        $lines = [];
        parity_baseline::compare_metrics(['counts' => ['courses' => 1], 'checksums' => []],
            ['counts' => ['courses' => 1], 'checksums' => [], 'layout' => ['modules' => ['assign']]],
            function (string $l) use (&$lines): void {
                $lines[] = $l;
            });
        $this->assertSame([], preg_grep('/NOTE\s+module type/', $lines));
    }

    public function test_the_module_types_are_part_of_the_layout_the_baseline_records(): void {
        $m = parity_metrics::collect(new fake_database(['user' => ['id', 'deleted', 'suspended'], 'modules' => ['id', 'name']]));
        $this->assertArrayHasKey('modules', $m['layout']);
        $this->assertSame([], $m['layout']['modules'], 'the fake database lists no rows');
        $m = parity_metrics::collect(new fake_database(['user' => ['id', 'deleted', 'suspended']]));
        $this->assertArrayNotHasKey('modules', $m['layout'], 'no modules table, no list');
    }

    public function test_checksums_are_left_out_on_an_engine_without_crc32(): void {
        $db = new fake_database(['user' => ['id', 'deleted', 'suspended'], 'course' => ['id', 'shortname']], [], 'postgres');
        $m = parity_metrics::collect($db);
        $this->assertNull($m['checksums']['course']['crc'], 'null is reported as SKIPPED by the comparison, never a match');
    }

    // The comparison.

    public function test_explained_counts_and_replaced_checksums_are_the_only_allowed_differences(): void {
        $base = ['counts' => ['enrolments' => 10, 'courses' => 3], 'aggregates' => ['grade_finalgrade_sum' => '5.0000'],
            'checksums' => ['user_enrolments' => ['rows' => 10, 'crc' => '1', 'cols' => ['id']],
                'course' => ['rows' => 3, 'crc' => '7', 'cols' => ['id']]], 'dbfamily' => 'mysql'];
        $now = ['counts' => ['enrolments' => 13, 'courses' => 3], 'aggregates' => ['grade_finalgrade_sum' => '5.0000'],
            'checksums' => ['user_enrolments' => ['rows' => 13, 'crc' => '9', 'cols' => ['id']],
                'course' => ['rows' => 3, 'crc' => '7', 'cols' => ['id']]], 'dbfamily' => 'mysql'];
        $lines = [];
        $collect = function (string $line) use (&$lines): void {
            $lines[] = $line;
        };

        $plain = parity_baseline::compare_metrics($base, $now, $collect);
        $this->assertSame(2, $plain['drift'], 'without the import\'s records the growth is drift');

        $lines = [];
        $explained = parity_baseline::compare_metrics($base, $now, $collect,
            ['counts' => ['enrolments' => 3], 'skip_checksums' => ['user_enrolments']]);
        $this->assertSame(0, $explained['drift']);
        $this->assertSame(0, $explained['skipped']);
        $this->assertNotEmpty(preg_grep('/MATCH enrolments\s+13 \(baseline 10 \+ 3/', $lines));

        // 2 rows explained, 3 inserted: still drift.
        $short = parity_baseline::compare_metrics($base, $now, $collect,
            ['counts' => ['enrolments' => 2], 'skip_checksums' => ['user_enrolments']]);
        $this->assertSame(1, $short['drift']);
    }

    public function test_a_metric_the_baseline_has_and_the_target_lacks_is_drift_and_a_new_one_is_informational(): void {
        $base = ['counts' => ['courses' => 3, 'forum_posts' => 4], 'checksums' => ['x' => ['rows' => 1, 'crc' => '1']]];
        $now = ['counts' => ['courses' => 3, 'role_assignments' => 9], 'checksums' => [], 'dbfamily' => 'mysql'];
        $lines = [];
        $r = parity_baseline::compare_metrics($base, $now, function (string $l) use (&$lines): void {
            $lines[] = $l;
        });
        $this->assertSame(2, $r['drift'], 'forum_posts count and the x checksum are gone');
        $this->assertNotEmpty(preg_grep('/NEW\s+role_assignments/', $lines));
    }

    public function test_a_baseline_without_checksums_is_not_proof(): void {
        $r = parity_baseline::compare_metrics(['counts' => ['courses' => 1]], ['counts' => ['courses' => 1], 'checksums' => []],
            function (string $l): void {
            });
        $this->assertSame(0, $r['drift']);
        $this->assertSame(1, $r['skipped'], 'the baseline predates value checksums: unproven, exit 2');
    }

    public function test_the_standalone_legacy_comparison_gives_the_same_verdict_as_the_framework_hook(): void {
        $fp = static fn(int $count, int $maxid, ?string $crc, array $columns = ['id']): array =>
            ['count' => $count, 'maxid' => $maxid, 'crc' => $crc, 'columns' => $columns];
        $base = ['a' => $fp(5, 5, '10'), 'b' => $fp(5, 5, '10'), 'c' => $fp(5, 5, '10'), 'd' => $fp(5, 5, null),
            'e' => $fp(5, 5, '10'), 'f' => $fp(5, 5, '10')];
        $now = ['a' => $fp(5, 5, '10'), 'b' => $fp(6, 6, '10'), 'c' => $fp(5, 5, '11'), 'd' => $fp(5, 5, null),
            'e' => $fp(5, 5, '10', ['id', 'x']), 'g' => $fp(1, 1, '3')];
        $this->assertSame(parity::compare_fingerprints($base, $now), parity_legacy::compare($base, $now));
        $comparison = parity_legacy::compare($base, $now);
        $this->assertSame(parity::comparison_problems($comparison), parity_legacy::problems($comparison));
        $this->assertSame(['f'], $comparison['missing']);
        $this->assertSame(['d'], $comparison['skipped']);
        $this->assertSame(['g'], $comparison['new']);
        $this->assertCount(3, $comparison['drift'], 'rows, crc and a column change');
    }

    public function test_the_other_legacy_prefixed_tables_are_never_a_hard_failure(): void {
        $fp = ['count' => 5, 'maxid' => 5, 'crc' => '10', 'columns' => ['id']];
        $lines = parity_legacy::other_unproven(['t' => $fp, 'u' => $fp], ['t' => ['count' => 6] + $fp]);
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('other_table_changed:', $lines[0]);
        $this->assertSame('other_table_missing:u', $lines[1]);
    }

    public function test_which_tables_are_other_legacy_tables(): void {
        $db = new fake_database([
            'local_classroom' => ['id'], 'local_sentientia_org' => ['id'], 'block_instances' => ['id'],
            'block_learnerscript' => ['id'], 'paygw_airpay' => ['id'], 'paygw_paypal' => ['id'], 'course' => ['id'],
            'local_costcenter' => ['id'],
        ]);
        $this->assertSame(['local_classroom', 'local_costcenter'], parity_legacy::known_present($db));
        $this->assertSame(['block_learnerscript', 'paygw_airpay'], parity_legacy::other_present($db),
            'stock Moodle, Sentientia and the 22 plugins\' own tables are not "other"');
    }

    // The explanation of the import's core writes.

    /**
     * @param array<string, array> $overrides
     * @return array{0: array, 1: array, 2: array} baseline core section, evidence, expected
     */
    private function core_case(array $overrides = []): array {
        $base = [
            'user_enrolments' => ['mode' => 'insert', 'count' => 10, 'maxid' => 10, 'crc' => '123', 'fixed' => ['id'],
                'writable' => []],
            'course' => ['mode' => 'update', 'count' => 3, 'maxid' => 3, 'crc' => '55', 'fixed' => ['id'],
                'writable' => ['open_cost', 'open_skill'], 'rows' => ['1|1|1,2', '2|2|3,4', '3|3|5,6']],
        ];
        $evidence = [
            'user_enrolments' => ['count' => 13, 'old_count' => 10, 'old_crc' => '123', 'new_ids' => [11, 12, 13],
                'changed' => [], 'fixed_changed' => [], 'removed' => []],
            'course' => ['count' => 3, 'old_count' => 3, 'old_crc' => '55', 'new_ids' => [],
                'changed' => [2 => ['open_cost']], 'fixed_changed' => [], 'removed' => []],
        ];
        $expected = ['user_enrolments' => ['inserted' => [11, 12, 13]], 'course' => ['changed' => [2 => ['open_cost']]]];
        foreach ($overrides as $key => $value) {
            [$part, $table] = explode('.', $key);
            ${$part}[$table] = $value + ${$part}[$table];
        }
        return [$base, $evidence, $expected];
    }

    public function test_a_change_the_import_records_is_explained(): void {
        [$base, $evidence, $expected] = $this->core_case();
        $verdict = parity_core::evaluate($base, $evidence, $expected);
        $this->assertSame([], $verdict['hard']);
        $this->assertSame([], $verdict['unproven']);
        $this->assertCount(2, $verdict['lines']);
        $this->assertStringContainsString('+3 inserted by the import', $verdict['lines'][0]);
    }

    public function test_an_old_row_that_changed_is_not_explained_by_any_record(): void {
        [$base, $evidence, $expected] = $this->core_case(['evidence.user_enrolments' => ['old_crc' => '124']]);
        $hard = implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']);
        $this->assertStringContainsString('core_rows_changed:user_enrolments: crc of the old rows 123->124', $hard);
        $this->assertStringContainsString('only inserts into this table', $hard);

        [$base, $evidence, $expected] = $this->core_case(['evidence.user_enrolments' => ['old_count' => 9]]);
        $this->assertStringContainsString('rows with an old id 10->9',
            implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']));
    }

    public function test_a_row_the_import_did_not_record_and_a_recorded_row_that_is_not_there(): void {
        [$base, $evidence, $expected] = $this->core_case(['evidence.user_enrolments' => ['new_ids' => [11, 12, 13, 14]]]);
        $this->assertStringContainsString('core_rows_added_not_in_the_import:user_enrolments: 1 (ids 14)',
            implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']));

        [$base, $evidence, $expected] = $this->core_case(['evidence.user_enrolments' => ['new_ids' => [11, 12]]]);
        $this->assertStringContainsString('core_rows_in_the_import_not_found:user_enrolments: 1 (ids 13)',
            implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']));
    }

    public function test_a_course_may_differ_only_in_the_columns_its_ledger_names(): void {
        // An extra column changed.
        [$base, $evidence, $expected] = $this->core_case(['evidence.course' => ['changed' => [2 => ['open_cost', 'open_skill']]]]);
        $this->assertStringContainsString('core_column_changed_not_in_the_import:course:id=2 columns open_skill',
            implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']));

        // A row nobody named.
        [$base, $evidence, $expected] = $this->core_case(['evidence.course' => ['changed' => [2 => ['open_cost'], 3 => ['open_skill']]]]);
        $this->assertStringContainsString('core_row_changed_not_in_the_import:course:id=3',
            implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']));

        // A ledger that names a row or column that did not change.
        [$base, $evidence, $expected] = $this->core_case(['expected.course' => ['changed' => [2 => ['open_cost', 'open_skill'], 1 => ['open_cost']]]]);
        $hard = implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']);
        $this->assertStringContainsString('core_ledger_names_an_unchanged_column:course:id=2 columns open_skill', $hard);
        $this->assertStringContainsString('core_ledger_names_an_unchanged_row:course:id=1', $hard);

        // A fixed column (the name, the tenant path) changed, or a course went.
        [$base, $evidence, $expected] = $this->core_case(['evidence.course' => ['fixed_changed' => [3], 'removed' => [1]]]);
        $hard = implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']);
        $this->assertStringContainsString('core_fixed_column_changed:course:id=3', $hard);
        $this->assertStringContainsString('core_row_removed:course:id=1', $hard);
    }

    public function test_what_cannot_be_checked_is_unproven_not_passed(): void {
        [$base, $evidence, $expected] = $this->core_case(['base.user_enrolments' => ['crc' => null]]);
        $verdict = parity_core::evaluate($base, $evidence, $expected);
        $this->assertSame([], $verdict['hard']);
        $this->assertSame(['core_crc_skipped:user_enrolments'], $verdict['unproven']);

        [$base, $evidence, $expected] = $this->core_case(['evidence.course' => ['rows_omitted' => true]]);
        $this->assertSame(['core_rows_not_compared:course'], parity_core::evaluate($base, $evidence, $expected)['unproven']);
    }

    public function test_a_core_table_or_column_that_went_missing_is_a_hard_problem(): void {
        [$base, $evidence, $expected] = $this->core_case();
        $evidence['user_enrolments'] = ['missing' => true];
        $evidence['course'] = ['columns_missing' => ['open_cost']];
        $hard = implode(' | ', parity_core::evaluate($base, $evidence, $expected)['hard']);
        $this->assertStringContainsString('core_table_missing:user_enrolments', $hard);
        $this->assertStringContainsString('core_columns_missing:course:open_cost', $hard);
    }

    // The configuration is read as data.

    public function test_config_literals_are_read_and_the_rest_is_ignored(): void {
        $code = "<?php\nunset(\$CFG);\nglobal \$CFG;\n\$CFG = new stdClass();\n"
            . "\$CFG->dbtype = 'mariadb';\n\$CFG->dbhost = \"db.example\";\n\$CFG->dbname = 'air' . 'pay_prod';\n"
            . "\$CFG->dbpass = getenv('SENTIENTIA_PARITY_TEST_VARIABLE_THAT_IS_NOT_SET');\n"
            . "\$CFG->prefix = 'mdl_'; // a comment\n"
            . "\$CFG->dboptions = array('dbpersist' => 0, 'dbport' => 3307, 'dbsocket' => '', 'clientflags' => 0 | 2048);\n"
            . "\$CFG->wwwroot = 'https://www.airpay.academy';\n\$CFG->dataroot = computed('/x');\n"
            . "\$CFG->enrolplugins = array('manual', 'self');\nrequire_once(__DIR__ . '/lib/setup.php');\n";
        $s = parity_config::literals($code);
        $this->assertSame('mariadb', $s['dbtype']);
        $this->assertSame('db.example', $s['dbhost']);
        $this->assertSame('airpay_prod', $s['dbname']);
        $this->assertSame('mdl_', $s['prefix']);
        $this->assertSame(['dbpersist' => 0, 'dbport' => 3307, 'dbsocket' => '', 'clientflags' => 2048], $s['dboptions']);
        $this->assertArrayNotHasKey('dbpass', $s, 'an unset environment variable is not a value');
        $this->assertArrayNotHasKey('dataroot', $s, 'a function call is not a literal and is never run');
        $this->assertSame(['manual', 'self'], $s['enrolplugins']);
    }

    public function test_config_database_takes_explicit_options_over_the_file_and_names_what_is_missing(): void {
        $dir = sys_get_temp_dir() . '/sentientia_parity_' . getmypid();
        mkdir($dir);
        $file = $dir . '/config.php';
        file_put_contents($file, "<?php\n\$CFG = new stdClass();\n\$CFG->dbtype = 'mysqli';\n\$CFG->dbhost = 'localhost';\n"
            . "\$CFG->dbname = 'a';\n\$CFG->dbuser = 'u';\n\$CFG->prefix = 'mdl_';\n\$CFG->wwwroot = 'https://x';\n");
        try {
            $c = parity_config::database($file, ['dbname' => 'b', 'dbpass' => 'secret']);
            $this->assertSame('b', $c['name']);
            $this->assertSame('secret', $c['pass']);
            $this->assertSame('u', $c['user']);
            $this->assertSame('mdl_', $c['prefix']);

            file_put_contents($file, "<?php\n\$CFG = new stdClass();\n\$CFG->dbtype = 'pgsql';\n");
            try {
                parity_config::database($file);
                $this->fail('PostgreSQL has no CRC32: refused');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not supported', $e->getMessage());
            }

            file_put_contents($file, "<?php\n\$CFG = new stdClass();\n\$CFG->dbtype = 'mysqli';\n\$CFG->dbhost = 'h';\n"
                . "\$CFG->dbname = 'n';\n\$CFG->dbuser = getenv('SENTIENTIA_PARITY_TEST_VARIABLE_THAT_IS_NOT_SET');\n");
            try {
                parity_config::database($file);
                $this->fail('a user the file does not hold as a plain value must be named');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('--dbuser', $e->getMessage());
            }
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }
}
