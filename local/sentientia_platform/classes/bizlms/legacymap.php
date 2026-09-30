<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Read API of local_sentientia_legacymap, the only idempotence key of the import
 * (ADR-032 decision 3).
 *
 * Importers resolve EVERY foreign legacy id through resolve(), even when the id
 * was kept: no importer may assume target id equals source id.
 *
 * This class only reads. Rows are written by the writer. The in-memory cache
 * doubles as the dry-run overlay: the runner remembers virtual outcomes in it,
 * so downstream features resolve against upstream rows that a dry run did not
 * write. Entries added inside a batch are visible to resolve() at once, are
 * merged into the cache on commit_batch() and dropped on rollback_batch(), so a
 * rolled-back batch leaves no trace.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacymap {

    /** Map table. */
    public const TABLE = 'local_sentientia_legacymap';

    /** Ids per IN() list and rows per preload page. */
    private const CHUNK = 1000;

    /** Plugin that owns the completion markers. */
    private const MARKER_COMPONENT = 'local_sentientia_platform';

    /**
     * @var array<string, array<string, array<int, array|false>>> sourcetable => subkey => sourceid => entry,
     *      where false records a known miss.
     */
    private array $cache = [];

    /** @var array<string, array<string, bool>> sourcetable => subkey => every row of the pair is cached. */
    private array $loaded = [];

    /** @var array<array{0: string, 1: string, 2: int, 3: array}> Entries added in the open batch. */
    private array $pending = [];

    /** @var bool */
    private bool $inbatch = false;

    /**
     * The target id a source row maps to.
     *
     * @param string $sourcetable Legacy table without prefix (or a #derived name).
     * @param int $sourceid
     * @param string $subkey Empty for the primary row.
     * @return int|null Null when the row is unmapped, or mapped with no target (archived, skipped).
     */
    public function resolve(string $sourcetable, int $sourceid, string $subkey = ''): ?int {
        $entry = $this->entry($sourcetable, $sourceid, $subkey);
        return $entry === null ? null : $entry['targetid'];
    }

    /**
     * Resolve many source ids at once, chunked at 1000 ids per query.
     *
     * @param string $sourcetable
     * @param int[] $sourceids
     * @param string $subkey
     * @return array<int, int|null> sourceid => targetid (null when unmapped or without a target)
     */
    public function resolve_many(string $sourcetable, array $sourceids, string $subkey = ''): array {
        $out = [];
        foreach ($this->entries($sourcetable, $sourceids, $subkey) as $id => $entry) {
            $out[$id] = $entry === null ? null : $entry['targetid'];
        }
        return $out;
    }

    /**
     * Load every row of a source table and subkey into memory, so later lookups
     * of that pair never touch the database. Declared by step::preload().
     *
     * @param string $sourcetable
     * @param string $subkey
     * @return void
     */
    public function preload(string $sourcetable, string $subkey = ''): void {
        global $DB;
        if (!empty($this->loaded[$sourcetable][$subkey])) {
            return;
        }
        // Page on sourceid, in the order of the unique key (sourcetable, sourceid, subkey): for one subkey
        // it is strictly increasing, and paging on id would let MySQL re-scan the whole table per page.
        $after = 0;
        do {
            $rows = $DB->get_records_sql(
                'SELECT id, sourceid, targettable, targetid, outcome, reason FROM {' . self::TABLE . '}
                  WHERE sourcetable = :st AND sourceid > :after AND subkey = :sk
               ORDER BY sourceid',
                ['st' => $sourcetable, 'sk' => $subkey, 'after' => $after], 0, self::CHUNK * 5);
            foreach ($rows as $row) {
                $this->cache[$sourcetable][$subkey][(int) $row->sourceid] = self::to_entry($row);
                $after = (int) $row->sourceid;
            }
        } while (count($rows) === self::CHUNK * 5);
        $this->loaded[$sourcetable][$subkey] = true;
    }

    /**
     * Has the feature finished its import? A recorded fact (the completion
     * marker), not a toggle.
     *
     * @param string $feature
     * @return bool
     */
    public static function feature_complete(string $feature): bool {
        return (int) get_config(self::MARKER_COMPONENT, 'bizlms_complete_' . $feature) > 0;
    }

    /**
     * The run that tripped this feature's side-effect tripwire, or 0. A recorded fact like the completion marker:
     * while it is set the feature does not start again (runner preflight).
     *
     * @param string $feature
     * @return int
     */
    public static function tripped_run(string $feature): int {
        return (int) get_config(self::MARKER_COMPONENT, 'bizlms_tripped_' . $feature);
    }

    /**
     * The full entry of a source row.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @return array{targettable: string, targetid: ?int, outcome: string, reason: ?string}|null
     */
    public function entry(string $sourcetable, int $sourceid, string $subkey = ''): ?array {
        return $this->entries($sourcetable, [$sourceid], $subkey)[$sourceid];
    }

    /**
     * Entries of many source rows.
     *
     * @param string $sourcetable
     * @param int[] $sourceids
     * @param string $subkey
     * @return array<int, array|null> sourceid => entry, null when unmapped
     */
    public function entries(string $sourcetable, array $sourceids, string $subkey = ''): array {
        global $DB;
        $out = [];
        $missing = [];
        foreach ($sourceids as $id) {
            $id = (int) $id;
            $hit = $this->lookup_memory($sourcetable, $subkey, $id);
            if ($hit !== null) {
                $out[$id] = $hit === false ? null : $hit;
            } else if (!empty($this->loaded[$sourcetable][$subkey])) {
                $out[$id] = null;
            } else {
                $missing[$id] = $id;
            }
        }

        foreach (array_chunk(array_values($missing), self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'blmsid');
            $params['st'] = $sourcetable;
            $params['sk'] = $subkey;
            $rows = $DB->get_records_sql(
                'SELECT id, sourceid, targettable, targetid, outcome, reason FROM {' . self::TABLE . "}
                  WHERE sourcetable = :st AND subkey = :sk AND sourceid {$insql}", $params);
            $found = [];
            foreach ($rows as $row) {
                $found[(int) $row->sourceid] = self::to_entry($row);
            }
            foreach ($chunk as $id) {
                $this->cache[$sourcetable][$subkey][$id] = $found[$id] ?? false;
                $out[$id] = $found[$id] ?? null;
            }
        }

        // Preserve the caller's order and duplicates-free shape.
        $ordered = [];
        foreach ($sourceids as $id) {
            $ordered[(int) $id] = $out[(int) $id] ?? null;
        }
        return $ordered;
    }

    /**
     * Record an entry the runner has just written (or, in a dry run, decided).
     * Inside a batch it stays pending until commit_batch().
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @param array{targettable: string, targetid: ?int, outcome: string, reason: ?string} $entry
     * @return void
     */
    public function remember(string $sourcetable, int $sourceid, string $subkey, array $entry): void {
        if ($this->inbatch) {
            $this->pending[] = [$sourcetable, $subkey, $sourceid, $entry];
            return;
        }
        $this->cache[$sourcetable][$subkey][$sourceid] = $entry;
    }

    /**
     * Drop what was cached for a source table while it was being imported, so a long
     * step does not hold one entry per row it has imported. A subkey that a step
     * declared in preload() is kept: it is a complete, deliberate load and dropping
     * it would turn every later resolve() into a query. Only safe for rows that are
     * in the database: the dry-run overlay is memory only and must never be forgotten.
     *
     * @param string $sourcetable
     * @return void
     */
    public function forget(string $sourcetable): void {
        foreach (array_keys($this->cache[$sourcetable] ?? []) as $subkey) {
            if (empty($this->loaded[$sourcetable][$subkey])) {
                unset($this->cache[$sourcetable][$subkey]);
            }
        }
    }

    /**
     * Forget everything, including the open batch. Used after a feature failed and
     * its transaction rolled back, so the cache cannot claim rows that are gone.
     *
     * @return void
     */
    public function reset(): void {
        $this->cache = [];
        $this->loaded = [];
        $this->pending = [];
        $this->inbatch = false;
    }

    /**
     * Start collecting entries of one batch.
     *
     * @return void
     */
    public function begin_batch(): void {
        $this->inbatch = true;
        $this->pending = [];
    }

    /**
     * The batch committed: its entries become part of the cache.
     *
     * @return void
     */
    public function commit_batch(): void {
        foreach ($this->pending as [$table, $subkey, $id, $entry]) {
            $this->cache[$table][$subkey][$id] = $entry;
        }
        $this->pending = [];
        $this->inbatch = false;
    }

    /**
     * The batch rolled back: forget its entries and any miss cached meanwhile.
     *
     * @return void
     */
    public function rollback_batch(): void {
        foreach ($this->pending as [$table, $subkey, $id]) {
            unset($this->cache[$table][$subkey][$id]);
        }
        $this->pending = [];
        $this->inbatch = false;
    }

    /**
     * Look an entry up in memory only.
     *
     * @param string $sourcetable
     * @param string $subkey
     * @param int $id
     * @return array|false|null entry, false for a known miss, null for unknown.
     */
    private function lookup_memory(string $sourcetable, string $subkey, int $id): array|false|null {
        if ($this->inbatch) {
            // Newest first, so a later entry wins.
            for ($i = count($this->pending) - 1; $i >= 0; $i--) {
                [$t, $s, $sid, $entry] = $this->pending[$i];
                if ($t === $sourcetable && $s === $subkey && $sid === $id) {
                    return $entry;
                }
            }
        }
        return $this->cache[$sourcetable][$subkey][$id] ?? null;
    }

    /**
     * @param \stdClass $row
     * @return array{targettable: string, targetid: ?int, outcome: string, reason: ?string}
     */
    private static function to_entry(\stdClass $row): array {
        return [
            'id' => (int) $row->id,
            'targettable' => (string) $row->targettable,
            'targetid' => $row->targetid === null ? null : (int) $row->targetid,
            'outcome' => (string) $row->outcome,
            'reason' => $row->reason === null ? null : (string) $row->reason,
        ];
    }
}
