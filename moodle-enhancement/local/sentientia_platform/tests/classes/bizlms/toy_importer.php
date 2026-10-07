<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\recompute_step;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\step;
use local_sentientia_platform\bizlms\tenant_resolver;
use local_sentientia_platform\bizlms\watches_tables;

/**
 * The toy importer the ADR-032 framework tests run (tests/fixtures/bizlms/toy.install.xml).
 *
 * It exercises every framework path with synthetic data: a PRESERVE step with an
 * adoptable header copy, a MAP step that resolves parents through the map and
 * checks users, truncates text and resolves a tenant, a grouped step that merges
 * duplicates, a derived-group step, a fan-out step with sub-rows, and a recompute
 * step. Public static knobs let a test break it on purpose; call reset() in setUp().
 *
 * It lives under tests/classes, which Moodle autoloads during PHPUnit as
 * local_sentientia_platform\tests\. Real importers do not write or read anything
 * outside their context; the knobs here are test scaffolding only.
 *
 * Not final: toy_files_importer extends it to implement the copies_files marker (IDN-04), because a class cannot
 * implement an interface conditionally.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toy_importer implements importer, watches_tables {

    /** @var bool Run the whole feature in one outer transaction when small. */
    public static bool $atomic = false;

    /** @var bool verify() writes a log row: the side-effect tripwire must trip. */
    public static bool $leak = false;

    /** @var bool verify() reports a failure. */
    public static bool $failverify = false;

    /** @var string|null Extra column name added to every toy.org row: the writer must refuse it. */
    public static ?string $unknownfield = null;

    /** @var int|null Length of the title toy.item writes straight through, without fit(): the writer must refuse it. */
    public static ?int $rawtitlelength = null;

    /** @var bool toy.org leaves a time column out: the writer must refuse it. */
    public static bool $omittimestamp = false;

    /** @var bool toy.item (a MAP step) wrongly declares external_refs: the registry must refuse it. */
    public static bool $maprefs = false;

    /** @var bool toy.org (a PRESERVE step) declares no external_refs: the registry must refuse it. */
    public static bool $preservenorefs = false;

    /** @var bool decisions() adds a required decision with no default. */
    public static bool $requiredecision = false;

    /** @var int Plugin version the importer claims to need; above the installed version the registry must refuse it. */
    public static int $requiresversion = 0;

    /** @var bool Declares a BizLMS legacy table as a target: the registry must refuse it. */
    public static bool $legacytarget = false;

    /** @var bool toy.dup skips the group winner but still merges the duplicates into it: the runner must refuse. */
    public static bool $skipwinner = false;

    /** @var array|null [table, id]: toy.fan folds into this row instead of inserting. */
    public static ?array $foldto = null;

    /** @var bool The importer declares a reviewed core write (so a purge must refuse). */
    public static bool $corewrites = false;

    /** @var decision[] Extra owner choices the importer declares, so a test can read them through a context. */
    public static array $extradecisions = [];

    /** @var bool toy.org triggers a core event in transform(): its log row is written after the commit, out of sight of the row counters. */
    public static bool $fireevent = false;

    /** @var bool toy.item (a MAP step) also inserts a sub-row into the PRESERVE target toy.org: the writer must refuse. */
    public static bool $mappreserve = false;

    /** @var bool toy.item answers every row with the reserved reason deferred: an apply run must refuse it. */
    public static bool $forcedeferred = false;

    /** @var bool toy.dup skips every row with a retryable reason: --retry-skipped must refuse a grouped step. */
    public static bool $dupunclear = false;

    /** @var bool toy.item filters kind c out of its source without archiving it: the unfiltered accounting must fail. */
    public static bool $itemfilter = false;

    /** @var string|null A core table declared in core_writes() that is not on the reviewed list: the registry must refuse it. */
    public static ?string $corewritetable = null;

    /** @var string|null A table added to target_tables(): another plugin's table or a legacy table the registry must refuse. */
    public static ?string $extratarget = null;

    /** @var bool toy.dup (a MAP step) names the PRESERVE target of toy.org as its own: the registry must refuse. */
    public static bool $dupintoorg = false;

    /** @var bool tenant_columns() is empty: the registry's tenant rule does not apply, so the run-time guard is what is tested. */
    public static bool $notenantcolumns = false;

    /** @var bool toy.fan reads the organisation table (has_orgs) in transform(). */
    public static bool $readorgs = false;

    /** @var bool finalise() writes a log row: the tripwire's look after finalise must trip. */
    public static bool $finaliseleak = false;

    /** @var bool toy.org writes a log row in transform(), which a dry run executes too. */
    public static bool $dryleak = false;

    /** @var array|null [component, filearea]: finalise() stores a file there, in the system context (IDN-04). */
    public static ?array $writefile = null;

    /** @var array The file areas toy_files_importer declares (copies_files): [source component, source area, target component, target area]. */
    public static array $fileareas = [['local_toy', 'source', 'local_sentientia_platform', 'toytarget']];

    /** @var bool preflight() asks the context for a decision the owner has not accepted: blocked() must not escape the runner (F-10). */
    public static bool $preflightdecision = false;

    /** @var bool[] What verify() saw in $ctx->dryrun, one entry per call. */
    public static array $verifyseen = [];

    /** @var string[] Lifecycle log: what finalise() saw. */
    public static array $finalised = [];

    /** @var string[] Marker state at the moment finalise() ran: complete or pending. */
    public static array $markerseen = [];

    /**
     * Put every knob back.
     *
     * @return void
     */
    public static function reset(): void {
        self::$atomic = false;
        self::$leak = false;
        self::$failverify = false;
        self::$unknownfield = null;
        self::$rawtitlelength = null;
        self::$omittimestamp = false;
        self::$maprefs = false;
        self::$preservenorefs = false;
        self::$requiredecision = false;
        self::$requiresversion = 0;
        self::$legacytarget = false;
        self::$skipwinner = false;
        self::$foldto = null;
        self::$corewrites = false;
        self::$extradecisions = [];
        self::$fireevent = false;
        self::$mappreserve = false;
        self::$forcedeferred = false;
        self::$dupunclear = false;
        self::$itemfilter = false;
        self::$corewritetable = null;
        self::$extratarget = null;
        self::$dupintoorg = false;
        self::$notenantcolumns = false;
        self::$readorgs = false;
        self::$finaliseleak = false;
        self::$dryleak = false;
        self::$writefile = null;
        self::$fileareas = [['local_toy', 'source', 'local_sentientia_platform', 'toytarget']];
        self::$preflightdecision = false;
        self::$verifyseen = [];
        self::$finalised = [];
        self::$markerseen = [];
    }

    /** @var string */
    private string $feature;

    /** @var string[] */
    private array $depends;

    /** @var string[]|null Legacy tables this instance claims; null claims all of them. */
    private ?array $only;

    /**
     * @param string $feature Feature key; step keys are prefixed with it.
     * @param string[] $depends
     * @param string[]|null $only Restrict the claimed tables (and so the steps).
     */
    public function __construct(string $feature = 'toy', array $depends = [], ?array $only = null) {
        $this->feature = $feature;
        $this->depends = $depends;
        $this->only = $only;
    }

    public function feature(): string {
        return $this->feature;
    }

    public function component(): string {
        return 'local_sentientia_platform';
    }

    public function requires_version(): int {
        return self::$requiresversion;
    }

    public function depends(): array {
        return $this->depends;
    }

    public function sources(): array {
        $all = [
            'local_toy_org' => new source_spec('local_toy_org'),
            'local_toy_item' => new source_spec('local_toy_item', true, ['kind' => ['a' => 'alpha', 'b' => 'beta', 'c' => 'gamma']]),
            'local_toy_dup' => new source_spec('local_toy_dup'),
            'local_toy_event' => new source_spec('local_toy_event'),
            'local_toy_fan' => new source_spec('local_toy_fan'),
        ];
        return $this->only === null ? $all : array_intersect_key($all, array_flip($this->only));
    }

    public function declined_tables(): array {
        return $this->only === null ? ['local_toy_unused' => 'configuration, read in place'] : [];
    }

    public function target_tables(): array {
        $tables = [
            'local_sentientia_toy_org', 'local_sentientia_toy_item', 'local_sentientia_toy_dup',
            'local_sentientia_toy_order', 'local_sentientia_toy_fan', 'local_sentientia_toy_fanout',
        ];
        if (self::$legacytarget) {
            $tables[] = 'local_costcenter';
        }
        if (self::$extratarget !== null) {
            $tables[] = self::$extratarget;
        }
        return $tables;
    }

    public function core_writes(): array {
        if (self::$corewritetable !== null) {
            return [self::$corewritetable => 'toy: a table nobody reviewed'];
        }
        return self::$corewrites ? ['course' => 'toy: a reviewed remap'] : [];
    }

    public function tenant_columns(): array {
        return self::$notenantcolumns ? [] : ['local_sentientia_toy_item' => 'tenantpath'];
    }

    public function reasons(): array {
        return [
            new reason('no_name', false, false),
            new reason('not_history', false, false),
            new reason('orphan_org', false, true),
            new reason('orphan_user', true, true),
            new reason('dup_natural_key', false, false),
            new reason('dup_unclear', true, false),
        ];
    }

    public function decisions(): array {
        $decisions = [new decision('toy.rounding', 'How amounts are rounded', true, 'half_up')];
        if (self::$requiredecision) {
            $decisions[] = new decision('toy.mandatory', 'A choice only the owner can make');
        }
        foreach (self::$extradecisions as $extra) {
            $decisions[] = $extra;
        }
        return $decisions;
    }

    public function atomic(): bool {
        return self::$atomic;
    }

    public function watched_tables(): array {
        // A declared target: the framework must ignore it, never trip on it.
        return ['local_sentientia_toy_fan'];
    }

    public function steps(): array {
        $steps = [];
        $claimed = $this->sources();
        if (isset($claimed['local_toy_org'])) {
            $steps[] = $this->org_step();
        }
        if (isset($claimed['local_toy_item'])) {
            $steps[] = $this->item_step();
        }
        if (isset($claimed['local_toy_dup'])) {
            $steps[] = $this->dup_step();
        }
        if (isset($claimed['local_toy_event'])) {
            $steps[] = $this->order_step();
        }
        if (isset($claimed['local_toy_fan'])) {
            $steps[] = $this->fan_step();
        }
        if (isset($claimed['local_toy_org'])) {
            $steps[] = $this->recompute_step();
        }
        return $steps;
    }

    public function preflight(context $ctx): preflight {
        if (self::$preflightdecision) {
            // Throws blocked() for a key the owner has left open: the runner records it, it does not escape.
            $ctx->decision('toy.credit');
        }
        return new preflight();
    }

    public function verify(context $ctx): array {
        $failures = [];
        self::$verifyseen[] = $ctx->dryrun;
        if (self::$leak) {
            self::leak_log_row();
        }
        if (self::$requiredecision) {
            $ctx->decision('toy.mandatory');
        }
        if (self::$failverify) {
            $failures[] = 'toy_verify_failed';
        }
        return $failures;
    }

    /**
     * Test-only: write to an append-only table the import must never write to.
     *
     * @return void
     */
    public static function leak_log_row(): void {
        global $DB;
        $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\\core\\event\\toy_leak', 'component' => 'core', 'action' => 'leaked',
            'target' => 'toy', 'objecttable' => null, 'objectid' => null, 'crud' => 'c', 'edulevel' => 0,
            'contextid' => 1, 'contextlevel' => 10, 'contextinstanceid' => 0, 'userid' => 0, 'courseid' => 0,
            'relateduserid' => null, 'anonymous' => 0, 'other' => null, 'timecreated' => time(),
            'origin' => 'cli', 'ip' => null, 'realuserid' => null,
        ]);
    }

    /**
     * Test-only: store one small file in the system context, the way file_rehome's copy does.
     *
     * @param string $component
     * @param string $filearea
     * @return void
     */
    public static function write_file(string $component, string $filearea): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id, 'component' => $component, 'filearea' => $filearea,
            'itemid' => 1, 'filepath' => '/', 'filename' => 'toy.txt',
        ], 'toy');
    }

    public function finalise(context $ctx): void {
        if (self::$finaliseleak) {
            self::leak_log_row();
        }
        if (self::$writefile !== null) {
            self::write_file(self::$writefile[0], self::$writefile[1]);
        }
        self::$finalised[] = $this->feature;
        self::$markerseen[] = legacymap::feature_complete($this->feature) ? 'complete' : 'pending';
    }

    /**
     * PRESERVE step with an adoptable header copy.
     *
     * @return step
     */
    private function org_step(): step {
        return new class($this->feature) extends step {
            public function __construct(private string $f) {
            }

            public function key(): string {
                return $this->f . '.org';
            }

            public function sourcetable(): string {
                return 'local_toy_org';
            }

            public function targettable(): string {
                return 'local_sentientia_toy_org';
            }

            public function idpolicy(): string {
                return idpolicy::PRESERVE;
            }

            public function external_refs(): array {
                return toy_importer::$preservenorefs ? [] : [['local_toy_item', 'orgid']];
            }

            public function transform(array $rows, context $ctx): array {
                if (toy_importer::$dryleak) {
                    toy_importer::leak_log_row();
                }
                if (toy_importer::$fireevent) {
                    // A core API fires an event. Its non-internal observers (the standard log) run only after the
                    // outermost transaction commits, and the log store buffers what it gets.
                    \core\event\dashboard_viewed::create(['context' => \context_system::instance()])->trigger();
                }
                $out = [];
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    if (trim((string) $row->name) === '') {
                        $out[] = outcome::skip($id, 'no_name');
                        continue;
                    }
                    if ((int) $row->status === 9) {
                        $out[] = outcome::archive($id, 'not_history');
                        continue;
                    }
                    $fields = (object) [
                        'name' => $row->name,
                        'path' => tenant_resolver::normalise($row->path),
                        // Derived in the recompute step, like a program's current level: 0 until then.
                        'visible' => 0,
                        'timecreated' => (int) $row->timecreated,
                    ];
                    if (!toy_importer::$omittimestamp) {
                        $fields->timemodified = (int) $row->timemodified;
                    }
                    if (toy_importer::$unknownfield !== null) {
                        $fields->{toy_importer::$unknownfield} = 1;
                    }
                    $out[] = outcome::insert($id, 'local_sentientia_toy_org', $fields);
                }
                return $out;
            }
        };
    }

    /**
     * MAP step: resolves its parent through the map, checks the user, truncates
     * text explicitly and resolves a tenant from the user's open_path.
     *
     * @return step
     */
    private function item_step(): step {
        return new class($this->feature) extends step {
            public function __construct(private string $f) {
            }

            public function key(): string {
                return $this->f . '.item';
            }

            public function sourcetable(): string {
                return 'local_toy_item';
            }

            public function targettable(): string {
                return 'local_sentientia_toy_item';
            }

            public function preload(): array {
                return [['local_toy_org', '']];
            }

            public function source_filter(): array {
                return toy_importer::$itemfilter ? ['t.kind <> :blmkind', ['blmkind' => 'c']] : ['', []];
            }

            public function external_refs(): array {
                return toy_importer::$maprefs ? [['local_toy_unused', 'note']] : [];
            }

            public function transform(array $rows, context $ctx): array {
                $out = [];
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    if (toy_importer::$forcedeferred) {
                        $out[] = outcome::skip($id, 'deferred');
                        continue;
                    }
                    $orgid = $ctx->map->resolve('local_toy_org', (int) $row->orgid);
                    if ($orgid === null) {
                        $out[] = outcome::skip($id, $ctx->is_deferred('local_toy_org') ? 'deferred' : 'orphan_org');
                        continue;
                    }
                    if (!$ctx->lookups->user_exists((int) $row->userid)) {
                        $out[] = outcome::skip($id, 'orphan_user', 'user_not_found');
                        continue;
                    }
                    [$path, $root, $method] = $ctx->tenant->resolve([
                        'row' => $row->path,
                        'user' => $ctx->lookups->user_path((int) $row->userid),
                    ]);
                    $title = toy_importer::$rawtitlelength !== null
                        ? str_repeat('x', toy_importer::$rawtitlelength)
                        : $ctx->text->fit($row->title, 40, 'title');
                    $out[] = outcome::insert($id, 'local_sentientia_toy_item', (object) [
                        'orgid' => $orgid,
                        'userid' => (int) $row->userid,
                        'title' => $title,
                        'kind' => $row->kind,
                        'tenantpath' => $path,
                        'timecreated' => (int) $row->timecreated,
                        'timemodified' => (int) $row->timemodified,
                    ])->tenant_method($method);
                    if (toy_importer::$mappreserve) {
                        // A MAP insert into a table a PRESERVE step owns would take a legacy id the next batch needs.
                        $out[] = outcome::insert($id, 'local_sentientia_toy_org', (object) [
                            'name' => 'Sub-row', 'path' => null, 'visible' => 0,
                            'timecreated' => (int) $row->timecreated, 'timemodified' => (int) $row->timemodified,
                        ], 'org:extra');
                    }
                }
                return $out;
            }
        };
    }

    /**
     * Grouped MAP step: rows with the same natural key merge into the first.
     *
     * @return step
     */
    private function dup_step(): step {
        return new class($this->feature) extends step {
            public function __construct(private string $f) {
            }

            public function key(): string {
                return $this->f . '.dup';
            }

            public function sourcetable(): string {
                return 'local_toy_dup';
            }

            public function targettable(): string {
                return toy_importer::$dupintoorg ? 'local_sentientia_toy_org' : 'local_sentientia_toy_dup';
            }

            public function group_by(): array {
                return ['natkey'];
            }

            public function transform(array $rows, context $ctx): array {
                if (toy_importer::$dupunclear) {
                    $out = [];
                    foreach ($rows as $row) {
                        $out[] = outcome::skip((int) $row->id, 'dup_unclear');
                    }
                    return $out;
                }
                $winner = $rows[0];
                if (toy_importer::$skipwinner) {
                    $out = [outcome::skip((int) $winner->id, 'no_name')];
                    foreach (array_slice($rows, 1) as $row) {
                        $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_natural_key');
                    }
                    return $out;
                }
                $out = [outcome::insert((int) $winner->id, 'local_sentientia_toy_dup', (object) [
                    'natkey' => $winner->natkey,
                    'label' => $winner->label,
                    'timecreated' => (int) $winner->timecreated,
                    'timemodified' => (int) $winner->timecreated,
                ])];
                foreach (array_slice($rows, 1) as $row) {
                    $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_natural_key');
                }
                return $out;
            }
        };
    }

    /**
     * Derived-group step: one order per cart, keyed by the cart id.
     *
     * @return step
     */
    private function order_step(): step {
        return new class($this->feature) extends step {
            public function __construct(private string $f) {
            }

            public function key(): string {
                return $this->f . '.order';
            }

            public function sourcetable(): string {
                return '#local_toy_event.cartid';
            }

            public function targettable(): string {
                return 'local_sentientia_toy_order';
            }

            public function group_by(): array {
                return ['cartid'];
            }

            public function transform(array $rows, context $ctx): array {
                $total = 0;
                $created = PHP_INT_MAX;
                foreach ($rows as $row) {
                    $total += (int) $row->amount;
                    $created = min($created, (int) $row->timecreated);
                }
                $cartid = (int) reset($rows)->cartid;
                return [outcome::insert($cartid, 'local_sentientia_toy_order', (object) [
                    'cartid' => $cartid, 'total' => $total, 'timecreated' => $created, 'timemodified' => $created,
                ])];
            }
        };
    }

    /**
     * Fan-out step: one primary row plus sub-rows with sub-keys.
     *
     * @return step
     */
    private function fan_step(): step {
        return new class($this->feature) extends step {
            public function __construct(private string $f) {
            }

            public function key(): string {
                return $this->f . '.fan';
            }

            public function sourcetable(): string {
                return 'local_toy_fan';
            }

            public function targettable(): string {
                return 'local_sentientia_toy_fan';
            }

            public function transform(array $rows, context $ctx): array {
                if (toy_importer::$readorgs) {
                    $ctx->lookups->has_orgs();
                }
                $out = [];
                foreach ($rows as $row) {
                    $id = (int) $row->id;
                    $created = (int) $row->timecreated;
                    if (toy_importer::$foldto !== null) {
                        $out[] = outcome::fold($id, toy_importer::$foldto[0], toy_importer::$foldto[1], 'dup_natural_key');
                        continue;
                    }
                    $out[] = outcome::insert($id, 'local_sentientia_toy_fan', (object) [
                        'title' => $row->title, 'timecreated' => $created, 'timemodified' => $created,
                    ]);
                    foreach (['a', 'b'] as $part) {
                        $out[] = outcome::insert($id, 'local_sentientia_toy_fanout', (object) [
                            'fanid' => $id, 'part' => $part, 'timecreated' => $created, 'timemodified' => $created,
                        ], 'part:' . $part);
                    }
                }
                return $out;
            }
        };
    }

    /**
     * Recompute step over the imported organisations.
     *
     * @return recompute_step
     */
    private function recompute_step(): recompute_step {
        return new class($this->feature) extends recompute_step {
            public function __construct(private string $f) {
            }

            public function key(): string {
                return $this->f . '.recompute';
            }

            public function targettable(): string {
                return 'local_sentientia_toy_org';
            }

            public function recompute(array $targetids, context $ctx): array {
                $out = [];
                foreach ($targetids as $id) {
                    $out[] = outcome::update('local_sentientia_toy_org', $id, (object) ['visible' => 1]);
                }
                return $out;
            }
        };
    }
}
