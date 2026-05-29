<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Sprint C — cross-tenant course-sharing engine.
 *
 * What this class owns
 * --------------------
 * The `local_airpay_courses_tenant_share` table maps courses to the
 * tenants that have borrowed them. A course appears in tenant N's
 * catalog if EITHER:
 *
 *   (a) the course's `open_path` field is inside tenant N's tree
 *       (the "owned" path — same as today), OR
 *   (b) a row exists in this table with status='active' for
 *       (courseid, tenant_id=N) — the "borrowed" path.
 *
 * Critically, COMPLETION DATA STAYS SEGREGATED automatically. A user
 * belongs to ONE tenant via `mdl_user.open_path`. Their completion
 * records in `mdl_course_completions` are joined to `mdl_user` for
 * tenant filtering, so a Public learner completing a borrowed Airpay
 * course shows up only in Public's reports — exactly the segregation
 * the Super Admin asked for.
 *
 * Public API
 * ----------
 *   share_course($courseid, $tenant_ids[])
 *     Create or re-activate share rows. Idempotent on (courseid, tenant_id).
 *     Fires \local_airpay_courses\event\course_share_created per new
 *     tenant_id. Returns array of results per tenant.
 *
 *   unshare_course($courseid, $tenant_id)
 *     Set status='withdrawn' on the row. Idempotent — re-running on an
 *     already-withdrawn row is a no-op. Fires course_share_withdrawn.
 *
 *   list_course_shares($courseid)
 *     Returns the current share state for a course, with one row per
 *     tenant root that EITHER has an active share OR previously had
 *     one (so the admin UI can show "withdrawn at" timestamps).
 *
 *   build_catalog_filter_sql($alias, $viewer_tenant)
 *     Returns [string $sql_fragment, array $params] suitable for use
 *     in the WHERE clause of catalog queries. Combines owned + borrowed
 *     paths into a single SQL fragment. See airpay_catalog/
 *     classes/catalog_manager.php for usage.
 *
 *   is_course_shared_to($courseid, $tenant_id)
 *     Quick boolean check — does an active share exist?
 *
 *   known_tenants()
 *     Returns the list of tenant roots this Moodle knows about
 *     ({1=Airpay, 77=Public, 177=ZEEA}) so the share form can render
 *     them as checkboxes. Sourced from the `local_costcenter` /
 *     `local_airpay_org` table at depth 1 (top-level orgs).
 *
 * @package local_airpay_courses
 */
class sharing_manager {

    /** Status: course is currently shared. */
    public const STATUS_ACTIVE    = 'active';
    /** Status: a share that was created then withdrawn (history kept). */
    public const STATUS_WITHDRAWN = 'withdrawn';

    /**
     * Share a course to one or more tenants.
     *
     * Idempotent: re-running on an already-shared (courseid, tenant_id)
     * pair updates timemodified but doesn't duplicate the row.
     * Withdrawn rows are reactivated.
     *
     * @param int   $courseid
     * @param int[] $tenant_ids
     * @param int|null $by_userid Defaults to current $USER->id.
     * @return array{shared: int[], reactivated: int[], unchanged: int[],
     *               errors: array<int,string>} per-tenant results.
     */
    public static function share_course(int $courseid, array $tenant_ids,
                                          ?int $by_userid = null): array {
        global $DB, $USER;
        $by_userid = $by_userid ?? (int) $USER->id;

        // Capability check is the caller's job (external WS does it);
        // this class is the data layer.

        if ($courseid <= 0) {
            throw new \moodle_exception('invalidcourse', 'local_airpay_courses');
        }
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            throw new \moodle_exception('invalidcourse', 'local_airpay_courses');
        }

        $now = time();
        $out = ['shared' => [], 'reactivated' => [], 'unchanged' => [], 'errors' => []];

        $known = array_flip(array_column(self::known_tenants(), 'id'));

