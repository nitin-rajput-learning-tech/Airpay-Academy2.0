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
 *     in \local_sentientia_cart\cart_manager::enrol_user_in_course().
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
        return \local_sentientia_platform\feature_flags::is_enabled_for_tenant(self::FLAG, $root);
    }

    /**
     * Should the catalogue offer this learner "Request enrolment" for this course, instead of Enroll?
     *
     * Visual-pass finding 7 (a), 2026-10-15. The rule that a request is the way in lives in the request plugin
     * (\local_sentientia_request\catalog_cta::state(): its flag sentientia.request.catalog_button, the :request
     * capability, not enrolled, a course submit() would accept; the theme never renders /enrol/index.php, so core's
     * self-enrolment form is not a way in). This adds
     * the three things only the catalogue knows, and so keeps its own routes first:
     *
     *   - a PAID course goes to the cart (and checkout), never to a request;
     *   - a FREE course this user is offered one-click enrolment for ({@see should_offer_oneclick()}) is enrolled
     *     at once, so there is nothing to request;
     *   - a FREE course whose own self-enrolment the viewer may use ({@see self_enrol_instance()}) is enrolled from
     *     course.php's button ({@see self_enrol()}, persona walk 2026-11-18), so there is nothing to request either.
     *     BizLMS's catalogue did the same: it offered a request only where approvalreqd = 1, and self-enrolment
     *     only where it was not.
     *
     * The request plugin is optional: without it, or with its flag OFF (the default), this is always 'none' and
     * the catalogue renders as it did. The flag is read before anything else, so the default path costs one
     * cached lookup per card.
     *
     * @param \stdClass $user    The viewer (a full user record, e.g. $USER).
     * @param int       $courseid
     * @param array|null $pricing The result of commerce::get_course_price(), when the caller has it already;
     *                   read here, after the flag check, when null (a catalogue card does not have it).
     * @return string 'request' (show the button), 'pending' (show that a request is pending) or 'none'.
     */
    public static function request_state(\stdClass $user, int $courseid, ?array $pricing = null): string {
        global $USER;
        if (!class_exists('\\local_sentientia_request\\catalog_cta')
                || !\local_sentientia_request\catalog_cta::enabled()) {
            return 'none';
        }
        $pricing ??= commerce::get_course_price($courseid);
        if (empty($pricing['is_free']) || self::should_offer_oneclick($user, $pricing)) {
            return 'none';
        }
        // self_enrol_instance() answers for the logged-in viewer only, as catalog_cta::state() does.
        if ((int) $user->id === (int) $USER->id && self::self_enrol_instance($courseid) !== null) {
            return 'none';
        }
        return \local_sentientia_request\catalog_cta::state($user, $courseid);
    }

    /**
     * Does BizLMS's own per-course rule allow self-enrolment?
     *
     * Parity with production's catalogue (local_courses\output\search::enrol_user_to_component('self'); see
     * docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md, finding 77): the course has selfenrol = 1 and does
     * not have approvalreqd = 1 (an approval-required course is entered through a request). On a schema without
     * those restored BizLMS columns (a vanilla install) the course's self-enrolment instance alone decides.
     *
     * @param \stdClass $course A full course record.
     * @return bool
     */
    public static function bizlms_allows_self_enrol(\stdClass $course): bool {
        if (property_exists($course, 'selfenrol') && (int) $course->selfenrol !== 1) {
            return false;
        }
        if (property_exists($course, 'approvalreqd') && (int) $course->approvalreqd === 1) {
            return false;
        }
        return true;
    }

    /**
     * The course's own self-enrolment instance, when the logged-in viewer may use it now with nothing to type.
     *
     * Persona walk 2026-11-18. The theme never renders /enrol/index.php (core_renderer::custom_secured_redirection()
     * sends it to this plugin's course.php), so course.php's "Enroll Now" button, which linked there, only reloaded
     * course.php. course.php now offers its own enrol button for a free course only when this finds an instance,
     * and {@see self_enrol()} asks again before it enrols. It grants nothing the course does not; all must hold:
     *
     *   - a real, logged-in viewer (never the guest account);
     *   - a course the viewer's catalogue shows (catalog_manager::assert_course_visible_to_viewer(): tenant gate);
     *   - BizLMS's per-course rule allows it ({@see bizlms_allows_self_enrol()});
     *   - an enabled 'self' instance, with the plugin enabled site-wide, that has NO enrolment key: a key is never
     *     bypassed here, and course.php has no field to type one;
     *   - enrol_self_plugin::can_self_enrol() says yes for the viewer: new enrolments allowed, inside the instance's
     *     enrolment dates, under its maximum, in its cohort if it names one, the capability enrol/self:enrolself,
     *     and no enrolment through that instance already (a suspended one included);
     *   - the course is not priced ({@see is_priced()}): a paid course goes to the cart, and self_enrol() would
     *     refuse it, so the button is never shown for a click that cannot work.
     *
     * Answers for the logged-in viewer only: can_self_enrol() reads $USER.
     *
     * @param int $courseid
     * @return \stdClass|null The usable instance, or null.
     */
    public static function self_enrol_instance(int $courseid): ?\stdClass {
        if ($courseid <= (int) SITEID || !isloggedin() || isguestuser()) {
            return null;
        }
        try {
            $course = catalog_manager::assert_course_visible_to_viewer($courseid);
        } catch (\moodle_exception $e) {
            return null;
        }
        if (!self::bizlms_allows_self_enrol($course)) {
            return null;
        }
        $plugin = enrol_get_plugin('self');
        if (!$plugin || !enrol_is_enabled('self')) {
            return null;
        }
        // enrol_get_instances(.., true): enabled instances of site-enabled plugins, in the course's order.
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol !== 'self' || (string) $instance->password !== '') {
                continue;
            }
            if ($plugin->can_self_enrol($instance, true) === true) {
                return self::is_priced($courseid) ? null : $instance;
            }
        }
        return null;
    }

    /**
     * MECHANISM: enrol the logged-in viewer through the course's own self-enrolment instance, as core's
     * "Enrol me" form does (enrol_self_plugin::enrol_self(): the instance's role and enrolment period).
     *
     * Persona walk 2026-11-18. Reached from course.php's 'enrolnow' action when the one-click policy did not apply.
     * Refuses (false) whatever {@see self_enrol_instance()} refuses, and every priced course ({@see is_priced()},
     * the guard enrol_now() uses), so it can never give a paid course away. Unlike enrol_now() it creates no
     * manual instance and bypasses no key. Idempotent: an active enrolment already held is success. An enrolment
     * already held that is not active (suspended, ended, not started) is refused: course.php shows why it does not
     * let the learner in, and a hand-made POST must not get round it through the self instance (core's
     * can_self_enrol() looks only at that instance's own enrolments). Review of the persona-walk fix, 2026-11-18.
     *
     * Core's enrol_self() queues its own "You are enrolled in the course." notification.
     *
     * @param int $courseid
     * @return bool True if the viewer now holds an active enrolment in the course.
     */
    public static function self_enrol(int $courseid): bool {
        global $USER;

        if ($courseid <= (int) SITEID || !isloggedin() || isguestuser()) {
            return false;
        }
        if (self::is_priced($courseid)) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        if (is_enrolled($context, $USER, '', true)) {
            return true;
        }
        if (is_enrolled($context, $USER)) {
            return false;   // Suspended, ended or not started: the L&D team's to change, not a self-enrolment's.
        }
        $instance = self::self_enrol_instance($courseid);
        if (!$instance) {
            return false;
        }
        enrol_get_plugin('self')->enrol_self($instance);
        return is_enrolled($context, $USER, '', true);
    }

    /**
     * Why does the user's enrolment in this course not let them in?
     *
     * Persona walk 2026-11-18: course.php showed "Continue Learning" for any enrolment (is_enrolled() without
     * $onlyactive), and for one that is not active the link went to /course/view.php, on to /enrol/index.php and
     * back to course.php. course.php now shows the reason instead. The rule is core's (enrol_get_enrolment_end()):
     * an enrolment counts when it and its instance are both enabled, it has started, and it has not ended.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null (object) ['reason' => 'notstarted'|'suspended'|'ended', 'time' => the start still to
     *         come, the end already passed, or 0 for 'suspended']; null when the user holds no enrolment in the
     *         course, or holds one that is active now. A start to come is reported first (it will let them in),
     *         then a suspension, then an end.
     */
    public static function inactive_enrolment(int $courseid, int $userid): ?\stdClass {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT ue.id, ue.status AS uestatus, ue.timestart, ue.timeend, e.status AS estatus
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]);
        if (!$rows) {
            return null;
        }

        $now = time();
        $suspended = false;
        $start = 0;   // The earliest start still to come.
        $end = 0;     // The latest end already passed.
        foreach ($rows as $row) {
            if ((int) $row->uestatus !== ENROL_USER_ACTIVE || (int) $row->estatus !== ENROL_INSTANCE_ENABLED) {
                $suspended = true;
            } else if ((int) $row->timestart > $now) {
                $start = $start ? min($start, (int) $row->timestart) : (int) $row->timestart;
            } else if ((int) $row->timeend !== 0 && (int) $row->timeend <= $now) {
                $end = max($end, (int) $row->timeend);
            } else {
                return null;   // An enrolment that is active now.
            }
        }
        if ($start) {
            return (object) ['reason' => 'notstarted', 'time' => $start];
        }
        if ($suspended) {
            return (object) ['reason' => 'suspended', 'time' => 0];
        }
        return (object) ['reason' => 'ended', 'time' => $end];
    }

    /**
     * Is this course priced? Either price reader saying so is enough: the catalogue's own
     * (commerce::get_course_price(), the enabled enrol_fee instance first, the config setting as the fallback) and
     * the order cart's (cart_manager::get_course_price()) where that plugin is installed. cart.price_source,
     * 2026-10-07; shared by enrol_now() and self_enrol() since the persona walk of 2026-11-18.
     *
     * @param int $courseid
     * @return bool
     */
    private static function is_priced(int $courseid): bool {
        $pricing = commerce::get_course_price($courseid);
        if (empty($pricing['is_free'])) {
            return true;
        }
        return class_exists('\\local_sentientia_cart\\cart_manager')
            && \local_sentientia_cart\cart_manager::get_course_price($courseid) !== null;
    }

    /**
     * MECHANISM — enrol a user in a FREE course immediately, bypassing any
     * self-enrol enrolment key by using Moodle's `manual` enrol plugin.
     *
     * Idempotent: a no-op (returns true) if the user is already enrolled.
     * Refuses (returns false) for non-existent / hidden / PAID courses, so it
     * can never be used to skip payment on a priced course. "Paid" is the
     * enabled enrol_fee price (commerce::get_course_price(), with the config
     * setting only as a fallback) AND the order cart's own price
     * (cart_manager::get_course_price()) when that plugin is installed:
     * cart.price_source, 2026-10-07.
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
        if ($userid <= 0 || isguestuser($userid)) {
            // ADR-031: the guest account is never enrolled; it is checked as
            // the Public tenant by the visibility gate below, so without this
            // a direct call could enrol it into any free Public course.
            return false;
        }

        // C2 fix (2026-09-03, docs/security/UAT-SECURITY-POSTURE-2026-09-03.md):
        // this used to be $DB->get_record('course', ['id' => $courseid,
        // 'visible' => 1], ...) — no tenant scoping. This is the LAST line
        // of defence against cross-tenant self-enrolment even if a
        // cross-tenant course id reached here directly (e.g. a forged POST
        // to cart.php's "enrol all free" action, bypassing course.php's own
        // guard entirely). Gates on $userid (the user being enrolled), not
        // necessarily the current session $USER — callers may enrol a
        // specific target user.
        try {
            catalog_manager::assert_course_visible_to_viewer($courseid, $userid);
        } catch (\moodle_exception $e) {
            return false;
        }

        // Server-side price re-check — never enrol into a paid course here.
        // cart.price_source (2026-10-07): the catalogue price now reads the enabled enrol_fee instance, but a guard
        // that stops paid courses being given away must not depend on one price reader being right, so the order
        // cart's own rule is asked as well. Either one saying "priced" refuses; neither depends on a feature flag.
        // (is_priced(), shared with self_enrol() since 2026-11-18.)
        if (self::is_priced($courseid)) {
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
