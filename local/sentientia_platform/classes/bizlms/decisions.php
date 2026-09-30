<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The checked-in decisions file: every owner choice the importers need
 * (ADR-032 decision 9). A flat JSON object of key => value.
 *
 * One reserved key, accepted_reasons, is a list of "feature:code" strings that
 * the owner has accepted; parity exits 2 for any needs-owner reason that is not
 * listed. The file's sha256 is stored on the run and, at cutover, must equal
 * --expect-decisions-hash, so the decisions rehearsed are the decisions applied.
 *
 * The hash is taken over the file with CRLF normalised to LF, so the same file
 * hashes identically on a Windows and a Linux checkout.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decisions {

    /** Reserved key listing accepted needs-owner reasons. */
    public const ACCEPTED_REASONS = 'accepted_reasons';

    /** @var array<string, mixed> */
    private array $data;

    /** @var string */
    private string $hash;

    /**
     * @param array<string, mixed> $data
     * @param string $hash
     */
    private function __construct(array $data, string $hash) {
        $this->data = $data;
        $this->hash = $hash;
    }

    /**
     * No decisions at all (hash of the empty file).
     *
     * @return self
     */
    public static function none(): self {
        return new self([], hash('sha256', ''));
    }

    /**
     * Build from an array, for tests and in-process callers. The hash is taken
     * over the canonical JSON of the array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function from_array(array $data): self {
        ksort($data);
        return new self($data, hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES)));
    }

    /**
     * Read and parse a decisions file.
     *
     * @param string $path
     * @return self
     * @throws blocked When the file is missing or is not a JSON object.
     */
    public static function load(string $path): self {
        if (!is_readable($path)) {
            throw new blocked('decisions_file_unreadable');
        }
        $raw = (string) file_get_contents($path);
        $normalised = str_replace("\r\n", "\n", $raw);
        $data = json_decode($normalised, true);
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new blocked('decisions_file_not_a_json_object');
        }
        return new self($data, hash('sha256', $normalised));
    }

    /**
     * @return string sha256 of the decisions.
     */
    public function hash(): string {
        return $this->hash;
    }

    /**
     * Every decision, for the report ("the decisions used").
     *
     * @return array<string, mixed>
     */
    public function all(): array {
        return $this->data;
    }

    /**
     * Enum values the owner has mapped for a legacy column, so preflight no
     * longer blocks on them. The file carries them under the key
     * enums.<table>.<column> as an object of value => meaning; the importer's
     * own code decides what a mapped value becomes.
     *
     * @param string $table Legacy table without prefix.
     * @param string $column
     * @return string[] The mapped values.
     */
    public function mapped_enum_values(string $table, string $column): array {
        $mapped = $this->data['enums.' . $table . '.' . $column] ?? [];
        return is_array($mapped) ? array_map('strval', array_keys($mapped)) : [];
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->data);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Needs-owner reasons the owner has accepted, as feature:code strings.
     *
     * @return string[]
     */
    public function accepted_reasons(): array {
        $list = $this->data[self::ACCEPTED_REASONS] ?? [];
        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    /**
     * @param string $feature
     * @param string $code
     * @return bool
     */
    public function accepts(string $feature, string $code): bool {
        return in_array($feature . ':' . $code, $this->accepted_reasons(), true);
    }
}
