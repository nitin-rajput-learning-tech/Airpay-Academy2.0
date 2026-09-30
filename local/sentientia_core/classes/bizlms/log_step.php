<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * What the two legacy_logs steps have in common (ADR-032, mapping doc section 8): both fill
 * local_sentientia_admin_log, both attribute a tenant to the actor, and both keep every source row.
 *
 * A step is pure: it returns outcomes and never writes. This base class only builds them.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class log_step extends step {

    /**
     * Both steps write the same table (a MAP step: nothing outside it stores a legacy id).
     *
     * @return string
     */
    public function targettable(): string {
        return legacy_logs_importer::TARGET;
    }

    /**
     * Repair text that is not valid UTF-8. The writer refuses a value that is not, which would stop a whole
     * batch; the original bytes stay in the legacy table, so the row is imported with the bad bytes replaced
     * and a warning says so.
     *
     * @param string|null $value
     * @return array{0: string, 1: bool} [text, repaired]
     */
    public static function clean_text(?string $value): array {
        $value = (string) $value;
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return [$value, false];
        }
        return [mb_scrub($value, 'UTF-8'), true];
    }

    /**
     * The source timestamps of a row. A missing modified time falls back to the created time, the only
     * fallback (BizLMS wrote both from one time() call, so the two are equal on every row it ever wrote).
     *
     * @param mixed $created
     * @param mixed $modified
     * @return array{0: int, 1: int, 2: bool} [timecreated, timemodified, derived]
     */
    public static function timestamps(mixed $created, mixed $modified): array {
        $created = (int) $created;
        $modified = (int) $modified;
        if ($modified <= 0) {
            return [$created, $created, true];
        }
        return [$created, $modified, false];
    }

    /**
     * Settle one row: attribute the tenant of the actor and build the outcome.
     *
     * The tenant is the actor's CURRENT user.open_path, normalised and checked against the tenant registry by
     * the context's resolver. A row whose tenant cannot be resolved is imported with no path (visible to
     * cross-tenant callers only) when the owner decided "pathless", which is the signed decision, and skipped
     * when the decision is "skip". A deleted actor keeps the row, and so does an actor with no user row.
     *
     * @param context $ctx
     * @param int $sourceid Legacy id.
     * @param int $actor The user whose tenant applies; 0 when the source names none.
     * @param \stdClass $row Every target column except actor_path.
     * @param string[] $warnings Warning codes already collected for the row.
     * @return outcome
     */
    protected function place(context $ctx, int $sourceid, int $actor, \stdClass $row, array $warnings): outcome {
        $candidates = ['actor' => $actor > 0 ? $ctx->lookups->user_path($actor) : null];
        [$path, , $method] = $ctx->tenant->resolve($candidates);

        if ($path === null && $ctx->decision(legacy_logs_importer::DECISION_TENANT) === 'skip') {
            return outcome::skip($sourceid, legacy_logs_importer::REASON_TENANT_UNRESOLVED, 'no_tenant');
        }

        $row->actor_path = $path;
        $out = outcome::insert($sourceid, legacy_logs_importer::TARGET, $row)->tenant_method($method);
        if ($actor <= 0) {
            $warnings[] = 'no_actor';
        } else if (!$ctx->lookups->user_exists($actor)) {
            $warnings[] = 'actor_not_found';
        }
        foreach ($warnings as $code) {
            $out->warn($code);
        }
        return $out;
    }
}
