<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Who may see whose profile: the single tenant rule for every entry point
 * in this plugin that returns another user's profile data by id.
 *
 * WHY THIS EXISTS (defect N1, UAT 2026-09-24)
 * -------------------------------------------
 * profile.php checked only require_login() and then built the full profile
 * context for whatever id it was given, and core /user/profile.php redirects
 * to it. Logged in as an ordinary Airpay learner (tenant /1), the profiles of
 * ZEEA (/177) and Public (/77) users rendered in full: email, job title,
 * employee id, points, rank, badges, skills, manager.
 *
 * THE RULE, in order
 * ------------------
 *   1. Your own profile: allowed.
 *   2. Site admin: allowed.
 *   3. Otherwise viewer and target must resolve to the SAME tenant root, the
 *      leading numeric segment of open_path (/1/2/3 -> 1). Different root:
 *      refused. Same root keeps today's behaviour, so colleague links from
 *      the leaderboard and the manager views still work.
 *   4. A viewer whose tenant cannot be resolved (null, '', '/', non-numeric)
 *      and who is not a site admin sees nothing but their own profile. Fail
 *      closed; never guess a tenant.
 *   5. A target whose tenant cannot be resolved is refused to everyone but a
 *      site admin (and the target themselves, by rule 1).
 *
 * A deleted account counts as unresolvable for rules 3-5: a peer gets the
 * same answer for a deleted colleague as for an id that never existed.
 *
 * NOT AN EXISTENCE ORACLE
 * -----------------------
 * A refusal for a non-existent id and a refusal for an out-of-tenant id must
 * be indistinguishable, otherwise the error page itself enumerates which ids
 * belong to other tenants. So callers check access FIRST and only then load
 * the record ({@see self::get_viewable_user()} does both in that order), and
 * every refusal is the one exception built by {@see self::not_available()}.
 * Never get_record(..., MUST_EXIST) before the check: dml_missing_record is a
 * different response from a refusal.
 *
 * Tenant roots are parsed by the shared primitive
 * {@see \local_sentientia_platform\tenant::root_for_user()}, which returns 0
 * for anything that is not a leading numeric segment, and roots are compared
 * as integers, so /1 never matches /10 or /177 (the '/1%' prefix trap).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_access {

    /** @var string Lang key of the one refusal every entry point shows. */
    public const ERROR_STRING = 'error_profilenotavailable';

    /**
     * May $viewerid see the profile of $targetid?
     *
     * Pure decision: never throws, never reveals whether $targetid exists.
     *
     * @param int $viewerid The user asking (normally $USER->id).
     * @param int $targetid The user whose profile is asked for.
     * @return bool
     */
    public static function can_view(int $viewerid, int $targetid): bool {
        // Not logged in, or a nonsense id: nobody to grant anything to.
        if ($viewerid <= 0 || $targetid <= 0) {
            return false;
        }

        // Rule 1: own profile.
        if ($viewerid === $targetid) {
            return true;
        }

        // Rule 2: site admin.
        if (is_siteadmin($viewerid)) {
            return true;
        }

        // Rule 4: an unresolvable viewer sees nothing beyond rule 1.
        $viewerroot = self::tenant_root($viewerid);
        if ($viewerroot <= 0) {
            return false;
        }

        // Rule 5: an unresolvable (or non-existent, or deleted) target is
        // refused. This is the branch a missing id takes too, which is why the
        // two cases cannot be told apart.
        $targetroot = self::tenant_root($targetid);
        if ($targetroot <= 0) {
            return false;
        }

        // Rule 3: same tenant root, compared as integers.
        return $viewerroot === $targetroot;
    }

    /**
     * Throw the refusal unless $viewerid may see $targetid.
     *
     * @param int $viewerid
     * @param int $targetid
     * @return void
     * @throws \moodle_exception error_profilenotavailable
     */
    public static function require_can_view(int $viewerid, int $targetid): void {
        if (!self::can_view($viewerid, $targetid)) {
            throw self::not_available();
        }
    }

    /**
     * Check access, THEN load the target user. The order is the point.
     *
     * Refused and missing come back as the same exception. The only viewer
     * who can reach the "passed the check but no record" branch is a site
     * admin (a same-tenant peer has already had the record found by
     * can_view()), and they get the same exception too.
     *
     * @param int $viewerid
     * @param int $targetid
     * @param bool $includedeleted Load a deleted account as well. Only
     *                             reachable by a site admin: can_view()
     *                             treats a deleted target as unresolvable.
     * @param string $fields Columns to fetch.
     * @return \stdClass The target user record.
     * @throws \moodle_exception error_profilenotavailable
     */
    public static function get_viewable_user(int $viewerid, int $targetid,
                                             bool $includedeleted = false,
                                             string $fields = '*'): \stdClass {
        global $DB;

        self::require_can_view($viewerid, $targetid);

        $conditions = ['id' => $targetid];
        if (!$includedeleted) {
            $conditions['deleted'] = 0;
        }
        $user = $DB->get_record('user', $conditions, $fields);
        if (!$user) {
            throw self::not_available();
        }
        return $user;
    }

    /**
     * The one refusal. Deliberately carries no id, no $a and no debug info,
     * so a missing id and an out-of-tenant id render byte-identically.
     *
     * @return \moodle_exception
     */
    public static function not_available(): \moodle_exception {
        return new \moodle_exception(self::ERROR_STRING, 'local_sentientia_users');
    }

    /**
     * Tenant root of a live (non-deleted) account, or 0 when there is no such
     * account or its open_path has no leading numeric segment.
     *
     * Read from the database rather than $USER so the decision is the same
     * for every caller and the class stays testable without a session.
     *
     * @param int $userid
     * @return int
     */
    private static function tenant_root(int $userid): int {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, open_path');
        if (!$user) {
            return 0;
        }
        return \local_sentientia_platform\tenant::root_for_user($user);
    }
}
