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
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {

    /** @var array */
    private array $data;

    /** @var resource|null */
    private $csv = null;

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
        $this->bump($feature, $stepkey, 'warnings', $code, $n);
    }

    /**
     * @param string $feature
     * @param string $stepkey
     * @param string $method exact, normalised, walked_up, fallback:name or unresolved.
     * @return void
     */
    public function count_tenant_method(string $feature, string $stepkey, string $method): void {
        $this->bump($feature, $stepkey, 'tenant_methods', $method, 1);
    }

    /**
     * @param string $feature
     * @param string $stepkey
     * @param string $reason
     * @return void
     */
    public function count_reason(string $feature, string $stepkey, string $reason): void {
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
        if ($this->csv !== null) {
            fputcsv($this->csv, [$feature, $sourcetable, $sourceid, $subkey, $outcome, $reason, $detail],
                ',', '"', '\\');
        }
    }

    /**
     * Start the CSV of non-imported rows.
     *
     * @param string $path
     * @return void
     */
    public function open_csv(string $path): void {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new blocked('report_csv_unwritable');
        }
        $this->csv = $handle;
        fputcsv($this->csv, ['feature', 'sourcetable', 'sourceid', 'subkey', 'outcome', 'reason', 'detail'],
            ',', '"', '\\');
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
