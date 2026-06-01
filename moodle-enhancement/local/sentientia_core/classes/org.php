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

    // ── Sentientia org model (ADR-020 Wave 3.2) ──────────────────────────────
    // These read the local_sentientia_org_unit / _member tables — the
    // Sentientia-owned org hierarchy. They are NEW capabilities (no production
    // code relies on a legacy equivalent), so they query the model directly,
    // returning empty/0 when the model isn't installed or seeded yet (dormant
    // until Wave 3.2b dual-write + 3.3 backfill populate it).
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
     * Does the user have any direct reports? (Is anyone's managerid edge.)
     *
     * @param int $userid
     * @return bool
     */
    public static function is_manager(int $userid): bool {
        global $DB;
        if ($userid <= 0 || !self::model_available()) {
            return false;
        }
        return $DB->record_exists('local_sentientia_org_member',
            ['managerid' => $userid]);
    }

    /**
     * Direct-report user ids of a manager — users whose managerid edge points at
     * this user (mirrors the open_supervisorid reverse lookup).
     *
     * @param int $userid
     * @return int[]
     */
    public static function direct_reports(int $userid): array {
        global $DB;
        if ($userid <= 0 || !self::model_available()) {
            return [];
        }
        return array_values(array_unique(array_map('intval',
            $DB->get_fieldset_select('local_sentientia_org_member', 'userid',
                'managerid = :uid', ['uid' => $userid]))));
    }
}
