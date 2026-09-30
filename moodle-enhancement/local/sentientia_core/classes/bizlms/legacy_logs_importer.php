<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * The legacy_logs importer (ADR-032, mapping doc section 8): BizLMS's admin log and its bulk course upload
 * error log become one Sentientia history table.
 *
 *   local_logs         -> local_sentientia_admin_log   (course insert, update and delete, by whom, when)
 *   local_courseerrors -> local_sentientia_admin_log   (one row per failed line of a bulk course upload)
 *
 * Every source row is kept: there is no dedupe, no filter and no status to map, so the accounting identity
 * is source rows = imported rows, unless the owner decided that rows with no resolvable tenant are skipped
 * (decision tenant.unresolved.legacy_logs = skip; the signed value is pathless).
 *
 * Ids are MAP: nothing outside the table stores a legacy id, and the idempotence key is the ADR-032 map
 * (sourcetable, sourceid), not a column of the target. Times come from the source.
 *
 * Nothing here sends, enrols, completes or fires an event. Importing these rows changes no other table.
 *
 * Depends on the org feature although the mapping doc's table says "none": the table has a tenant column
 * (actor_path), and the registry refuses any importer with a tenant column that does not have org in its
 * dependency closure (tenant resolution reads the organisation table the org importer fills).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_logs_importer implements importer {

    /** Feature key. */
    public const FEATURE = 'legacy_logs';

    /** The only table the importer writes. */
    public const TARGET = 'local_sentientia_admin_log';

    /** Plugin version that carries the target table (db/upgrade.php step 2026093001). */
    public const REQUIRES_VERSION = 2026093001;

    /** Skip reason used only when the owner decides that a row with no tenant is not imported. */
    public const REASON_TENANT_UNRESOLVED = 'tenant_unresolved';

    /** Decision: rows whose tenant cannot be resolved. pathless (signed) or skip. */
    public const DECISION_TENANT = 'tenant.unresolved.legacy_logs';

    /** Decision: retention of the imported rows. The only supported value keeps every row. */
    public const DECISION_RETENTION = 'legacy_logs.retention';

    /** Decision: what an erasure request does to a description. The only supported value keeps the row. */
    public const DECISION_ERASURE = 'legacy_logs.description_erasure';

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_core';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        return ['org'];
    }

    public function sources(): array {
        return [
            admin_log_step::SOURCE => new source_spec(admin_log_step::SOURCE),
            upload_error_step::SOURCE => new source_spec(upload_error_step::SOURCE),
        ];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return [self::TARGET];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [self::TARGET => 'actor_path'];
    }

    public function reasons(): array {
        // A row is only skipped for this reason when the owner chose "skip" for tenant.unresolved.legacy_logs
        // (the signed value is pathless, so a signed run skips nothing). Retryable: after a re-approval that
        // changes the decision, --retry-skipped picks the rows up. Needs an owner: rows that were not imported.
        return [new reason(self::REASON_TENANT_UNRESOLVED, true, true)];
    }

    public function decisions(): array {
        return [
            new decision(self::DECISION_TENANT,
                'Rows whose tenant cannot be resolved: import with no tenant path (pathless) or skip them', true,
                null, ['pathless', 'skip']),
            new decision(self::DECISION_RETENTION,
                'Retention of the imported admin log: keep every row, purge nothing (keep_no_purge)', true,
                null, ['keep_no_purge']),
            new decision(self::DECISION_ERASURE,
                'Erasure of the first names in log descriptions: keep the row, scrub the name (keep_row_scrub_name)',
                true, null, ['keep_row_scrub_name']),
        ];
    }

    public function atomic(): bool {
        return false;
    }

    public function steps(): array {
        return [new admin_log_step(), new upload_error_step()];
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();
        // Informational counts: rows that will import with no actor, which is expected for cron-style writers
        // and is reported so the pathless total is explainable.
        if ($ctx->legacy->exists(admin_log_step::SOURCE)) {
            $pf->count('no_actor:' . admin_log_step::SOURCE,
                $ctx->legacy->count(admin_log_step::SOURCE, ['t.usercreated = :blmactor', ['blmactor' => 0]]));
        }
        if ($ctx->legacy->exists(upload_error_step::SOURCE)) {
            $pf->count('no_actor:' . upload_error_step::SOURCE,
                $ctx->legacy->count(upload_error_step::SOURCE, ['(t.userid IS NULL OR t.userid = :blmactor)', ['blmactor' => 0]]));
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $sources = [admin_log_step::SOURCE, upload_error_step::SOURCE];

        // What the map says was imported must be what the target holds, per source table.
        foreach ($sources as $source) {
            if (!$ctx->legacy->exists($source)) {
                continue;
            }
            $imported = $DB->count_records(legacymap::TABLE,
                ['sourcetable' => $source, 'subkey' => '', 'outcome' => 'imported']);
            $held = $DB->count_records(self::TARGET, ['source' => $source]);
            if ($imported !== $held) {
                $failures[] = 'target_rows_differ:' . $source . ': imported=' . $imported . ' target=' . $held;
            }
        }

        // A source value this importer never writes means another writer has been at the table.
        [$insql, $params] = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED, 'blmsrc', false);
        $stray = $DB->count_records_select(self::TARGET, 'source ' . $insql, $params);
        if ($stray > 0) {
            $failures[] = 'unexpected_source_values: rows=' . $stray;
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        // Nothing to do: MAP ids (no sequence to reset), no files, no caches.
    }
}