        foreach ($tenant_ids as $tid) {
            $tid = (int) $tid;
            if ($tid <= 0) {
                $out['errors'][$tid] = 'invalid tenant id';
                continue;
            }
            if (!isset($known[$tid])) {
                $out['errors'][$tid] = "tenant $tid is not a known top-level org";
                continue;
            }

            $existing = $DB->get_record('local_airpay_courses_tenant_share',
                ['courseid' => $courseid, 'tenant_id' => $tid]);

            if ($existing) {
                if ($existing->status === self::STATUS_ACTIVE) {
                    $out['unchanged'][] = $tid;
                    continue;
                }
                // Reactivate a previously-withdrawn share.
                $existing->status       = self::STATUS_ACTIVE;
                $existing->shared_by    = $by_userid;
                $existing->timemodified = $now;
                $DB->update_record('local_airpay_courses_tenant_share', $existing);
                $out['reactivated'][] = $tid;
            } else {
                $DB->insert_record('local_airpay_courses_tenant_share', (object) [
                    'courseid'     => $courseid,
                    'tenant_id'    => $tid,
                    'shared_by'    => $by_userid,
                    'status'       => self::STATUS_ACTIVE,
                    'timeshared'   => $now,
                    'timemodified' => $now,
                ]);
                $out['shared'][] = $tid;
            }

            // Fire the audit event — every successful insert/reactivation.
            // Note: we DON'T pass the top-level `courseid` key here.
            // Moodle's event base would emit "Inconsistent courseid -
            // context combination detected" because the context is
            // system (this is a tenant-administrative action that
            // crosses course boundaries) but courseid implies a
            // single-course event. The course id lives inside `other`
            // so audit consumers can still filter on it.
            $event = event\course_share_created::create([
                'objectid'     => $courseid,
                'context'      => \context_system::instance(),
                'userid'       => $by_userid,
                'other'        => [
                    'tenant_id' => $tid,
                    'courseid'  => $courseid,
                ],
            ]);
            $event->trigger();
        }

