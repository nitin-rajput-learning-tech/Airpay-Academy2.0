<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_talent;

defined('MOODLE_INTERNAL') || die();

use context_system;
use local_sentientia_platform\feature_flags;
use local_sentientia_platform\tenant;

/**
 * Talent mobility operations — career paths, succession planning, and the
 * internal opportunity board.
 *
 * Security contract (HR-sensitive data):
 *   - Every public method that reads or writes succession data requires
 *     the relevant capability AND a tenant check via
 *     \local_sentientia_platform\tenant.
 *   - All list/read queries are tenant-scoped through tenant::sql_filter()
 *     so a Public-tenant manager can never see Airpay successors.
 *   - The master feature flag (sentientia.talent.enabled) is checked by
 *     callers (pages/WS) before reaching here; {@see require_enabled()}
 *     gives them one helper to do so.
 *   - Every mutation writes an audit row via {@see audit::record()}.
 *
 * @package local_sentientia_talent
 */
class talent_manager {

    public const PATH_TABLE  = 'local_sentientia_talent_path';
    public const SUCC_TABLE  = 'local_sentientia_talent_succ';
    public const OPP_TABLE   = 'local_sentientia_talent_opp';
    public const INT_TABLE   = 'local_sentientia_talent_int';

    /** Valid readiness codes for succession nominations. */
    public const READINESS = ['ready_now', 'ready_1y', 'ready_2y', 'developing'];

    /** Valid opportunity status codes. */
    public const OPP_STATUS = ['open', 'closed', 'filled'];

    // ─────────────────────────────────────────────────────────────────
    // Feature-flag + tenant helpers
    // ─────────────────────────────────────────────────────────────────

    /** Master flag key. */
    public const FLAG_MASTER        = 'sentientia.talent.enabled';
    /** Opportunity-board sub-flag key. */
    public const FLAG_OPPORTUNITIES = 'sentientia.talent.opportunities';

    /** Is the talent suite enabled for the current user's tenant? */
    public static function is_enabled(): bool {
        return feature_flags::is_enabled(self::FLAG_MASTER);
    }

    /** Is the learner-facing opportunity board enabled? (master AND sub). */
    public static function opportunities_enabled(): bool {
        return self::is_enabled() && feature_flags::is_enabled(self::FLAG_OPPORTUNITIES);
    }

    /**
     * Throw if the talent suite is OFF for this tenant. Pages/WS call this
     * first so a flag-OFF tenant gets a clean "feature disabled" rather
     * than a half-rendered surface.
     */
    public static function require_enabled(): void {
        if (!self::is_enabled()) {
            throw new \moodle_exception('error_featuredisabled', 'local_sentientia_talent');
        }
    }

    /** Current user's tenant root, validated. */
    public static function current_tenant(): int {
        return tenant::root_for_current_user();
    }

    // ═════════════════════════════════════════════════════════════════
    // CAREER PATHS
    // ═════════════════════════════════════════════════════════════════

    /**
     * List career paths for the current tenant.
     *
     * @param bool $activeonly Only published paths
     * @return array<int,\stdClass>
     */
    public static function list_paths(bool $activeonly = true): array {
        global $DB;
        require_capability('local/sentientia_talent:viewcareerpath', context_system::instance());
        [$tnsql, $tnparams] = tenant::sql_filter('p');
        $extra = $activeonly ? ' AND p.active = 1' : '';
        return $DB->get_records_sql(
            "SELECT p.* FROM {" . self::PATH_TABLE . "} p
              WHERE $tnsql$extra
           ORDER BY p.sort_order ASC, p.name ASC",
            $tnparams);
    }

    /**
     * Career paths whose source role matches a designation — "where can
     * someone in this role progress to?". Learner-facing.
     *
     * @param string $designation
     * @return array<int,\stdClass>
     */
    public static function paths_from(string $designation): array {
        global $DB;
        require_capability('local/sentientia_talent:viewcareerpath', context_system::instance());
        if ($designation === '') {
            return [];
        }
        [$tnsql, $tnparams] = tenant::sql_filter('p');
        $tnparams['desig'] = $designation;
        return $DB->get_records_sql(
            "SELECT p.* FROM {" . self::PATH_TABLE . "} p
              WHERE $tnsql AND p.active = 1 AND p.from_designation = :desig
           ORDER BY p.sort_order ASC, p.name ASC",
            $tnparams);
    }

