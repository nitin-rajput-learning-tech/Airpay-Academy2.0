<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Feeds a step its source rows in batches of groups (ADR-032, "Reading and
 * performance").
 *
 * Ungrouped step: keyset paging, WHERE id > watermark ORDER BY id with a LIMIT;
 * every row is a group of one and the group key is its id.
 *
 * Grouped step (dedupe and fold units, derived groups): legacy tables cannot be
 * given new indexes, so two phases. Phase 1 scans id plus the group columns in
 * primary-key pages and builds group => ids in memory, capped by
 * --max-group-scan. Phase 2 processes groups ordered by their MINIMUM source id,
 * fetching each batch by id IN (...). The resume watermark is that minimum id.
 * Keyset paging on a string group key is never used.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_source {

    /** Rows per page of the phase 1 scan. */
    private const SCAN_PAGE = 5000;

    /** @var legacy_reader */
    private legacy_reader $reader;

    /** @var step */
    private step $step;

    /** @var int Groups whose key is at or below this are already done. */
    private int $watermark;

    /** @var int */
    private int $batchsize;

    /** @var int */
    private int $maxgroupscan;

    /** @var int[]|null Restrict to these source ids (retry mode), ascending. */
    private ?array $onlyids;

    /** @var string[] Columns to read. */
    private array $columns;

    /** @var array<int, int[]>|null Grouped: minimum id => ids of the group, in processing order. */
    private ?array $queue = null;

    /** @var int[] Grouped: the queue's minimum ids in processing order. */
    private array $order = [];

    /** @var int Grouped: next position in $order. */
    private int $cursor = 0;

    /** @var int[] Ungrouped retry mode: the ids still to fetch. */
    private array $pendingids = [];

    /** @var int Ungrouped: last id returned. */
    private int $last;

    /** @var int|null Number of groups, known after phase 1 of a grouped step. */
    private ?int $groupcount = null;

    /**
     * @param legacy_reader $reader
     * @param step $step
     * @param int $watermark
     * @param int $batchsize
     * @param int $maxgroupscan
     * @param int[]|null $onlyids
     * @param string[]|null $columns Columns to read; null reads the step's columns().
     */
    public function __construct(legacy_reader $reader, step $step, int $watermark, int $batchsize,
                                int $maxgroupscan, ?array $onlyids = null, ?array $columns = null) {
        $this->reader = $reader;
        $this->step = $step;
        $this->columns = $columns ?? $step->columns();
        $this->watermark = $watermark;
        $this->last = $watermark;
        $this->batchsize = max(1, $batchsize);
        $this->maxgroupscan = $maxgroupscan;
        if ($onlyids !== null) {
            $onlyids = array_values(array_unique(array_map('intval', $onlyids)));
            sort($onlyids);
        }
        $this->onlyids = $onlyids;
        if ($onlyids !== null && !$step->group_by()) {
            $this->pendingids = array_values(array_filter($onlyids, fn(int $id): bool => $id > $watermark));
        }
    }

    /**
     * The next batch of groups.
     *
     * @return array<int, array{key: int, rows: array<int, \stdClass>}> Empty when the step is exhausted.
     */
    public function next_batch(): array {
        return $this->step->group_by() ? $this->next_grouped() : $this->next_ungrouped();
    }

    /**
     * Number of groups of a grouped step, once phase 1 has run.
     *
     * @return int|null
     */
    public function group_count(): ?int {
        return $this->groupcount;
    }

    /**
     * @return array<int, array{key: int, rows: array<int, \stdClass>}>
     */
    private function next_ungrouped(): array {
        $table = $this->step->physical_table();
        if ($this->onlyids !== null) {
            $ids = array_splice($this->pendingids, 0, $this->batchsize);
            $rows = $ids ? $this->reader->fetch($table, $ids, $this->columns) : [];
        } else {
            $rows = $this->reader->page($table, $this->last, $this->batchsize,
                $this->columns, $this->step->source_filter());
        }
        $out = [];
        foreach ($rows as $id => $row) {
            $out[] = ['key' => $id, 'rows' => [$id => $row]];
            $this->last = $id;
        }
        return $out;
    }

    /**
     * @return array<int, array{key: int, rows: array<int, \stdClass>}>
     */
    private function next_grouped(): array {
        if ($this->queue === null) {
            $this->scan_groups();
        }
        $take = [];
        $total = 0;
        while ($this->cursor < count($this->order) && ($total === 0 || $total < $this->batchsize)) {
            $min = $this->order[$this->cursor++];
            $take[$min] = $this->queue[$min];
            $total += count($take[$min]);
            unset($this->queue[$min]);
        }
        if (!$take) {
            return [];
        }
        $allids = [];
        foreach ($take as $ids) {
            foreach ($ids as $id) {
                $allids[] = $id;
            }
        }
        $rows = $this->reader->fetch($this->step->physical_table(), $allids, $this->columns);
        $out = [];
        foreach ($take as $min => $ids) {
            $group = [];
            foreach ($ids as $id) {
                if (isset($rows[$id])) {
                    $group[$id] = $rows[$id];
                }
            }
            if ($group) {
                $out[] = ['key' => $min, 'rows' => $group];
            }
        }
        return $out;
    }

    /**
     * Phase 1: scan id plus the group columns and build the ordered queue.
     *
     * @return void
     */
    private function scan_groups(): void {
        $table = $this->step->physical_table();
        $columns = $this->step->group_by();
        $filter = $this->step->source_filter();
        $only = $this->onlyids === null ? null : array_flip($this->onlyids);

        $memorylimit = self::memory_limit_bytes();
        $groups = [];
        $scanned = 0;
        $after = 0;
        do {
            $rows = $this->reader->group_page($table, $columns, $after, self::SCAN_PAGE, $filter);
            foreach ($rows as $id => $row) {
                $scanned++;
                if ($scanned > $this->maxgroupscan) {
                    throw new blocked('max_group_scan_exceeded:' . $table);
                }
                if (($scanned & 0xFFFF) === 0 && $memorylimit > 0 && memory_get_usage(true) > 0.8 * $memorylimit) {
                    // --max-group-scan caps rows, not bytes; stop before PHP dies mid-batch.
                    throw new blocked('group_scan_memory_exhausted:' . $table);
                }
                $parts = [];
                foreach ($columns as $column) {
                    $parts[] = $row->{$column} === null ? "\0NULL" : (string) $row->{$column};
                }
                $hash = md5(implode("\x1f", $parts), true);
                if (!isset($groups[$hash])) {
                    $groups[$hash] = $id;
                } else {
                    // A group of one stays a bare integer; it becomes a list when a second row arrives.
                    $groups[$hash] = is_array($groups[$hash]) ? $groups[$hash] : [$groups[$hash]];
                    $groups[$hash][] = $id;
                }
                $after = $id;
            }
        } while (count($rows) === self::SCAN_PAGE);

        // The database groups with the column's collation. If PHP (exact bytes) found MORE groups than the
        // database does, two rows the target's unique key treats as one would land in different groups and
        // every batch containing them would roll back on that key.
        if (count($groups) > $this->reader->count_groups($table, $columns, $filter)) {
            throw new blocked('group_key_differs_from_db_collation:' . $table);
        }

        // Groups come out in first-seen order, which is ascending minimum id.
        $queue = [];
        foreach ($groups as $ids) {
            $ids = (array) $ids;
            if ($only !== null && !array_intersect_key(array_flip($ids), $only)) {
                continue;
            }
            $this->groupcount = ($this->groupcount ?? 0) + 1;
            if ($ids[0] > $this->watermark) {
                $queue[$ids[0]] = $ids;
            }
        }
        $this->groupcount ??= 0;
        $this->queue = $queue;
        $this->order = array_keys($queue);
        $this->cursor = 0;
    }

    /**
     * The PHP memory limit in bytes; 0 when there is none.
     *
     * @return int
     */
    private static function memory_limit_bytes(): int {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return 0;
        }
        $number = (int) $limit;
        switch (strtolower(substr($limit, -1))) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
        }
        return $number;
    }
}
