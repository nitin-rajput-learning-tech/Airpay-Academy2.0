<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * "Request enrolment" on the course catalogue: when is a request the learner's way in?
 *
 * Visual-pass finding 7 (a), 2026-10-15. amd/src/request_button.js has always listened for a click on
 * [data-action="request-course"][data-courseid][data-coursename] and opens the Request enrolment dialog
 * (FX-06b), but no template or page ever rendered such an element, so a learner had no way to start a request
 * from the catalogue at all: on a course with nothing to enrol through, the card's Enroll link and the detail
 * page's "Enroll Now" led to /enrol/index.php, which the theme redirects to /my.
 *
 * This class owns the rule for WHEN the button belongs, so the two catalogue surfaces that render it (the
 * course card and course.php, local_sentientia_catalog) share one answer and the catalogue does not
 * re-implement the request plugin's rules. A request is the way in when the learner:
 *
 *   1. is a real, logged-in user holding local/sentientia_request:request;
 *   2. is not enrolled in the course already;
 *   3. may be enrolled in it, which is the test submit() applies (visible, in the learner's tenant scope, ADR-031);
 *
 * and, with the feature flag ON, shows "Request pending" instead of the button while a request of theirs for the
 * course is pending (submit() would refuse a second one).
 *
 * Self-enrolment is deliberately NOT a way in here (fix 2026-10-15, review of 7a). enrol/index.php, where both the
 * enrolment-key form and the "Enrol me" form render, uses the 'incourse' layout, and theme/sentientia sends
 * /enrol/index.php to /my for everyone (core_renderer::custom_secured_redirection(); airpayux does the same from
 * full_header), so a learner can never reach a self-enrolment instance, keyed or not, and a course whose only
 * enabled method is self-enrolment would otherwise be a dead end with no button. The routes that come before a
 * request are the catalogue's own: a paid course goes to the cart, and a free course offered one-click enrolment is
 * enrolled at once. Count self-enrolment as a way in again only once a reachable self-enrol route exists.
 *
 * What the catalogue adds on its side, because only it knows the price and its own one-click policy: a paid
 * course goes to the cart, and a free course the learner is offered one-click enrolment for is enrolled at once
 * (local_sentientia_catalog\enrolment::request_state()).
 *
 * Gated by the feature flag sentientia.request.catalog_button, default OFF (db/feature_flags.php): with it OFF
 * nothing is rendered and the catalogue is exactly what it was. The check is for the viewer only: the catalogue
 * renders the button for the logged-in user, and the answer is never given for anybody else.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalog_cta {

    /** Feature flag key (registered in db/feature_flags.php, default OFF). */
    public const FLAG = 'sentientia.request.catalog_button';

    /** Nothing to offer: render the catalogue as it was. */
    public const NONE = 'none';

    /** Offer the "Request enrolment" button. */
    public const REQUEST = 'request';

    /** A request of the learner's for this course is already pending: say so, offer nothing to click. */
    public const PENDING = 'pending';

    /**
     * Is the catalogue button on for the current user's customer and tenant?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * What the catalogue should offer this learner for this course, as far as the request plugin's rules go.
     *
     * Only ever answers for the logged-in viewer: for any other user it is NONE.
     *
     * @param \stdClass $user The viewer (a full user record, e.g. $USER).
     * @param int $courseid
     * @return string One of the NONE, REQUEST and PENDING constants.
     */
    public static function state(\stdClass $user, int $courseid): string {
        global $USER, $DB;

        if (!self::enabled()) {
            return self::NONE;
        }
        if (empty($user->id) || isguestuser($user) || (int) $user->id !== (int) $USER->id || $courseid <= 0) {
            return self::NONE;
        }
        $system = \context_system::instance();
        if (!has_capability('local/sentientia_request:request', $system, $user)) {
            return self::NONE;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || is_enrolled($context, $user)) {
            return self::NONE;
        }
        if (!request_manager::can_request_course($user, $courseid)) {
            return self::NONE;
        }

        // A request imported from BizLMS counts only where the lists show it (imported_history flag), as in submit().
        $pending = $DB->record_exists_select('local_sentientia_request',
            'userid = :u AND courseid = :c AND status = :s' . imported_history::filter_sql(''),
            ['u' => (int) $user->id, 'c' => $courseid, 's' => 'pending']);
        return $pending ? self::PENDING : self::REQUEST;
    }
}
