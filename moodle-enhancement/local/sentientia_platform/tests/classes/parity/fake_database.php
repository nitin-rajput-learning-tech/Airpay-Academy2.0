<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\parity;

defined('MOODLE_INTERNAL') || die();

/**
 * A database that is only a list of tables and columns and a few canned answers, for the tests of the parity metrics.
 *
 * It records every statement the metrics ask, so a test can say which tables a version's metrics read, and never
 * touch a real database. An answer is chosen by a fragment of the statement; a statement with no matching fragment
 * answers 0.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_database implements \local_sentientia_platform\parity\database {

    /** @var string[] Every statement asked, in order. */
    public array $log = [];

    /**
     * @param array<string, string[]> $columns Table (no prefix) => its columns.
     * @param array<string, string> $answers Fragment of a statement => the scalar to answer. First match wins.
     * @param string $family 'mysql' or another family.
     */
    public function __construct(private array $columns, private array $answers = [], private string $family = 'mysql') {
    }

    public function tables(): array {
        return array_keys($this->columns);
    }

    public function table_exists(string $table): bool {
        return isset($this->columns[$table]);
    }

    public function columns(string $table): array {
        return $this->columns[$table] ?? [];
    }

    public function scalar(string $sql): ?string {
        $this->log[] = $sql;
        foreach ($this->answers as $fragment => $answer) {
            if (str_contains($sql, (string) $fragment)) {
                return $answer;
            }
        }
        return '0';
    }

    public function rows(string $sql): array {
        $this->log[] = $sql;
        return [];
    }

    public function family(): string {
        return $this->family;
    }

    public function server(): string {
        return 'fake';
    }

    /**
     * Every statement asked, as one string.
     *
     * @return string
     */
    public function asked(): string {
        return implode("\n", $this->log);
    }
}
