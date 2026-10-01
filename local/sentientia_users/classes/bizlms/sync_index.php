<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;

/**
 * Which HRMS upload each BizLMS sync error belongs to (ADR-032, mapping doc section 10, "Run matching").
 *
 * BizLMS stored a sync error with the uploader (modified_by) and a time (date_created), and never the run it
 * came from. The Sentientia error table needs a run, so every error is matched to the run it most likely
 * belongs to:
 *
 *  1. an error row written by the HR web service (sync_file_name 'Service') is never matched to a run: it goes to
 *     a synthetic "service" run per uploader and server-timezone day;
 *  2. an error (written with time() when it happened) attaches to the legacy run of the same uploader whose
 *     window contains its time. The window is (the uploader's previous run, this run's time], and never earlier
 *     than an hour before the run (hrms_async caps one run at an hour);
 *  3. a warning (written with the midnight of the upload day) attaches to the first run of the same uploader on
 *     that server-timezone day;
 *  4. whatever is left goes to a synthetic "orphan" run per uploader and server-timezone day.
 *
 * Whether a row is a warning is the production-only type column when the database has it, else the inference
 * "its time is an exact midnight in Moodle's server timezone" (BizLMS wrote warnings at midnight and errors at
 * time()). Both are known inferences and the report says which was used.
 *
 * ONE pass over the source tables builds everything the steps need: the legacy runs by uploader, how many errors
 * each legacy run got, and for each uploader and kind the synthetic runs with their counts and last time. It
 * holds aggregates only, never a row, so it stays small on a large table. It is built once per context (one
 * context is one run of one feature), so the three steps that use it agree and a dry run builds its own.
 *
 * Why an index and not a recompute step: every number a run shows (total rows, error and warning counts,
 * time) is a function of the source rows alone, so it is computed where the run is written and a second pass
 * over imported rows has nothing to add. The mapping doc proposed the second pass before the run matching was
 * written down.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_index {

    /** Rows per read of a source table. */
    private const PAGE = 5000;

    /** An hour: how long before its own time a run's errors can be (hrms_async caps a run at one hour). */
    public const WINDOW_BEFORE = 3600;

    /** More error rows than this and the index would not fit in memory on a small host: the operator splits the work. */
    public const MAX_ERROR_ROWS = 1000000;

    /** Synthetic run kinds. */
    public const ORPHAN = 'orphan';

    /** @see self::ORPHAN */
    public const SERVICE = 'service';

    /** @var \WeakMap<context, self>|null One index per context. */
    private static ?\WeakMap $memo = null;

    /** @var \DateTimeZone */
    private \DateTimeZone $tz;

    /** @var bool The source has the production-only type column. */
    private bool $hastype = false;

    /** @var bool The source has the production-only sync_file_name column. */
    private bool $hasfile = false;

    /** @var array<int, array<int, array{0: int, 1: int}>> uploader => [[run time, run id], ...] ascending. */
    private array $runs = [];

    /** @var array<int, array<string, int>> uploader => server day => id of the uploader's first run that day. */
    private array $firstrunofday = [];

    /** @var array<int, int> legacy run id => error rows attached to it (warnings are not counted). */
    private array $attached = [];

    /** @var array<string, int> uploader key => lowest error id of that uploader. */
    private array $minid = [];

    /** @var array<string, array<string, array<string, array{0: int, 1: int, 2: int}>>> kind => uploader key => day key => [errors, warnings, last time]. */
    private array $synthetic = [self::ORPHAN => [], self::SERVICE => []];

    /** @var array<string, array<string, string[]>> kind => uploader key => day keys, ascending. */
    private array $keys = [self::ORPHAN => [], self::SERVICE => []];

    /** @var array<int, array{0: int, 1: string}> uploader => [cost centre, tenant method]. */
    private array $tenants = [];

    /** @var array<int, true>|null User ids that are cross-tenant now. */
    private ?array $crosstenant = null;

    /** @var int Error rows read, for the report. */
    private int $errorrows = 0;

    /**
     * The index of this context's run, built on first use.
     *
     * @param context $ctx
     * @return self
     */
    public static function for_context(context $ctx): self {
        self::$memo ??= new \WeakMap();
        if (!isset(self::$memo[$ctx])) {
            self::$memo[$ctx] = new self($ctx);
        }
        return self::$memo[$ctx];
    }

    /**
     * @param context $ctx
     */
    private function __construct(context $ctx) {
        $this->tz = $ctx->servertz();
        if ($ctx->legacy->exists('local_userssyncdata')) {
            $this->read_runs($ctx);
        }
        if ($ctx->legacy->exists('local_syncerrors')) {
            $this->hastype = $ctx->legacy->has_column('local_syncerrors', 'type');
            $this->hasfile = $ctx->legacy->has_column('local_syncerrors', 'sync_file_name');
            $this->read_errors($ctx);
        }
    }

    /**
     * Key that names an uploader group the way the grouped step does: a NULL modified_by is a group of its own,
     * apart from 0 (the framework groups on the raw value).
     *
     * @param mixed $modifiedby
     * @return string
     */
    public static function uploader_key(mixed $modifiedby): string {
        return $modifiedby === null ? 'null' : (string) (int) $modifiedby;
    }

    /**
     * Does the error row come from the HR web service?
     *
     * @param \stdClass $row
     * @return bool
     */
    public function is_service(\stdClass $row): bool {
        return $this->hasfile && strcasecmp(trim((string) ($row->sync_file_name ?? '')), 'Service') === 0;
    }

    /**
     * Is the error row a warning (as opposed to an error)?
     *
     * @param \stdClass $row
     * @return bool
     */
    public function is_warning(\stdClass $row): bool {
        if ($this->hastype) {
            return strcasecmp(trim((string) ($row->type ?? '')), 'Warning') === 0;
        }
        $time = (int) ($row->date_created ?? 0);
        return $time > 0 && $this->at($time)->format('His') === '000000';
    }

    /**
     * Where an error row goes.
     *
     * @param \stdClass $row A local_syncerrors row (id, date_created, modified_by and the optional type and
     *        sync_file_name).
     * @return array{0: string, 1: int|string} ['run', legacy run id], or [ORPHAN|SERVICE, day key]
     */
    public function destination(\stdClass $row): array {
        $time = (int) ($row->date_created ?? 0);
        $uploader = (int) ($row->modified_by ?? 0);
        if ($this->is_service($row)) {
            return [self::SERVICE, $this->day_key($time)];
        }
        $run = $this->is_warning($row) ? $this->run_for_warning($uploader, $time) : $this->run_for_error($uploader, $time);
        if ($run !== null) {
            return ['run', $run];
        }
        return [self::ORPHAN, $this->day_key($time)];
    }

    /**
     * Error rows (not warnings) the legacy run got.
     *
     * @param int $runid
     * @return int
     */
    public function attached_errors(int $runid): int {
        return $this->attached[$runid] ?? 0;
    }

    /**
     * Lowest error id of an uploader group: the id the group's derived map rows are keyed by.
     *
     * @param string $uploaderkey
     * @return int|null
     */
    public function min_error_id(string $uploaderkey): ?int {
        return $this->minid[$uploaderkey] ?? null;
    }

    /**
     * The synthetic runs of an uploader group, as day keys in ascending order.
     *
     * @param string $kind ORPHAN or SERVICE.
     * @param string $uploaderkey
     * @return string[]
     */
    public function synthetic_keys(string $kind, string $uploaderkey): array {
        return $this->keys[$kind][$uploaderkey] ?? [];
    }

    /**
     * Counts of one synthetic run.
     *
     * @param string $kind
     * @param string $uploaderkey
     * @param string $daykey
     * @return array{errors: int, warnings: int, last: int}
     */
    public function synthetic_stats(string $kind, string $uploaderkey, string $daykey): array {
        $stats = $this->synthetic[$kind][$uploaderkey][$daykey] ?? [0, 0, 0];
        return ['errors' => $stats[0], 'warnings' => $stats[1], 'last' => $stats[2]];
    }

    /**
     * The map sub-key of a synthetic run: the chronologically first run of the group is the group's primary row
     * (sub-key ''); every other one is a sub-row named by its day key.
     *
     * @param string $kind
     * @param string $uploaderkey
     * @param string $daykey
     * @return string
     */
    public function subkey_for(string $kind, string $uploaderkey, string $daykey): string {
        $keys = $this->keys[$kind][$uploaderkey] ?? [];
        return ($keys && $keys[0] === $daykey) ? '' : $daykey;
    }

    /**
     * The cost centre (tenant root) of a run an uploader made, and how it was found.
     *
     * BizLMS stored the cost centre of the LAST row processed (0 after an organisation error, and never set by
     * the cron import), so it is never used. The tenant is the uploader's tenant NOW, by the signed rules:
     * a cross-tenant uploader (site admin, or a holder of the cross-tenant capability) gets 0 under the decision
     * users.admin_runs_tenant = zero and their own root under uploader_root; an uploader with no resolvable
     * tenant gets 0 (the run is then visible to cross-tenant callers only, which the signed decision
     * tenant.unresolved.users = pathless says).
     *
     * @param context $ctx
     * @param int $uploader User id; 0 when the source names none.
     * @return array{0: int, 1: string} [cost centre, tenant method]
     */
    public function tenant_for_uploader(context $ctx, int $uploader): array {
        if (isset($this->tenants[$uploader])) {
            return $this->tenants[$uploader];
        }
        if ($uploader <= 0) {
            return $this->tenants[$uploader] = [0, 'unresolved'];
        }
        if ($this->crosstenant === null) {
            $this->crosstenant = array_fill_keys(\local_sentientia_platform\tenant::cross_tenant_userids(), true);
        }
        if (isset($this->crosstenant[$uploader]) && $ctx->decision('users.admin_runs_tenant') === 'zero') {
            return $this->tenants[$uploader] = [0, 'fallback:crosstenant'];
        }
        [, $root, $method] = $ctx->tenant->resolve(['uploader' => $ctx->lookups->user_path($uploader)]);
        return $this->tenants[$uploader] = $root === null ? [0, 'unresolved'] : [(int) $root, $method];
    }

    /**
     * Error rows read, for the report and the tests.
     *
     * @return int
     */
    public function error_rows(): int {
        return $this->errorrows;
    }

    // Building.

    /**
     * @param context $ctx
     * @return void
     */
    private function read_runs(context $ctx): void {
        $after = 0;
        do {
            $rows = $ctx->legacy->page('local_userssyncdata', $after, self::PAGE, ['usercreated', 'timecreated']);
            foreach ($rows as $id => $row) {
                $uploader = (int) ($row->usercreated ?? 0);
                $this->runs[$uploader][] = [(int) ($row->timecreated ?? 0), (int) $id];
                $after = (int) $id;
            }
        } while (count($rows) === self::PAGE);

        foreach ($this->runs as $uploader => $list) {
            // Ascending by time, then id: the id keeps two runs in one second in the order BizLMS wrote them.
            usort($list, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $this->runs[$uploader] = $list;
            foreach ($list as [$time, $id]) {
                $day = $this->day_key($time);
                if (!isset($this->firstrunofday[$uploader][$day])) {
                    $this->firstrunofday[$uploader][$day] = $id;
                }
            }
        }
    }

    /**
     * @param context $ctx
     * @return void
     */
    private function read_errors(context $ctx): void {
        $after = 0;
        do {
            $rows = $ctx->legacy->page('local_syncerrors', $after, self::PAGE,
                ['date_created', 'modified_by', 'type', 'sync_file_name']);
            foreach ($rows as $id => $row) {
                $after = (int) $id;
                if (++$this->errorrows > self::MAX_ERROR_ROWS) {
                    throw new blocked('sync_error_index_too_large');
                }
                $this->account($row);
            }
        } while (count($rows) === self::PAGE);

        foreach (array_keys($this->synthetic) as $kind) {
            foreach ($this->synthetic[$kind] as $uploaderkey => $days) {
                $keys = array_keys($days);
                sort($keys, SORT_STRING);
                $this->keys[$kind][$uploaderkey] = $keys;
            }
        }
    }

    /**
     * Count one error row into the aggregates.
     *
     * @param \stdClass $row
     * @return void
     */
    private function account(\stdClass $row): void {
        $id = (int) $row->id;
        $ukey = self::uploader_key($row->modified_by ?? null);
        if (!isset($this->minid[$ukey])) {
            $this->minid[$ukey] = $id;
        }
        $warning = $this->is_warning($row);
        [$kind, $ref] = $this->destination($row);
        if ($kind === 'run') {
            if (!$warning) {
                $this->attached[$ref] = ($this->attached[$ref] ?? 0) + 1;
            }
            return;
        }
        $time = (int) ($row->date_created ?? 0);
        $stats = $this->synthetic[$kind][$ukey][$ref] ?? [0, 0, 0];
        $stats[$warning ? 1 : 0]++;
        $stats[2] = max($stats[2], $time);
        $this->synthetic[$kind][$ukey][$ref] = $stats;
    }

    // Matching.

    /**
     * The legacy run an error of this uploader at this time belongs to, or null.
     *
     * @param int $uploader
     * @param int $time
     * @return int|null
     */
    private function run_for_error(int $uploader, int $time): ?int {
        $list = $this->runs[$uploader] ?? [];
        if (!$list || $time <= 0) {
            return null;
        }
        // The first run at or after the error: the error happened while it was running.
        $low = 0;
        $high = count($list);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($list[$middle][0] < $time) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }
        if ($low >= count($list)) {
            return null;
        }
        [$runtime, $runid] = $list[$low];
        return $time >= $runtime - self::WINDOW_BEFORE ? $runid : null;
    }

    /**
     * The first run of this uploader on the server-timezone day of a warning, or null.
     *
     * @param int $uploader
     * @param int $time
     * @return int|null
     */
    private function run_for_warning(int $uploader, int $time): ?int {
        if ($time <= 0) {
            return null;
        }
        return $this->firstrunofday[$uploader][$this->day_key($time)] ?? null;
    }

    /**
     * @param int $time
     * @return \DateTimeImmutable
     */
    private function at(int $time): \DateTimeImmutable {
        return (new \DateTimeImmutable('@' . $time))->setTimezone($this->tz);
    }

    /**
     * Server-timezone day of a time, as the sub-key the map stores ("day:20260930"); "day:none" for no time.
     *
     * @param int $time
     * @return string
     */
    private function day_key(int $time): string {
        return $time > 0 ? 'day:' . $this->at($time)->format('Ymd') : 'day:none';
    }
}