    /**
     * Create or update a career path. Requires managecareerpaths.
     *
     * @param object $data id (0 = create), name, from_designation,
     *                     to_designation, description, sort_order, active
     * @return int path id
     */
    public static function save_path(object $data): int {
        global $DB, $USER;
        require_capability('local/sentientia_talent:managecareerpaths', context_system::instance());

        $name = trim((string) ($data->name ?? ''));
        $from = trim((string) ($data->from_designation ?? ''));
        $to   = trim((string) ($data->to_designation ?? ''));
        if ($name === '' || $from === '' || $to === '') {
            throw new \moodle_exception('error_missingfields', 'local_sentientia_talent');
        }

        $tenantid = self::current_tenant();
        $now = time();

        if (!empty($data->id)) {
            $existing = $DB->get_record(self::PATH_TABLE, ['id' => $data->id], '*', MUST_EXIST);
            tenant::require_access((int) $existing->costcenterid);
            $existing->name             = $name;
            $existing->description      = (string) ($data->description ?? '');
            $existing->from_designation = $from;
            $existing->to_designation   = $to;
            $existing->sort_order       = (int) ($data->sort_order ?? $existing->sort_order);
            $existing->active           = isset($data->active) ? (int) (bool) $data->active : (int) $existing->active;
            $existing->usermodified     = (int) $USER->id;
            $existing->timemodified     = $now;
            $DB->update_record(self::PATH_TABLE, $existing);
            audit::record((int) $existing->costcenterid, 'path_saved', self::PATH_TABLE,
                (int) $existing->id, null, ['from' => $from, 'to' => $to]);
            return (int) $existing->id;
        }

        $id = (int) $DB->insert_record(self::PATH_TABLE, (object) [
            'costcenterid'     => $tenantid,
            'name'             => $name,
            'description'      => (string) ($data->description ?? ''),
            'from_designation' => $from,
            'to_designation'   => $to,
            'sort_order'       => (int) ($data->sort_order ?? 0),
            'active'           => isset($data->active) ? (int) (bool) $data->active : 1,
            'usermodified'     => (int) $USER->id,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
        audit::record($tenantid, 'path_saved', self::PATH_TABLE, $id, null,
            ['from' => $from, 'to' => $to]);
        return $id;
    }

    /** Delete a career path. Requires managecareerpaths + tenant match. */
    public static function delete_path(int $id): bool {
        global $DB;
        require_capability('local/sentientia_talent:managecareerpaths', context_system::instance());
        $existing = $DB->get_record(self::PATH_TABLE, ['id' => $id], '*', MUST_EXIST);
        tenant::require_access((int) $existing->costcenterid);
        $DB->delete_records(self::PATH_TABLE, ['id' => $id]);
        audit::record((int) $existing->costcenterid, 'path_deleted', self::PATH_TABLE, $id);
        return true;
    }

    // ═════════════════════════════════════════════════════════════════
    // SUCCESSION PLANNING (highest sensitivity)
    // ═════════════════════════════════════════════════════════════════

    /**
     * List succession nominations for a designation in the current tenant.
     * Requires viewsuccession — NEVER exposed to learners.
     *
     * @param string $designation Optional filter; '' = all roles
     * @return array<int,array> enriched candidate rows with match %
     */
    public static function list_succession(string $designation = ''): array {
        global $DB;
        require_capability('local/sentientia_talent:viewsuccession', context_system::instance());

        [$tnsql, $tnparams] = tenant::sql_filter('s');
        $extra = '';
        if ($designation !== '') {
            $extra = ' AND s.designation = :desig';
            $tnparams['desig'] = $designation;
        }
        $rows = $DB->get_records_sql(
            "SELECT s.*, c.firstname AS cand_first, c.lastname AS cand_last,
                    i.firstname AS inc_first, i.lastname AS inc_last
               FROM {" . self::SUCC_TABLE . "} s
          LEFT JOIN {user} c ON c.id = s.candidateid
          LEFT JOIN {user} i ON i.id = s.incumbentid
              WHERE $tnsql$extra
           ORDER BY s.designation ASC, s.readiness ASC, s.timemodified DESC",
            $tnparams);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'            => (int) $r->id,
                'designation'   => format_string($r->designation),
                'candidateid'   => (int) $r->candidateid,
                'candidatename' => format_string(trim(($r->cand_first ?? '') . ' ' . ($r->cand_last ?? ''))),
                'incumbentid'   => $r->incumbentid !== null ? (int) $r->incumbentid : null,
                'incumbentname' => format_string(trim(($r->inc_first ?? '') . ' ' . ($r->inc_last ?? ''))),
                'readiness'     => $r->readiness,
                'readiness_label' => get_string('readiness_' . $r->readiness, 'local_sentientia_talent'),
                'notes'         => format_text($r->notes ?? '', FORMAT_PLAIN),
                'matchpct'      => skills_bridge::match_percentage((int) $r->candidateid, $r->designation),
                'timemodified'  => (int) $r->timemodified,
            ];
        }
        return $out;
    }

