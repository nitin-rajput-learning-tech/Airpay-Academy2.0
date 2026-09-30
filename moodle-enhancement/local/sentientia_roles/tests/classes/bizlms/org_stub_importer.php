<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * Stands in for the org feature in the org_roles tests.
 *
 * The real org importer (local_sentientia_org) is another deliverable. org_roles depends on the feature 'org' and
 * resolves every organisation id through the map the org feature leaves, so the tests register this importer under
 * that key. It claims local_costcenter, like the real one, so the framework's dependency rule is exercised: a
 * reader of a table another feature owns must depend on that feature.
 *
 * It writes nothing. The test seed stands for an org import that already ran: it puts one map row per
 * local_costcenter row (and the local_sentientia_org rows those point at) in place before the run, so the stub's
 * step finds every source row mapped and does no work. transform() throws, so a test that forgets a map row fails
 * loudly instead of quietly skipping an organisation.
 *
 * @package    local_sentientia_roles
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_stub_importer implements importer {

    /**
     * @return string
     */
    public function feature(): string {
        return 'org';
    }

    /**
     * @return string
     */
    public function component(): string {
        return 'local_sentientia_roles';
    }

    /**
     * @return int
     */
    public function requires_version(): int {
        return 0;
    }

    /**
     * @return string[]
     */
    public function depends(): array {
        return [];
    }

    /**
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return ['local_costcenter' => new source_spec('local_costcenter')];
    }

    /**
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [];
    }

    /**
     * The registry wants a declared target that belongs to the plugin. The stub never writes it.
     *
     * @return string[]
     */
    public function target_tables(): array {
        return ['local_sentientia_roles_auditlog'];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [new reason('not_an_org_row', false, false)];
    }

    /**
     * @return decision[]
     */
    public function decisions(): array {
        return [];
    }

    /**
     * @return bool
     */
    public function atomic(): bool {
        return false;
    }

    /**
     * @return array
     */
    public function steps(): array {
        return [new class extends step {
            public function key(): string {
                return 'org.stub';
            }

            public function sourcetable(): string {
                return 'local_costcenter';
            }

            public function targettable(): string {
                return 'local_sentientia_roles_auditlog';
            }

            public function transform(array $rows, context $ctx): array {
                throw new \coding_exception('the org stub found a local_costcenter row with no map row: seed its map row');
            }
        }];
    }

    /**
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        return new preflight();
    }

    /**
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        return [];
    }

    /**
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
    }
}
