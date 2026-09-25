<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * ADR-031 (2026-09-25): WHERE a caller of the webhooks and SCIM admin pages may act.
 *
 * :webhooks_manage and :scim_manage say WHAT a caller may do (manage outbound
 * subscriptions / SCIM clients). They never say WHERE. Until 2026-09-25 both
 * pages listed, created, rotated and deleted rows for every tenant, and both
 * capabilities defaulted to the manager archetype that every tenant admin
 * holds at system context. The pages were only saved by sitting inside the
 * `$hassiteconfig` admin-tree block.
 *
 * Now:
 *   - a cross-tenant caller (site admin or local/sentientia_platform:crosstenant)
 *     is unscoped, exactly as before, and may still create costcenterid 0
 *     ("every tenant") rows;
 *   - anyone else is confined to their own tenant root: lists are filtered,
 *     every id-named action checks the row's tenant, and a create is forced
 *     into their tenant. Rows with costcenterid 0 belong to every tenant, so
 *     only a cross-tenant caller may touch them;
 *   - a scoped caller whose tenant does not resolve is refused outright.
 *
 * What this does NOT bound (corrected 2026-09-25, adversarial review S1): it
 * decides which ROWS a caller manages, not what those rows can do. A SCIM
 * client a scoped caller creates or rotates carries a bearer token that can
 * create, rename, re-email, suspend and move every ordinary account in the
 * tenant. \local_sentientia_api\scim\handler keeps every cross-tenant
 * principal (site admin, :crosstenant holder) out of a scoped client's reach
 * even when their open_path sits under that tenant; everyone else in the
 * tenant is within it by design.
 *
 * @package local_sentientia_api
 */
final class admin_scope {

    /**
     * The tenant root the current user is confined to.
     *
     * @return int|null null = cross-tenant (every tenant); N = that tenant root only
     * @throws \moodle_exception error_outoftenant for a scoped caller with no resolvable tenant
     */
    public static function tenant_root(): ?int {
        $path = tenant::scope_path();
        if ($path === '') {
            return null;
        }
        if ($path === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return (int) substr($path, 1);
    }

    /**
     * The costcenterid a new subscription / client is stored with.
     *
     * A cross-tenant caller keeps their choice (0 = every tenant); anyone else
     * gets their own tenant, whatever the form sent.
     *
     * @param int      $requested
     * @param int|null $scoperoot from tenant_root()
     * @return int
     */
    public static function costcenter_for_create(int $requested, ?int $scoperoot): int {
        return $scoperoot === null ? $requested : $scoperoot;
    }

    /**
     * Refuse unless a row with this costcenterid is inside the caller's scope.
     *
     * @param int      $rowcostcenterid
     * @param int|null $scoperoot from tenant_root()
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_costcenter(int $rowcostcenterid, ?int $scoperoot): void {
        if ($scoperoot === null) {
            return;
        }
        if ($rowcostcenterid !== $scoperoot) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
    }

    /**
     * Refuse unless webhook subscription $id is inside the caller's scope.
     *
     * @param int      $id
     * @param int|null $scoperoot
     */
    public static function require_subscription(int $id, ?int $scoperoot): void {
        global $DB;
        if ($scoperoot === null) {
            return;
        }
        $cid = $DB->get_field(webhooks\subscription::TABLE, 'costcenterid', ['id' => $id]);
        if ($cid === false) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        self::require_costcenter((int) $cid, $scoperoot);
    }

    /**
     * Refuse unless delivery row $id belongs to a subscription inside the caller's scope.
     *
     * @param int      $id
     * @param int|null $scoperoot
     */
    public static function require_delivery(int $id, ?int $scoperoot): void {
        global $DB;
        if ($scoperoot === null) {
            return;
        }
        $subid = $DB->get_field(webhooks\queue::TABLE, 'subid', ['id' => $id]);
        if ($subid === false) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        self::require_subscription((int) $subid, $scoperoot);
    }

    /**
     * Refuse unless SCIM client $id is inside the caller's scope.
     *
     * @param int      $id
     * @param int|null $scoperoot
     */
    public static function require_client(int $id, ?int $scoperoot): void {
        global $DB;
        if ($scoperoot === null) {
            return;
        }
        $cid = $DB->get_field(scim\client::TABLE, 'costcenterid', ['id' => $id]);
        if ($cid === false) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        self::require_costcenter((int) $cid, $scoperoot);
    }
}
