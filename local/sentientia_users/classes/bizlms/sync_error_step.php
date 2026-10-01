<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_syncerrors -> local_sentientia_users_sync_errors (ADR-032, mapping doc section 10): one row per failed
 * line of an HRMS upload.
 *
 * MAP: nothing outside the table stores a legacy error id. Every source row becomes one error row; none is
 * skipped, because a row that cannot find its run still has a synthetic one made for it.
 *
 * The run comes from sync_index (see its description for the matching rules). It is the target of a map row
 * written earlier in this feature: a BizLMS run (local_userssyncdata) or a synthetic one
 * (#local_syncerrors.orphan_day or .service_day, keyed by the uploader's lowest error id). A run that is not
 * there is a bug or a changed source, so the step stops; it never invents a run id.
 *
 * What BizLMS never stored: the CSV line (0, which the page shows as a dash), the username ("-"). The text
 * columns keep the dash BizLMS wrote for an empty e-mail or code. The first and last name exist only in the
 * production-only columns; without them they are "". An error row BizLMS wrote with the same text in both name
 * fields (cronfunctionality.php:999) keeps only the first.
 *
 * This table holds e-mail addresses, employee codes and names, so the privacy provider declares, exports and
 * anonymises it (decision users.erasure_treatment = anonymise).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_error_step extends step {

    /** The only table the step writes. */
    public const TARGET = 'local_sentientia_users_sync_errors';

    public function key(): string {
        return 'users.sync_errors';
    }

    public function sourcetable(): string {
        return 'local_syncerrors';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function preload(): array {
        return [
            ['local_userssyncdata', ''],
            ['#local_syncerrors.orphan_day', ''],
            ['#local_syncerrors.service_day', ''],
        ];
    }

    public function transform(array $rows, context $ctx): array {
        $index = sync_index::for_context($ctx);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $warnings = [];

            $runid = $this->run_for($row, $index, $ctx);
            $severity = $index->is_warning($row) ? 'warning' : 'error';

            [$message, $bad1] = clean::utf8((string) ($row->error ?? ''));
            [$mandatory, $bad2] = clean::utf8($row->mandatory_fields ?? null);
            if (($row->mandatory_fields ?? null) === null) {
                // NULL stays NULL: the column is nullable and the page tests for an empty value.
                $mandatory = null;
            }
            [$email, $bad3] = clean::utf8(trim((string) ($row->email ?? '')));
            [$code, $bad4] = clean::utf8(trim((string) ($row->idnumber ?? '')));
            [$first, $bad5] = clean::utf8(trim((string) ($row->firstname ?? '')));
            [$last, $bad6] = clean::utf8(trim((string) ($row->lastname ?? '')));
            if ($bad1 || $bad2 || $bad3 || $bad4 || $bad5 || $bad6) {
                $warnings[] = 'invalid_utf8';
            }
            if ($severity === 'error' && $last !== '' && $last === $first) {
                $last = '';
            }

            $out[] = $this->with_warnings(outcome::insert($id, self::TARGET, (object) [
                'runid' => $runid,
                'csv_line_number' => 0,
                'email' => $ctx->text->fit($email !== '' ? $email : '-', 254, 'email'),
                'employee_code' => $ctx->text->fit($code !== '' ? $code : '-', 100, 'employee_code'),
                'username' => '-',
                'firstname' => $ctx->text->fit($first, 100, 'firstname'),
                'lastname' => $ctx->text->fit($last, 100, 'lastname'),
                'error_message' => $message,
                'mandatory_fields' => $mandatory,
                'severity' => $severity,
                'modified_by' => clean::id($row->modified_by ?? null),
                'timecreated' => clean::time($row->date_created ?? null),
            ]), $warnings);
        }
        return $out;
    }

    /**
     * The target id of the run the error belongs to.
     *
     * @param \stdClass $row
     * @param sync_index $index
     * @param context $ctx
     * @return int
     */
    private function run_for(\stdClass $row, sync_index $index, context $ctx): int {
        [$kind, $ref] = $index->destination($row);
        if ($kind === 'run') {
            $runid = $ctx->map->resolve('local_userssyncdata', (int) $ref);
        } else {
            $ukey = sync_index::uploader_key($row->modified_by ?? null);
            $minid = $index->min_error_id($ukey);
            $runid = $minid === null ? null : $ctx->map->resolve('#local_syncerrors.' . $kind . '_day', $minid,
                $index->subkey_for($kind, $ukey, (string) $ref));
        }
        if ($runid === null) {
            throw new bizlms_exception('sync_error_run_not_mapped:' . (int) $row->id);
        }
        return $runid;
    }

    /**
     * @param outcome $outcome
     * @param string[] $warnings
     * @return outcome
     */
    private function with_warnings(outcome $outcome, array $warnings): outcome {
        foreach ($warnings as $code) {
            $outcome->warn($code);
        }
        return $outcome;
    }
}
