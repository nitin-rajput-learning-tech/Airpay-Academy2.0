<?php
/**
 * Public Course Detail — accessible to guests (no login required).
 * Shows course info with "Add to Cart" / "Enroll Free" / "Login to Access" CTAs.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $CFG, $OUTPUT, $PAGE, $USER, $SESSION;

$id = optional_param('id', 0, PARAM_INT);
if (!$id) {
    redirect(\local_sentientia_catalog\catalog_manager::catalog_home_url($USER));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/sentientia_catalog/course.php', ['id' => $id]);
$PAGE->set_pagelayout('standard');

// C2 fix (2026-09-03, docs/security/UAT-SECURITY-POSTURE-2026-09-03.md):
// this used to be $DB->get_record('course', ['id' => $id, 'visible' => 1],
// '*', MUST_EXIST) — visibility only, no tenant scoping — which let a
// Public-tenant (/77) learner open an Airpay/ZEEA internal course by id
// straight from the URL. assert_course_visible_to_viewer() adds the same
// owned-or-shared tenant gate the catalog browse queries already enforce.
$course = \local_sentientia_catalog\catalog_manager::assert_course_visible_to_viewer($id);
$PAGE->set_title(format_string($course->fullname));

// Handle Add to Cart action.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'addtocart') {
    require_sesskey();
    \local_sentientia_catalog\commerce::add_to_cart($id);
    redirect(new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id, 'added' => 1]),
        s(get_string('course_addedtocart_short', 'local_sentientia_catalog')), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Persona walk 2026-11-18: "Log in" brings the visitor back to this course. Core login/index.php reads ?wantsurl=
// only on Behat sites, so set the session's wantsurl (what require_login() does), and only on this click.
if ($action === 'login') {
    if (!isloggedin() || isguestuser()) {
        $SESSION->wantsurl = (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]))->out(false);
        redirect(get_login_url());
    }
    redirect(new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]));
}

// Handle one-click immediate free enrolment (QA-walk P1, 2026-05-29).
// Offered only to logged-in internal-tenant users on free courses when the
// feature flag is ON (see classes/enrolment.php). Bypasses any self-enrol
// key via the manual enrol plugin; paid/Public/guest fall back to the cart.
if ($action === 'enrolnow') {
    require_sesskey();
    // Persona walk 2026-11-18: an enrolment already held is never enrolled again or reported as new. Active: open the
    // course. Not active (suspended, ended, not started): back to this page, which says why (inactive_enrolment()).
    if (isloggedin() && !isguestuser()) {
        $heldcontext = context_course::instance($id);
        if (is_enrolled($heldcontext, $USER)) {
            redirect(is_enrolled($heldcontext, $USER, '', true)
                ? new moodle_url('/course/view.php', ['id' => $id])
                : new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]));
        }
    }
    $pricing = \local_sentientia_catalog\commerce::get_course_price($id);
    if (\local_sentientia_catalog\enrolment::should_offer_oneclick($USER, $pricing)
            && \local_sentientia_catalog\enrolment::enrol_now($id)) {
        redirect(new moodle_url('/course/view.php', ['id' => $id]),
            get_string('enrolled_welcome', 'local_sentientia_catalog'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    // Policy declined (not internal / flag off) or enrol failed → safe
    // fallback to the cart so the click is never silently lost.
    \local_sentientia_catalog\commerce::add_to_cart($id);
    redirect(new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id, 'added' => 1]),
        s(get_string('course_addedtocart_short', 'local_sentientia_catalog')), null,
        \core\output\notification::NOTIFY_INFO);
}

$pricing = \local_sentientia_catalog\commerce::get_course_price($id);
$cart_count = \local_sentientia_catalog\commerce::get_cart_count();
$is_loggedin = isloggedin() && !isguestuser();
// Persona walk 2026-11-18: a learner of the Public storefront tenant (enrolment::public_tenant_id(), the tenant
// public.php sells) is a consumer: no L&D team, and the cart is the way into a free course (decision 2026-05-29).
$storefrontviewer = $is_loggedin
    && \local_sentientia_platform\tenant::root_for_user($USER) === \local_sentientia_catalog\enrolment::public_tenant_id();
// A course public.php itself lists (owned by the storefront tenant): its card there adds it to the basket.
$storefrontcourse = \local_sentientia_platform\tenant::root_for_user((object) ['open_path' => (string) ($course->open_path ?? '')])
    === \local_sentientia_catalog\enrolment::public_tenant_id();
$added = optional_param('added', 0, PARAM_INT);

// Check if already enrolled (for logged-in users).
// Persona walk 2026-11-18: is_enrolled() without $onlyactive also counts a suspended, ended or not-yet-started
// enrolment, and "Continue Learning" on one of those went to /course/view.php, which sends a learner without an
// active enrolment to /enrol/index.php, which the theme sends back here. $is_enrolled keeps its meaning (any
// enrolment: no Enroll or Request button); $is_active says the course opens now; $inactivemessage says why not.
$is_enrolled = false;
$is_active = false;
$canview = false;
$inactivemessage = '';
if ($is_loggedin) {
    $coursecontext = context_course::instance($id);
    $is_enrolled = is_enrolled($coursecontext, $USER);
    $is_active = $is_enrolled && is_enrolled($coursecontext, $USER, '', true);
    // Someone core lets into the course without an enrolment (moodle/course:view: a site or category admin).
    $canview = !$is_active && is_viewing($coursecontext, $USER);
    if ($is_enrolled && !$is_active && !$canview) {
        $inactive = \local_sentientia_catalog\enrolment::inactive_enrolment($id, (int) $USER->id);
        $when = !empty($inactive->time) ? userdate($inactive->time, get_string('strftimedate', 'langconfig')) : '';
        $inactivemessage = match ($inactive->reason ?? 'suspended') {
            'notstarted' => get_string('enrolment_notstarted', 'local_sentientia_catalog', $when),
            'ended' => get_string($storefrontviewer ? 'enrolment_ended_public' : 'enrolment_ended',
                'local_sentientia_catalog', $when),
            default => get_string($storefrontviewer ? 'enrolment_suspended_public' : 'enrolment_suspended',
                'local_sentientia_catalog'),
        };
    }
}

// Visual-pass finding 7 (a), 2026-10-15: "Request enrolment" where a request is the learner's way in
// (local_sentientia_request, flag sentientia.request.catalog_button, default OFF): 'request', 'pending' or
// 'none'. The one-click and cart routes below keep their place (enrolment::request_state() yields 'none'
// for them); the dialog is loaded only when the button is.
$requeststate = ($is_loggedin && !$is_enrolled)
    ? \local_sentientia_catalog\enrolment::request_state($USER, $id, $pricing) : 'none';
if ($requeststate === 'request') {
    $PAGE->requires->js_call_amd('local_sentientia_request/request_button', 'init');
}

// Get enrollment count.
$enrolled_count = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
     JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :cid",
    ['cid' => $id]);

// Course summary. Persona walk 2026-11-18 (V5): an empty summary shows no "About" heading.
$summary = format_text($course->summary, $course->summaryformat);
$hassummary = trim(strip_tags($summary, '<img>')) !== '';

// Category name.
$categoryname = \local_sentientia_catalog\category_manager::get_name((int)($course->open_categoryid ?? 0));
if (!empty($categoryname)) { $categoryname = format_string($categoryname); }

// Persona walk 2026-11-18 (V5): the course's overview image beside the title, and the price only for a buyer
// (a priced course, a visitor, a storefront learner); an employee is not one.
$poster = \local_sentientia_catalog\catalog_manager::course_poster($id);
$showprice = !$pricing['is_free'] || !$is_loggedin || $storefrontviewer;

echo $OUTPUT->header();
?>

<div class="ap-public-course" style="max-width:900px; margin:0 auto;">

    <?php if ($added): ?>
    <div class="alert alert-success" style="border-radius:10px; display:flex; align-items:center; gap:10px;">
        <i class="fa fa-check-circle"></i>
        <span><?php echo get_string('course_addedtocart', 'local_sentientia_catalog', format_string($course->fullname)); ?>
        <a href="<?php echo (new moodle_url('/local/sentientia_catalog/cart.php'))->out(); ?>"><?php echo s(get_string('course_viewcart', 'local_sentientia_catalog', $cart_count)); ?></a></span>
    </div>
    <?php endif; ?>

    <div style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:16px; overflow:hidden; margin-bottom:20px;">

        <!-- Hero -->
        <div class="ap-public-course__hero" style="background:linear-gradient(135deg,#0066A7 0%,#0d5da1 100%); color:#fff;">
            <div class="ap-public-course__hero-inner">
            <div class="ap-public-course__hero-text">
            <?php if ($categoryname): ?>
            <span style="background:rgba(255,255,255,0.2); padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600;"><?php echo s($categoryname); ?></span>
            <?php endif; ?>
            <h1 class="ap-public-course__title" style="margin:12px 0 8px; font-weight:800;"><?php echo format_string($course->fullname); ?></h1>
            <div class="ap-public-course__meta" style="font-size:14px; opacity:0.9;">
                <span><i class="fa fa-users"></i> <?php echo s(get_string('course_enrolledcount', 'local_sentientia_catalog', $enrolled_count)); ?></span>
                <span><i class="fa fa-clock-o"></i> <?php echo s(get_string('course_selfpaced', 'local_sentientia_catalog')); ?></span>
            </div>
            </div>
            <?php if ($poster['imageurl'] !== ''): ?>
            <img class="ap-public-course__poster" src="<?php echo s($poster['imageurl']); ?>" alt="" aria-hidden="true" loading="lazy">
            <?php endif; ?>
            </div>
        </div>

        <!-- Body -->
        <div class="ap-public-course__body">

            <!-- Pricing + CTA -->
            <div class="ap-public-course__cta-row" style="margin-bottom:24px; padding:16px 20px; background:var(--ap-bg,#F2F4FB); border-radius:12px;">
                <?php if ($showprice): ?>
                <div>
                    <span style="font-size:24px; font-weight:800; color:<?php echo $pricing['is_free'] ? '#16a34a' : 'var(--ap-primary,#0066A7)'; ?>;">
                        <?php echo $pricing['display']; ?>
                    </span>
                    <?php if (!$pricing['is_free']): ?>
                    <small style="color:var(--ap-text-muted); margin-left:4px;"><?php echo s(get_string('course_onetime', 'local_sentientia_catalog')); ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="ap-public-course__actions">
                    <?php if ($is_active || ($is_enrolled && $canview)): ?>
                        <a href="<?php echo (new moodle_url('/course/view.php', ['id' => $id]))->out(); ?>"
                           style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                  background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; text-decoration:none;">
                            <i class="fa fa-play-circle"></i> <?php echo s(get_string('continuelearning', 'local_sentientia_catalog')); ?>
                        </a>
                    <?php elseif ($is_enrolled): ?>
                        <!-- An enrolment that does not let the learner in now (suspended, ended, not started): say why. -->
                        <span role="status"
                              style="display:inline-block; max-width:420px; padding:10px 16px; border-radius:10px;
                                     font-size:14px; font-weight:600; line-height:1.4;
                                     background:var(--ap-surface-2,#f8f9fc); color:var(--ap-text-secondary,#5a6070);
                                     border:1px solid var(--ap-border,#e3eaf3);">
                            <i class="fa fa-info-circle" aria-hidden="true"></i> <?php echo s($inactivemessage); ?>
                        </span>
                    <?php elseif ($pricing['is_free'] && $is_loggedin && \local_sentientia_catalog\enrolment::should_offer_oneclick($USER, $pricing)): ?>
                        <!-- One-click immediate enrol (internal tenant + flag ON). -->
                        <form method="post" action="<?php echo (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]))->out(false); ?>" style="display:inline;">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="enrolnow">
                            <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                    background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; border:none; cursor:pointer;">
                                <i class="fa fa-sign-in"></i> <?php echo s(get_string('enrol_now_free', 'local_sentientia_catalog')); ?>
                            </button>
                        </form>
                    <?php elseif ($requeststate === 'request'): ?>
                        <!-- Request enrolment: nothing here enrols this learner themselves (local_sentientia_request). -->
                        <button type="button" data-action="request-course"
                                data-courseid="<?php echo (int) $id; ?>"
                                data-coursename="<?php echo s(strip_tags(format_string($course->fullname, true, ['escape' => false]))); ?>"
                                style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                       background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; border:none; cursor:pointer;">
                            <i class="fa fa-paper-plane"></i> <?php echo s(get_string('requestcourse_long', 'local_sentientia_request')); ?>
                        </button>
                    <?php elseif ($requeststate === 'pending'): ?>
                        <span role="status"
                              style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                     background:var(--ap-surface-2,#f8f9fc); color:var(--ap-text-secondary,#5a6070);
                                     border:1px solid var(--ap-border,#e3eaf3);">
                            <i class="fa fa-clock-o"></i> <?php echo s(get_string('requestpending_short', 'local_sentientia_request')); ?>
                        </span>
                    <?php elseif ($pricing['is_free'] && $is_loggedin): ?>
                        <?php if ($canview): ?>
                        <a href="<?php echo (new moodle_url('/course/view.php', ['id' => $id]))->out(); ?>"
                           style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                  background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; text-decoration:none;">
                            <i class="fa fa-play-circle"></i> <?php echo s(get_string('open_course', 'local_sentientia_catalog')); ?>
                        </a>
                        <?php elseif ($storefrontviewer && $storefrontcourse): ?>
                        <!-- The same action as public.php's card for this course: the basket, whose Enrol in all (free)
                             enrols (decision 2026-05-29: the Public tenant keeps the cart). -->
                        <form method="post" action="<?php echo (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]))->out(false); ?>" style="display:inline;">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="addtocart">
                            <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                    background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; border:none; cursor:pointer;">
                                <i class="fa fa-cart-plus" aria-hidden="true"></i> <?php echo s(get_string('public_addtocart', 'local_sentientia_catalog')); ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <!-- Nothing this learner can enrol through here: say so, not a button that goes nowhere. -->
                        <span role="status"
                              style="display:inline-block; max-width:420px; padding:10px 16px; border-radius:10px;
                                     font-size:14px; font-weight:600; line-height:1.4;
                                     background:var(--ap-surface-2,#f8f9fc); color:var(--ap-text-secondary,#5a6070);
                                     border:1px solid var(--ap-border,#e3eaf3);">
                            <i class="fa fa-info-circle" aria-hidden="true"></i> <?php echo s(get_string($storefrontviewer ? 'enrolment_managed_public' : 'enrolment_managed', 'local_sentientia_catalog')); ?>
                        </span>
                        <?php endif; ?>
                    <?php elseif (!$is_loggedin): ?>
                        <form method="post" action="<?php echo (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]))->out(false); ?>" style="display:inline;">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="addtocart">
                            <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                    background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; border:none; cursor:pointer;">
                                <i class="fa fa-cart-plus"></i> <?php echo s(get_string('public_addtocart', 'local_sentientia_catalog')); ?>
                            </button>
                        </form>
                        <a href="<?php echo (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id, 'action' => 'login']))->out(); ?>"
                           style="padding:10px 20px; border-radius:10px; font-size:14px; font-weight:600;
                                  background:var(--ap-surface,#fff); color:var(--ap-text,#1a1a2e); text-decoration:none;
                                  border:1px solid var(--ap-border,#e3eaf3);">
                            <?php echo s(get_string('course_login', 'local_sentientia_catalog')); ?>
                        </a>
                    <?php else: ?>
                        <form method="post" action="<?php echo (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]))->out(false); ?>" style="display:inline;">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="addtocart">
                            <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:15px; font-weight:600;
                                    background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; border:none; cursor:pointer;">
                                <i class="fa fa-cart-plus"></i> <?php echo get_string('course_addtocart_price', 'local_sentientia_catalog', $pricing['display']); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($hassummary): ?>
            <!-- Description -->
            <div style="margin-bottom:24px;">
                <h3 style="font-size:17px; font-weight:700; color:var(--ap-text,#1a1a2e); margin:0 0 12px;"><?php echo s(get_string('course_about', 'local_sentientia_catalog')); ?></h3>
                <div style="font-size:14px; line-height:1.7; color:var(--ap-text-secondary,#607286);">
                    <?php echo $summary; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back to catalog -->
            <div style="padding-top:16px; border-top:1px solid var(--ap-border,#e3eaf3);">
                <a href="<?php echo \local_sentientia_catalog\catalog_manager::catalog_home_url($USER)->out(); ?>"
                   style="font-size:13px; color:var(--ap-primary); text-decoration:none;">
                    <i class="fa fa-arrow-left"></i> <?php echo s(get_string('course_backtocatalog', 'local_sentientia_catalog')); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
