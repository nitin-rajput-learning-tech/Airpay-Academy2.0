<?php
// This file is part of Sentientia LMS.

/**
 * Who may see the compliance report, and how much of it.
 *
 * @package    local_sentientia_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_compliance_report;

defined('MOODLE_INTERNAL') || die();

/**
 * The single place that decides a viewer's reach into the compliance report.
 *
 * Until 2026-09-24 index.php decided access inline and then scoped everyone
 * who got in to their whole tenant. That included a line manager admitted only
 * because somebody reports to them, who could read the compliance status of
 * every employee in the company. Now there are three levels:
 *
 *   site    site admin                                  every tenant
 *   tenant  compliance admin (local/courses:manage or   their own tenant
 *           the BizLMS compliance role, id 9, at a
 *           category), a moodle/site:viewreports
 *           holder (HRBP, trainer), or a holder of
 *           the export capability (export.php hands
 *           them the tenant anyway)
 *   team    a line manager - admitted only through      their reporting tree:
 *           the supervisor relationship                 direct reports and
 *                                                       everyone below them
 *
 * Anyone else, and any non-site-admin whose tenant cannot be resolved from
 * their open_path, gets no scope at all. Every report query reads '' as
 * "the whole site", so an unresolved tenant must never become ''.
 */
final class viewer_scope {

    /** Site admin: every tenant. */
    public const LEVEL_SITE = 'site';
    /** Compliance admin or report viewer: their own tenant. */
    public const LEVEL_TENANT = 'tenant';
    /** Line manager: their reporting tree inside their tenant. */
    public const LEVEL_TEAM = 'team';

    /** BizLMS compliance officer role, assigned at course-category context. */
    private const COMPLIANCE_ROLE_ID = 9;

    /**
     * @param string $level     one of the LEVEL_* constants
     * @param string $orgpath   '' only for LEVEL_SITE; otherwise the tenant root, e.g. '/1'
     * @param int[]|null $userids null unless LEVEL_TEAM: exactly the people this viewer may see
     */
    private function __construct(
        public readonly string $level,
        public readonly string $orgpath,
        public readonly ?array $userids,
    ) {
    }

    /**
     * Resolve the scope of a user, or null when they may not see the report.
     *
     * @param \stdClass $user a user record carrying id and open_path
     * @return self|null
     */
    public static function for_user(\stdClass $user): ?self {
        $level = self::level_for($user);
        if ($level === null) {
            return null;
        }
        if ($level === self::LEVEL_SITE) {
            return new self(self::LEVEL_SITE, '', null);
        }

        // Fail closed: '' would be read as the whole site.
        $orgpath = self::tenant_path($user);
        if ($orgpath === null) {
            return null;
        }

        if ($level === self::LEVEL_TEAM) {
            // Admission follows the tree, not the direct reports: a manager
            // whose direct reports have all left, but who still has people
            // further down, sees them. Nobody below them at all -> no access.
            $tree = compliance_engine::get_reporting_tree((int) $user->id, $orgpath);
            return $tree ? new self(self::LEVEL_TEAM, $orgpath, $tree) : null;
        }
        return new self(self::LEVEL_TENANT, $orgpath, null);
    }

