<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The checked-in decisions file: every owner choice the importers need
 * (ADR-032 decision 9), docs/cutover/bizlms-import-decisions.json.
 *
 * The file has this shape:
 *
 *     {
 *       "version": 1, "approved_by": "...", "approved_on": "YYYY-MM-DD", "basis": "...",
 *       "decisions": {
 *         "<feature>.<key>": {"value": ..., "why": "...", "source": "...", "status": "accepted"}
 *       },
 *       "accepted_reasons": ["<feature>:<code>", "<feature>:<code><=<rows>", ...],
 *       "enums": {"<legacy table>.<column>": {"<value>": "<meaning>"}}
 *     }
 *
 * - decisions: the owner's choices. Only an entry whose status is "accepted" counts.
 *   Any other status (finance-confirm is the one in use) means the owner has not
 *   finished deciding: has() and get() ignore the entry, status() reports it, and a
 *   selected feature that declares the key is blocked at preflight. A decision
 *   carried with another status can therefore never be replaced by the importer's
 *   own default.
 * - accepted_reasons (optional): needs-owner reasons the owner has accepted.
 *   Parity exits 2 for any needs-owner reason that is not listed. A reason is listed only
 *   after the Stage B rehearsal has shown that it OCCURS, with the number of rows the owner
 *   looked at ("<feature>:<code><=<rows>"): at cutover a count above that exits 2 again, so
 *   an acceptance cannot also accept growth in the data since the rehearsal. A bare
 *   "<feature>:<code>" accepts any count; use it only for a code that is meant to have none
 *   to look at. No importer pre-fills this list when it is built.
 * - enums (optional): values of a declared enum column the owner has mapped, so
 *   preflight no longer blocks on them. The importer's code decides what a mapped
 *   value becomes.
 *
 * The file's sha256 is stored on the run and, at cutover, must equal
 * --expect-decisions-hash, so the decisions rehearsed are the decisions applied.
 * The hash is taken over the file with CRLF normalised to LF, so the same file
 * hashes identically on a Windows and a Linux checkout.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decisions {

    /** The only status that counts as a decision. */
    public const ACCEPTED = 'accepted';

    /** Shape of a decision key: feature, then one or more dot-separated parts. */
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/';

    /** Shape of an accepted reason: feature:code, optionally followed by <=rows (the most rows the owner accepted). */
    private const REASON_PATTERN = '/^([a-z][a-z0-9_]*:[a-z][a-z0-9_]*)(?:<=(\d{1,9}))?$/';

    /** Shape of the key of an enum mapping: legacy table.column. */
    private const ENUM_PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    /** @var array<string, array{value: mixed, status: string}> */
    private array $entries;

    /** @var string[] As written in the file: feature:code, or feature:code<=rows. */
    private array $acceptedreasons;

    /** @var array<string, int|null> feature:code => the most rows accepted, null for any number of rows. */
    private array $reasonlimits = [];

    /** @var array<string, array<string, string>> table.column => value => meaning */
    private array $enums;

    /** @var array<string, mixed> version, approved_by, approved_on, basis */
    private array $approval;

    /** @var string */
    private string $hash;

    /**
     * @param array<string, array{value: mixed, status: string}> $entries
     * @param string[] $acceptedreasons
     * @param array<string, array<string, string>> $enums
     * @param array<string, mixed> $approval
     * @param string $hash
     */
    private function __construct(array $entries, array $acceptedreasons, array $enums, array $approval, string $hash) {
        $this->entries = $entries;
        $this->acceptedreasons = $acceptedreasons;
        foreach ($acceptedreasons as $reason) {
            if (!preg_match(self::REASON_PATTERN, $reason, $match)) {
                continue;
            }
            $limit = isset($match[2]) ? (int) $match[2] : null;
            // Listed twice (only an in-process caller can): an unbounded entry beats a bounded one, and the larger
            // bound beats the smaller.
            if (array_key_exists($match[1], $this->reasonlimits)) {
                $known = $this->reasonlimits[$match[1]];
                $limit = ($known === null || $limit === null) ? null : max($known, $limit);
            }
            $this->reasonlimits[$match[1]] = $limit;
        }
        $this->enums = $enums;
        $this->approval = $approval;
        $this->hash = $hash;
    }

    /**
     * No decisions at all (hash of the empty file).
     *
     * @return self
     */
    public static function none(): self {
        return new self([], [], [], [], hash('sha256', ''));
    }

    /**
     * Build from an array, for tests and in-process callers. The keys are decision keys
     * with their values (status accepted); two reserved keys stand for the file's
     * sections: accepted_reasons (a list) and enums.<table>.<column> (value => meaning).
     * The hash is taken over the canonical JSON of the arguments.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $statuses Decision key => status, for a decision that is not accepted.
     * @return self
     */
    public static function from_array(array $data, array $statuses = []): self {
        ksort($data);
        ksort($statuses);
        $entries = [];
        $reasons = [];
        $enums = [];
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ($key === 'accepted_reasons') {
                $reasons = array_values(array_map('strval', (array) $value));
            } else if (strncmp($key, 'enums.', 6) === 0) {
                $enums[substr($key, 6)] = array_map('strval', (array) $value);
            } else {
                $entries[$key] = ['value' => $value, 'status' => $statuses[$key] ?? self::ACCEPTED];
            }
        }
        $canonical = $statuses ? [$data, $statuses] : $data;
        return new self($entries, $reasons, $enums, [], hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES)));
    }

    /**
     * Read and parse a decisions file.
     *
     * @param string $path
     * @return self
     * @throws blocked When the file is missing or is not in the documented shape.
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
        $block = $data['decisions'] ?? null;
        if (!is_array($block) || ($block !== [] && array_is_list($block))) {
            throw new blocked('decisions_file_has_no_decisions_object');
        }

        $entries = [];
        foreach ($block as $key => $entry) {
            $key = (string) $key;
            if (!preg_match(self::KEY_PATTERN, $key)) {
                throw new blocked('decisions_file_invalid_key:' . \core_text::substr($key, 0, 60));
            }
            if (!is_array($entry) || !array_key_exists('value', $entry) || !isset($entry['status'])
                    || !is_string($entry['status']) || $entry['status'] === '') {
                throw new blocked('decisions_file_entry_invalid:' . $key);
            }
            $entries[$key] = ['value' => $entry['value'], 'status' => $entry['status']];
        }

        $reasons = [];
        $seen = [];
        foreach ((array) ($data['accepted_reasons'] ?? []) as $reason) {
            if (!is_string($reason) || !preg_match(self::REASON_PATTERN, $reason, $match)) {
                throw new blocked('decisions_file_invalid_accepted_reason');
            }
            // One entry per reason: "toy:x" and "toy:x<=3" side by side would leave it unclear which one the owner meant.
            if (isset($seen[$match[1]])) {
                throw new blocked('decisions_file_duplicate_accepted_reason:' . $match[1]);
            }
            $seen[$match[1]] = true;
            $reasons[] = $reason;
        }

        $enums = [];
        $section = $data['enums'] ?? [];
        if (!is_array($section) || ($section !== [] && array_is_list($section))) {
            throw new blocked('decisions_file_invalid_enums');
        }
        foreach ($section as $name => $mapping) {
            if (!preg_match(self::ENUM_PATTERN, (string) $name) || !is_array($mapping)) {
                throw new blocked('decisions_file_invalid_enums:' . \core_text::substr((string) $name, 0, 60));
            }
            $enums[(string) $name] = array_map('strval', $mapping);
        }

        $approval = [];
        foreach (['version', 'approved_by', 'approved_on', 'basis'] as $field) {
            if (isset($data[$field]) && is_scalar($data[$field])) {
                $approval[$field] = $data[$field];
            }
        }
        return new self($entries, $reasons, $enums, $approval, hash('sha256', $normalised));
    }

    /**
     * @return string sha256 of the decisions.
     */
    public function hash(): string {
        return $this->hash;
    }

    /**
     * Every decision with its status, for the report ("the decisions used"). The
     * owner's prose (why, source) stays in the file.
     *
     * @return array<string, array{value: mixed, status: string}>
     */
    public function all(): array {
        return $this->entries;
    }

    /**
     * Decisions the owner has not accepted (finance-confirm and the like).
     *
     * @return array<string, string> key => status
     */
    public function not_accepted(): array {
        $out = [];
        foreach ($this->entries as $key => $entry) {
            if ($entry['status'] !== self::ACCEPTED) {
                $out[$key] = $entry['status'];
            }
        }
        return $out;
    }

    /**
     * Who approved the file and when, for the report.
     *
     * @return array<string, mixed>
     */
    public function approval(): array {
        return $this->approval;
    }

    /**
     * The status the file gives a decision.
     *
     * @param string $key
     * @return string|null Null when the file does not carry the key.
     */
    public function status(string $key): ?string {
        return $this->entries[$key]['status'] ?? null;
    }

    /**
     * Enum values the owner has mapped for a legacy column, so preflight no
     * longer blocks on them. The importer's own code decides what a mapped
     * value becomes.
     *
     * @param string $table Legacy table without prefix.
     * @param string $column
     * @return string[] The mapped values.
     */
    public function mapped_enum_values(string $table, string $column): array {
        return array_map('strval', array_keys($this->enums[$table . '.' . $column] ?? []));
    }

    /**
     * Is there an ACCEPTED decision under this key? A decision in any other status is not a
     * decision yet (see status()).
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        return $this->status($key) === self::ACCEPTED;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed The value of an accepted decision, else the default.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->has($key) ? $this->entries[$key]['value'] : $default;
    }

    /**
     * Needs-owner reasons the owner has accepted, as feature:code strings.
     *
     * @return string[]
     */
    public function accepted_reasons(): array {
        return $this->acceptedreasons;
    }

    /**
     * Has the owner accepted this needs-owner reason, for this many rows?
     *
     * @param string $feature
     * @param string $code
     * @param int|null $count The rows that carry the reason now. Null asks only whether the reason is listed at all
     *        (with any bound).
     * @return bool A bare entry accepts any count; "feature:code<=n" accepts up to n rows, so a count above what the
     *         owner looked at is not accepted.
     */
    public function accepts(string $feature, string $code, ?int $count = null): bool {
        $key = $feature . ':' . $code;
        if (!array_key_exists($key, $this->reasonlimits)) {
            return false;
        }
        $limit = $this->reasonlimits[$key];
        return $limit === null || $count === null || $count <= $limit;
    }

    /**
     * The most rows the owner accepted for a reason.
     *
     * @param string $feature
     * @param string $code
     * @return int|null Null when the reason is not listed, or is listed without a bound.
     */
    public function accepted_limit(string $feature, string $code): ?int {
        return $this->reasonlimits[$feature . ':' . $code] ?? null;
    }
}
