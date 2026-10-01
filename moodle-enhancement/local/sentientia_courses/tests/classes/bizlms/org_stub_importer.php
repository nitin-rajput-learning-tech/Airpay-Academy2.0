<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * A stand-in for the org feature, for the course_lookups tests only.
 *
 * course_lookups depends on 'org' (two of its tables name an organisation, and tenant resolution reads the
 * organisation table the org importer fills), and the registry refuses an importer whose dependency is not
 * registered. The real org importer is another feature's deliverable, so a test registers this one beside the
 * importer under test.
 *
 * It must leave no trace: the importer contract asserts that the map is empty after a crashed run, and the org
 * feature's own rows would be in it. So it claims a legacy table the test database never creates
 * (local_costcenter_permissions), which makes the feature not applicable: the runner records it as such, runs no
 * step and writes no map row, and a dependent may still run ("complete or not_applicable first"). The registry
 * refuses an importer with no step, so it has one, which never runs. Because it does not claim local_costcenter, the
 * importer under test reads the legacy organisation table directly, as it does in a dry run before the org
 * feature has written anything.
 *
 * It belongs to local_sentientia_courses so that the registry reads its (never written) declared target from this
 * plugin's schema. It lives under tests/classes, which Moodle autoloads during PHPUnit as
 * local_sentientia_courses\tests\.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_stub_importer implements importer {

    public function feature(): string {
        return 'org';
    }

    public function component(): string {
        return 'local_sentientia_courses';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return [];
    }

    public function sources(): array {
        return ['local_costcenter_permissions' => new source_spec('local_costcenter_permissions', false)];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return ['local_sentientia_courses_tenant_share'];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [];
    }

    public function reasons(): array {
        return [new reason('org_stub', false, false)];
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
                return 'local_costcenter_permissions';
            }

            public function targettable(): string {
                return 'local_sentientia_courses_tenant_share';
            }

            public function transform(array $rows, context $ctx): array {
                $out = [];
                foreach ($rows as $row) {
                    $out[] = outcome::archive((int) $row->id, 'org_stub');
                }
                return $out;
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
