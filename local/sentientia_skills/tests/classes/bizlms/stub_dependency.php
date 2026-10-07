<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;

/**
 * A stand-in for a feature the skills importer depends on (org, recompletion) in the tests of this plugin.
 *
 * The registry refuses an importer whose depends() names a feature nobody registered, and the org and
 * recompletion importers live in other plugins. The stub is a real importer, not a mock: it claims one legacy
 * table that never exists in the test database, so its feature is not applicable and never writes anything, and
 * it satisfies the dependency exactly as a finished feature would. Its one step is a required part of the
 * contract (an importer with no steps is refused).
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stub_dependency implements importer {

    /** @var string */
    private string $feature;

    /** @var string[] */
    private array $depends;

    /**
     * @param string $feature The feature key the stub stands in for.
     * @param string[] $depends Features the stand-in itself depends on.
     */
    public function __construct(string $feature, array $depends = []) {
        $this->feature = $feature;
        $this->depends = $depends;
    }

    /**
     * The legacy table the stub claims. It is never created, so the stub feature is never applicable.
     *
     * @return string
     */
    private function table(): string {
        return 'local_blmstub_' . $this->feature;
    }

    public function feature(): string {
        return $this->feature;
    }

    public function component(): string {
        return 'local_sentientia_skills';
    }

    public function requires_version(): int {
        return 0;
    }

    public function depends(): array {
        return $this->depends;
    }

    public function sources(): array {
        return [$this->table() => new source_spec($this->table())];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        // A table of the plugin's own schema, so the registry accepts it. Nothing is ever written to it.
        return ['local_sentientia_skill_interest'];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [];
    }

    public function reasons(): array {
        return [new reason('stub', false, false)];
    }

    public function decisions(): array {
        return [];
    }

    public function atomic(): bool {
        return false;
    }

    public function steps(): array {
        return [new class($this->feature, $this->table()) extends step {
            /** @var string */
            private string $feature;

            /** @var string */
            private string $table;

            public function __construct(string $feature, string $table) {
                $this->feature = $feature;
                $this->table = $table;
            }

            public function key(): string {
                return $this->feature . '.stub';
            }

            public function sourcetable(): string {
                return $this->table;
            }

            public function targettable(): string {
                return 'local_sentientia_skill_interest';
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
