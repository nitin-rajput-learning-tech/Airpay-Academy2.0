<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * A stand-in for the org importer, so the classroom importer's depends() on it can be registered in a test.
 *
 * The registry refuses an importer whose dependency is not registered, and the classroom importer
 * depends on org (tenant resolution reads the organisation table the org importer fills). The org
 * importer is another feature's deliverable; this stub claims a legacy table that does not exist, so it
 * reports not applicable and the classroom feature runs after it, exactly as it does on a database that has
 * no local_costcenter table. The tests seed local_sentientia_org themselves.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_stub implements importer {

    /** A legacy table that never exists. */
    private const SOURCE = 'local_org_stub_never_exists';

    public function feature(): string {
        return 'org';
    }

    public function component(): string {
        return 'local_sentientia_classroom';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return [];
    }

    public function sources(): array {
        return [self::SOURCE => new source_spec(self::SOURCE, false)];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return ['local_sentientia_classroom_courses'];
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
                return 'local_org_stub_never_exists';
            }

            public function targettable(): string {
                return 'local_sentientia_classroom_courses';
            }

            public function idpolicy(): string {
                return idpolicy::MAP;
            }

            public function transform(array $rows, context $ctx): array {
                $out = [];
                foreach ($rows as $row) {
                    $out[] = outcome::archive((int) $row->id, 'stub');
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
