<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\tests\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * A stand-in for an importer the request importer depends on (classroom, program, learningplan).
 *
 * The registry refuses an importer whose depends() names a feature that is not registered, and those three
 * importers live in other plugins. The stand-in claims the legacy tables the request importer resolves ids of,
 * so the runner's "a reader must declare the owner of a table it reads" rule is exercised for real, and it is
 * never applicable: its tables do not exist in the test database, so the runner marks it not_applicable and
 * treats its feature as available. The tests write the map rows the real importer would have written
 * (bizlms_import_test::seed_dependency_map()).
 *
 * It lives under tests/classes, which Moodle autoloads during PHPUnit as local_sentientia_request\tests\.
 *
 * @package    local_sentientia_request
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dependency_stub implements importer {

    /** @var string */
    private string $feature;

    /** @var string[] */
    private array $tables;

    /** @var string[] */
    private array $depends;

    /**
     * @param string $feature Feature key, for example classroom.
     * @param string[] $tables The legacy tables this feature owns.
     * @param string[] $depends
     */
    public function __construct(string $feature, array $tables, array $depends = []) {
        $this->feature = $feature;
        $this->tables = $tables;
        $this->depends = $depends;
    }

    public function feature(): string {
        return $this->feature;
    }

    public function component(): string {
        return 'local_sentientia_request';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return $this->depends;
    }

    public function sources(): array {
        $sources = [];
        foreach ($this->tables as $table) {
            $sources[$table] = new source_spec($table, false);
        }
        return $sources;
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return ['local_sentientia_request'];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [];
    }

    public function reasons(): array {
        return [];
    }

    public function decisions(): array {
        return [];
    }

    public function atomic(): bool {
        return false;
    }

    public function steps(): array {
        $feature = $this->feature;
        $table = reset($this->tables);
        return [new class($feature, $table) extends step {
            public function __construct(private string $f, private string $t) {
            }

            public function key(): string {
                return $this->f . '.stub';
            }

            public function sourcetable(): string {
                return $this->t;
            }

            public function targettable(): string {
                return 'local_sentientia_request';
            }

            public function idpolicy(): string {
                return idpolicy::MAP;
            }

            public function transform(array $rows, context $ctx): array {
                throw new \coding_exception('a dependency stand-in has no table and must never transform a row');
            }
        }];
    }

    public function preflight(context $ctx): preflight {
        return new preflight();
    }

    public function verify(context $ctx): array {
        return [];
    }

    public function finalise(context $ctx): void {
    }
}