    /**
     * Which level a user qualifies for, before their tenant is resolved.
     *
     * Order matters: every admission that gives a whole tenant is tried before
     * the supervisor relationship, so a compliance admin or report viewer who
     * also manages people keeps the tenant view they had.
     *
     * @param \stdClass $user
     * @return string|null a LEVEL_* constant, or null for no access
     */
    public static function level_for(\stdClass $user): ?string {
        global $DB;

        $userid = (int) $user->id;
        if ($userid <= 0 || isguestuser($userid)) {
            return null;
        }
        if (is_siteadmin($userid)) {
            return self::LEVEL_SITE;
        }

        $systemcontext = \context_system::instance();
        // local/courses:manage is declared only where BizLMS is installed. On
        // UAT and fresh Sentientia installs it does not exist, and asking
        // has_capability() about it only raises a debugging notice.
        if (get_capability_info('local/courses:manage')
                && has_capability('local/courses:manage', $systemcontext, $userid)) {
            return self::LEVEL_TENANT;
        }
        if ($DB->record_exists_sql(
                "SELECT 1
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid
                  WHERE ra.userid = :uid AND ra.roleid = :roleid AND ctx.contextlevel = :level",
                ['uid' => $userid, 'roleid' => self::COMPLIANCE_ROLE_ID, 'level' => CONTEXT_COURSECAT])) {
            return self::LEVEL_TENANT;
        }
        if (has_capability('moodle/site:viewreports', $systemcontext, $userid)) {
            return self::LEVEL_TENANT;
        }
        // Whoever may DOWNLOAD the tenant's matrix (export.php) sees it on
        // screen too. Without this, a category Manager with direct reports was
        // shown "your team only" beside an Export button handing over the tenant.
        if (permission::can_export($userid)) {
            return self::LEVEL_TENANT;
        }
        // A candidate line manager: anyone with a direct report, whatever that
        // report's status. for_user() admits them only if their reporting tree
        // (which walks through deleted and suspended middle managers) holds
        // somebody. Until 2026-09-24 this needed an ACTIVE direct report, so a
        // manager whose direct reports had all left was refused although
        // active people still reported to them further down.
        if (self::has_direct_reports($userid)) {
            return self::LEVEL_TEAM;
        }
        return null;
    }

    /**
     * Why for_user() refused: 'notenant' when the user qualifies but their
     * tenant cannot be resolved (an administrator can fix their account),
     * 'noaccess' otherwise. Only meaningful when for_user() returned null.
     */
    public static function refusal_reason(\stdClass $user): string {
        $level = self::level_for($user);
        return ($level !== null && $level !== self::LEVEL_SITE && self::tenant_path($user) === null)
            ? self::REFUSED_NO_TENANT : self::REFUSED_NO_ACCESS;
    }

    /** The user qualifies, but has no resolvable tenant. */
    public const REFUSED_NO_TENANT = 'notenant';
    /** The user does not qualify at all. */
    public const REFUSED_NO_ACCESS = 'noaccess';

    /**
     * The user's tenant root ('/N'), or null when it cannot be resolved.
     */
    private static function tenant_path(\stdClass $user): ?string {
        $orgpath = \local_sentientia_org\tenant_manager::get_tenant_path($user);
        return ($orgpath === '' || $orgpath === '/') ? null : $orgpath;
    }

    /**
     * True for anyone who reads the report as an administrator (site or tenant).
     */
    public function is_admin_level(): bool {
        return $this->level !== self::LEVEL_TEAM;
    }

    /**
     * Whether this viewer may use the Configure tab and its four actions.
     *
     * Site admins only: the engine's configuration methods are not
     * tenant-scoped (compliance courses and exclusions are site-wide).
     */
    public function can_configure(): bool {
        return $this->level === self::LEVEL_SITE;
    }

    /**
     * The KPI cache key for a filter path under this scope.
     */
    public function kpi_cache_key(string $filterpath): string {
        return 'kpis_' . md5($filterpath . '|' . $this->cache_key());
    }

    /**
     * A cache-key fragment that differs whenever the visible population does.
     *
     * The KPI cache used to be keyed on the org path alone. With team scope,
     * two managers in one tenant share that path and would have been served
     * each other's figures - or the whole tenant's.
     */
    public function cache_key(): string {
        if ($this->userids === null) {
            return $this->level . ':' . $this->orgpath;
        }
        $ids = $this->userids;
        sort($ids);
        return $this->level . ':' . $this->orgpath . ':' . sha1(implode(',', $ids));
    }

    /**
     * Whether anyone - in any status - reports directly to this user.
     */
    private static function has_direct_reports(int $userid): bool {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->field_exists(new \xmldb_table('user'), new \xmldb_field('open_supervisorid'))) {
            return false;
        }
        return $DB->record_exists('user', ['open_supervisorid' => $userid]);
    }
}
