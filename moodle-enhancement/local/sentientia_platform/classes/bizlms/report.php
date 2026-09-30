<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Collects the report of a run (ADR-032, "CLI"): per run, feature and step the
 * source count, max id and CRC, counts per outcome, skipped rows per reason,
 * warnings, tenant-method counts, rows per second, the tripwire result, verify
 * failures and the decisions used.
 *
 * Reports leave the database, so they carry IDS AND CODES ONLY: never a name,
 * an e-mail address or free text. The optional CSV lists every row that was not
 * imported (merged, folded, archived, skipped) by source table, id, outcome and
 * reason code.
 *
 * The CSV is the evidence the owner accepts, and legacymap is what it is the evidence of. It is streamed as the
 * batches commit, and rewritten from the map when an apply run ends (rewrite_csv()): a crash between a commit and
 * the line's write loses a line that --resume would never write, because that row is mapped already. A line must
 * mean a row is in the map. The
 * per-row counters and CSV lines are therefore HELD while a batch (or, in feature mode, a whole
 * feature) is in its transaction: release() writes them after the commit, discard() drops them
 * with the rollback. Without that, a rolled-back batch leaves phantom lines and --resume
 * appends the same rows again.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {

    /** @var array */
    private array $data;

    /** @var resource|null */
    private $csv = null;

    /** @var string Path of the open CSV. */
    private string $csvpath = '';

    /**
     * @var array<int, array<int, array{0: string, 1: array}>> Stack of held calls, one list per open transaction
     *      level. While it is not empty the per-row methods queue instead of writing.
     */
    private array $held = [];

    /**
     * @param array $meta Run-level facts (mode, fingerprint, decisions hash, ...).
     */
    public function __construct(array $meta = []) {
        $this->data = ['meta' => $meta, 'features' => []];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function meta(string $key, mixed $value): void {
        $this->data['meta'][$key] = $value;
    }

    /**
     * Merge fields into a feature's section.
     *
     * @param string $feature
     * @param array $fields
     * @return void
     */
    public function set_feature(string $feature, array $fields): void {
        $this->data['features'][$feature] = $fields + ($this->data['features'][$feature] ?? []);
    }

    /**
     * Merge fields into a step's section.
     *
     * @param string $feature
     * @param string $stepkey
     * @param array $fields
     * @return void
     */
    public function set_step(string $feature, string $stepkey, array $fields): void {
        $current = $this->data['features'][$feature]['steps'][$stepkey] ?? [];
        $this->data['features'][$feature]['steps'][$stepkey] = $fields + $current;
    }

    /**
     * @param string $feature
     * @param string $stepkey
     * @param string $code Warning code, for example truncated:name.
     * @param int $n
     * @return void
     */
    public function count_warning(string $feature, string $stepkey, string $code, int $n = 1): void {
        if ($this->hold_call(__FUNCTION__, func_get_args())) {
            return;
        }
        $this->bump($feature, $stepkey, 'warnings', $code, $n);
    }

    /**
     * @param string $feature
     * @param string $stepkey
     * @param string $method exact, normalised, walked_up, fallback:name or unresolved.
     * @return void
     */
    public function count_tenant_method(string $feature, string $stepkey, string $method): void {
        if ($this->hold_call(__FUNCTION__, func_get_args())) {
            return;
        }
        $this->bump($feature, $stepkey, 'tenant_methods', $method, 1);
    }

    /**
     * @param string $feature
     * @param string $stepkey
     * @param string $reason
     * @return void
     */
    public function count_reason(string $feature, string $stepkey, string $reason): void {
        if ($this->hold_call(__FUNCTION__, func_get_args())) {
            return;
        }
        $this->bump($feature, $stepkey, 'skipped_by_reason', $reason, 1);
    }

    /**
     * Note a row that was not imported; writes one CSV line when a CSV is open.
     *
     * @param string $feature
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @param string $outcome merged, folded, archived or skipped.
     * @param string $reason
     * @param string $detail Codes and ids only.
     * @return void
     */
    public function non_imported(string $feature, string $sourcetable, int $sourceid, string $subkey,
                                 string $outcome, string $reason, string $detail): void {
        if ($this->hold_call(__FUNCTION__, func_get_args())) {
            return;
        }
        if ($this->csv !== null) {
            fputcsv($this->csv, [$feature, $sourcetable, $sourceid, $subkey, $outcome, $reason, $detail],
                ',', '"', '\\');
        }
    }

    /**
     * Start holding the per-row counters and CSV lines: a transaction level has begun. Calls nest.
     *
     * @return void
     */
    public function hold(): void {
        $this->held[] = [];
    }

    /**
     * The transaction level committed: its lines go to the enclosing level if there is one (a batch inside
     * a feature-mode transaction is not durable yet), else to the report and the CSV.
     *
     * @return void
     */
    public function release(): void {
        $calls = array_pop($this->held);
        if ($calls === null) {
            return;
        }
        if ($this->held) {
            $parent = count($this->held) - 1;
            $this->held[$parent] = array_merge($this->held[$parent], $calls);
            return;
        }
        foreach ($calls as [$method, $args]) {
            $this->{$method}(...$args);
        }
    }

    /**
     * The transaction level rolled back: its lines never happened.
     *
     * @return void
     */
    public function discard(): void {
        array_pop($this->held);
    }

    /**
     * Start the CSV of non-imported rows.
     *
     * A resumed run only sees the rows left to do, so it appends to the file the
     * interrupted run began instead of truncating what that run already listed.
     *
     * @param string $path
     * @param bool $append Continue an existing file (and keep its header).
     * @return void
     */
    public function open_csv(string $path, bool $append = false): void {
        $exists = $append && is_file($path) && filesize($path) > 0;
        $handle = fopen($path, $exists ? 'ab' : 'wb');
        if ($handle === false) {
            throw new blocked('report_csv_unwritable');
        }
        $this->csv = $handle;
        $this->csvpath = $path;
        if (!$exists) {
            fputcsv($this->csv, ['feature', 'sourcetable', 'sourceid', 'subkey', 'outcome', 'reason', 'detail'],
                ',', '"', '\\');
        }
    }

    /**
     * @return bool A CSV of non-imported rows is open.
     */
    public function csv_is_open(): bool {
        return $this->csv !== null;
    }

    /**
     * Replace the CSV with these lines (the rows of legacymap that were not imported). Written beside the file and
     * moved over it, so an interrupted rewrite leaves the streamed file, not half of a new one.
     *
     * @param iterable $lines Each [feature, sourcetable, sourceid, subkey, outcome, reason, detail].
     * @return void
     * @throws blocked When the file cannot be written.
     */
    public function rewrite_csv(iterable $lines): void {
        if ($this->csv === null || $this->csvpath === '') {
            return;
        }
        $temp = $this->csvpath . '.rebuild';
        $handle = fopen($temp, 'wb');
        if ($handle === false) {
            throw new blocked('report_csv_unwritable');
        }
        fputcsv($handle, ['feature', 'sourcetable', 'sourceid', 'subkey', 'outcome', 'reason', 'detail'], ',', '"', '\\');
        foreach ($lines as $line) {
            fputcsv($handle, $line, ',', '"', '\\');
        }
        fclose($handle);
        fclose($this->csv);
        $this->csv = null;
        if (!rename($temp, $this->csvpath)) {
            @unlink($temp);
            throw new blocked('report_csv_unwritable');
        }
        $reopened = fopen($this->csvpath, 'ab');
        if ($reopened === false) {
            throw new blocked('report_csv_unwritable');
        }
        $this->csv = $reopened;
    }

    /**
     * @return array
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Write the JSON report.
     *
     * @param string $path
     * @return void
     */
    public function write_json(string $path): void {
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (file_put_contents($path, $json . "\n") === false) {
            throw new blocked('report_json_unwritable');
        }
    }

    /**
     * Close the CSV, if open.
     *
     * @return void
     */
    public function close(): void {
        if ($this->csv !== null) {
            fclose($this->csv);
            $this->csv = null;
        }
    }

    /**
     * Queue a per-row call while a transaction level is held.
     *
     * @param string $method
     * @param array $args
     * @return bool True when the call was queued.
     */
    private function hold_call(string $method, array $args): bool {
        if (!$this->held) {
            return false;
        }
        $this->held[count($this->held) - 1][] = [$method, $args];
        return true;
    }

    /**
     * @param string $feature
     * @param string $stepkey
     * @param string $section
     * @param string $code
     * @param int $n
     * @return void
     */
    private function bump(string $feature, string $stepkey, string $section, string $code, int $n): void {
        $current = $this->data['features'][$feature]['steps'][$stepkey][$section][$code] ?? 0;
        $this->data['features'][$feature]['steps'][$stepkey][$section][$code] = $current + $n;
    }
}
