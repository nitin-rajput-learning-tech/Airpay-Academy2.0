<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * The one load step of the cohort_scope import: local_groups to local_sentientia_cohort_scope.
 *
 * local_groups is the tenant satellite of a core cohort: one row per cohort that says which
 * organisation (open_path) and departments it belongs to (BizLMS local/groups/lib.php:245-254).
 * Core cohort and cohort_members are carried unchanged by the restored database; only this
 * satellite is copied.
 *
 * The step is grouped by cohortid because the target holds one row per cohort. The source index is
 * not unique, so a cohort can in theory own more than one local_groups row; the rows of a cohort
 * are settled together and exactly one of them is imported, the rest merged into it.
 *
 * The join is on cohortid only. The row id means nothing: local_groups_update_groups() updates the
 * row whose id equals the COHORT id (BizLMS local/groups/lib.php:292-293), so an edit overwrote the
 * path of whichever row had that id. The path of a row is therefore checked against the cohort's
 * context, and a disagreement is reported (path_context_mismatch), never silently repaired.
 *
 * Tenant: the row's own normalised open_path when it names a registered tenant ('0', the NOT NULL
 * default, and '' mean unknown); else the organisation that owns the course category of the
 * cohort's context; else the row's costcenterid when it is a tenant root. A cohort that none of
 * these places is imported with no tenant path (visible to cross-tenant callers only) or skipped,
 * as the tenant.unresolved.cohort_scope decision says.
 *
 * transform() is pure: it reads only through the context and returns outcomes.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_scope_step extends step {

    /** @var cohort_context Cohort, context and category facts, loaded on first use. */
    private cohort_context $cohorts;

    /**
     * @param cohort_context|null $cohorts Shared facts; a fresh loader when null.
     */
    public function __construct(?cohort_context $cohorts = null) {
        $this->cohorts = $cohorts ?? new cohort_context();
    }

    public function key(): string {
        return cohort_scope_importer::FEATURE . '.scope';
    }

    public function sourcetable(): string {
        return cohort_scope_importer::SOURCE;
    }

    public function targettable(): string {
        return cohort_scope_importer::TARGET;
    }

    public function group_by(): array {
        return ['cohortid'];
    }

    public function columns(): array {
        // costcenterid, departmentid, open_path and the actor columns are named in the source_spec as optional:
        // a production table that lacks one is still read.
        return ['cohortid', 'costcenterid', 'departmentid', 'open_path', 'timemodified', 'usermodified'];
    }

    public function transform(array $rows, context $ctx): array {
        $cohortid = (int) $rows[0]->cohortid;
        $core = $cohortid > 0 ? $this->cohorts->cohort($ctx, $cohortid) : null;
        if ($core === null) {
            // The satellite outlived its cohort (or never had one). There is nothing to scope.
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, 'orphan_cohort',
                    $cohortid > 0 ? 'cohort_not_found' : 'cohortid_invalid');
            }
            return $out;
        }

        $derived = $this->cohorts->org_path($ctx, $cohortid);
        $contextplace = $derived === null ? [null, null, 'unresolved'] : $ctx->tenant->resolve(['context' => $derived]);

        $candidates = [];
        foreach ($rows as $row) {
            [$path, , $method] = $ctx->tenant->resolve([
                'row' => $row->open_path ?? null,
                'context' => $derived,
                'costcenter' => isset($row->costcenterid) ? (int) $row->costcenterid : null,
            ]);
            [$ownpath, $ownroot] = $ctx->tenant->resolve(['row' => $row->open_path ?? null]);
            // The row agrees with the cohort's context unless both name a tenant and the tenants differ.
            $disagrees = $ownpath !== null && $contextplace[0] !== null && $ownroot !== $contextplace[1];
            $candidates[] = [
                'row' => $row,
                'id' => (int) $row->id,
                'path' => $path,
                'method' => $method,
                'resolved' => $path === null ? 0 : 1,
                'consistent' => $disagrees ? 0 : 1,
                'modified' => (int) ($row->timemodified ?? 0),
                'mismatch' => $disagrees,
            ];
        }
        // The winner: a row that places the cohort, then one that agrees with the cohort's context, then the
        // newest change, then the highest id. One row is the common case and all of this is then moot.
        usort($candidates, static function (array $a, array $b): int {
            return [$b['resolved'], $b['consistent'], $b['modified'], $b['id']]
                <=> [$a['resolved'], $a['consistent'], $a['modified'], $a['id']];
        });
        $winner = $candidates[0];
        $winnerrow = $winner['row'];

        if ($winner['path'] === null && $ctx->decision(cohort_scope_importer::DECISION_UNRESOLVED) === 'skip') {
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, 'no_tenant', 'tenant_unresolved');
            }
            return $out;
        }

        $warnings = [];
        if ($winner['path'] === null) {
            $warnings[] = 'tenant_unresolved';
        }
        if ($winner['mismatch']) {
            $warnings[] = 'path_context_mismatch';
        }
        $changed = false;
        $departments = self::clean_departments($winnerrow->departmentid ?? null, $changed);
        if ($changed) {
            $warnings[] = 'departments_cleaned';
        }
        $modified = $winner['modified'];
        if ($modified <= 0) {
            // The satellite never recorded a change: the cohort's own row is the nearest honest date.
            $modified = $core['timemodified'] > 0 ? $core['timemodified'] : $core['timecreated'];
            $warnings[] = 'derived_timestamp';
        }

        $insert = outcome::insert((int) $winnerrow->id, cohort_scope_importer::TARGET, (object) [
            'cohortid' => $cohortid,
            'open_path' => $winner['path'],
            'departmentids' => $departments,
            'usermodified' => max(0, (int) ($winnerrow->usermodified ?? 0)),
            'timemodified' => $modified,
        ])->tenant_method($winner['method']);
        foreach ($warnings as $warning) {
            $insert->warn($warning);
        }

        $out = [$insert];
        foreach ($candidates as $candidate) {
            if ($candidate['id'] !== $winner['id']) {
                $out[] = outcome::merge($candidate['id'], $winner['id'], 'dup_cohort_row');
            }
        }
        return $out;
    }

    /**
     * A comma list of positive department ids, each once, in the order given.
     *
     * The source is a free CHAR(100): it holds '5,12', but also ' 5 , 12', an empty string, '0', and whatever
     * a form posted when an array was saved into it ('Array'). Only positive whole numbers are departments.
     * A '0' or an empty item is "none" and is dropped quietly; any other item that is not a department id is
     * dropped and reported.
     *
     * @param string|null $raw The source value.
     * @param bool $changed Set to true when a non-empty, non-zero item was dropped.
     * @return string|null The cleaned list, or null when no department is left.
     */
    public static function clean_departments(?string $raw, bool &$changed): ?string {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $ids = [];
        foreach (explode(',', $raw) as $item) {
            $item = trim($item);
            if ($item === '' || $item === '0') {
                continue;
            }
            if (ctype_digit($item) && strlen($item) <= 10 && (int) $item > 0) {
                $ids[(int) $item] = true;
            } else {
                $changed = true;
            }
        }
        return $ids ? implode(',', array_keys($ids)) : null;
    }
}
