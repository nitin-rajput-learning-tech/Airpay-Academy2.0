<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Everything a step, preflight or verify may read (ADR-032, "Importer interface").
 *
 * It is read-only by design: the map, the lookups and the legacy reader only
 * read, and there is no writer here. Steps reach the database only through
 * this object, which is what makes a dry run and an apply run execute the same
 * code.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class context {

    /** @var bool True in a dry run: nothing is written, map ids are virtual. */
    public readonly bool $dryrun;

    /** @var int Run id; 0 in a dry run. */
    public readonly int $runid;

    /** @var legacymap Resolves legacy ids; in a dry run an in-memory overlay sits over it. */
    public readonly legacymap $map;

    /** @var tenant_resolver */
    public readonly tenant_resolver $tenant;

    /** @var lookups Users, courses, organisations: bulk-loaded, read-only. */
    public readonly lookups $lookups;

    /** @var legacy_reader Bounded, read-only access to other legacy tables. */
    public readonly legacy_reader $legacy;

    /** @var text fit(): explicit truncation that reports. */
    public readonly text $text;

    /** @var string Feature this context belongs to. */
    public readonly string $feature;

    /** @var decisions */
    private decisions $decisions;

    /** @var array<string, decision> Declared decisions of the feature. */
    private array $declared;

    /** @var \Closure(string): bool */
    private \Closure $deferred;

    /**
     * Use build(); the constructor only stores the collaborators.
     *
     * @param string $feature
     * @param bool $dryrun
     * @param int $runid
     * @param legacymap $map
     * @param tenant_resolver $tenant
     * @param lookups $lookups
     * @param legacy_reader $legacy
     * @param text $text
     * @param decisions $decisions
     * @param array<string, decision> $declared
     * @param \Closure $deferred
     */
    public function __construct(string $feature, bool $dryrun, int $runid, legacymap $map, tenant_resolver $tenant,
                                lookups $lookups, legacy_reader $legacy, text $text, decisions $decisions,
                                array $declared, \Closure $deferred) {
        $this->feature = $feature;
        $this->dryrun = $dryrun;
        $this->runid = $runid;
        $this->map = $map;
        $this->tenant = $tenant;
        $this->lookups = $lookups;
        $this->legacy = $legacy;
        $this->text = $text;
        $this->decisions = $decisions;
        $this->declared = $declared;
        $this->deferred = $deferred;
    }

    /**
     * Build a context for an importer, reusing shared collaborators when given.
     *
     * @param importer $importer
     * @param bool $dryrun
     * @param int $runid
     * @param decisions $decisions
     * @param array $shared Optional map, lookups, tenant, legacy, text, deferred to reuse.
     * @return self
     */
    public static function build(importer $importer, bool $dryrun, int $runid, decisions $decisions,
                                 array $shared = []): self {
        $lookups = $shared['lookups'] ?? new lookups();
        $declared = [];
        foreach ($importer->decisions() as $decision) {
            $declared[$decision->key] = $decision;
        }
        return new self(
            $importer->feature(),
            $dryrun,
            $runid,
            $shared['map'] ?? new legacymap(),
            $shared['tenant'] ?? new tenant_resolver($lookups),
            $lookups,
            $shared['legacy'] ?? new legacy_reader(),
            $shared['text'] ?? new text(),
            $decisions,
            $declared,
            $shared['deferred'] ?? static fn(string $sourcetable): bool => false,
        );
    }

    /**
     * An owner choice from the decisions file.
     *
     * @param string $key A key the importer declared in decisions().
     * @return mixed The file's value, else the declared default.
     * @throws blocked When the decision is required, unset and has no default.
     */
    public function decision(string $key): mixed {
        if (!isset($this->declared[$key])) {
            throw new \coding_exception("decision {$key} is not declared by the {$this->feature} importer");
        }
        if ($this->decisions->has($key)) {
            return $this->decisions->get($key);
        }
        $declared = $this->declared[$key];
        if ($declared->default !== null) {
            return $declared->default;
        }
        if ($declared->required) {
            throw new blocked('missing_decision:' . $key);
        }
        return null;
    }

    /**
     * Moodle's effective server timezone, for legacy timestamps that BizLMS
     * wrote as local date strings.
     *
     * @return \DateTimeZone
     */
    public function servertz(): \DateTimeZone {
        return \core_date::get_server_timezone_object();
    }

    /**
     * Is this legacy table owned by a dependency that is neither complete nor
     * simulated in this run? A single-feature dry run then reports rows that
     * cannot resolve a parent as deferred (reason::DEFERRED), not as failures.
     *
     * @param string $sourcetable Legacy table of the parent, without prefix.
     * @return bool
     */
    public function is_deferred(string $sourcetable): bool {
        return ($this->deferred)($sourcetable);
    }
}
