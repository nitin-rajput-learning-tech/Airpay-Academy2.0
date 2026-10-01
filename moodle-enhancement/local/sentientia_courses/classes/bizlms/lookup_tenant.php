<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * Tenant attribution for the two BizLMS lookup tables that name an organisation
 * (local_course_types.orgid and local_custom_category.costcenterid).
 *
 * Both columns hold a local_costcenter id. The importer resolves it the way every ADR-032
 * importer resolves a foreign legacy id: through the legacy map first (org is PRESERVE, so the
 * target org has the same id, but the importer asks the map and does not assume), and then,
 * when the org importer has not written that organisation yet (a single-feature dry run), from
 * the legacy local_costcenter row itself. The tenant resolver validates the root through
 * tenant::assert_valid() and reports how it got the answer.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lookup_tenant {

    /** Legacy table that holds the BizLMS organisations. */
    private const COSTCENTER = 'local_costcenter';

    /**
     * The tenant path of a BizLMS cost centre id.
     *
     * @param context $ctx
     * @param int $costcenterid local_costcenter.id; zero or less means "no organisation".
     * @param bool $rootonly True for the tenant root ('/1'), false for the organisation's own path ('/1/5').
     * @return array{0: ?string, 1: ?string} [path, method]. Path is null and method null when the row names
     *         no organisation at all; path null and method 'unresolved' when it names one that cannot be
     *         resolved to a registered tenant; otherwise the path and the resolver's method (exact,
     *         normalised, walked_up or fallback:<name>).
     */
    public static function of_costcenter(context $ctx, int $costcenterid, bool $rootonly): array {
        if ($costcenterid <= 0) {
            return [null, null];
        }

        $candidates = [];
        $orgid = $ctx->map->resolve(self::COSTCENTER, $costcenterid);
        if ($orgid !== null && $orgid > 0) {
            $org = $ctx->lookups->org($orgid);
            if ($org !== null && $org->path !== '') {
                $candidates['org'] = $org->path;
            }
        }
        if ($ctx->legacy->exists(self::COSTCENTER)) {
            $legacy = $ctx->legacy->fetch(self::COSTCENTER, [$costcenterid], ['path']);
            if (isset($legacy[$costcenterid]) && trim((string) ($legacy[$costcenterid]->path ?? '')) !== '') {
                $candidates['costcenter'] = (string) $legacy[$costcenterid]->path;
            }
        }

        [$path, $root, $method] = $ctx->tenant->resolve($candidates);
        if ($path === null || $root === null) {
            return [null, 'unresolved'];
        }
        return [$rootonly ? '/' . $root : $path, $method];
    }
}
