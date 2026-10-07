<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * Stand-in for the org feature in the tests of the program feature.
 *
 * The registry refuses a feature whose depends() names a feature it does not know, and refuses a tenant importer
 * whose dependency closure lacks the org feature. A test that registers only the program importer would fail both.
 * This importer is the org feature's place in the registry and does nothing: it claims a legacy table that no
 * database has, so the feature is not_applicable and its dependents run, and the test puts the organisation rows
 * the tenant resolver validates against into local_sentientia_org itself.
 *
 * The real org importer is a separate deliverable (mapping doc section 3). The registry judges where an importer's
 * code lives by its component, so this stand-in names this plugin as its component (and one of this plugin's tables
 * as its never-written target) and lives under tests/classes/bizlms, which the registry accepts for test importers.
 *
 * @package    local_sentientia_programs
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_stub_importer implements importer {

    /** A legacy table name that no database has. */
    public const ABSENT_TABLE = 'local_bizlms_org_stub_absent';

    /** One of this plugin's tables; the stub never writes it. */
    public const NOMINAL_TARGET = 'local_sentientia_programs_trainerfb';

    public function feature(): string {
        return 'org';
    }

    public function component(): string {
        return 'local_sentientia_programs';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return [];
    }

    public function sources(): array {
        return [self::ABSENT_TABLE => new source_spec(self::ABSENT_TABLE)];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return [self::NOMINAL_TARGET];
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
        return [new class extends step {
            public function key(): string {
                return 'org.stub';
            }

            public function sourcetable(): string {
                return org_stub_importer::ABSENT_TABLE;
            }

            public function targettable(): string {
                return org_stub_importer::NOMINAL_TARGET;
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
