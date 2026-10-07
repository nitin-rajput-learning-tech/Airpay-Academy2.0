<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Who sees what on the HRMS sync history pages (sync_runs.php, sync_run_detail.php).
 *
 * Two rules, both signed on 2026-10-07 under Nitin's delegation:
 *
 *  1. IDN-07, users.sync_history_visibility = runs_tenant_wide_lines_uploader_only. The run list (counts, uploader,
 *     time) stays tenant-wide, as BizLMS's sync statistics were (BizLMS local/users/lib.php manage_syncstatistics_count()).
 *     The rejected-line rows of a run (the e-mail address, employee code and name of a PROSPECTIVE employee, who may
 *     not be an account) are shown to a viewer only for a run they uploaded, plus to cross-tenant callers
 *     (ADR-031: a site admin, or a holder of local/sentientia_platform:crosstenant), for imported and native runs
 *     alike. This is BizLMS parity (manage_syncerrors_count() filtered a non-admin to modified_by = their own id),
 *     so nobody sees more prospective-employee data after the cutover than today. A run with no uploader (cron,
 *     usercreated 0) therefore shows its lines to cross-tenant callers only. A per-customer widening, if shared HRMS
 *     duty needs it, would be a default-OFF flag; un-showing personal data is not reversible, showing it is.
 *
 *  2. XC-IMPORTED-HISTORY-READERS, framework.imported_rows_on_admin_pages = history_rows_behind_reader_flag_entities_unflagged.
 *     The runs the BizLMS import put in the table (source 'bizlms') are imported HISTORY that nothing references, so
 *     they are shown only while sentientia.users.imported_sync_history is ON (default OFF; Nitin flips it after
 *     reviewing the visual evidence). With it OFF the list leaves them out and the detail page refuses them with a
 *     notice. Native runs are never behind the flag.
 *
 * Nothing here writes. The tenant bound itself (a run of another tenant is refused) stays in the pages.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_access {

    /**
     * The WHERE clause of the run list (table alias r) for the current user, and its parameters.
     *
     * Tenant scoping first: only a cross-tenant caller (ADR-031: a site admin or :crosstenant) sees every run;
     * anyone else sees their own tenant's runs, and a caller with no resolvable tenant sees none (this used to stay
     * '1=1' for them, i.e. every tenant's runs; "costcenterid = 0" would be wrong too, because that is exactly the
     * cross-tenant and cron runs). Then the imported-history flag: while it is OFF the runs the BizLMS import made
     * are left out.
     *
     * @return array{0: string, 1: array<string, mixed>} [where, params]
     */
    public static function runs_where(): array {
        $where = '1=1';
        $params = [];
        if (!\local_sentientia_platform\tenant::is_cross_tenant()) {
            $tenant = \local_sentientia_platform\tenant::root_for_current_user();
            if ($tenant > 0) {
                $where = 'r.costcenterid = :cc';
                $params['cc'] = $tenant;
            } else {
                $where = '1=0';
            }
        }
        if (!legacy_history::sync_history_enabled()) {
            $where .= ' AND r.source <> :importedsource';
            $params['importedsource'] = legacy_history::SOURCE_BIZLMS;
        }
        return [$where, $params];
    }

    /**
     * Is the run in the current user's tenant? A cross-tenant caller may open any run; anyone else only a run of
     * their own tenant, and nothing without one.
     *
     * @param \stdClass $run A row of local_sentientia_users_sync_runs (only ->costcenterid is read).
     * @return bool
     */
    public static function in_callers_tenant(\stdClass $run): bool {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return true;
        }
        // The same helper as runs_where(), so the list and the detail page cannot disagree about whose tenant this is.
        $tenant = \local_sentientia_platform\tenant::root_for_current_user();
        return $tenant !== 0 && (int) $run->costcenterid === $tenant;
    }

    /**
     * Is the run one the BizLMS import made?
     *
     * @param \stdClass $run A row of local_sentientia_users_sync_runs (only ->source is read).
     * @return bool
     */
    public static function is_imported(\stdClass $run): bool {
        return (string) ($run->source ?? '') === legacy_history::SOURCE_BIZLMS;
    }

    /**
     * Is this run hidden from the history pages because it is imported history and the reader flag is OFF?
     *
     * @param \stdClass $run
     * @return bool
     */
    public static function is_hidden_imported_run(\stdClass $run): bool {
        return self::is_imported($run) && !legacy_history::sync_history_enabled();
    }

    /**
     * May the current user see the rejected lines of this run?
     *
     * Cross-tenant callers always; anyone else only for a run they uploaded (usercreated is the uploader, 0 for a
     * cron or API run, which no tenant-scoped user can have uploaded).
     *
     * @param \stdClass $run A row of local_sentientia_users_sync_runs (only ->usercreated is read).
     * @param int|null $userid The viewer; defaults to the current user.
     * @return bool
     */
    public static function can_see_lines(\stdClass $run, ?int $userid = null): bool {
        global $USER;
        $userid ??= (int) ($USER->id ?? 0);
        if (\local_sentientia_platform\tenant::is_cross_tenant($userid)) {
            return true;
        }
        $uploader = (int) ($run->usercreated ?? 0);
        return $userid > 0 && $uploader > 0 && $uploader === $userid;
    }
}
