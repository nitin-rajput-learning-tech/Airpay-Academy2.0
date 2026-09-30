<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The part of the legacy map an importer may see: four reads and nothing else.
 *
 * The runner's own legacymap also remembers, resets, forgets and opens, commits
 * and rolls back batches. A step handed that object could corrupt the dry-run
 * overlay or the list of entries pending in the open batch, so context::$map is
 * this view instead.
 *
 * Every read is checked by the runner's access rule: a step that resolves an id of a
 * table another feature owns must have declared that feature in depends(). Without
 * the rule, the alphabetical tie-break of the feature order could run the reader
 * before the owner, and every row would be skipped as an orphan.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacymap_view {

    /** @var legacymap */
    private legacymap $map;

    /** @var \Closure|null function(string $sourcetable): void, throws when the read is not allowed. */
    private ?\Closure $access;

    /**
     * @param legacymap $map
     * @param \Closure|null $access Rule applied to the source table of every read.
     */
    public function __construct(legacymap $map, ?\Closure $access = null) {
        $this->map = $map;
        $this->access = $access;
    }

    /**
     * The target id a source row maps to.
     *
     * @param string $sourcetable Legacy table without prefix (or a #derived name).
     * @param int $sourceid
     * @param string $subkey Empty for the primary row.
     * @return int|null Null when the row is unmapped, or mapped with no target (archived, skipped).
     */
    public function resolve(string $sourcetable, int $sourceid, string $subkey = ''): ?int {
        $this->check($sourcetable);
        return $this->map->resolve($sourcetable, $sourceid, $subkey);
    }

    /**
     * Resolve many source ids at once.
     *
     * @param string $sourcetable
     * @param int[] $sourceids
     * @param string $subkey
     * @return array<int, int|null> sourceid => targetid
     */
    public function resolve_many(string $sourcetable, array $sourceids, string $subkey = ''): array {
        $this->check($sourcetable);
        return $this->map->resolve_many($sourcetable, $sourceids, $subkey);
    }

    /**
     * The full entry of a source row.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @return array|null
     */
    public function entry(string $sourcetable, int $sourceid, string $subkey = ''): ?array {
        $this->check($sourcetable);
        return $this->map->entry($sourcetable, $sourceid, $subkey);
    }

    /**
     * Entries of many source rows.
     *
     * @param string $sourcetable
     * @param int[] $sourceids
     * @param string $subkey
     * @return array<int, array|null>
     */
    public function entries(string $sourcetable, array $sourceids, string $subkey = ''): array {
        $this->check($sourcetable);
        return $this->map->entries($sourcetable, $sourceids, $subkey);
    }

    /**
     * @param string $sourcetable
     * @return void
     */
    private function check(string $sourcetable): void {
        if ($this->access !== null) {
            ($this->access)($sourcetable);
        }
    }
}
