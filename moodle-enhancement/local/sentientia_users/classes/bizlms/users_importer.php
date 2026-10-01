<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * The users importer (ADR-032, mapping doc section 10): the history BizLMS's local_users plugin left in the
 * database becomes ordinary Sentientia history.
 *
 *   local_userssyncdata      -> local_sentientia_users_sync_runs       one row per HRMS upload
 *   local_syncerrors         -> local_sentientia_users_sync_errors     one row per failed line, matched to a run
 *                               (+ synthetic runs for an error that matches no upload, in sync_runs)
 *   local_transcript_history -> local_sentientia_users_transcript      earlier training records
 *   local_uniquelogins       -> local_sentientia_users_logindays       one row per user per login day
 *   local_domains / _positions -> local_sentientia_users_domain / _position   lookups, ids kept
 *   local_userdata           declined: a derived mirror of user.open_path; preflight reports mismatches only
 *
 * Never written or called: the HRMS importer, user_create_user and user_update_user (so no user_created or
 * user_updated event and no welcome e-mail), the cron's hrms_sync_last_run settings, {user}.open_path (nothing is
 * ever written back from local_userdata), course_completions, the standard log, the xAPI store, sessions.
 * Every table written is one of this plugin's own new tables or its existing sync_runs and sync_errors.
 *
 * Ids: the lookups are PRESERVE (user.open_positionid and open_domainid hold them); every other step is MAP.
 *
 * Depends on org because transcript carries a tenant path, which the framework resolves against the
 * organisation tree.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class users_importer implements importer {

    /** Feature key. */
    public const FEATURE = 'users';

    /** Plugin version that carries the four new tables (db/upgrade.php step 2026100101). */
    public const REQUIRES_VERSION = 2026100101;

    /** An uploader with no unattached error gets no orphan run: the derived group is archived. */
    public const REASON_NO_UNATTACHED = 'no_unattached_errors';

    /** An uploader with no web-service error gets no service run: the derived group is archived. */
    public const REASON_NO_SERVICE = 'no_service_errors';

    /** A second row of the same user and day (BizLMS wrote one on every login). */
    public const REASON_DUPLICATE_LOGIN_DAY = 'duplicate_login_day';

    /** A login row with no user or no day cannot form the (user, day) key. */
    public const REASON_INVALID_LOGIN_ROW = 'invalid_login_row';

    /** The owner decided not to import this table. */
    public const REASON_DECLINED = 'declined_by_decision';

    /** Decision: rows whose tenant cannot be resolved import with no path (the only value supported). */
    public const DECISION_TENANT = 'tenant.unresolved.users';

    /** Decision: a run uploaded by a cross-tenant admin gets tenant 0 (zero) or the admin's own root. */
    public const DECISION_ADMIN_RUNS = 'users.admin_runs_tenant';

    /** Decision: how a raw transcript status is normalised. */
    public const DECISION_STATUS_MAP = 'users.transcript_status_map';

    /** Decision: import the login days or archive them. */
    public const DECISION_LOGINS = 'users.uniquelogins';

    /** Decision: transcript rows count toward completion totals (false: they never do). */
    public const DECISION_TOTALS = 'users.transcript_counts_toward_totals';

    /** Decision: an erasure request anonymises imported rows (the only treatment the provider implements). */
    public const DECISION_ERASURE = 'users.erasure_treatment';

    /** Decision: user.open_path wins over local_userdata; mismatches are only reported. */
    public const DECISION_RECONCILE = 'users.userdata_reconciliation';

    /** Decision: import the position and domain lookups. */
    public const DECISION_LOOKUPS = 'users.positions_domains_lookup_import';

    /** Legacy rows read per page by preflight. */
    private const PAGE = 5000;

    /** Distinct transcript statuses a preflight histogram will list. */
    private const STATUS_VALUES = 200;

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_users';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        return ['org'];
    }

    public function sources(): array {
        return [
            'local_userssyncdata' => new source_spec('local_userssyncdata'),
            // firstname, lastname, type and sync_file_name exist only on the production table (not in the
            // snapshot's install file); the steps read them when they are there.
            'local_syncerrors' => new source_spec('local_syncerrors', true, [],
                ['firstname', 'lastname', 'type', 'sync_file_name']),
            'local_transcript_history' => new source_spec('local_transcript_history', false),
            'local_uniquelogins' => new source_spec('local_uniquelogins', false),
            // No install file in the snapshot: preflight checks the columns that are there.
            'local_domains' => new source_spec('local_domains', false, [], ['name', 'code', 'costcenter']),
            'local_positions' => new source_spec('local_positions', false, [],
                ['name', 'code', 'domain', 'costcenter', 'sortorder']),
        ];
    }

    public function declined_tables(): array {
        return [
            'local_userdata' => 'a derived mirror of user.open_path (the signed decision users.userdata_reconciliation:'
                . ' user.open_path wins); preflight reports mismatches and nothing is written back',
        ];
    }

    public function target_tables(): array {
        return [
            sync_run_step::TARGET, sync_error_step::TARGET, transcript_step::TARGET, login_day_step::TARGET,
            domain_step::TARGET, position_step::TARGET,
        ];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        // sync_runs.costcenterid is an integer root, not a path: verify() checks it. The transcript path is the
        // matched learner's org path at import time.
        return [transcript_step::TARGET => 'open_path'];
    }

    public function reasons(): array {
        return [
            // Derived groups with nothing to make: kept only as their key, no owner needed.
            new reason(self::REASON_NO_UNATTACHED, false, false),
            new reason(self::REASON_NO_SERVICE, false, false),
            // Collapsed duplicates stay in the legacy table.
            new reason(self::REASON_DUPLICATE_LOGIN_DAY, false, false),
            // A login row that cannot be keyed is not imported: the owner accepts the count.
            new reason(self::REASON_INVALID_LOGIN_ROW, false, true),
            // Archived because the owner said so.
            new reason(self::REASON_DECLINED, false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision(self::DECISION_TENANT,
                'Rows whose tenant cannot be resolved import with no path, visible to cross-tenant callers only (pathless)',
                true, null, ['pathless']),
            new decision(self::DECISION_ADMIN_RUNS,
                'A run uploaded by a cross-tenant admin: tenant 0, cross-tenant only (zero), or the admin\'s own root'
                . ' (uploader_root)', true, null, ['zero', 'uploader_root']),
            new decision(self::DECISION_STATUS_MAP,
                'Normalisation list for the free-text transcript status (the raw text is always kept)', true),
            new decision(self::DECISION_LOGINS,
                'Import the BizLMS login days (import) or leave them in the legacy table (skip)', true, null,
                ['import', 'skip']),
            new decision(self::DECISION_TOTALS,
                'Transcript rows never count toward completion totals (false is the only supported value)', true,
                null, [false]),
            new decision(self::DECISION_ERASURE,
                'On erasure the imported transcript and sync-error rows are anonymised, not deleted (anonymise is the'
                . ' only supported value)', true, null, ['anonymise']),
            new decision(self::DECISION_RECONCILE,
                'local_userdata is a derived mirror: user.open_path wins and a mismatch is only reported', true, null,
                ['user_open_path_authoritative']),
            new decision(self::DECISION_LOOKUPS,
                'Import the position and domain lookups with their ids kept (true) or leave them in the legacy tables'
                . ' (false)', true, null, [true, false]),
        ];
    }

    public function atomic(): bool {
        return false;
    }

    public function steps(): array {
        return [
            new sync_run_step(),
            new orphan_day_step(),
            new service_day_step(),
            new sync_error_step(),
            new transcript_step(),
            new login_day_step(),
            new domain_step(),
            new position_step(),
        ];
    }

    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        $legacy = $ctx->legacy;

        if ($legacy->exists('local_syncerrors')) {
            // Say which inference the run matching will rest on.
            if (!$legacy->has_column('local_syncerrors', 'type')) {
                $pf->warn('severity_inferred_from_midnight');
            }
            if (!$legacy->has_column('local_syncerrors', 'sync_file_name')) {
                $pf->warn('no_sync_file_name_column');
            }
            // Employee codes the target column (100) is too short for: they are truncated, and counted here first.
            $pf->count('employee_code_over_100', $legacy->count('local_syncerrors',
                ['(' . $DB->sql_length('t.idnumber') . ' > 100)', []]));
        }

        if ($legacy->exists('local_transcript_history')) {
            $this->preflight_statuses($pf, $ctx);
        }

        foreach (['local_domains', 'local_positions'] as $table) {
            if ($legacy->exists($table) && !$legacy->has_column($table, 'name')) {
                // A lookup with no name column has nothing a profile could show.
                $pf->block('lookup_table_without_name:' . $table);
            }
        }

        if ($legacy->exists('local_userdata')) {
            $pf->count('userdata_path_mismatch', $this->userdata_mismatches($ctx));
        }
        return $pf;
    }

    /**
     * How many transcript rows get each status, and how many fall to "unknown", so the owner sees the effect of the
     * signed list before the run.
     *
     * @param preflight $pf
     * @param context $ctx
     * @return void
     */
    private function preflight_statuses(preflight $pf, context $ctx): void {
        global $DB;
        try {
            $map = (array) $ctx->decision(self::DECISION_STATUS_MAP);
        } catch (\Throwable $e) {
            // The decision is missing or not accepted: the runner already blocks the feature for that.
            return;
        }
        $rows = $DB->get_records_sql(fingerprint::value_histogram_sql('local_transcript_history', 'status'), null, 0,
            self::STATUS_VALUES + 1);
        if (count($rows) > self::STATUS_VALUES) {
            $pf->warn('too_many_transcript_status_values');
            return;
        }
        $histogram = [];
        $unknown = 0;
        foreach ($rows as $row) {
            $status = transcript_parser::status($row->v === null ? '' : (string) $row->v, $map);
            $histogram[$status] = ($histogram[$status] ?? 0) + (int) $row->n;
            if ($status === transcript_parser::UNKNOWN) {
                $unknown += (int) $row->n;
            }
        }
        $pf->histogram('local_sentientia_users_transcript.status', $histogram);
        $pf->count('transcript_status_unknown', $unknown);
    }

    /**
     * Rows of local_userdata whose org path differs from the user's open_path now. Read-only: the signed
     * decision says open_path wins and nothing is written back.
     *
     * @param context $ctx
     * @return int
     */
    private function userdata_mismatches(context $ctx): int {
        $after = 0;
        $mismatches = 0;
        do {
            $rows = $ctx->legacy->page('local_userdata', $after, self::PAGE, ['userid', 'costcenterpath']);
            foreach ($rows as $id => $row) {
                $after = (int) $id;
                $mirror = tenant_resolver::normalise((string) ($row->costcenterpath ?? ''));
                $actual = tenant_resolver::normalise($ctx->lookups->user_path((int) ($row->userid ?? 0)));
                if ($mirror !== $actual) {
                    $mismatches++;
                }
            }
        } while (count($rows) === self::PAGE);
        return $mismatches;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $runs = sync_run_step::TARGET;
        $errors = sync_error_step::TARGET;

        // Every imported or adopted map row points at a row that exists, per target table.
        foreach ($this->target_tables() as $table) {
            $gone = $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . legacymap::TABLE . '} m
                  WHERE m.feature = :f AND m.targettable = :t AND m.outcome IN (\'imported\', \'adopted\')
                    AND NOT EXISTS (SELECT 1 FROM {' . $table . '} x WHERE x.id = m.targetid)',
                ['f' => self::FEATURE, 't' => $table]);
            if ($gone > 0) {
                $failures[] = 'target_rows_missing:' . $table . ': rows=' . $gone;
            }
        }

        // The run tenant is 0 or a registered root (the generic check cannot read an integer column).
        $roots = $DB->get_fieldset_sql(
            'SELECT DISTINCT costcenterid FROM {' . $runs . '} WHERE source = :s', ['s' => 'bizlms']);
        foreach ($roots as $root) {
            if ((int) $root === 0) {
                continue;
            }
            try {
                \local_sentientia_platform\tenant::assert_valid((int) $root);
            } catch (\Throwable $e) {
                $failures[] = 'invalid_run_tenant:' . (int) $root;
            }
        }

        // The error rows of imported runs are exactly the imported source rows (a BizLMS run only ever holds them).
        $held = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . $errors . '} e JOIN {' . $runs . '} r ON r.id = e.runid WHERE r.source = :s',
            ['s' => 'bizlms']);
        $mapped = $DB->count_records(legacymap::TABLE,
            ['sourcetable' => 'local_syncerrors', 'subkey' => '', 'outcome' => 'imported']);
        if ($held !== $mapped) {
            $failures[] = 'sync_error_rows_differ: mapped=' . $mapped . ' held=' . $held;
        }

        // No error row without its run, and a severity the page knows.
        $loose = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . $errors . '} e
              WHERE NOT EXISTS (SELECT 1 FROM {' . $runs . '} r WHERE r.id = e.runid)');
        if ($loose > 0) {
            $failures[] = 'sync_errors_without_a_run: rows=' . $loose;
        }
        $odd = $DB->count_records_select($errors, 'severity NOT IN (:a, :b)', ['a' => 'error', 'b' => 'warning']);
        if ($odd > 0) {
            $failures[] = 'unknown_severity: rows=' . $odd;
        }

        // Transcript: the imported rows are the source rows, and no status outside the signed list.
        $imported = $DB->count_records(legacymap::TABLE,
            ['sourcetable' => 'local_transcript_history', 'subkey' => '', 'outcome' => 'imported']);
        $transcript = $DB->count_records(transcript_step::TARGET, ['source' => 'bizlms']);
        if ($imported !== $transcript) {
            $failures[] = 'transcript_rows_differ: mapped=' . $imported . ' held=' . $transcript;
        }
        try {
            $allowed = transcript_parser::statuses((array) $ctx->decision(self::DECISION_STATUS_MAP));
        } catch (\Throwable $e) {
            $allowed = null;
        }
        if ($allowed !== null && $imported > 0) {
            [$insql, $params] = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'blmst', false);
            $stray = $DB->count_records_select(transcript_step::TARGET, 'source = :s AND status ' . $insql,
                $params + ['s' => 'bizlms']);
            if ($stray > 0) {
                $failures[] = 'transcript_status_outside_the_signed_list: rows=' . $stray;
            }
        }

        // PRESERVE kept the legacy id on every lookup row.
        foreach ([domain_step::TARGET, position_step::TARGET] as $table) {
            $moved = $DB->count_records_select(legacymap::TABLE,
                'feature = :f AND targettable = :t AND outcome = \'imported\' AND sourceid <> targetid',
                ['f' => self::FEATURE, 't' => $table]);
            if ($moved > 0) {
                $failures[] = 'lookup_ids_not_kept:' . $table . ': rows=' . $moved;
            }
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        // Nothing to do: the framework resets the sequences of the two PRESERVE tables after the last commit, and
        // this importer has no file to copy and no cache to purge.
    }
}