    /**
     * Create or update a succession nomination. Requires managesuccession.
     *
     * @param object $data id, designation, candidateid, incumbentid,
     *                     readiness, notes
     * @return int succession row id
     */
    public static function save_succession(object $data): int {
        global $DB, $USER;
        require_capability('local/sentientia_talent:managesuccession', context_system::instance());

        $designation = trim((string) ($data->designation ?? ''));
        $candidateid = (int) ($data->candidateid ?? 0);
        if ($designation === '' || $candidateid <= 0) {
            throw new \moodle_exception('error_missingfields', 'local_sentientia_talent');
        }
        $readiness = (string) ($data->readiness ?? 'developing');
        if (!in_array($readiness, self::READINESS, true)) {
            throw new \moodle_exception('error_invalidreadiness', 'local_sentientia_talent');
        }

        // Candidate must exist and belong to the planner's tenant — you
        // cannot nominate a user from another tenant.
        $candidate = $DB->get_record('user', ['id' => $candidateid, 'deleted' => 0],
            'id, open_path', MUST_EXIST);
        $tenantid = self::current_tenant();
        if (!is_siteadmin() && tenant::root_for_user($candidate) !== $tenantid) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        $incumbentid = !empty($data->incumbentid) ? (int) $data->incumbentid : null;
        if ($incumbentid) {
            $inc = $DB->get_record('user', ['id' => $incumbentid, 'deleted' => 0],
                'id, open_path', MUST_EXIST);
            if (!is_siteadmin() && tenant::root_for_user($inc) !== $tenantid) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
        }

        $now = time();

        if (!empty($data->id)) {
            $existing = $DB->get_record(self::SUCC_TABLE, ['id' => $data->id], '*', MUST_EXIST);
            tenant::require_access((int) $existing->costcenterid);
            $existing->designation  = $designation;
            $existing->candidateid  = $candidateid;
            $existing->incumbentid  = $incumbentid;
            $existing->readiness    = $readiness;
            $existing->notes        = (string) ($data->notes ?? '');
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = $now;
            $DB->update_record(self::SUCC_TABLE, $existing);
            audit::record((int) $existing->costcenterid, 'succession_saved', self::SUCC_TABLE,
                (int) $existing->id, $candidateid,
                ['designation' => $designation, 'readiness' => $readiness]);
            return (int) $existing->id;
        }

        // Enforce the unique (tenant, designation, candidate) nomination.
        if ($DB->record_exists(self::SUCC_TABLE, [
                'costcenterid' => $tenantid,
                'designation'  => $designation,
                'candidateid'  => $candidateid])) {
            throw new \moodle_exception('error_duplicatenomination', 'local_sentientia_talent');
        }

        $id = (int) $DB->insert_record(self::SUCC_TABLE, (object) [
            'costcenterid' => $tenantid,
            'designation'  => $designation,
            'incumbentid'  => $incumbentid,
            'candidateid'  => $candidateid,
            'readiness'    => $readiness,
            'notes'        => (string) ($data->notes ?? ''),
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        audit::record($tenantid, 'succession_saved', self::SUCC_TABLE, $id, $candidateid,
            ['designation' => $designation, 'readiness' => $readiness]);
        return $id;
    }

    /** Delete a succession nomination. Requires managesuccession + tenant match. */
    public static function delete_succession(int $id): bool {
        global $DB;
        require_capability('local/sentientia_talent:managesuccession', context_system::instance());
        $existing = $DB->get_record(self::SUCC_TABLE, ['id' => $id], '*', MUST_EXIST);
        tenant::require_access((int) $existing->costcenterid);
        $DB->delete_records(self::SUCC_TABLE, ['id' => $id]);
        audit::record((int) $existing->costcenterid, 'succession_deleted', self::SUCC_TABLE,
            $id, (int) $existing->candidateid);
        return true;
    }

    // ═════════════════════════════════════════════════════════════════
    // INTERNAL OPPORTUNITIES
    // ═════════════════════════════════════════════════════════════════

    /**
     * List opportunities for the current tenant.
     *
     * @param bool $openonly Only opportunities with status='open'
     * @return array<int,\stdClass>
     */
    public static function list_opportunities(bool $openonly = true): array {
        global $DB;
        require_capability('local/sentientia_talent:viewopportunities', context_system::instance());
        [$tnsql, $tnparams] = tenant::sql_filter('o');
        $extra = $openonly ? " AND o.status = 'open'" : '';
        return $DB->get_records_sql(
            "SELECT o.* FROM {" . self::OPP_TABLE . "} o
              WHERE $tnsql$extra
           ORDER BY o.timecreated DESC",
            $tnparams);
    }

    /**
     * The learner-facing opportunity feed: open opportunities in the
     * viewer's tenant, each annotated with the viewer's skill-match %.
     *
     * @param int $userid The learner (defaults to current $USER).
     * @return array<int,array>
     */
    public static function opportunity_feed(?int $userid = null): array {
        global $DB, $USER;
        require_capability('local/sentientia_talent:viewopportunities', context_system::instance());
        $userid = $userid ?? (int) $USER->id;

        [$tnsql, $tnparams] = tenant::sql_filter('o');
        $rows = $DB->get_records_sql(
            "SELECT o.* FROM {" . self::OPP_TABLE . "} o
              WHERE $tnsql AND o.status = 'open'
           ORDER BY o.timecreated DESC",
            $tnparams);

        // Already-registered interest, to mark the feed.
        $registered = $DB->get_records_menu(self::INT_TABLE, ['userid' => $userid],
            '', 'opportunityid, id');

        $out = [];
        foreach ($rows as $o) {
            $desig = (string) ($o->designation ?? '');
            $out[] = [
                'id'           => (int) $o->id,
                'title'        => format_string($o->title),
                'description'  => format_text($o->description ?? '', FORMAT_PLAIN),
                'designation'  => format_string($desig),
                'department'   => format_string((string) ($o->department ?? '')),
                'closes'       => $o->closes !== null ? (int) $o->closes : null,
                'matchpct'     => $desig !== '' ? skills_bridge::match_percentage($userid, $desig) : 0,
                'has_skilldata' => $desig !== '' && !empty(skills_bridge::required_skills_for_designation($desig)),
                'registered'   => isset($registered[$o->id]),
            ];
        }
        return $out;
    }

    /**
     * Create or update an opportunity. Requires manageopportunities.
     *
     * @param object $data id, title, description, designation, department,
     *                     status, closes
     * @return int opportunity id
     */
    public static function save_opportunity(object $data): int {
        global $DB, $USER;
        require_capability('local/sentientia_talent:manageopportunities', context_system::instance());

        $title = trim((string) ($data->title ?? ''));
        if ($title === '') {
            throw new \moodle_exception('error_missingfields', 'local_sentientia_talent');
        }
        $status = (string) ($data->status ?? 'open');
        if (!in_array($status, self::OPP_STATUS, true)) {
            throw new \moodle_exception('error_invalidstatus', 'local_sentientia_talent');
        }

        $tenantid = self::current_tenant();
        $now = time();

        if (!empty($data->id)) {
            $existing = $DB->get_record(self::OPP_TABLE, ['id' => $data->id], '*', MUST_EXIST);
            tenant::require_access((int) $existing->costcenterid);
            $existing->title        = $title;
            $existing->description  = (string) ($data->description ?? '');
            $existing->designation  = trim((string) ($data->designation ?? '')) ?: null;
            $existing->department   = trim((string) ($data->department ?? '')) ?: null;
            $existing->status       = $status;
            $existing->closes       = !empty($data->closes) ? (int) $data->closes : null;
            $existing->usermodified = (int) $USER->id;
            $existing->timemodified = $now;
            $DB->update_record(self::OPP_TABLE, $existing);
            audit::record((int) $existing->costcenterid, 'opp_saved', self::OPP_TABLE,
                (int) $existing->id, null, ['status' => $status]);
            return (int) $existing->id;
        }

        $id = (int) $DB->insert_record(self::OPP_TABLE, (object) [
            'costcenterid' => $tenantid,
            'title'        => $title,
            'description'  => (string) ($data->description ?? ''),
            'designation'  => trim((string) ($data->designation ?? '')) ?: null,
            'department'   => trim((string) ($data->department ?? '')) ?: null,
            'postedby'     => (int) $USER->id,
            'status'       => $status,
            'closes'       => !empty($data->closes) ? (int) $data->closes : null,
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        audit::record($tenantid, 'opp_saved', self::OPP_TABLE, $id, null, ['status' => $status]);
        return $id;
    }

    /** Delete an opportunity (and its interest rows). Requires manageopportunities. */
    public static function delete_opportunity(int $id): bool {
        global $DB;
        require_capability('local/sentientia_talent:manageopportunities', context_system::instance());
        $existing = $DB->get_record(self::OPP_TABLE, ['id' => $id], '*', MUST_EXIST);
        tenant::require_access((int) $existing->costcenterid);
        $tx = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::INT_TABLE, ['opportunityid' => $id]);
            $DB->delete_records(self::OPP_TABLE, ['id' => $id]);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        audit::record((int) $existing->costcenterid, 'opp_deleted', self::OPP_TABLE, $id);
        return true;
    }

    /**
     * List who expressed interest in an opportunity. Requires
     * manageopportunities (HR sees applicants).
     *
     * @param int $opportunityid
     * @return array<int,array>
     */
    public static function list_interest(int $opportunityid): array {
        global $DB;
        require_capability('local/sentientia_talent:manageopportunities', context_system::instance());
        $opp = $DB->get_record(self::OPP_TABLE, ['id' => $opportunityid], '*', MUST_EXIST);
        tenant::require_access((int) $opp->costcenterid);

        $rows = $DB->get_records_sql(
            "SELECT i.*, u.firstname, u.lastname, u.email
               FROM {" . self::INT_TABLE . "} i
               JOIN {user} u ON u.id = i.userid
              WHERE i.opportunityid = :oid
           ORDER BY i.matchpct DESC, i.timecreated ASC",
            ['oid' => $opportunityid]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->id,
                'userid'       => (int) $r->userid,
                'fullname'     => format_string(trim($r->firstname . ' ' . $r->lastname)),
                'message'      => format_text($r->message ?? '', FORMAT_PLAIN),
                'matchpct'     => (int) $r->matchpct,
                'timecreated'  => (int) $r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * Register the current user's interest in an opportunity.
     * Requires registerinterest. The opportunity must be in the user's
     * tenant and open.
     *
     * @param int    $opportunityid
     * @param string $message
     * @return int interest row id
     */
    public static function register_interest(int $opportunityid, string $message = ''): int {
        global $DB, $USER;
        require_capability('local/sentientia_talent:registerinterest', context_system::instance());

        $opp = $DB->get_record(self::OPP_TABLE, ['id' => $opportunityid], '*', MUST_EXIST);
        tenant::require_access((int) $opp->costcenterid);
        if ($opp->status !== 'open') {
            throw new \moodle_exception('error_opportunityclosed', 'local_sentientia_talent');
        }

        $userid = (int) $USER->id;
        $desig  = (string) ($opp->designation ?? '');
        $matchpct = $desig !== '' ? skills_bridge::match_percentage($userid, $desig) : 0;
        $now = time();

        $existing = $DB->get_record(self::INT_TABLE,
            ['opportunityid' => $opportunityid, 'userid' => $userid]);
        if ($existing) {
            $existing->message      = $message;
            $existing->matchpct     = $matchpct;
            $existing->timemodified = $now;
            $DB->update_record(self::INT_TABLE, $existing);
            audit::record((int) $opp->costcenterid, 'interest_registered', self::INT_TABLE,
                (int) $existing->id, $userid, ['opportunityid' => $opportunityid]);
            return (int) $existing->id;
        }

        $id = (int) $DB->insert_record(self::INT_TABLE, (object) [
            'costcenterid'  => (int) $opp->costcenterid,
            'opportunityid' => $opportunityid,
            'userid'        => $userid,
            'message'       => $message,
            'matchpct'      => $matchpct,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
        audit::record((int) $opp->costcenterid, 'interest_registered', self::INT_TABLE,
            $id, $userid, ['opportunityid' => $opportunityid]);
        return $id;
    }

    /**
     * Withdraw the current user's own interest in an opportunity.
     * Requires registerinterest; users can only withdraw their own row.
     *
     * @param int $opportunityid
     * @return bool
     */
    public static function withdraw_interest(int $opportunityid): bool {
        global $DB, $USER;
        require_capability('local/sentientia_talent:registerinterest', context_system::instance());
        $userid = (int) $USER->id;
        $existing = $DB->get_record(self::INT_TABLE,
            ['opportunityid' => $opportunityid, 'userid' => $userid]);
        if (!$existing) {
            return false;
        }
        $DB->delete_records(self::INT_TABLE, ['id' => $existing->id]);
        audit::record((int) $existing->costcenterid, 'interest_withdrawn', self::INT_TABLE,
            (int) $existing->id, $userid, ['opportunityid' => $opportunityid]);
        return true;
    }

    // ═════════════════════════════════════════════════════════════════
    // Helpers — designation options + KPI counts
    // ═════════════════════════════════════════════════════════════════

    /**
     * Distinct designations available for pickers — combines role-skills
     * designations (from local_sentientia_skills) with active user
     * designations, scoped to the current tenant for the user list.
     *
     * @return list<string>
     */
    public static function list_designations(): array {
        global $DB;
        $rows = $DB->get_fieldset_sql(
            "SELECT DISTINCT designation FROM {local_sentientia_role_skills}
              WHERE designation IS NOT NULL AND designation <> ''");
        try {
            if ($DB->get_manager()->field_exists('user',
                    new \xmldb_field('open_designation', XMLDB_TYPE_CHAR, '200'))) {
                [$tnsql, $tnparams] = tenant::path_filter('u', 'open_path', true);
                $userdesigs = $DB->get_fieldset_sql(
                    "SELECT DISTINCT u.open_designation FROM {user} u
                      WHERE u.open_designation IS NOT NULL AND u.open_designation <> ''
                        AND u.deleted = 0 AND $tnsql
                   ORDER BY u.open_designation ASC", $tnparams);
                $rows = array_unique(array_merge($rows, $userdesigs));
            }
        } catch (\Throwable $e) {
            // open_designation may not exist on stock Moodle — fine.
            debugging('local_sentientia_talent: designation lookup skipped: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
        sort($rows, SORT_STRING | SORT_FLAG_CASE);
        return array_values($rows);
    }

    /** KPI counts for the dashboard, tenant-scoped. */
    public static function counts(): array {
        global $DB;
        [$tnsql, $tnparams] = tenant::sql_filter();
        return [
            'paths'         => $DB->count_records_select(self::PATH_TABLE, $tnsql, $tnparams),
            'successions'   => $DB->count_records_select(self::SUCC_TABLE, $tnsql, $tnparams),
            'opportunities' => $DB->count_records_select(self::OPP_TABLE,
                $tnsql . " AND status = 'open'", $tnparams),
        ];
    }
}