        return $out;
    }

    /**
     * Withdraw a single (courseid, tenant_id) share.
     *
     * Sets status='withdrawn'; keeps the row for audit history.
     * Re-running on an already-withdrawn row is a no-op (returns false).
     *
     * @param int $courseid
     * @param int $tenant_id
     * @param int|null $by_userid
     * @return bool true if a row was changed, false if no-op
     */
    public static function unshare_course(int $courseid, int $tenant_id,
                                            ?int $by_userid = null): bool {
        global $DB, $USER;
        $by_userid = $by_userid ?? (int) $USER->id;

        if ($courseid <= 0 || $tenant_id <= 0) {
            throw new \moodle_exception('invalidparameter', 'local_airpay_courses');
        }

        $existing = $DB->get_record('local_airpay_courses_tenant_share',
            ['courseid' => $courseid, 'tenant_id' => $tenant_id]);

        if (!$existing || $existing->status === self::STATUS_WITHDRAWN) {
            return false;  // nothing to do
        }

        $existing->status       = self::STATUS_WITHDRAWN;
        $existing->shared_by    = $by_userid;  // record who withdrew
        $existing->timemodified = time();
        $DB->update_record('local_airpay_courses_tenant_share', $existing);

        // Audit event. See course_share_created for the reason `courseid`
        // is not on the top-level payload (Inconsistent courseid - context
        // combination notice).
        $event = event\course_share_withdrawn::create([
            'objectid'     => $courseid,
            'context'      => \context_system::instance(),
            'userid'       => $by_userid,
            'other'        => [
                'tenant_id' => $tenant_id,
                'courseid'  => $courseid,
            ],
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Get the current sharing state for a course.
     *
     * Returns one entry per tenant that has ever been linked — active
     * or withdrawn — so the admin UI can render "Tenant Public:
     * SHARED since 2026-04-01" and "Tenant ZEEA: WITHDRAWN on
     * 2026-05-01".
     *
     * @param int $courseid
     * @return array<int, object> indexed by tenant_id
     */
    public static function list_course_shares(int $courseid): array {
        global $DB;
        $rows = $DB->get_records('local_airpay_courses_tenant_share',
            ['courseid' => $courseid], 'tenant_id ASC');
        // Re-index by tenant_id for easy lookup from templates.
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->tenant_id] = $r;
        }
        return $out;
    }

    /**
     * Quick boolean check for catalog filtering.
     *
     * @param int $courseid
     * @param int $tenant_id
     * @return bool
     */
    public static function is_course_shared_to(int $courseid, int $tenant_id): bool {
        global $DB;
        if ($courseid <= 0 || $tenant_id <= 0) {
            return false;
        }
        return $DB->record_exists('local_airpay_courses_tenant_share', [
            'courseid'  => $courseid,
            'tenant_id' => $tenant_id,
            'status'    => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Build a SQL fragment that combines tenant-owned + tenant-borrowed
     * scoping, suitable for plugging into a catalog WHERE clause.
     *
     * Returns SQL of the form:
     *   ( c.open_path = :share_orgexact
     *     OR c.open_path LIKE :share_orgprefix
     *     OR EXISTS (SELECT 1 FROM {local_airpay_courses_tenant_share} csh
     *                 WHERE csh.courseid = <alias>.id
     *                   AND csh.tenant_id = :share_tenant_id
     *                   AND csh.status = 'active') )
     *
     * The catalog manager substitutes <alias> (usually 'c') and uses
     * the returned params alongside its own param array.
     *
     * Site admins (passed `$viewer_tenant === 0`) get a permissive
     * '1=1' so they see every course regardless of share state.
     *
     * **Deprecated (2026-05-29):** callers should prefer
     * \local_airpay_courses\sharing_manager::build_catalog_filter_sql_v2()
     * which uses the canonical category-path resolver
     * (\local_airpay_org\accesslib::get_tenant_category_id) as the base
     * filter and unions in the EXISTS share clause. This v1 method stays
     * intact for backward compatibility with external callers and the
     * existing PHPUnit suite (sharing_manager_test.php). Internal catalog
     * code has migrated to v2 in commit following db5242c9a.
     *
     * @param string $alias    Course table alias (e.g. 'c')
     * @param int    $viewer_tenant Tenant root for the viewer (0 = siteadmin / unscoped)
     * @return array{0: string, 1: array} sql fragment + named params
     */
    public static function build_catalog_filter_sql(string $alias,
                                                      int $viewer_tenant): array {
        global $DB;

        if ($viewer_tenant <= 0) {
            return ['1=1', []];
        }

        $col_open_path = $alias === '' ? 'open_path' : "$alias.open_path";
        $col_id        = $alias === '' ? 'id'        : "$alias.id";

        $exact_path  = '/' . $viewer_tenant;
        $prefix_path = $DB->sql_like_escape('/' . $viewer_tenant . '/') . '%';

        $sql = "($col_open_path = :share_orgexact
                  OR $col_open_path LIKE :share_orgprefix
                  OR EXISTS (
                       SELECT 1 FROM {local_airpay_courses_tenant_share} csh
                        WHERE csh.courseid  = $col_id
                          AND csh.tenant_id = :share_tenant_id
                          AND csh.status    = :share_status))";
        $params = [
            'share_orgexact'   => $exact_path,
            'share_orgprefix'  => $prefix_path,
            'share_tenant_id'  => $viewer_tenant,
            'share_status'     => self::STATUS_ACTIVE,
        ];
        return [$sql, $params];
    }

    /**
     * Build a CATEGORY-PATH-based catalog tenant filter (broader-sweep
     * follow-up to db5242c9a's onboarding fix, 2026-05-29).
     *
     * Drives the BASE tenant filter through the canonical resolver
     * \local_airpay_org\accesslib::get_tenant_category_id, so onboarding,
     * dashboard recommendations, AI recommendations AND the catalog all
     * agree on what "this learner's tenant" means. Then UNIONs in the
     * Sprint C/D EXISTS check so a borrowed Airpay course shared to
     * tenant 77 still surfaces in Public's catalog.
     *
     * Returns SQL of the form:
     *   ( cc.id = :share_v2_catid
     *     OR cc.path LIKE :share_v2_catpathwild
     *     OR EXISTS (SELECT 1 FROM {local_airpay_courses_tenant_share} csh2
     *                 WHERE csh2.courseid = <alias>.id
     *                   AND csh2.tenant_id = :share_v2_tenant_id
     *                   AND csh2.status = 'active') )
     *
     * The caller MUST include a JOIN to {course_categories} cc on
     * `<alias>.category = cc.id` in the same query — catalog_manager
     * methods already do, so this is a zero-cost requirement.
     *
     * Failure modes:
     *   - viewer_tenant=0 (siteadmin / unscoped) -> '1=1' passthrough.
     *   - resolver returns null (tenant id given but accesslib chain
     *     fails) -> '0=1' fail closed. The catalog renders nothing.
     *     Previous v1 method silently returned '1=1' on tenant=0 even
     *     for non-admins, which was the wrong default for the
     *     onboarding-leak risk model.
     *
     * @param string $course_alias Course-table alias used in the calling
     *                              query (e.g. 'c'). Used by the EXISTS
     *                              subquery's join condition.
     * @param int    $viewer_tenant Tenant root for the viewer (0 =
     *                              siteadmin / unscoped). When > 0 the
     *                              accesslib resolver is invoked using
     *                              '/'.$viewer_tenant as the open_path.
     * @return array{0: string, 1: array} SQL fragment + named params.
     */
    public static function build_catalog_filter_sql_v2(string $course_alias,
                                                         int $viewer_tenant): array {
        global $DB;

        if ($viewer_tenant <= 0) {
            return ['1=1', []];
        }

        $tenant_catid = \local_airpay_org\accesslib::get_tenant_category_id(
            '/' . $viewer_tenant);
        if ($tenant_catid === null) {
            // Tenant ID provided but accesslib can't resolve it (no
            // BizLMS row at /TENANT, no Sentientia-native idnumber match).
            // Fail closed -- rendering zero courses is safer than
            // rendering every course.
            return ['0=1', []];
        }
        $tenant_catpath = (string) $DB->get_field('course_categories', 'path',
            ['id' => $tenant_catid]);
        if ($tenant_catpath === '') {
            // Resolver returned a catid but the category record has no
            // path (shouldn't happen on a healthy Moodle, but guard).
            return ['0=1', []];
        }

        $col_id = $course_alias === '' ? 'id' : "$course_alias.id";
        $sql = "(cc.id = :share_v2_catid
                  OR " . $DB->sql_like('cc.path', ':share_v2_catpathwild') . "
                  OR EXISTS (
                       SELECT 1 FROM {local_airpay_courses_tenant_share} csh2
                        WHERE csh2.courseid  = $col_id
                          AND csh2.tenant_id = :share_v2_tenant_id
                          AND csh2.status    = :share_v2_status))";
        $params = [
            'share_v2_catid'        => $tenant_catid,
            'share_v2_catpathwild'  => $tenant_catpath . '/%',
            'share_v2_tenant_id'    => $viewer_tenant,
            'share_v2_status'       => self::STATUS_ACTIVE,
        ];
        return [$sql, $params];
    }

    /**
     * Return the list of known top-level tenants for this Moodle.
     *
     * Sources from `local_airpay_org` if available (Airpay's tenant
     * registry); falls back to a hard-coded {1, 77, 177} list for
     * environments where the org plugin isn't installed (the master
     * tenants this codebase has shipped against).
     *
     * @return array<int, object{id: int, name: string}>
     */
    public static function known_tenants(): array {
        global $DB;

        // Preferred source: BizLMS-style top-level org table.
        // The org table column is `fullname` (not `name` — that's the
        // bizlms-era costcenter column name we don't carry). Sprint C
        // hotfix: corrected after PHPUnit caught the bad column name.
        if ($DB->get_manager()->table_exists('local_airpay_org')) {
            // Depth 1 = top-level orgs (tenants). path is something
            // like '/1' for Airpay's root org.
            $rows = $DB->get_records_sql(
                "SELECT id, fullname FROM {local_airpay_org}
                  WHERE depth = 1
                    AND visible = 1
                  ORDER BY id ASC");
            if (!empty($rows)) {
                return array_map(fn($r) => (object) [
                    'id'   => (int) $r->id,
                    'name' => $r->fullname,
                ], array_values($rows));
            }
        }

        // Fallback: the three known tenants this codebase ships against.
        return [
            (object) ['id' => 1,   'name' => 'Airpay'],
            (object) ['id' => 77,  'name' => 'Public'],
            (object) ['id' => 177, 'name' => 'ZEEA'],
        ];
    }
}
