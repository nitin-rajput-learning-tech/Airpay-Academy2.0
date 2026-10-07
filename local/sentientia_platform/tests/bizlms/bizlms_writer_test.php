<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\writer;
use local_sentientia_platform\bizlms\writer_refused;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\toy_importer;

/**
 * The writer, the only code of the BizLMS import that writes (ADR-032, "Writing rules").
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\writer
 * @covers     \local_sentientia_platform\phpunit\legacy_schema_fixture
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_writer_test extends \advanced_testcase {
    use legacy_schema_fixture;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/../fixtures/bizlms/toy.install.xml'];
    }

    /**
     * @param bool $dryrun
     * @return writer A writer restricted to the toy importer's declared tables.
     */
    private function writer(bool $dryrun = false): writer {
        $this->resetAfterTest();
        toy_importer::reset();
        return (new writer($dryrun))->for_importer(new toy_importer());
    }

    /**
     * A valid local_sentientia_toy_item row.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function item(array $overrides = []): \stdClass {
        return (object) ($overrides + ['orgid' => 1, 'userid' => 1, 'title' => 'ok', 'kind' => 'a', 'tenantpath' => null,
            'timecreated' => 10, 'timemodified' => 11]);
    }

    private function assert_refused(string $fragment, callable $write): void {
        try {
            $write();
            $this->fail("the writer accepted a write that should be refused: {$fragment}");
        } catch (writer_refused $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
    }

    public function test_a_valid_row_is_inserted_and_its_id_returned(): void {
        global $DB;
        $writer = $this->writer();
        $id = $writer->insert('local_sentientia_toy_item', $this->item());
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($DB->record_exists('local_sentientia_toy_item', ['id' => $id]));
    }

    public function test_only_declared_tables_are_writable(): void {
        $writer = $this->writer();
        $this->assert_refused('undeclared_table:user', fn() => $writer->insert('user', (object) ['username' => 'x']));
    }

    public function test_a_legacy_table_is_never_writable_even_if_declared(): void {
        $this->resetAfterTest();
        toy_importer::reset();
        toy_importer::$legacytarget = true;
        $writer = (new writer(false))->for_importer(new toy_importer());
        $this->assert_refused('legacy_table_is_read_only:local_costcenter',
            fn() => $writer->insert('local_costcenter', (object) ['fullname' => 'x']));
    }

    public function test_an_unknown_field_is_refused_not_silently_dropped(): void {
        $writer = $this->writer();
        $this->assert_refused('unknown_field:local_sentientia_toy_item.nope',
            fn() => $writer->insert('local_sentientia_toy_item', $this->item(['nope' => 1])));
    }

    public function test_a_missing_required_value_is_refused_before_the_query(): void {
        $writer = $this->writer();
        // A NOT NULL integer with no default aborts the INSERT in strict mode, so it is refused first. The
        // framework's own map table has one, sourceid; every NOT NULL integer of the toy schema has a default.
        $row = (object) ['feature' => 'toy', 'sourcetable' => 'local_toy_item', 'subkey' => '', 'targettable' => '',
            'targetid' => null, 'outcome' => 'skipped', 'reason' => 'x', 'detail' => null, 'runid' => 1,
            'timecreated' => 1];
        $this->assert_refused('missing_required:local_sentientia_legacymap.sourceid',
            fn() => $writer->insert_map_rows([$row]));
        // A NOT NULL value that is given as null is refused too.
        $this->assert_refused('null_in_not_null:local_sentientia_toy_item.title',
            fn() => $writer->insert('local_sentientia_toy_item', $this->item(['title' => null])));
    }

    public function test_a_not_null_char_left_out_is_accepted_because_the_engine_defaults_it(): void {
        global $DB;
        // XMLDB creates a NOT NULL char column with no DEFAULT as DEFAULT '' on MySQL, MariaDB, PostgreSQL and
        // SQL Server (sql_generator::$default_for_char), so the live column reports a default and the INSERT does
        // not abort. The writer reads the live column, as the ADR says, so it must not refuse the row.
        $columns = $DB->get_columns('local_sentientia_toy_item');
        $this->assertTrue($columns['title']->not_null);
        $this->assertTrue($columns['title']->has_default, 'the engine gave the NOT NULL char a default');

        $writer = $this->writer();
        $row = $this->item();
        unset($row->title);
        $id = $writer->insert('local_sentientia_toy_item', $row);
        $this->assertSame('', $DB->get_field('local_sentientia_toy_item', 'title', ['id' => $id]));
    }

    public function test_every_time_column_must_be_set_explicitly(): void {
        $writer = $this->writer();
        $row = $this->item();
        unset($row->timemodified);
        $this->assert_refused('missing_timestamp:local_sentientia_toy_item.timemodified',
            fn() => $writer->insert('local_sentientia_toy_item', $row));
    }

    public function test_an_overlong_char_is_refused(): void {
        $writer = $this->writer();
        $this->assert_refused('too_long:local_sentientia_toy_item.title',
            fn() => $writer->insert('local_sentientia_toy_item', $this->item(['title' => str_repeat('é', 41)])));
        // The limit counts characters, not bytes.
        $writer->insert('local_sentientia_toy_item', $this->item(['title' => str_repeat('é', 40)]));
    }

    public function test_a_non_integer_or_out_of_range_value_for_an_int_column_is_refused(): void {
        $writer = $this->writer();
        foreach (['abc', 1.5, true, [1]] as $bad) {
            $this->assert_refused('local_sentientia_toy_item.orgid',
                fn() => $writer->insert('local_sentientia_toy_item', $this->item(['orgid' => $bad])));
        }
        // Digits in a string are fine.
        $writer->insert('local_sentientia_toy_item', $this->item(['orgid' => '42']));
        // The visible column is int(1), which holds at most 127 on every engine. toy_org is the importer's PRESERVE
        // target, so its rows come in through import_preserved() with the legacy id; insert() refuses that table.
        $this->assert_refused('integer_out_of_range:local_sentientia_toy_org.visible',
            fn() => $writer->import_preserved('local_sentientia_toy_org', (object) ['name' => 'n', 'path' => null,
                'visible' => 500, 'timecreated' => 1, 'timemodified' => 1], 71));
        $this->assert_refused('not_an_integer:local_sentientia_toy_org.visible',
            fn() => $writer->import_preserved('local_sentientia_toy_org', (object) ['name' => 'n', 'path' => null,
                'visible' => 'x', 'timecreated' => 1, 'timemodified' => 1], 72));
    }

    public function test_the_integer_limit_follows_the_native_type_not_the_display_width(): void {
        // MySQL 8.0.19+ reports tinyint, smallint and int without a display width, so max_length is the
        // numeric precision (3, 5, 10) and cannot say how wide the column is. visible is a tinyint.
        // toy_org is a PRESERVE target: its rows are written by import_preserved() with the legacy id, and
        // insert() refuses a MAP row there (test_a_map_insert_into_a_preserve_table_is_refused).
        $writer = $this->writer();
        $org = fn(int $visible) => (object) ['name' => 'n', 'path' => null, 'visible' => $visible,
            'timecreated' => 1, 'timemodified' => 1];
        $writer->import_preserved('local_sentientia_toy_org', $org(127), 41);
        $this->assert_refused('integer_out_of_range:local_sentientia_toy_org.visible',
            fn() => $writer->import_preserved('local_sentientia_toy_org', $org(200), 42));
        $this->assert_refused('integer_out_of_range:local_sentientia_toy_org.visible',
            fn() => $writer->import_preserved('local_sentientia_toy_org', $org(-200), 43));
        // The dry run runs the same check, so it refuses the same row.
        $this->assert_refused('integer_out_of_range:local_sentientia_toy_org.visible',
            fn() => $writer->check('local_sentientia_toy_org', $org(200)));
    }

    public function test_a_map_insert_into_a_preserve_table_is_refused(): void {
        global $DB;
        $writer = $this->writer();
        // An insert with an explicit id raises AUTO_INCREMENT on MySQL and MariaDB, so a MAP row could take a legacy
        // id the PRESERVE step has not written yet. The guard comes before the value checks: the row is valid.
        $row = (object) ['name' => 'n', 'path' => null, 'visible' => 1, 'timecreated' => 1, 'timemodified' => 1];
        $this->assert_refused('map_insert_into_a_preserve_table:local_sentientia_toy_org',
            fn() => $writer->insert('local_sentientia_toy_org', $row));
        $this->assert_refused('map_insert_into_a_preserve_table:local_sentientia_toy_org',
            fn() => $writer->check('local_sentientia_toy_org', $row, false, true));
        $this->assertSame(0, $DB->count_records('local_sentientia_toy_org'), 'the refused insert wrote nothing');
    }

    public function test_a_dry_run_refuses_a_map_row_that_carries_an_id_like_apply_does(): void {
        $writer = $this->writer(true);
        $withid = $this->item(['id' => 5]);
        $this->assert_refused('id_not_allowed_for_map_insert', fn() => $writer->check('local_sentientia_toy_item', $withid, false, true));
        $writer->check('local_sentientia_toy_item', $withid);
    }

    public function test_bytes_that_are_not_utf8_are_refused_with_the_column_named(): void {
        $writer = $this->writer();
        $this->assert_refused('invalid_utf8:local_sentientia_toy_item.title',
            fn() => $writer->insert('local_sentientia_toy_item', $this->item(['title' => "bad \xC3\x28 byte"])));
    }

    public function test_preserve_writes_the_legacy_id_and_asserts_it(): void {
        global $DB;
        $writer = $this->writer();
        $row = (object) ['name' => 'Kept', 'path' => null, 'visible' => 1, 'timecreated' => 1, 'timemodified' => 1];
        $this->assertSame(77, $writer->import_preserved('local_sentientia_toy_org', $row, 77));
        $this->assertSame('Kept', $DB->get_field('local_sentientia_toy_org', 'name', ['id' => 77]));

        $clash = (object) ['id' => 5, 'name' => 'x', 'path' => null, 'visible' => 1, 'timecreated' => 1, 'timemodified' => 1];
        $this->assert_refused('preserve_id_mismatch', fn() => $writer->import_preserved('local_sentientia_toy_org', $clash, 6));
        $this->assert_refused('id_not_allowed_for_map_insert',
            fn() => $writer->insert('local_sentientia_toy_org', $clash));
    }

    public function test_update_own_only_touches_rows_the_import_created(): void {
        global $DB;
        $writer = $this->writer();
        $id = $writer->insert('local_sentientia_toy_item', $this->item());
        $this->assert_refused('update_of_a_row_the_import_did_not_create',
            fn() => $writer->update_own('local_sentientia_toy_item', $id, (object) ['title' => 'changed']));

        $DB->insert_record(legacymap::TABLE, (object) ['feature' => 'toy', 'sourcetable' => 'local_toy_item', 'sourceid' => 1,
            'subkey' => '', 'targettable' => 'local_sentientia_toy_item', 'targetid' => $id, 'outcome' => 'imported',
            'reason' => null, 'detail' => null, 'runid' => 1, 'timecreated' => 1]);
        $writer->update_own('local_sentientia_toy_item', $id, (object) ['title' => 'changed']);
        $this->assertSame('changed', $DB->get_field('local_sentientia_toy_item', 'title', ['id' => $id]));

        $this->assert_refused('unknown_field', fn() => $writer->update_own('local_sentientia_toy_item', $id, (object) ['x' => 1]));
    }

    public function test_core_tables_are_writable_only_when_reviewed_and_declared(): void {
        $writer = $this->writer();
        $this->assert_refused('not_a_reviewed_core_write:course',
            fn() => $writer->update_core('course', 1, (object) ['shortname' => 'x']));
    }

    public function test_a_core_table_is_writable_only_in_the_ways_it_was_reviewed_for(): void {
        global $DB;
        $this->resetAfterTest();
        toy_importer::reset();
        toy_importer::$corewrites = true;
        $writer = (new writer(false))->for_importer(new toy_importer());

        // course is declared for the open_* backfill UPDATE, and for nothing else.
        $this->assert_refused('core_write_operation_not_reviewed:course:insert',
            fn() => $writer->insert('course', (object) ['fullname' => 'x', 'shortname' => 'blmx']));
        $this->assert_refused('core_write_operation_not_reviewed:course:adopt',
            fn() => $writer->adopt('course', 1, (object) ['summary' => 'x']));
        $this->assert_refused('core_write_operation_not_reviewed:course:update_own',
            fn() => $writer->update_own('course', 1, (object) ['summary' => 'x']));
        $this->assert_refused('core_write_operation_not_reviewed:course:purge', fn() => $writer->purge_rows('course', [1]));
        $this->assertSame(0, $DB->count_records('course', ['shortname' => 'blmx']), 'the refused insert wrote nothing');

        // The dry run applies the same rule: a row the writer would refuse in an apply run fails there too.
        $dry = (new writer(true))->for_importer(new toy_importer());
        $this->assert_refused('core_write_operation_not_reviewed:course:insert',
            fn() => $dry->check('course', (object) ['fullname' => 'x'], false, true));
        $this->assert_refused('core_write_operation_not_reviewed:course:adopt',
            fn() => $dry->check('course', (object) ['summary' => 'x']));
        $dry->check('course', (object) ['summary' => 'x'], true);

        // The reviewed operation works.
        $writer->update_core('course', 1, (object) ['summary' => 'reviewed']);
        $this->assertSame('reviewed', $DB->get_field('course', 'summary', ['id' => 1]));
    }

    public function test_a_core_table_that_allows_an_insert_still_takes_no_adopt(): void {
        $this->resetAfterTest();
        toy_importer::reset();
        toy_importer::$corewritetable = 'user_enrolments';
        $writer = (new writer(false))->for_importer(new toy_importer());
        // user_enrolments is reviewed for insert and update (orphaned enrolments become manual), never for adopting
        // a row as if it were the importer's own.
        $this->assert_refused('core_write_operation_not_reviewed:user_enrolments:adopt',
            fn() => $writer->adopt('user_enrolments', 1, (object) ['status' => 0]));
        $this->assert_refused('core_write_operation_not_reviewed:user_enrolments:update_own',
            fn() => $writer->update_own('user_enrolments', 1, (object) ['status' => 0]));
    }

    public function test_a_dry_run_writer_refuses_every_write_but_still_checks_rows(): void {
        $writer = $this->writer(true);
        $this->assert_refused('write_during_a_dry_run', fn() => $writer->insert('local_sentientia_toy_item', $this->item()));
        $this->assert_refused('write_during_a_dry_run', fn() => $writer->set_marker('toy', 1));
        $this->assert_refused('write_during_a_dry_run', fn() => $writer->insert_map_rows([]));
        // check() is what a dry run calls: the same refusals, no write.
        $writer->check('local_sentientia_toy_item', $this->item());
        $this->assert_refused('unknown_field', fn() => $writer->check('local_sentientia_toy_item', $this->item(['nope' => 1])));
    }

    public function test_reset_sequence_never_runs_inside_a_transaction(): void {
        global $DB;
        $writer = $this->writer();
        $tx = $DB->start_delegated_transaction();
        try {
            $this->assert_refused('reset_sequence_inside_a_transaction',
                fn() => $writer->reset_sequence('local_sentientia_toy_org'));
        } finally {
            $tx->allow_commit();
        }
        $writer->reset_sequence('local_sentientia_toy_org');
        $this->assert_refused('reset_sequence_needs_a_declared_target', fn() => $writer->reset_sequence('user'));
    }

    /**
     * EV-26: the runner passes a floor that covers every id the legacy table ever issued, and the writer raises the
     * counter to it when the target's own highest id is lower.
     */
    public function test_reset_sequence_floors_the_counter_above_ids_the_legacy_table_ever_issued(): void {
        global $DB;
        $writer = $this->writer();
        $org = static fn(): \stdClass => (object) ['name' => 'n', 'path' => null, 'visible' => 1, 'timecreated' => 1,
            'timemodified' => 1];
        $writer->import_preserved('local_sentientia_toy_org', $org(), 5);

        $this->assertSame(6, $writer->reset_sequence('local_sentientia_toy_org'), 'the table\'s own highest id + 1');
        $this->assertSame(6, $writer->reset_sequence('local_sentientia_toy_org', 3),
            'a floor below what the table already needs changes nothing');
        $this->assertSame(6, $writer->reset_sequence('local_sentientia_toy_org', 6));

        if (!in_array($DB->get_dbfamily(), ['mysql', 'postgres'], true)) {
            $this->assertSame(6, $writer->reset_sequence('local_sentientia_toy_org', 500),
                'a database whose counter cannot be set says what it could do, and the runner reports the gap');
            return;
        }
        $this->assertSame(500, $writer->reset_sequence('local_sentientia_toy_org', 500));
        $this->assertSame(500, (int) $DB->insert_record('local_sentientia_toy_org', $org()), 'the first native id is the floor');
        // The table's own highest id is now above a stale floor: the floor never lowers a counter.
        $this->assertSame(501, $writer->reset_sequence('local_sentientia_toy_org', 100));
        $this->assert_refused('reset_sequence_needs_a_declared_target', fn() => $writer->reset_sequence('user', 500));
    }

    /**
     * Data review of 2026-10-07: Moodle's own reset_sequence() sets the counter to MAX(id) + 1, which is LOWER than a
     * counter an earlier run raised to a floor, or one that sits above the highest id because the newest rows were
     * deleted. A re-run after cutover must not take it back down, or ids that were already issued are handed out again.
     */
    public function test_reset_sequence_never_lowers_a_counter_that_already_sits_above_the_table_and_the_floor(): void {
        global $DB;
        $writer = $this->writer();
        if (!in_array($DB->get_dbfamily(), ['mysql', 'postgres'], true)) {
            $this->markTestSkipped('This database family cannot set a counter, so there is none to keep.');
        }
        $table = 'local_sentientia_toy_org';
        $org = static fn(): \stdClass => (object) ['name' => 'n', 'path' => null, 'visible' => 1, 'timecreated' => 1,
            'timemodified' => 1];
        $writer->import_preserved($table, $org(), 5);

        // The counter sits at 500, far above the highest id (5) and above the floor of the run that follows.
        $this->assertSame(500, $writer->reset_sequence($table, 500));
        $this->assertSame(500, $writer->reset_sequence($table, 100), 'a lower floor does not take it down');
        $this->assertSame(500, $writer->reset_sequence($table), 'neither does no floor at all (a re-run)');
        $this->assertSame(500, $writer->reset_sequence($table, 500), 'and the same floor changes nothing');
        $this->assertSame(500, (int) $DB->insert_record($table, $org()), 'the first native id is still 500');

        // A floor ABOVE the counter does raise it, and what the counter already is still counts when it is the higher.
        $this->assertSame(900, $writer->reset_sequence($table, 900));
        $this->assertSame(900, $writer->reset_sequence($table, 0));
        $this->assertSame(900, (int) $DB->insert_record($table, $org()));
        $this->assertSame(901, $writer->reset_sequence($table, 10), 'the highest id + 1 once it passes everything else');
    }

    public function test_map_rows_are_checked_like_any_other_row(): void {
        $writer = $this->writer();
        $row = (object) ['feature' => 'toy', 'sourcetable' => str_repeat('t', 65), 'sourceid' => 1, 'subkey' => '',
            'targettable' => '', 'targetid' => null, 'outcome' => 'skipped', 'reason' => 'x', 'detail' => null,
            'runid' => 1, 'timecreated' => 1];
        $this->assert_refused('too_long:local_sentientia_legacymap.sourcetable', fn() => $writer->insert_map_rows([$row]));
    }

    public function test_markers_are_set_and_cleared_through_the_writer(): void {
        $writer = $this->writer();
        $this->assertFalse(legacymap::feature_complete('toy'));
        $writer->set_marker('toy', 12);
        $this->assertTrue(legacymap::feature_complete('toy'));
        $writer->clear_marker('toy');
        $this->assertFalse(legacymap::feature_complete('toy'));
    }

    public function test_the_fixture_refuses_a_database_that_is_not_the_phpunit_one(): void {
        global $CFG;
        $this->resetAfterTest();
        $prefix = $CFG->prefix;
        $CFG->prefix = 'mdl_';
        try {
            self::create_legacy_tables(__DIR__ . '/../fixtures/bizlms/toy.install.xml', ['local_toy_unused']);
            $this->fail('the fixture created tables in a database with a different prefix');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('phpunit prefix', $e->getMessage());
        } finally {
            $CFG->prefix = $prefix;
        }
    }
}
