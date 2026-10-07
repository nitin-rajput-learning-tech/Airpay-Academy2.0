<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\parity;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\parity\database;

/**
 * A real database seen through a window: the named tables are hidden, as if the Moodle version did not have them.
 *
 * It lets one database answer as a Moodle 4.1 database (without scorm_attempt, scorm_scoes_value and scorm_element) and
 * as a 5.x database (without scorm_scoes_track) in the same test, without dropping a core table the rest of the
 * PHPUnit run needs.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hiding_database implements database {

    /** @var array<string, bool> */
    private array $hidden;

    /**
     * @param database $inner
     * @param string[] $hidden Table names (no prefix) to hide.
     */
    public function __construct(private database $inner, array $hidden) {
        $this->hidden = array_fill_keys($hidden, true);
    }

    public function tables(): array {
        return array_values(array_filter($this->inner->tables(), fn(string $t): bool => !isset($this->hidden[$t])));
    }

    public function table_exists(string $table): bool {
        return !isset($this->hidden[$table]) && $this->inner->table_exists($table);
    }

    public function columns(string $table): array {
        return isset($this->hidden[$table]) ? [] : $this->inner->columns($table);
    }

    public function scalar(string $sql): ?string {
        return $this->inner->scalar($sql);
    }

    public function rows(string $sql): array {
        return $this->inner->rows($sql);
    }

    public function family(): string {
        return $this->inner->family();
    }

    public function server(): string {
        return $this->inner->server();
    }
}
