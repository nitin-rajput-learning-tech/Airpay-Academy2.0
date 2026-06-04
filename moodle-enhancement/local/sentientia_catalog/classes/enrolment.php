<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * One-click free self-enrolment for internal-tenant employees.
 *
 * QA-walk P1 (2026-05-29) — Airpay-tenant employees could not enrol in
 * "Free" courses. The catalog's "Enroll" button routed every free course
 * through `course.php?action=addtocart` (a session cart), which never
 * enrolled anyone; the only real enrol path (`cart.php` → core
 * `enrol_self()`) silently no-ops on key-gated courses (and still reported
 * success). See docs/qa-walk-2026-05-29/.
 *
 * This class provides:
 *   - {@see should_offer_oneclick()} — the POLICY: who sees the one-click
 *     "Enrol now" CTA instead of "Add to cart". Encodes the two product
 *     decisions taken on 2026-05-29:
 *       (1) Scope  = internal tenants only (Airpay /1, ZEEA /177, and any
 *                    future customer tenant). The Public storefront tenant
 *                    (/77) KEEPS the cart so its B2C signup / checkout
 *                    funnel is untouched.
 *       (2) Keys   = bypass self-enrol enrolment keys (enrol via the
 *                    `manual` plugin) — catalog tenant-visibility is the
 *                    access gate for internal staff.
 *   - {@see enrol_now()} — the MECHANISM: idempotent manual enrolment in a
 *     FREE course, bypassing any self-enrol key. Mirrors the proven pattern
 *     in \local_airpay_cart\cart_manager::enrol_user_in_course().
 *
 * The CTA routing is gated behind the {@see FLAG} feature flag (default
 * OFF per CLAUDE.md §13, so OFF reproduces today's production behaviour).
 * Enable it for the internal tenants to unblock employees.
 *
 * @package local_sentientia_catalog
 */
class enrolment {

    /** Feature flag that gates the one-click "Enrol now" CTA. Default OFF. */
    public const FLAG = 'sentientia.catalog.free_oneclick_enrol.enabled';

    /**
     * Resolve the Public storefront tenant root (the B2C tenant that keeps
     * the cart). Configurable so future deployments can repoint it; falls
     * back to Airpay's production value (77).
     *
     * @return int
     */
    public static function public_tenant_id(): int {
        return (int) (get_config('local_sentientia_pages', 'public_tenant_id') ?: 77);
    }

    /**
     * Top-level tenant root from a user's open_path.
     * "/1/79/115" → 1, "/77" → 77, "" → 0. Matches the convention used by
     * catalog_manager::viewer_tenant_root() and cart_manager::get_tenant_root().
     *
     * @param \stdClass $user
     * @return int
     */
    public static function tenant_root(\stdClass $user): int {
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        $first = $parts[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }

    /**
     * POLICY — should this user be offered one-click "Enrol now" (vs the
     * cart) for this course?
     *
     * This is the single place the 2026-05-29 product decisions live. All
     * conditions must hold:
     *   1. The {@see FLAG} feature flag is ON for the user's tenant.
     *   2. The user is genuinely logged in (not a guest).
     *   3. The course is free.
     *   4. The user belongs to an INTERNAL tenant — i.e. a real tenant root
     *      that is NOT the Public storefront tenant. This is what keeps the
     *      Public B2C funnel on the cart while giving Airpay/ZEEA/future
     *      customers one-click enrol.
     *
     * @param \stdClass $user    A full user record (e.g. $USER).
     * @param array     $pricing The result of commerce::get_course_price().
     * @return bool
     */
    public static function should_offer_oneclick(\stdClass $user, array $pricing): bool {
        // (2) Logged-in, non-guest only. Guests / not-logged-in fall through
        //     to the cart (which captures signup on the Public storefront).
        if (empty($user->id) || isguestuser($user)) {
            return false;
        }

        // (3) Free courses only. Paid courses always use the cart/checkout.
        if (empty($pricing['is_free'])) {
            return false;
        }

        // (4) Internal tenant only — a real tenant that is not the Public
        //     storefront tenant. Root 0 (unclassified) fails closed.
        $root = self::tenant_root($user);
        if ($root <= 0 || $root === self::public_tenant_id()) {
            return false;
        }

        // (1) Feature flag, resolved for THIS user's tenant.
        return \local_airpay_core\feature_flags::is_enabled_for_tenant(self::FLAG, $root);
    }

    /**
     * MECHANISM — enrol a user in a FREE course immediately, bypassing any
     * self-enrol enrolment key by using Moodle's `manual` enrol plugin.
     *
     * Idempotent: a no-op (returns true) if the user is already enrolled.
     * Refuses (returns false) for non-existent / hidden / PAID courses, so it
     * can never be used to skip payment on a priced course.
     *
     * Safe to reuse from any free-enrol surface (the one-click CTA handler
     * AND the cart's "Enrol in all (free)" action), which is why it carries
     * no tenant gate — the tenant policy lives in {@see should_offer_oneclick()}.
     *
     * @param int      $courseid
     * @param int|null $userid   Defaults to the current $USER.
     * @return bool  True if the user is enrolled (or already was); false if refused/failed.
     */
    public static function enrol_now(int $courseid, ?int $userid = null): bool {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;
        if ($userid <= 0) {
            return false;
        }

        // Course must exist and be visible.
        $course = $DB->get_record('course', ['id' => $courseid, 'visible' => 1],
            'id, fullname, shortname', IGNORE_MISSING);
        if (!$course) {
            return false;
        }

        // Server-side price re-check — never enrol into a paid course here.
        $pricing = commerce::get_course_price($courseid);
        if (empty($pricing['is_free'])) {
            return false;
        }

        // Idempotent: already enrolled is success.
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) {
            return true;
        }

        // Manual enrol bypasses self-enrol keys. Create a manual instance if
        // the course has none (mirrors cart_manager::enrol_user_in_course()).
        $manual = enrol_get_plugin('manual');
        if (!$manual) {
            return false;
        }
        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]);
        if (!$instance) {
            $fullcourse = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $manual->add_default_instance($fullcourse);
            $instance = $DB->get_record('enrol',
                ['courseid' => $courseid, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED]);
        }
        if (!$instance) {
            return false;
        }

        $studentroleid = (int) ($DB->get_field('role', 'id', ['shortname' => 'student']) ?: 5);
        $manual->enrol_user($instance, $userid, $studentroleid, time(), 0, ENROL_USER_ACTIVE);

        return is_enrolled($context, $userid);
    }
}
