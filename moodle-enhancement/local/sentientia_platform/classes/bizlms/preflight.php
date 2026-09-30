<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Result of a read-only preflight: blockers stop the whole run, warnings go to
 * the report (ADR-032). Carries ids and codes only.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preflight {

    /** @var string[] Blocker lines, code first, for example unknown_enum:local_x.status=7. */
    private array $blockers = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var array<string, int> Named counts, for example source rows per step. */
    private array $counts = [];

    /** @var array<string, array<string, int>> "table.column" => value => rows. */
    private array $histograms = [];

    /**
     * @param string $code Short machine code, optionally followed by a colon and ids.
     * @return self
     */
    public function block(string $code): self {
        $this->blockers[] = $code;
        return $this;
    }

    /**
     * @param string $code
     * @return self
     */
    public function warn(string $code): self {
        $this->warnings[] = $code;
        return $this;
    }

    /**
     * @param string $name
     * @param int $count
     * @return self
     */
    public function count(string $name, int $count): self {
        $this->counts[$name] = $count;
        return $this;
    }

    /**
     * @param string $column "table.column".
     * @param array<string, int> $values value => row count.
     * @return self
     */
    public function histogram(string $column, array $values): self {
        $this->histograms[$column] = $values;
        return $this;
    }

    /**
     * @return bool
     */
    public function has_blockers(): bool {
        return !empty($this->blockers);
    }

    /**
     * @return string[]
     */
    public function blockers(): array {
        return $this->blockers;
    }

    /**
     * @return string[]
     */
    public function warnings(): array {
        return $this->warnings;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array {
        return $this->counts;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function histograms(): array {
        return $this->histograms;
    }

    /**
     * Fold another preflight into this one.
     *
     * @param preflight $other
     * @return self
     */
    public function merge(preflight $other): self {
        $this->blockers = array_merge($this->blockers, $other->blockers);
        $this->warnings = array_merge($this->warnings, $other->warnings);
        $this->counts = array_merge($this->counts, $other->counts);
        $this->histograms = array_merge($this->histograms, $other->histograms);
        return $this;
    }

    /**
     * @return array
     */
    public function to_array(): array {
        return [
            'blockers' => $this->blockers,
            'warnings' => $this->warnings,
            'counts' => $this->counts,
            'histograms' => $this->histograms,
        ];
    }
}
