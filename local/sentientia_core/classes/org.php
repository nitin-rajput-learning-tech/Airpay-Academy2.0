<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Org-hierarchy seam — ADR-020, Sentientia independence Wave 3.1.
 *
 * The sanctioned way to read a user's manager relationship, instead of touching
 * the BizLMS `$user->open_supervisorid` column directly (the SOFT coupling in
 * docs/DEPRECATION-SCHEDULE.md row 8).
 *
 * This is the **additive seam only** — Wave 3.1. Behind a default-ON
 * `org_legacy` flag it reads the legacy `open_supervisorid`, so behaviour is
 * identical to current production. When a future wave builds the Sentientia org
 * model (`local_sentientia_org_*` tables, ADR-020 §2 — gated on Nitin's go +
 * a clone-DB rehearsal), flipping the flag OFF switches the source; until then
 * the OFF path safely falls back to legacy, so it can never break.
 *
 * Scope note: only the manager-id ACCESSOR ships in 3.1 (it reads a record
 * property, so it is unit-testable on vanilla Moodle). Reverse lookups
 * (is_manager / direct reports) + unit-tree walks query the BizLMS-extended
 * user/costcenter tables, which don't exist on a vanilla Moodle test DB — they
 * arrive in Wave 3.2 alongside the `local_sentientia_org_*` schema.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org {

    /** Sentinel for "no manager". */
    public const NO_MANAGER = 0;

    /**
     * Is the legacy open_supervisorid / local_costcenter resolver active?
     *
     * Default ON: an unset config is treated as enabled so production behaviour
     * never changes implicitly.
     */
    public static function use_legacy_costcenter(): bool {
        $v = get_config('local_sentientia_core', 'org_legacy');
        return $v === false ? true : (bool) $v;
    }

    /**
     * Is the org dual-write reconciler active? (ADR-020 Wave 3.2b.)
     *
     * Default OFF: until an admin opts in, the reconcile_org scheduled task
     * no-ops, the local_sentientia_org_* tables stay empty, and deploying the
     * wave changes nothing. Independent of {@see use_legacy_costcenter()} —
     * dual-write warms the model while reads still come from legacy.
     */
    public static function use_dualwrite(): bool {
        return (bool) get_config('local_sentientia_core', 'org_dualwrite_enabled');
    }

    /**
     * Resolve a user's manager (line-manager) user id.
     *
     * @param \stdClass $user A user record carrying (at least) open_supervisorid.
     * @return int The manager's user id, or self::NO_MANAGER (0) if none.
     */
    public static function manager_id_of(\stdClass $user): int {
        if (self::use_legacy_costcenter()) {
            return self::legacy_manager_id($user);
        }
        // org_legacy OFF (Wave 3.2) — resolve via the Sentientia org model.
        $uid = (int) ($user->id ?? 0);
        if ($uid > 0) {
            $mgr = self::manager_via_model($uid);
            if ($mgr !== self::NO_MANAGER) {
                return $mgr;
            }
        }
        // User not yet mapped into the org model (pre-backfill, or an external
        // user with no unit) — fall back to legacy so OFF never breaks manager UX.
        debugging('local_sentientia_core: user not in the Sentientia org model; '
            . 'falling back to legacy open_supervisorid.', DEBUG_DEVELOPER);
        return self::legacy_manager_id($user);
    }

    /**
     * Resolve the manager for the current $USER.
     *
     * @return int Manager user id, or self::NO_MANAGER (0) if logged out / none.
     */
    public static function manager_id_for_current_user(): int {
        global $USER;
        if (empty($USER->id)) {
            return self::NO_MANAGER;
        }
        return self::manager_id_of($USER);
    }

    /**
     * Legacy resolver: the BizLMS open_supervisorid column.
     *
     * @param \stdClass $user
     * @return int
     */
    private static function legacy_manager_id(\stdClass $user): int {
        return (int) ($user->open_supervisorid ?? self::NO_MANAGER);
    }

    // ── Sentientia org model (ADR-020 Wave 3.2 / 3.4) ────────────────────────
    // These read the local_sentientia_org_unit / _member tables — the
    // Sentientia-owned org hierarchy. Two categories:
    //
    //  - Unit-tree methods (parent_of / ancestors / children / units_of /
    //    members_of) and manager_via_model are NEW capabilities with no legacy
    //    equivalent; they query the model directly, returning empty/0 until it
    //    is seeded (dormant until Wave 3.2b dual-write + 3.3 backfill populate it).
    //
    //  - Reverse-reporting methods (is_manager / direct_reports /
    //    reports_by_manager) DO have a legacy equivalent (the open_supervisorid
    //    reverse lookup), so — ADR-020 Wave 3.4 — they are FLAG-AWARE: org_legacy
    //    ON reads legacy (guarded on the BizLMS open_supervisorid column), OFF
    //    reads the model with a legacy fallback for any not-yet-backfilled user,
    //    symmetric with manager_id_of(). This makes them correct drop-ins for the
    //    raw open_supervisorid reverse readers, so a cutover auto-switches them.
    //
    // The manager relationship uses the DIRECT EDGE (org_member.managerid,
    // mirroring BizLMS open_supervisorid) per the 2026-06-01 modelling decision
    // — NOT the unit 'manager' role: in BizLMS, cost-center membership and the
    // reporting line are independent (two peers in one unit can report to
    // different managers). The 'role' column is retained for a future
    // 'unit lead' concept, separate from the reporting line.

    /** Are the Sentientia org-model tables installed? (request-cached) */
    public static function model_available(): bool {
        global $DB;
        static $available = null;
        if ($available === null) {
            $dbman = $DB->get_manager();
            $available = $dbman->table_exists('local_sentientia_org_unit')
                && $dbman->table_exists('local_sentientia_org_member');
        }
        return $available;
    }

    /**
     * Manager user id for a user via the org model — the direct-manager edge
     * (org_member.managerid, mirroring open_supervisorid).
     *
     * @param int $userid
     * @return int Manager user id, or self::NO_MANAGER (0).
     */
    public static function manager_via_model(int $userid): int {
        global $DB;
        if ($userid <= 0 || !self::model_available()) {
            return self::NO_MANAGER;
        }
        $mgr = $DB->get_field_sql(
            "SELECT managerid
               FROM {local_sentientia_org_member}
              WHERE userid = :uid AND managerid > 0
           ORDER BY id ASC",
            ['uid' => $userid], IGNORE_MULTIPLE);
        return $mgr ? (int) $mgr : self::NO_MANAGER;
    }

    /**
     * Parent unit id of an org unit (0 = root / none).
     *
     * @param int $unitid
     * @return int
     */
    public static function parent_of(int $unitid): int {
        global $DB;
        if ($unitid <= 0 || !self::model_available()) {
            return 0;
        }
        return (int) ($DB->get_field('local_sentientia_org_unit', 'parentid', ['id' => $unitid]) ?: 0);
    }

    /**
     * Ancestor unit ids, nearest-first (parent, grandparent, …). Cycle-guarded.
     *
     * @param int $unitid
     * @return int[]
     */
    public static function ancestors(int $unitid): array {
        $out = [];
        $seen = [];
        $cur = self::parent_of($unitid);
        while ($cur > 0 && empty($seen[$cur])) {
            $out[] = $cur;
            $seen[$cur] = true;
            $cur = self::parent_of($cur);
        }
        return $out;
    }

    /**
     * Direct child unit ids of a unit.
     *
     * @param int $unitid
     * @return int[]
     */
    public static function children(int $unitid): array {
        global $DB;
        if ($unitid < 0 || !self::model_available()) {
            return [];
        }
        return array_map('intval',
            $DB->get_fieldset_select('local_sentientia_org_unit', 'id', 'parentid = :p', ['p' => $unitid]));
    }

    /**
     * The unit ids a user belongs to.
     *
     * @param int $userid
     * @return int[]
     */
    public static function units_of(int $userid): array {
        global $DB;
        if ($userid <= 0 || !self::model_available()) {
            return [];
        }
        return array_map('intval',
            $DB->get_fieldset_select('local_sentientia_org_member', 'unitid', 'userid = :u', ['u' => $userid]));
    }

    /**
     * The member user ids of a unit.
     *
     * @param int $unitid
     * @return int[]
     */
    public static function members_of(int $unitid): array {
        global $DB;
        if ($unitid <= 0 || !self::model_available()) {
            return [];
        }
        return array_map('intval',
            $DB->get_fieldset_select('local_sentientia_org_member', 'userid', 'unitid = :u', ['u' => $unitid]));
    }

    /**
     * Does the user have any direct reports? Flag-aware (see the category note
     * above): org_legacy ON reads open_supervisorid, OFF reads the managerid edge
     * with a legacy fallback.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_manager(int $userid): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        if (self::use_legacy_costcenter()) {
            return self::legacy_is_manager($userid);
        }
        // org_legacy OFF — model, with a legacy fallback for the pre-backfill gap.
        if (self::model_available()
                && $DB->record_exists('local_sentientia_org_member', ['managerid' => $userid])) {
            return true;
        }
        return self::legacy_is_manager($userid);
    }

    /**
     * Direct-report user ids of a manager. Flag-aware: org_legacy ON reads the
     * open_supervisorid reverse lookup, OFF reads the model managerid edge with a
     * legacy fallback for a manager whose reports are not yet backfilled.
     *
     * @param int $userid
     * @return int[]
     */
    public static function direct_reports(int $userid): array {
        global $DB;
        if ($userid <= 0) {
            return [];
        }
        if (self::use_legacy_costcenter()) {
            return self::legacy_direct_reports($userid);
        }
        // org_legacy OFF — model, with a legacy fallback for the pre-backfill gap.
        if (self::model_available()) {
            $reports = array_values(array_unique(array_map('intval',
                $DB->get_fieldset_select('local_sentientia_org_member', 'userid',
                    'managerid = :uid', ['uid' => $userid]))));
            if (!empty($reports)) {
                return $reports;
            }
        }
        return self::legacy_direct_reports($userid);
    }

    /**
     * Map of manager user id => direct-report user ids — the aggregate primitive
     * for "group users by their manager" readers (e.g. manager digest crons).
     * Flag-aware: org_legacy ON groups by open_supervisorid, OFF groups by the
     * model managerid edge (with a legacy fallback when the model is empty).
     * ADR-020 Wave 3.4.
     *
     * @param int[]|null $managerids Restrict to these managers; null = all.
     * @return array<int,int[]> manager user id => report user ids
     */
    public static function reports_by_manager(?array $managerids = null): array {
        if ($managerids !== null) {
            $managerids = array_values(array_filter(
                array_map('intval', $managerids), static fn($id) => $id > 0));
            if (empty($managerids)) {
                return [];
            }
        }
        if (!self::use_legacy_costcenter() && self::model_available()) {
            $map = self::model_reports_by_manager($managerids);
            if (!empty($map)) {
                return $map;
            }
        }
        return self::legacy_reports_by_manager($managerids);
    }

    // ── Legacy reverse-lookup helpers (BizLMS open_supervisorid) ──────────────
    // Guarded on the column's existence so they degrade to empty on a vanilla /
    // Enterprise-N DB that has no open_supervisorid (rather than erroring).

    /** Is user.open_supervisorid queryable here? (request-cached.) */
    private static function legacy_reverse_available(): bool {
        global $DB;
        static $ok = null;
        if ($ok === null) {
            $ok = array_key_exists('open_supervisorid', $DB->get_columns('user'));
        }
        return $ok;
    }

    /** Legacy reverse: does anyone report to $userid via open_supervisorid? */
    private static function legacy_is_manager(int $userid): bool {
        global $DB;
        if (!self::legacy_reverse_available()) {
            return false;
        }
        return $DB->record_exists_select('user',
            'open_supervisorid = :uid AND deleted = 0 AND suspended = 0', ['uid' => $userid]);
    }

    /** Legacy reverse: user ids whose open_supervisorid points at $userid. */
    private static function legacy_direct_reports(int $userid): array {
        global $DB;
        if (!self::legacy_reverse_available()) {
            return [];
        }
        return array_values(array_map('intval', $DB->get_fieldset_select('user', 'id',
            'open_supervisorid = :uid AND deleted = 0 AND suspended = 0', ['uid' => $userid])));
    }

    /** Model grouping: manager id => report ids, via the managerid edge. */
    private static function model_reports_by_manager(?array $managerids): array {
        global $DB;
        $where = 'managerid > 0';
        $params = [];
        if ($managerids !== null) {
            [$insql, $params] = $DB->get_in_or_equal($managerids, SQL_PARAMS_NAMED, 'mgr');
            $where .= " AND managerid {$insql}";
        }
        $rs = $DB->get_recordset_select('local_sentientia_org_member', $where, $params, '',
            'id, userid, managerid');
        $map = [];
        foreach ($rs as $r) {
            $map[(int) $r->managerid][] = (int) $r->userid;
        }
        $rs->close();
        return $map;
    }

    /** Legacy grouping: manager id => report ids, via open_supervisorid. */
    private static function legacy_reports_by_manager(?array $managerids): array {
        global $DB;
        if (!self::legacy_reverse_available()) {
            return [];
        }
        $where = 'open_supervisorid > 0 AND deleted = 0 AND suspended = 0';
        $params = [];
        if ($managerids !== null) {
            [$insql, $params] = $DB->get_in_or_equal($managerids, SQL_PARAMS_NAMED, 'mgr');
            $where .= " AND open_supervisorid {$insql}";
        }
        $rs = $DB->get_recordset_select('user', $where, $params, '', 'id, open_supervisorid');
        $map = [];
        foreach ($rs as $r) {
            $map[(int) $r->open_supervisorid][] = (int) $r->id;
        }
        $rs->close();
        return $map;
    }
}
