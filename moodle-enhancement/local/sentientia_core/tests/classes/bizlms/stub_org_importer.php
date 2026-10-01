<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * A stand-in for the real org importer (the sentientia_org plugin's, built on its own branch), so that the
 * legacy_logs tests can register an importer that depends on the org feature.
 *
 * The registry refuses an importer whose depends() names a feature that is not registered, and refuses an
 * importer with a tenant column when no org feature is registered at all (tenant resolution reads what the org
 * importer fills). A test that registers legacy_logs alone would fail on both, so it registers this stub first.
 *
 * The stub claims a legacy table that never exists (local_stub_org, not required), so the runner finds the
 * feature not applicable, records it as available and runs the importers that depend on it. It writes nothing.
 * Its target table is declared in tests/fixtures/bizlms/stub_org.install.xml, where the registry's test mode
 * looks for a plugin's own tables.
 *
 * It lives under tests/classes/bizlms: a test registry accepts code there, a real one does not.
 *
 * @package    local_sentientia_core
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stub_org_importer implements importer {

    public function feature(): string {
        return 'org';
    }

    public function component(): string {
        return 'local_sentientia_core';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return [];
    }

    public function sources(): array {
        return ['local_stub_org' => new source_spec('local_stub_org', false)];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return ['local_sentientia_stub_org'];
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
        return [new class extends step {
            public function key(): string {
                return 'org.stub';
            }

            public function sourcetable(): string {
                return 'local_stub_org';
            }

            public function targettable(): string {
                return 'local_sentientia_stub_org';
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
