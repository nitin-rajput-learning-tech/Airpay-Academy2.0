<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\toy_importer;
use local_sentientia_platform\tests\bizlms\toy_seed;

/**
 * The importer contract (ADR-032, "Test approach" 4) run against the toy importer.
 *
 * This is the template every feature test follows: use the two traits, say where
 * the fixture XML is, and supply a seed.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\runner
 * @covers     \local_sentientia_platform\phpunit\importer_contract
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class toy_importer_contract_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use toy_seed;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/../fixtures/bizlms/toy.install.xml'];
    }

    protected function contract_importer(): importer {
        toy_importer::reset();
        // Atomic, so the contract's feature-mode test runs; every other contract test passes
        // atomic_threshold 0 and so stays in batch mode.
        toy_importer::$atomic = true;
        return new toy_importer();
    }

    protected function contract_seed(): void {
        $this->seed_toy_data();
    }

    protected function contract_mutate_source(): void {
        global $DB;
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $DB->import_record('local_toy_org', (object) ['id' => 50, 'name' => 'Late arrival', 'parentid' => 0,
            'path' => '/1', 'status' => 1, 'timecreated' => self::$toyt0, 'timemodified' => self::$toyt0]);
    }

    protected function contract_collision(): ?array {
        return ['table' => 'local_sentientia_toy_org', 'row' => (object) ['id' => 1, 'name' => 'Somebody else',
            'path' => null, 'visible' => 1, 'timecreated' => 5, 'timemodified' => 5]];
    }

    protected function contract_adoptable(): ?array {
        // What migrate_all.php wrote: the same id, name and timecreated as the source row.
        return ['table' => 'local_sentientia_toy_org', 'sourcetable' => 'local_toy_org',
            'row' => (object) ['id' => 2, 'name' => 'Beta', 'path' => null, 'visible' => 0,
                'timecreated' => self::$toyt0 + 2, 'timemodified' => 7]];
    }
}
