<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * Stand-in for a feature the evaluation importer depends on (org, classroom, program) in the tests of this plugin.
 *
 * The registry refuses a feature whose depends() names a feature it does not know, and refuses a tenant importer
 * whose dependency closure lacks the org feature, so a test that registers only the evaluation importer would fail
 * both. Each of these takes its feature's place in the registry and does nothing: it claims a legacy table that
 * the test database does not have, so the feature is not_applicable and its dependents run. The claim is what lets
 * the framework's dependency rule apply to the real importer: a map read of local_classroom or local_program is
 * refused unless evaluation declares the owner in depends().
 *
 * A test puts the rows the dependency would have written (organisations, classroom and program map rows) into
 * the database itself. The real importers are separate deliverables; in a full-registry run they replace these.
 *
 * It lives under tests/classes/bizlms, which the registry accepts for test importers of this plugin.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class parent_stub_importer implements importer {

    /** @var string */
    private string $feature;

    /** @var string */
    private string $legacytable;

    /**
     * @param string $feature The feature this stands in for: org, classroom or program.
     * @param string $legacytable The legacy table that feature owns: local_costcenter, local_classroom, local_program.
     */
    public function __construct(string $feature, string $legacytable) {
        $this->feature = $feature;
        $this->legacytable = $legacytable;
    }

    public function feature(): string {
        return $this->feature;
    }

    public function component(): string {
        return 'local_sentientia_evaluation';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return [];
    }

    public function sources(): array {
        // Not required: the table is absent, and an absent table makes the feature not_applicable instead of blocked.
        return [$this->legacytable => new source_spec($this->legacytable, false)];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        // Any table of this plugin's own schema: the registry wants a declared target that the plugin defines.
        return ['local_sentientia_evaluation_template'];
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
        return true;
    }

    public function steps(): array {
        return [new class($this->feature, $this->legacytable) extends step {
            public function __construct(private string $f, private string $table) {
            }

            public function key(): string {
                return $this->f . '.stub';
            }

            public function sourcetable(): string {
                return $this->table;
            }

            public function targettable(): string {
                return 'local_sentientia_evaluation_template';
            }

            public function transform(array $rows, context $ctx): array {
                return [];
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
