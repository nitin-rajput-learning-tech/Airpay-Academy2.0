<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

defined('MOODLE_INTERNAL') || die();

/**
 * The request feature of the BizLMS import (ADR-032; mapping doc, section 19).
 *
 * Sources: local_request_records (the requests), local_learningplan_approval (the learning-plan plugin's second
 * record of a request, owned here so there is one owner per legacy table) and local_request_comments (folded
 * into the decision note, expected empty). Target: local_sentientia_request, MAP ids, one new column
 * (legacy_source) that marks the rows.
 *
 * What the import does NOT do, on purpose: it never calls request_manager (submit, decide, cancel and the
 * cron jobs send messages, enrol and fire events), sends nothing, enrols nobody, sets no deadline (timedue and
 * timeescalated stay NULL, so nothing escalates), and never flips a flag. A legacy pending course or path
 * request is routed to the approver a new submission would get and stays pending; a person still decides, and
 * only once the sentientia.request.imported_history flag makes the row visible.
 *
 * Tenant: a request stores no tenant in BizLMS. costcenterid is the requester's CURRENT tenant root, 0 when
 * there is none (visible to cross-tenant callers only). Because it is a root and not a path, the importer
 * declares no tenant_columns(), and reaches the organisation data only through the features it depends on.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements \local_sentientia_platform\bizlms\importer {

    /** Version of local_sentientia_request that carries legacy_source. */
    private const REQUIRES_VERSION = 2026093001;

    public function feature(): string {
        return 'request';
    }

    public function component(): string {
        return 'local_sentientia_request';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * A request points at a classroom, a program or a learning plan, so their ids must be mapped first
     * (mapping doc, section 2). The organisation data tenant resolution uses is reached through them.
     *
     * @return string[]
     */
    public function depends(): array {
        return ['classroom', 'program', 'learningplan'];
    }

    public function sources(): array {
        return [
            legacy_request::SOURCE_RECORDS => new source_spec(legacy_request::SOURCE_RECORDS, true, [
                'status' => [
                    'PENDING' => 'pending', 'APPROVED' => 'approved', 'REJECTED' => 'rejected',
                ],
                'compname' => [
                    'elearning' => 'course', 'learningplan' => 'path', 'classroom' => 'classroom',
                    'program' => 'program', 'certification' => 'certification',
                ],
            ]),
            legacy_request::SOURCE_APPROVALS => new source_spec(legacy_request::SOURCE_APPROVALS, false, [
                'approvestatus' => [0 => 'pending', 1 => 'approved', 2 => 'rejected'],
            ]),
            legacy_request::SOURCE_COMMENTS => new source_spec(legacy_request::SOURCE_COMMENTS, false),
        ];
    }

    public function declined_tables(): array {
        $declined = [
            'local_request_config' => 'form settings; its only reader (request_form.php) is never instantiated',
        ];
        foreach (self::unmapped_tables() as $table) {
            $declined[$table] = 'no Sentientia counterpart; a preflight blocker while it holds rows';
        }
        return $declined;
    }

    /**
     * Legacy tables with no map. BizLMS code wrote the block_request_* tables (admin/comment.php,
     * deny_course.php, bulk_deny.php) and read the local_crequest_* tables (dead code); neither is in the
     * snapshot's install files, so the tables may or may not exist. Preflight stops for the owner if one
     * holds rows, so nothing is lost silently.
     *
     * @return string[]
     */
    private static function unmapped_tables(): array {
        global $DB;
        $tables = [
            'block_request_records', 'block_request_comments', 'block_request_config',
            'local_request_formfields', 'local_request_form_data',
            'local_crequest_config', 'local_crequest_records', 'local_crequest_comments',
        ];
        foreach (array_keys($DB->get_tables()) as $table) {
            if (strncmp($table, 'local_crequest_', 15) === 0) {
                $tables[] = $table;
            }
        }
        $tables = array_values(array_unique($tables));
        sort($tables);
        return $tables;
    }

    public function target_tables(): array {
        return [legacy_request::TARGET];
    }

    public function core_writes(): array {
        return [];
    }

    /**
     * None: the target stores a tenant ROOT (costcenterid), not a path, so the generic path check does not
     * apply. verify() checks the root of every imported row instead.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [];
    }

    public function reasons(): array {
        return [
            // The requester has no user row at all.
            new reason('orphan_user', true, true),
            // The item is not in Sentientia (a plan that does not exist, or one the import did not import).
            new reason('orphan_item', true, true),
            // A comment whose request is not there.
            new reason('orphan_request', true, true),
            // The owner chose to skip rows whose tenant cannot be resolved.
            new reason('no_tenant', false, true),
            // The owner chose to leave out what BizLMS hid (deleted item, deleted or suspended requester).
            new reason('hidden_in_bizlms', false, false),
            // A learning-plan approval the request row already covers.
            new reason('dup_of_request', false, false),
            // A comment, now part of the request's decision note.
            new reason('folded_into_note', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision('request.pending',
                'Legacy pending requests: actionable (course and path, routed), readonly (in nobody\'s inbox) or expired',
                true, null, ['actionable', 'readonly', 'expired']),
            new decision('request.pending_classroom_program',
                'Pending classroom and program requests: history only (decide() cannot enrol into them)',
                true, null, ['history_only']),
            new decision('request.certification',
                'Certification requests: kept unmapped with the legacy id (no owner entity exists yet)',
                true, null, ['unmapped']),
            new decision('request.decided_route',
                'Route label on decided legacy rows: admin, or a new legacy value',
                true, null, ['admin', 'legacy']),
            new decision('request.hidden_rows',
                'Rows BizLMS hid (deleted item, deleted or suspended requester): show them or filter them out',
                true, null, ['show', 'filter']),
            new decision('request.tenant_basis',
                'The tenant of a request: the requester\'s current root (the source stores none)',
                true, null, ['requester_current_root']),
            new decision('request.pending_approver',
                'Approver of a legacy pending request: Sentientia routing',
                true, null, ['sentientia_routing']),
            new decision('request.comments',
                'Request comments: folded into the decision note',
                true, null, ['fold_into_decision_note']),
            new decision('tenant.unresolved.request',
                'Rows whose tenant cannot be resolved: import with no tenant (cross-tenant callers only) or skip',
                true, null, ['pathless', 'skip']),
        ];
    }

    /**
     * Not atomic (mapping doc, section 2): the feature runs in batches.
     *
     * @return bool
     */
    public function atomic(): bool {
        return false;
    }

    /**
     * The records step runs first: the approvals step folds into its rows, and the comments step settles
     * against them.
     *
     * @return array
     */
    public function steps(): array {
        return [new records_step(), new approvals_step(), new comments_step()];
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();

        // Columns nothing wrote and nothing reads are not copied. If any holds a value, the owner decides what
        // it means before the import proceeds.
        if ($ctx->legacy->exists(legacy_request::SOURCE_RECORDS)) {
            foreach (legacy_request::UNUSED_COLUMNS as $column) {
                if (!$ctx->legacy->has_column(legacy_request::SOURCE_RECORDS, $column)) {
                    continue;
                }
                $n = $ctx->legacy->count(legacy_request::SOURCE_RECORDS, ['t.' . $column . ' IS NOT NULL', []]);
                if ($n > 0) {
                    $pf->block('needs_owner:unmapped_column:' . legacy_request::SOURCE_RECORDS . '.' . $column . '=' . $n);
                }
            }
        }

        // Tables with no map. Rows in one of them would drop out of sight at cutover.
        foreach (self::unmapped_tables() as $table) {
            if (!$ctx->legacy->exists($table)) {
                continue;
            }
            $n = $ctx->legacy->count($table);
            if ($n > 0) {
                $pf->block('needs_owner:unmapped_table_has_rows:' . $table . '=' . $n);
            }
        }

        // Figures for the report.
        if ($ctx->legacy->exists(legacy_request::SOURCE_COMMENTS)) {
            $pf->count('comments_to_fold', $ctx->legacy->count(legacy_request::SOURCE_COMMENTS));
        }
        if ($ctx->legacy->exists(legacy_request::SOURCE_APPROVALS)) {
            $pf->count('learningplan_approvals', $ctx->legacy->count(legacy_request::SOURCE_APPROVALS));
        }
        return $pf;
    }

    /**
     * Checks on what the import left, read-only. The runner has already verified the accounting identity.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $table = legacy_request::TARGET;
        $mark = ['ls' => legacy_request::MARK];

        // An imported row carries no clock: nothing may escalate it.
        $n = $DB->count_records_select($table,
            'legacy_source = :ls AND (timedue IS NOT NULL OR timeescalated IS NOT NULL)', $mark);
        if ($n) {
            $failures[] = 'imported_rows_with_a_deadline:' . $n;
        }

        // Only the states a request can be in.
        [$insql, $params] = $DB->get_in_or_equal(['pending', 'approved', 'rejected', 'expired'],
            SQL_PARAMS_NAMED, 'vst', false);
        $n = $DB->count_records_select($table, 'legacy_source = :ls AND status ' . $insql, $mark + $params);
        if ($n) {
            $failures[] = 'imported_rows_with_an_unknown_status:' . $n;
        }
        [$insql, $params] = $DB->get_in_or_equal(['course', 'path', 'classroom', 'program', 'certification'],
            SQL_PARAMS_NAMED, 'vit', false);
        $n = $DB->count_records_select($table, 'legacy_source = :ls AND item_type ' . $insql, $mark + $params);
        if ($n) {
            $failures[] = 'imported_rows_with_an_unknown_item_type:' . $n;
        }

        // A request nobody can decide has no approver: it would sit in an inbox with no buttons.
        [$insql, $params] = $DB->get_in_or_equal(legacy_request::DECIDABLE, SQL_PARAMS_NAMED, 'vdc', false);
        $n = $DB->count_records_select($table,
            "legacy_source = :ls AND status = 'pending' AND approver_userid IS NOT NULL AND item_type {$insql}",
            $mark + $params);
        if ($n) {
            $failures[] = 'pending_rows_of_an_undecidable_type_with_an_approver:' . $n;
        }

        // The tenant root of every imported row is 0 or a registered tenant.
        foreach ($DB->get_fieldset_select($table, 'DISTINCT costcenterid', 'legacy_source = :ls', $mark) as $root) {
            if ((int) $root === 0) {
                continue;
            }
            try {
                \local_sentientia_platform\tenant::assert_valid((int) $root);
            } catch (\Throwable $e) {
                $failures[] = 'imported_rows_with_an_unregistered_tenant:' . (int) $root;
            }
        }

        // Every row the import made carries the marker the cron jobs and the lists rely on.
        $n = (int) $DB->get_field_sql(
            "SELECT COUNT(1)
               FROM {local_sentientia_legacymap} m
               JOIN {" . $table . "} r ON r.id = m.targetid
              WHERE m.feature = :f AND m.targettable = :t AND m.outcome = 'imported'
                AND (r.legacy_source IS NULL OR r.legacy_source <> :ls)",
            ['f' => $this->feature(), 't' => $table, 'ls' => legacy_request::MARK]);
        if ($n) {
            $failures[] = 'imported_rows_without_the_marker:' . $n;
        }
        return $failures;
    }

    /**
     * Nothing to do after the load: no PRESERVE target (no sequence to reset), no file, no cache. The marker
     * the runner writes next is the only record.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
    }
}
