<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Explicit, reporting truncation (ADR-032, "Writing rules" 3).
 *
 * The writer refuses a char value longer than its column, so a step that must
 * shorten text calls fit(). The full value stays in the legacy table and a
 * truncated:<column> warning is recorded, which the runner drains after each
 * transform call into the step's warning counts.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text {

    /** @var string[] Warnings recorded since the last drain(). */
    private array $pending = [];

    /**
     * Shorten a value to at most $max characters, recording a warning when
     * anything was cut.
     *
     * @param string|null $value
     * @param int $max Maximum length in characters (the column's max_length).
     * @param string $column Column name, for the warning code.
     * @return string
     */
    public function fit(?string $value, int $max, string $column): string {
        $value = (string) $value;
        if ($max < 0) {
            throw new \coding_exception('text::fit() needs a non-negative maximum');
        }
        if (\core_text::strlen($value) <= $max) {
            return $value;
        }
        $this->pending[] = 'truncated:' . $column;
        return \core_text::substr($value, 0, $max);
    }

    /**
     * Return and clear the warnings recorded since the last call.
     *
     * @return string[]
     */
    public function drain(): array {
        $out = $this->pending;
        $this->pending = [];
        return $out;
    }
}
